"""Build a deterministic cPanel ZIP from an explicit manifest, without private state."""
import argparse
import hashlib
import json
import re
import zipfile
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
MANIFEST = ROOT / 'tools/build/release-manifest.json'


def allowed(relative, policy):
    path = Path(relative)
    if path.is_absolute() or '..' in path.parts:
        return False
    if any(part.startswith('.') and part != '.htaccess' for part in path.parts):
        return False
    if any(part in policy['excluded_directory_names'] for part in path.parts):
        return False
    if relative == 'config/local.php' or path.name.endswith('.local.php'):
        return False
    if relative.startswith('config/'):
        return relative in ['config/config.example.php', 'config/mail.local.example.php', 'config/backup.local.example.php', 'config/migrations.local.example.php', 'config/restore.local.example.php','config/external.local.example.php','config/anaf.local.example.php']
    if path.suffix in policy['excluded_suffixes']:
        return False
    if relative.startswith(('tests/', 'output/', 'release/')):
        return False
    if relative.startswith('docs/') and path.suffix.lower() in {'.png', '.jpg', '.jpeg', '.webp'}:
        return False
    if relative.startswith('storage/') and relative != 'storage/.htaccess':
        return False
    if relative.startswith('public/') and not relative.startswith('public/assets/'):
        return relative in ['public/index.php', 'public/.htaccess', 'public/hosting-check.php']
    if relative.startswith('public/assets/'):
        return path.suffix in ['.css', '.js', '.svg', '.png', '.jpg', '.jpeg', '.webp', '.woff2', '.txt', '.wasm', '.gz', '.json']
    return True


def collect(profile):
    policy = json.loads(MANIFEST.read_text(encoding='utf-8'))
    if profile not in policy['profiles']:
        raise ValueError('Unknown release profile.')
    directories = ['OpenBread_Codex_v1_2026-10-23'] if profile == 'pilot' else policy['runtime_directories'] + policy['documentation_directories']
    files = ['LICENSE', 'LICENSING.md', 'THIRD_PARTY_NOTICES.md', 'release.json'] if profile == 'pilot' else policy['runtime_files']
    if profile == 'full':
        directories += policy['full_directories']
        files += policy['full_files']
    selected = set()
    for directory in directories:
        base = ROOT / directory
        if not base.is_dir():
            raise ValueError('Required release directory missing: ' + directory)
        for path in base.rglob('*'):
            if path.is_symlink():
                raise ValueError('Symlinks cannot be included: ' + path.relative_to(ROOT).as_posix())
            if path.is_file():
                relative = path.relative_to(ROOT).as_posix()
                if allowed(relative, policy):
                    selected.add(relative)
    for relative in files:
        if not allowed(relative, policy) or not (ROOT / relative).is_file() or (ROOT / relative).is_symlink():
            raise ValueError('Missing or forbidden release file: ' + relative)
        selected.add(relative)
    return sorted(selected)


def build(profile, output, version=None):
    release = json.loads((ROOT / 'release.json').read_text(encoding='utf-8'))
    version = version or release['version']
    if not re.fullmatch(r'[0-9]+\.[0-9]+\.[0-9]+(?:-[a-z0-9.-]+)?', version):
        raise ValueError('Version must be an explicit semantic version.')
    output = output.resolve()
    if output.is_relative_to((ROOT / 'public').resolve()):
        raise ValueError('Release archives must remain outside the public document root.')
    output.mkdir(parents=True, exist_ok=True)
    selected = collect(profile)
    hashes = {name: hashlib.sha256((ROOT / name).read_bytes()).hexdigest() for name in selected}
    manifest = {'release': version, 'profile': profile, 'date': release['date'], 'files_sha256': hashes, 'migrations': [p.stem for p in sorted((ROOT / 'database/migrations').glob('*.sql'))] if profile != 'pilot' else [], 'excluded': ['config/local.php', 'config/mail.local.php', '.tools', 'tests/runtime', 'production data and credentials'], 'document_root': 'public/', 'license_scope': 'See LICENSING.md; software MIT does not relicense product documents.'}
    archive = output / f'OpenAliment_{version}_{profile}_cPanel.zip'
    timestamp = datetime.strptime(release['date'], '%Y-%m-%d').timetuple()[:6]
    entries = {name: (ROOT / name).read_bytes() for name in selected}
    if profile != 'pilot':
        for directory in ['storage/private', 'storage/logs', 'storage/cache']:
            entries[directory + '/.keep'] = b''
    entries['RELEASE_MANIFEST.json'] = (json.dumps(manifest, ensure_ascii=False, indent=2) + '\n').encode('utf-8')
    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as package:
        for name in sorted(entries):
            info = zipfile.ZipInfo(name, timestamp)
            info.create_system = 3
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            package.writestr(info, entries[name], compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    with zipfile.ZipFile(archive) as package:
        if package.testzip() is not None:
            raise ValueError('ZIP integrity check failed.')
        for name, expected in hashes.items():
            if hashlib.sha256(package.read(name)).hexdigest() != expected:
                raise ValueError('Package hash mismatch: ' + name)
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    (output / (archive.name + '.sha256')).write_text(digest + '  ' + archive.name + '\n', encoding='ascii')
    return {'archive': str(archive), 'profile': profile, 'version': version, 'files': len(hashes), 'bytes': archive.stat().st_size, 'sha256': digest}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--profile', choices=['app', 'full', 'pilot'], default='full', help='full retains the historical complete distribution')
    parser.add_argument('--output', type=Path, default=ROOT / 'release')
    parser.add_argument('--version')
    args = parser.parse_args()
    print(json.dumps(build(args.profile, args.output, args.version)))


if __name__ == '__main__':
    main()
