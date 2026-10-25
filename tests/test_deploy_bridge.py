"""Exercise the real PHP maintenance endpoint against an isolated file tree."""
import base64
import hashlib
import json
import os
import shutil
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request
import uuid
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'tools/deploy'))
from prepare import prepare
from client import Client, DeployError, validate_local_path

PHP = os.environ.get('OA_PHP') or shutil.which('php')
if not PHP:
    raise RuntimeError('Set OA_PHP or install PHP 8.4+ on PATH.')
BASE = ROOT / 'tests/runtime/deploy-tests' / uuid.uuid4().hex
BASE.mkdir(parents=True)
SITE = BASE / 'site'
for directory in ['public', 'app', 'config', 'storage', 'contracts', 'database/migrations', 'tools']:
    (SITE / directory).mkdir(parents=True, exist_ok=True)
(SITE / 'config/local.php').write_text('<?php throw new RuntimeException("Configuration must never execute");', encoding='utf-8')
prepared = prepare(SITE)
private = SITE / '.tools/deploy/production.json'
credential = json.loads(private.read_text(encoding='utf-8'))
KEY = credential['key']
endpoint = SITE / 'public/openaliment-connect.php'
shutil.copyfile(prepared['upload_file'], endpoint)
original_endpoint = endpoint.read_text(encoding='utf-8')
store = SITE / 'storage/maintenance' / credential['connection_id']
flags = subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0
checks = []


def check(name, condition):
    checks.append({'name': name, 'passed': bool(condition)})
    if not condition:
        raise AssertionError(name)
    print('PASS', name, flush=True)


with socket.socket() as reservation:
    reservation.bind(('127.0.0.1', 0))
    port = reservation.getsockname()[1]
URL = f'http://127.0.0.1:{port}/openaliment-connect.php'
router = SITE / 'router.php'
router.write_text('''<?php
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/openaliment-connect.php') { http_response_code(404); exit; }
$_SERVER['HTTPS'] = ($_SERVER['HTTP_X_TEST_TRANSPORT'] ?? '') === 'http' ? 'off' : 'on';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_TEST_HOST'] ?? 'openaliment.ro';
require __DIR__.'/public/openaliment-connect.php';
''', encoding='utf-8')
log = (BASE / 'php.log').open('wb')
server = subprocess.Popen([str(PHP), '-S', f'127.0.0.1:{port}', '-t', str(SITE / 'public'), str(router)], stdout=log, stderr=log, creationflags=flags)


def request(data=None, key=KEY, method='POST', headers=None, raw=None):
    body = raw if raw is not None else json.dumps(data or {'action': 'status'}).encode()
    h = {'Content-Type': 'application/json', **(headers or {})}
    if key is not None:
        h['X-OpenAliment-Key'] = key
    req = urllib.request.Request(URL, body if method != 'GET' else None, h, method=method)
    try:
        response = urllib.request.urlopen(req, timeout=10)
    except urllib.error.HTTPError as error:
        response = error
    raw_response = response.read()
    return response.status, response.headers, json.loads(raw_response) if raw_response else None


def data_file(path, content, before=None):
    raw = content.encode() if isinstance(content, str) else content
    return {'path': path, 'content_b64': base64.b64encode(raw).decode(), 'sha256': hashlib.sha256(raw).hexdigest(), 'before_sha256': before}


def stage(files, deployment_id=None):
    deployment_id = deployment_id or uuid.uuid4().hex
    status, _, data = request({'action': 'stage', 'deployment_id': deployment_id, 'label': 'Isolated security test', 'files': files})
    return status, data, deployment_id


def action(name, deployment_id):
    return request({'action': name, 'deployment_id': deployment_id})


