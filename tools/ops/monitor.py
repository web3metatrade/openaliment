"""Read-only HTTPS monitor. Run outside the hosting account; exit 1 on failure."""
import argparse
import http.client
import json
import ssl
import time
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urlsplit


def probe(base, path, accept, timeout):
    started = time.monotonic()
    connection = http.client.HTTPSConnection(base.hostname, base.port or 443, timeout=timeout, context=ssl.create_default_context())
    try:
        connection.request('GET', path, headers={'Accept': accept, 'User-Agent': 'OpenAliment-Availability/1.0'})
        response = connection.getresponse()
        body = response.read(262145)
        if len(body) > 262144:
            raise ValueError('response_too_large')
        result = {'path': path, 'status': response.status, 'milliseconds': round((time.monotonic()-started)*1000), 'passed': response.status == 200}
        if path.endswith('/status'):
            value = json.loads(body)
            result['passed'] = result['passed'] and value.get('status') == 'operational' and value.get('ai_required') is False
        elif path == '/.well-known/gs1resolver':
            value = json.loads(body)
            result['passed'] = result['passed'] and isinstance(value, dict) and bool(value)
        elif path == '/acasa/':
            result['passed'] = result['passed'] and response.getheader('X-OpenAliment-App') == 'home'
        return result
    except (OSError, ValueError, http.client.HTTPException):
        return {'path': path, 'passed': False, 'error': 'transport_or_response', 'milliseconds': round((time.monotonic()-started)*1000)}
    finally:
        connection.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base-url', required=True)
    parser.add_argument('--output', type=Path, help='Private JSON report; no email is sent by this tool.')
    parser.add_argument('--timeout', type=float, default=5)
    args = parser.parse_args()
    base = urlsplit(args.base_url)
    if base.scheme != 'https' or not base.hostname or base.username or base.password or base.query or base.fragment or base.path not in ['', '/'] or not 1 <= args.timeout <= 10:
        parser.error('A canonical HTTPS origin without credentials and a timeout of 1–10 seconds are required.')
    checks = [probe(base, path, accept, args.timeout) for path, accept in [('/api/v2/public/status','application/json'),('/.well-known/gs1resolver','application/json'),('/acasa/','text/html'),('/','text/html')]]
    try:
        context = ssl.create_default_context()
        import socket
        with socket.create_connection((base.hostname,base.port or 443),timeout=args.timeout) as raw:
            with context.wrap_socket(raw,server_hostname=base.hostname) as secure:
                expiry = ssl.cert_time_to_seconds(secure.getpeercert()['notAfter'])
                remaining = int((expiry-time.time())//86400)
                checks.append({'path':'TLS','days_remaining':remaining,'passed':remaining >= 14})
    except (OSError, ValueError, KeyError):
        checks.append({'path':'TLS','passed':False,'error':'certificate_check'})
    report = {'checked_at':datetime.now(timezone.utc).isoformat(),'origin':args.base_url,'passed':all(c['passed'] for c in checks),'checks':checks,'sends_notifications':False}
    if args.output:
        args.output.parent.mkdir(parents=True,exist_ok=True)
        temporary = args.output.with_suffix(args.output.suffix+'.tmp')
        temporary.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
        temporary.chmod(0o600)
        temporary.replace(args.output)
    print(json.dumps(report,ensure_ascii=False))
    return 0 if report['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
