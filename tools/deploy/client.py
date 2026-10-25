"""Scoped HTTPS file maintenance; never print the key or follow redirects."""
import argparse
import base64
import hashlib
import json
import re
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DEFAULT_CONFIG = ROOT / '.tools/deploy/production.json'
MAX_RESPONSE = 16 * 1024 * 1024


class DeployError(RuntimeError):
    pass


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Client:
    def __init__(self, config: Path = DEFAULT_CONFIG, allow_local_http=False, root=ROOT):
        self.root = root.resolve()
        self.config = json.loads(config.read_text(encoding='utf-8'))
        endpoint = urllib.parse.urlsplit(self.config['endpoint'])
        expected_host = {'production': 'openaliment.ro', 'staging': 'dev.openaliment.ro'}.get(self.config.get('environment', 'production'))
        local = allow_local_http and endpoint.hostname in {'127.0.0.1', 'localhost', '::1'} and endpoint.scheme == 'http'
        if not expected_host or endpoint.username or endpoint.password or endpoint.query or endpoint.fragment or (not local and (endpoint.scheme != 'https' or endpoint.hostname != expected_host)):
            raise DeployError('Endpointul trebuie să folosească HTTPS, fără credentiale sau chei în URL.')
        if not re.fullmatch(r'[a-f0-9]{64}', self.config.get('key', '')):
            raise DeployError('Configurație de conexiune invalidă.')
        self.opener = urllib.request.build_opener(NoRedirect(), urllib.request.HTTPSHandler(context=ssl.create_default_context()))

    def call(self, action, **parameters):
        body = json.dumps({'action': action, **parameters}, ensure_ascii=False, separators=(',', ':')).encode()
        request = urllib.request.Request(self.config['endpoint'], body, {
            'Content-Type': 'application/json',
            'X-OpenAliment-Key': self.config['key'],
            'User-Agent': 'OpenAliment-Maintenance/1.0',
        }, method='POST')
        try:
            response = self.opener.open(request, timeout=60)
        except urllib.error.HTTPError as error:
            raw = error.read(4096)
            try:
                reason = json.loads(raw).get('error', 'request_rejected')
            except (ValueError, AttributeError):
                reason = 'request_rejected'
            if not isinstance(reason, str) or not re.fullmatch(r'[a-z0-9_]+', reason):
                reason = 'request_rejected'
            raise DeployError(f'HTTP {error.code}: {reason}') from None
        except (urllib.error.URLError, TimeoutError, OSError):
            raise DeployError('Conexiunea nu a reușit; verifică domeniul, TLS și disponibilitatea hostingului.') from None
        raw = response.read(MAX_RESPONSE + 1)
        if len(raw) > MAX_RESPONSE:
            raise DeployError('Răspunsul depășește limita permisă.')
        try:
            result = json.loads(raw)
        except ValueError:
            raise DeployError('Răspuns JSON invalid.') from None
        if not isinstance(result, dict):
            raise DeployError('Răspuns invalid.')
        return result

    def deploy(self, paths, label, dry_run=False, expected=None):
        if not paths or len(paths) != len(set(paths)):
            raise DeployError('Specifică explicit fișiere distincte pentru actualizare.')
        paths = [validate_local_path(path, self.root) for path in paths]
        status = self.call('status')
        if status.get('protocol') != 'openaliment.deploy.v1':
            raise DeployError('Protocol incompatibil.')
        before = {entry['path']: entry['sha256'] for entry in self.call('inspect', paths=paths)['files']}
        if expected is not None and any(before[path]!=expected.get(path) for path in paths):
            raise DeployError('Versiunea remote diferă de baseline-ul verificat. Reconciliază modificările înainte de publicare.')
        files = []
        total = 0
        for path in paths:
            source = self.root / path
            if not source.is_file():
                raise DeployError('Fișierul local nu există: ' + path)
            data = source.read_bytes()
            total += len(data)
            if len(data) > status['max_file_bytes'] or total > status['max_deployment_bytes']:
                raise DeployError('Actualizarea depășește limitele conexiunii; împarte fișierele în loturi.')
            digest = hashlib.sha256(data).hexdigest()
            if digest != before[path]:
                files.append({'path': path, 'sha256': digest, 'before_sha256': before[path], 'content_b64': base64.b64encode(data).decode()})
        preview = {'changed_files': [{key: value for key, value in item.items() if key != 'content_b64'} for item in files]}
        if dry_run or not files:
            return {'dry_run': dry_run, **preview}
        deployment_id = uuid.uuid4().hex
        self.call('stage', deployment_id=deployment_id, label=label, files=files)
        receipt = self.root / '.tools/deploy/receipts' / (deployment_id + '.json')
        receipt.parent.mkdir(parents=True, exist_ok=True)
        receipt.write_text(json.dumps({'deployment_id': deployment_id, 'state': 'staged', **preview}, indent=2), encoding='utf-8')
        try:
            result = self.call('commit', deployment_id=deployment_id)
        except DeployError:
            raise DeployError('Activarea nu a fost confirmată. Verifică starea deployment ' + deployment_id + ' înainte de orice reîncercare; copia și jurnalul sunt păstrate.') from None
        if result.get('state') != 'applied':
            raise DeployError('Stare neașteptată pentru deployment ' + deployment_id)
        actual = {entry['path']: entry['sha256'] for entry in self.call('inspect', paths=[item['path'] for item in files])['files']}
        if any(actual[item['path']] != item['sha256'] for item in files):
            raise DeployError('Verificarea după upload a eșuat pentru deployment ' + deployment_id)
        receipt.write_text(json.dumps(result, indent=2), encoding='utf-8')
        return result