try:
    for attempt in range(100):
        try:
            status, headers, result = request()
            break
        except urllib.error.URLError:
            if server.poll() is not None:
                raise RuntimeError('PHP fixture did not start') from None
            time.sleep(0.05)
    else:
        raise RuntimeError('PHP fixture did not start')
    check('Authenticated status uses standalone endpoint without loading private config', status == 200 and result['protocol'] == 'openaliment.deploy.v1' and result['database_execution'] is False)
    check('Generated PHP contains only a hash, never the client key', KEY not in original_endpoint and hashlib.sha256(KEY.encode()).hexdigest() in original_endpoint)
    check('Responses prevent caching and do not start sessions', headers['Cache-Control'].startswith('no-store') and 'Set-Cookie' not in headers)
    check('Missing key is concealed with 404', request(key=None)[0] == 404)
    check('Wrong key is concealed with 404', request(key='0' * 64)[0] == 404)
    check('GET cannot authorize maintenance', request(method='GET')[0] == 404)
    check('Plain HTTP is rejected even with valid key', request(headers={'X-Test-Transport': 'http'})[0] == 404)
    check('Unexpected hostname is rejected', request(headers={'X-Test-Host': 'attacker.invalid'})[0] == 404)
    check('Cross-origin request is rejected', request(headers={'Origin': 'https://attacker.invalid'})[0] == 403)
    check('Non-JSON content is rejected', request(headers={'Content-Type': 'text/plain'})[0] == 415)
    check('Malformed JSON is rejected', request(raw=b'{invalid')[0] == 400)
    check('JSON list cannot select an operation', request(raw=b'[]')[0] == 400)
    check('Unknown operations are rejected', request({'action': 'execute_shell', 'command': 'id'})[0] == 422)
    check('Deployment IDs cannot traverse paths', request({'action': 'deployment', 'deployment_id': '../config'})[0] == 422)
    for path in ['config/local.php', 'storage/private/document.php', 'OpenBread_Codex_v1_2026-10-23/original.php', 'public/openaliment-connect.php', '../config/local.php', '/etc/passwd', 'app/../config/local.php', 'app/../../outside.php', 'app\\outside.php', 'app/%2e%2e/outside.php', 'app/.hidden/test.php', 'app/.htaccess/test.php', 'public/assets/test.php', 'public/assets/.htaccess']:
        check('Protected or invalid path rejected: ' + path, request({'action': 'read', 'paths': [path]})[0] == 422)
    baseline = '<?php\ndeclare(strict_types=1);\nreturn "old";\n'
    updated = '<?php\ndeclare(strict_types=1);\nreturn "new";\n'
    target = SITE / 'app/Test.php'
    target.write_text(baseline, encoding='utf-8', newline='\n')
    before = hashlib.sha256(target.read_bytes()).hexdigest()
    status, _, response = request({'action': 'read', 'paths': ['app/Test.php']})
    check('Scoped source read has matching digest', status == 200 and hashlib.sha256(base64.b64decode(response['files'][0]['content_b64'])).hexdigest() == before)
    status, _, response = request({'action': 'inspect', 'paths': ['app/Absent.php']})
    check('Inspection reports absent files without arbitrary directory listings', status == 200 and response['files'][0]['sha256'] is None)
    check('Invalid PHP is rejected before any update', stage([data_file('app/Test.php', '<?php broken (', before)])[0] == 422 and target.read_text(encoding='utf-8') == baseline)
    check('Missing opening tag cannot disguise PHP as plain text', stage([data_file('app/New.php', 'not php')])[0] == 422)
    bad = data_file('app/Test.php', updated, before); bad['sha256'] = '0' * 64
    check('Content hashes are verified', stage([bad])[0] == 422)
    bad = data_file('app/New.php', '<?php return 1;'); bad['content_b64'] = '%%%%'
    check('Malformed base64 is rejected', stage([bad])[0] == 422)
    bad = data_file('app/New.php', '<?php return 1;'); del bad['before_sha256']
    check('Every file requires an explicit precondition', stage([bad])[0] == 422)
    check('Stale precondition cannot overwrite current file', stage([data_file('app/Test.php', updated, '0' * 64)])[0] == 409)
    repeated = data_file('app/Test.php', updated, before)
    check('Duplicate paths cannot share a journal', stage([repeated, repeated])[0] == 422)
    huge = data_file('public/assets/oversize.js', b'x' * (2097152 + 1))
    check('Per-file upload limit enforced', stage([huge])[0] == 413)
    files = [data_file('app/Test.php', updated, before), data_file('public/assets/new/test.js', b'console.log("test");\n')]
    status, staged, deployment_id = stage(files)
    check('Staging validates a multi-file update without activating it', status == 201 and staged['state'] == 'staged' and target.read_text(encoding='utf-8') == baseline and not (SITE / 'public/assets/new/test.js').exists())
    check('Existing deployment ID cannot be overwritten', stage(files, deployment_id)[0] == 409)
    check('Staged deployment cannot be rolled back as applied', action('rollback', deployment_id)[0] == 409)
    status, _, applied = action('commit', deployment_id)
    check('Commit activates exact bytes and creates nested asset directory', status == 200 and applied['state'] == 'applied' and target.read_text(encoding='utf-8') == updated and (SITE / 'public/assets/new/test.js').is_file())
    check('Private backup retains exact previous bytes', (store / deployment_id / 'before-0.bin').read_text(encoding='utf-8') == baseline)
    check('Repeated commit is idempotent', action('commit', deployment_id)[2]['state'] == 'applied')
    check('Deployment status is recoverable after an uncertain response', action('deployment', deployment_id)[2]['state'] == 'applied')
    status, _, rolled = action('rollback', deployment_id)
    check('Rollback restores old bytes and removes newly added code', status == 200 and rolled['state'] == 'rolled_back' and target.read_text(encoding='utf-8') == baseline and not (SITE / 'public/assets/new/test.js').exists())
    check('Repeated rollback is idempotent', action('rollback', deployment_id)[2]['state'] == 'rolled_back')
    check('Rolled-back update cannot be silently recommitted', action('commit', deployment_id)[0] == 409)
    status, _, conflict_id = stage([data_file('app/Test.php', updated, before)])
    target.write_text('<?php return "external";', encoding='utf-8')
    check('Commit rechecks changes made since staging', action('commit', conflict_id)[0] == 409 and 'external' in target.read_text(encoding='utf-8'))
    target.write_text(baseline, encoding='utf-8', newline='\n')
    _, _, tamper_id = stage([data_file('app/Test.php', updated, before)])
    (store / tamper_id / 'after-0.bin').write_bytes(b'tampered')
    check('Staging corruption is detected before replacing code', action('commit', tamper_id)[0] == 409 and target.read_text(encoding='utf-8') == baseline)
    _, _, backup_id = stage([data_file('app/Test.php', updated, before)])
    action('commit', backup_id)
    target.write_text('<?php return "external";', encoding='utf-8')
    check('Rollback refuses to overwrite subsequent external changes', action('rollback', backup_id)[0] == 409 and 'external' in target.read_text(encoding='utf-8'))
    target.write_text(updated, encoding='utf-8', newline='\n')
    backup = store / backup_id / 'before-0.bin'
    backup.write_bytes(b'tampered')
    check('Backup corruption is detected before restoration', action('rollback', backup_id)[0] == 409 and target.read_text(encoding='utf-8') == updated)
    backup.write_text(baseline, encoding='utf-8', newline='\n')
    check('Restoration succeeds once backup integrity is restored', action('rollback', backup_id)[2]['state'] == 'rolled_back')
    _, _, recovery_id = stage([data_file('app/Test.php', updated, before)])
    action('commit', recovery_id)
    journal_path = store / recovery_id / 'journal.json'
    journal = json.loads(journal_path.read_text()); journal['state'] = 'applying'; journal_path.write_text(json.dumps(journal))
    check('Interrupted applying state supports explicit recovery', action('rollback', recovery_id)[2]['state'] == 'rolled_back' and target.read_text(encoding='utf-8') == baseline)
    # A separate PHP process holds exactly the same OS flock as the endpoint.
    holder = subprocess.Popen([str(PHP), '-r', '$h=fopen($argv[1],"c");flock($h,LOCK_EX);echo "locked\\n";fflush(STDOUT);fgets(STDIN);', str(store / 'operation.lock')], stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, creationflags=flags)
    try:
        assert holder.stdout.readline().strip() == b'locked'
        check('Concurrent operations are rejected while maintenance lock is held', request()[0] == 409)
    finally:
        holder.communicate(b'release\n', timeout=5)
    link = SITE / 'app/Linked'
    outside = BASE / 'outside'; outside.mkdir()
    (outside / 'Secret.php').write_text('<?php return "outside";', encoding='utf-8')
    try:
        os.symlink(outside, link, target_is_directory=True)
    except OSError:
        checks.append({'name': 'Symlink ancestor rejection', 'skipped': 'Windows account cannot create symbolic links'})
    else:
        check('Symlink ancestors cannot escape project root', request({'action': 'read', 'paths': ['app/Linked/Secret.php']})[0] == 422)
        link.unlink()
    client_config = BASE / 'client.json'
    client_config.write_text(json.dumps({**credential, 'endpoint': URL}))
    remote = Client(client_config, allow_local_http=True, root=SITE)
    check('Python client authenticates without printing keys', remote.call('status')['protocol'] == 'openaliment.deploy.v1')
    try:
        Client(client_config)
    except DeployError:
        check('Production client refuses plaintext HTTP', True)
    else:
        check('Production client refuses plaintext HTTP', False)
    client_config.write_text(json.dumps({**credential, 'endpoint': 'https://attacker.invalid/openaliment-connect.php'}))
    try:
        Client(client_config)
    except DeployError:
        check('Production client cannot send key to another hostname', True)
    else:
        check('Production client cannot send key to another hostname', False)
    client_config.write_text(json.dumps({**credential, 'endpoint': URL}))
    local = BASE / 'workspace'; (local / 'app').mkdir(parents=True)
    (local / 'app/Test.php').write_text(updated, encoding='utf-8', newline='\n')
    updater = Client(client_config, allow_local_http=True, root=local)
    try:
        updater.deploy(['app/Test.php'], 'Stale baseline', dry_run=True, expected={'app/Test.php':'0'*64})
        raise AssertionError('Stale client baseline accepted')
    except DeployError:
        check('Client rejects a changed remote baseline before staging',target.read_text(encoding='utf-8')==baseline)
    preview = updater.deploy(['app/Test.php'], 'Client flow', dry_run=True,expected={'app/Test.php':hashlib.sha256(baseline.encode()).hexdigest()})
    check('Client dry-run inspects changes without replacing files', len(preview['changed_files']) == 1 and target.read_text(encoding='utf-8') == baseline)
    receipt = updater.deploy(['app/Test.php'], 'Client flow')
    check('Client performs inspect-stage-commit and verifies remote bytes', receipt['state'] == 'applied' and target.read_text(encoding='utf-8') == updated)
    check('Client avoids redundant updates', updater.deploy(['app/Test.php'], 'No change')['changed_files'] == [])
    updater.call('rollback', deployment_id=receipt['id'])
    check('Client rollback uses the retained server backup', target.read_text(encoding='utf-8') == baseline)
    check('Audit contains changes but never credentials or file contents', 'applied' in (store / 'audit.ndjson').read_text() and KEY not in (store / 'audit.ndjson').read_text() and baseline not in (store / 'audit.ndjson').read_text())
    expiry = int(datetime.fromisoformat(credential['expires_at']).timestamp())
    expired = original_endpoint.replace('const OA_DEPLOY_EXPIRES = ' + str(expiry) + ';', 'const OA_DEPLOY_EXPIRES = 1;')
    assert expired != original_endpoint
    endpoint.write_text(expired, encoding='utf-8', newline='\n')
    check('Expired key cannot authorize maintenance', request()[0] == 404)
    endpoint.write_text(original_endpoint, encoding='utf-8', newline='\n')
    check('Authenticated disable revokes the endpoint', request({'action': 'disable'})[2]['disabled'] is True and request()[0] == 404)
    report = {'suite': 'Standalone HTTPS deployment bridge', 'environment': 'Isolated local file tree; transport checks simulated by private localhost test router', 'passed': sum(item.get('passed', False) for item in checks), 'failed': 0, 'skipped': sum('skipped' in item for item in checks), 'checks': checks}
    destination = ROOT / 'tests/runtime/reports/deploy-bridge-report.json'
    destination.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({key: report[key] for key in ['passed', 'failed', 'skipped']}), flush=True)
finally:
    server.terminate()
    try:
        server.wait(timeout=5)
    except subprocess.TimeoutExpired:
        server.kill(); server.wait(timeout=5)
    log.close()
