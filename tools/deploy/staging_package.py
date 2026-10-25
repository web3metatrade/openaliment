"""Prepare the first staging installation. Never reads production configuration or data."""
import hashlib
import importlib.util
import json
import os
import secrets
import time
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def load(name, relative):
    spec = importlib.util.spec_from_file_location(name, ROOT / relative)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def build():
    output = ROOT / 'release/staging'
    output.mkdir(parents=True, exist_ok=True)
    prepare = load('staging_prepare', 'tools/deploy/prepare.py')
    credential_path = ROOT / '.tools/deploy/staging.json'
    bridge = output / 'openaliment-connect.php'
    if not credential_path.exists():
        prepare.prepare(ROOT, environment='staging')
    credential = json.loads(credential_path.read_text(encoding='utf-8'))
    if credential.get('environment') != 'staging' or credential.get('endpoint') != 'https://dev.openaliment.ro/openaliment-connect.php':
        raise ValueError('The staging credential does not match the expected host.')
    if not bridge.is_file() or ('const OA_DEPLOY_KEY_HASH = ' + repr(hashlib.sha256(credential['key'].encode()).hexdigest())) not in bridge.read_text(encoding='utf-8'):
        raise ValueError('Staging endpoint and client key do not match; regenerate explicitly.')
    from datetime import datetime
    if datetime.fromisoformat(credential['expires_at']).timestamp() <= time.time():
        raise ValueError('Staging connection expired; regenerate explicitly with --environment staging --rotate.')
    package = load('staging_package_builder', 'tools/build/package.py')
    source = package.build('app', output / 'base')
    with zipfile.ZipFile(source['archive']) as archive:
        entries = {name: archive.read(name) for name in archive.namelist()}
    setup = secrets.token_hex(32)
    config = (ROOT / 'tools/deploy/staging.config.example.php').read_text(encoding='utf-8')
    config = config.replace('__OA_STAGING_SETUP_HASH__', hashlib.sha256(setup.encode()).hexdigest())
    entries['config/local.php'] = config.encode('utf-8')
    entries['public/openaliment-connect.php'] = bridge.read_bytes()
    instructions = (
        '# Prima instalare staging\n\n'
        'Extrage arhiva in /home/apelero/dev.openaliment.ro/, deasupra public/.\n'
        'Foloseste PHP 8.4+ si Document Root /home/apelero/dev.openaliment.ro/public/.\n'
        'Creeaza baza apelero_openaliment_dev si un utilizator SQL dedicat aceleiasi baze.\n'
        'Completeaza datele SQL numai in config/local.php; parola este intentionat goala in acest pachet.\n'
        'Nu copia configuratii sau date de productie. Restrictioneaza stagingul echipei prin hosting.\n'
        'Dupa verificarea hosting-check.php, deschide /setup si creeaza contul administratorului.\n'
        'Tokenul este in fisierul local separat SETUP_TOKEN_PRIVATE.txt, exclus din aceasta arhiva.\n'
        'Dupa instalare, elimina setup_token_hash din config/local.php. SMTP/AI sunt dezactivate.\n'
        'Pachetul este destinat doar primei instalari pe staging; nu se extrage peste productie.\n'
    )
    entries['STAGING_INSTALL.md'] = instructions.encode('utf-8')
    manifest = json.loads(entries.pop('RELEASE_MANIFEST.json'))
    manifest['initial_staging_install'] = True
    manifest['origin'] = 'https://dev.openaliment.ro'
    manifest['excluded'] = ['production configuration and credentials', 'production data', '.tools', 'historical pilot', 'setup plaintext token']
    manifest['files_sha256'] = {name: hashlib.sha256(data).hexdigest() for name, data in sorted(entries.items())}
    entries['RELEASE_MANIFEST.json'] = (json.dumps(manifest, ensure_ascii=False, indent=2) + '\n').encode()
    destination = output / 'OpenAliment_staging_initial.zip'
    with zipfile.ZipFile(destination, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for name, data in sorted(entries.items()):
            info = zipfile.ZipInfo(name, (2026, 10, 25, 0, 0, 0))
            info.create_system = 3
            info.external_attr = (0o100600 if name == 'config/local.php' else 0o100644) << 16
            archive.writestr(info, data, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    with zipfile.ZipFile(destination) as archive:
        if archive.testzip() is not None:
            raise ValueError('Staging ZIP integrity check failed.')
        for name, digest in manifest['files_sha256'].items():
            if hashlib.sha256(archive.read(name)).hexdigest() != digest:
                raise ValueError('Staging ZIP content hash mismatch.')
        if any(setup.encode() in archive.read(name) or credential['key'].encode() in archive.read(name) for name in archive.namelist()):
            raise ValueError('A plaintext setup/deployment token was included in the ZIP.')
    token_path = output / 'SETUP_TOKEN_PRIVATE.txt'
    descriptor = os.open(token_path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(descriptor, 'w', encoding='utf-8') as handle:
        handle.write(setup + '\n')
    digest = hashlib.sha256(destination.read_bytes()).hexdigest()
    (output / (destination.name + '.sha256')).write_text(digest + '  ' + destination.name + '\n', encoding='ascii')
    (output / 'STAGING_INSTALL.md').write_text(instructions, encoding='utf-8')
    return {'archive': str(destination), 'sha256': digest, 'bytes': destination.stat().st_size,
            'private_setup_token_file': str(token_path), 'plaintext_secrets_in_archive': False,
            'database_password_configured': False, 'production_accessed': False}


if __name__ == '__main__':
    print(json.dumps(build(), ensure_ascii=False, indent=2))