def validate_local_path(path, root=ROOT):
    if not re.fullmatch(r'[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)+', path) or any(part in {'.', '..'} for part in path.split('/')):
        raise DeployError('Cale relativă invalidă.')
    base = root.resolve()
    candidate = base / path
    for component in [candidate, *candidate.parents]:
        if component == base:
            break
        if component.is_symlink():
            raise DeployError('Legăturile simbolice nu sunt acceptate.')
    if not candidate.resolve().is_relative_to(base):
        raise DeployError('Fișierul este în afara proiectului.')
    return path


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config', type=Path, default=DEFAULT_CONFIG)
    commands = parser.add_subparsers(dest='command', required=True)
    commands.add_parser('status')
    inspect = commands.add_parser('inspect'); inspect.add_argument('paths', nargs='+')
    read = commands.add_parser('read'); read.add_argument('path'); read.add_argument('--output', required=True, type=Path)
    deploy = commands.add_parser('deploy'); deploy.add_argument('--files', nargs='+', required=True); deploy.add_argument('--label', required=True); deploy.add_argument('--dry-run', action='store_true');deploy.add_argument('--baseline',type=Path)
    for action in ['deployment', 'rollback']:
        sub = commands.add_parser(action); sub.add_argument('deployment_id')
    commands.add_parser('disable')
    args = parser.parse_args()
    client = Client(args.config)
    if args.command == 'deploy':
        expected=json.loads(args.baseline.read_text(encoding='utf-8'))['files'] if args.baseline else None
        result = client.deploy(args.files, args.label, args.dry_run,expected)
    elif args.command == 'inspect':
        result = client.call('inspect', paths=args.paths)
    elif args.command == 'read':
        output = args.output.resolve()
        if not output.is_relative_to((ROOT / '.tools/deploy').resolve()):
            raise DeployError('Salvează copia remote numai în .tools/deploy/.')
        result = client.call('read', paths=[args.path])['files'][0]
        if result['sha256'] is None:
            raise DeployError('Fișierul remote nu există.')
        content = base64.b64decode(result.pop('content_b64'), validate=True)
        if hashlib.sha256(content).hexdigest() != result['sha256']:
            raise DeployError('Hashul copiei remote nu corespunde.')
        output.parent.mkdir(parents=True, exist_ok=True)
        output.write_bytes(content)
        result['saved_to'] = str(output)
    elif args.command in {'deployment', 'rollback'}:
        if not re.fullmatch(r'[a-f0-9]{32}', args.deployment_id):
            raise DeployError('ID de deployment invalid.')
        result = client.call(args.command, deployment_id=args.deployment_id)
    else:
        result = client.call(args.command)
    print(json.dumps(result, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    try:
        main()
    except DeployError as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
