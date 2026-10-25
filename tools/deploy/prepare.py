"""Create one HTTPS endpoint and its private local client credential."""
import argparse
import hashlib
import json
import os
import secrets
import subprocess
import time
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def prepare(root: Path, days: int = 7, rotate: bool = False, environment: str = 'production'):
    hosts = {'production': 'openaliment.ro', 'staging': 'dev.openaliment.ro'}
    if environment not in hosts or days not in range(1, 31):
        raise ValueError('Unknown deployment environment or invalid lifetime.')
    private = root / '.tools/deploy' / (environment + '.json')
    destination = root / ('release/conexiune' if environment == 'production' else 'release/staging') / 'openaliment-connect.php'
    if private.exists() and not rotate:
        raise RuntimeError('Conexiunea există deja; folosește --rotate numai pentru înlocuirea explicită a cheii și reîncarcă fișierul PHP.')
    token = secrets.token_hex(32)
    digest = hashlib.sha256(token.encode()).hexdigest()
    connection_id = secrets.token_hex(16)
    expires = int(time.time()) + days * 86400
    template = (ROOT / 'tools/deploy/bridge.template.php').read_text(encoding='utf-8')
    template = template.replace("const OA_DEPLOY_HOST = 'openaliment.ro';", "const OA_DEPLOY_HOST = '" + hosts[environment] + "';")
    script = template.replace('__OA_KEY_HASH__', digest).replace('__OA_EXPIRES__', str(expires)).replace('__OA_ID__', connection_id)
    destination.parent.mkdir(parents=True, exist_ok=True)
    destination.write_text(script, encoding='utf-8', newline='\n')
    php = ROOT / '.tools/php/php.exe'
    if php.is_file():
        subprocess.run([str(php), '-l', str(destination)], check=True, capture_output=True)
    private.parent.mkdir(parents=True, exist_ok=True)
    credential = {
        'protocol': 'openaliment.deploy.v1',
        'environment': environment,
        'endpoint': 'https://' + hosts[environment] + '/openaliment-connect.php',
        'key': token,
        'connection_id': connection_id,
        'expires_at': datetime.fromtimestamp(expires, timezone.utc).isoformat(),
    }
    descriptor = os.open(private, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(descriptor, 'w', encoding='utf-8') as handle:
        json.dump(credential, handle, indent=2)
    os.chmod(private, 0o600)
    assert token not in script
    return {'upload_file': str(destination), 'private_config': str(private), 'expires_at': credential['expires_at'], 'sha256': hashlib.sha256(destination.read_bytes()).hexdigest()}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--rotate', action='store_true')
    parser.add_argument('--environment', choices=['production', 'staging'], default='production')
    parser.add_argument('--days', type=int, choices=range(1, 31), default=7, metavar='1..30')
    args = parser.parse_args()
    print(json.dumps(prepare(ROOT, args.days, args.rotate, args.environment), ensure_ascii=False, indent=2))
