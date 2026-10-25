"""Bounded production HTTPS checks; no login, real tokens, user data or writes."""
import json
import ssl
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def main():
    opener = urllib.request.build_opener(NoRedirect(), urllib.request.HTTPSHandler(context=ssl.create_default_context()))
    checks, responses = [], []

    def check(name, passed):
        checks.append({'name': name, 'passed': bool(passed)})

    cases = [
        ('/', {200}), ('/login', {200}), ('/api/docs', {200}),
        ('/assets/api-docs.css', {200}), ('/01/09520000000011', {302, 303, 307}),
        ('/01/09520000000011/10/DEMO-PBT-01?linkType=linkset', {200}),
        ('/hosting-check.php', {404}), ('/setup', {404}),
        ('/__openaliment_security_missing__', {404}),
        ('/assets/__openaliment_security_probe.php', {403}),
    ]
    for path, expected in cases:
        request = urllib.request.Request('https://openaliment.ro' + path, headers={'User-Agent': 'OpenAliment-Security-ReadOnly-QA/0.5.3'})
        try:
            response = opener.open(request, timeout=25)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            body = response.read(2 * 1024 * 1024 + 1)
            headers = response.headers
            check('Status ' + path, response.status in expected)
            check('HSTS ' + path, headers.get_all('Strict-Transport-Security', []) == ['max-age=86400'])
            check('No PHP disclosure ' + path, not headers.get('X-Powered-By'))
            check('No MIME sniffing ' + path, headers.get('X-Content-Type-Options') == 'nosniff')
            check('Bounded body ' + path, len(body) <= 2 * 1024 * 1024)
            if path == '/login':
                cookies = headers.get_all('Set-Cookie', [])
                check('Producer session cookie Secure HttpOnly SameSite', any(all(part in value.lower() for part in ['secure', 'httponly', 'samesite=lax']) for value in cookies))
                check('Login CSP disallows external scripts', "script-src 'self'" in headers.get('Content-Security-Policy', ''))
            responses.append({'path': path, 'status': response.status, 'hsts': headers.get_all('Strict-Transport-Security', []), 'powered_by_absent': not headers.get('X-Powered-By')})
    failed = sum(not item['passed'] for item in checks)
    report = {'executed_at': datetime.now(timezone.utc).isoformat(), 'environment': 'production HTTPS, read-only; no credentials, user data or mutations', 'passed': len(checks) - failed, 'failed': failed, 'checks': checks, 'responses': responses}
    output = ROOT / 'tests/runtime/reports/security-production-smoke.json'
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
    print(json.dumps({'passed': len(checks) - failed, 'failed': failed, 'failures': [item['name'] for item in checks if not item['passed']]}))
    return bool(failed)


if __name__ == '__main__':
    raise SystemExit(main())
