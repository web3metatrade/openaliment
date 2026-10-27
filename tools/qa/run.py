"""Portable, ordered QA. Only --reset-test-db authorizes the disposable DB reset."""
import argparse
import json
import os
import shutil
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
RUNTIME = ROOT / 'tests/runtime'


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--suite', choices=['quick', 'local', 'browser'], default='quick')
    parser.add_argument('--reset-test-db', action='store_true', help='Permit resetting ONLY openaliment_tests')
    parser.add_argument('--php', default=os.environ.get('OA_PHP') or shutil.which('php'))
    parser.add_argument('--node', default=os.environ.get('OA_NODE') or shutil.which('node'))
    args = parser.parse_args()
    if not args.php or not args.node:
        parser.error('PHP 8.4+ and Node 24+ must be on PATH or supplied with --php/--node.')
    if args.suite != 'quick' and not args.reset_test_db:
        parser.error('The local/browser suite resets openaliment_tests; pass --reset-test-db explicitly.')
    args.php = shutil.which(args.php) or str(Path(args.php).resolve())
    args.node = shutil.which(args.node) or str(Path(args.node).resolve())
    for directory in ['reports', 'previews', 'logs']:
        (RUNTIME / directory).mkdir(parents=True, exist_ok=True)
    environment = dict(os.environ, PYTHONIOENCODING='utf-8', OA_PHP=args.php, OA_NODE=args.node)
    results = []

    def run(command, name=None):
        label = name or command[1]
        started = time.monotonic()
        result = subprocess.run(command, cwd=ROOT, env=environment, capture_output=True, encoding='utf-8', errors='replace')
        # Raw logs remain private; only sanitized counters are published by CI.
        filename = ''.join(c if c.isalnum() else '-' for c in label)
        (RUNTIME / 'logs' / (filename + '.log')).write_text(result.stdout + result.stderr, encoding='utf-8')
        passed = sum(line.startswith('PASS ') for line in result.stdout.splitlines())
        for line in reversed(result.stdout.splitlines()):
            if line.startswith('{'):
                try:
                    summary = json.loads(line)
                    if isinstance(summary.get('passed'), int):
                        passed = summary['passed']
                        break
                except (ValueError, AttributeError):
                    continue
        results.append({'suite': label, 'passed': passed, 'exit_code': result.returncode, 'seconds': round(time.monotonic() - started, 2)})
        print(json.dumps(results[-1]), flush=True)
        if result.returncode:
            print('FAILED: ' + label + '; details remain in tests/runtime/logs/', file=sys.stderr)
            raise RuntimeError('QA stopped at the first failed suite.')

    server = None
    log = None
    exit_code = 0
    try:
        run([sys.executable, 'tools/qa/check.py', '--php', args.php, '--node', args.node], 'repository checks')
        run([sys.executable, 'tools/build/artifacts.py', '--check'], 'generated contracts')
        run([sys.executable, 'tests/test_repository.py'])
        run([sys.executable, 'tests/test_external_seed.py'])
        run([sys.executable, 'tests/test_collaboration.py'])
        run([sys.executable, 'tests/test_branch_policy.py'])
        run([sys.executable, 'tests/test_security_sources.py'])
        run([sys.executable, 'tools/qa/security_sources.py', '--check'], 'focused source security checks')
        run([args.php, 'tests/bootstrap-unit.php'])
        run([args.php, 'tests/gs1-compatibility.php'])
        run([args.php, 'tests/security-unit.php'])
        run([args.php, 'tests/operations-unit.php'])
        run([args.php, 'tests/api-documentation.php'])
        run([sys.executable, 'tests/test_api_reference_quality.py'])
        run([sys.executable, 'tests/test_public_content.py'])
        for script in ['consumer-domain.mjs', 'cloud-client-domain.mjs', 'receipt-domain.mjs', 'api-explorer.mjs','gs1-compatibility.mjs','external-domain.mjs']:
            run([args.node, 'tests/' + script])
        if args.suite != 'quick':
            run([sys.executable, 'tests/generators/legacy_handover.py'], 'synthetic legacy import package')
            with socket.socket() as reservation:
                reservation.bind(('127.0.0.1', 0))
                port = reservation.getsockname()[1]
            base = f'http://localhost:{port}'
            environment['OA_TEST_BASE_URL'] = base
            environment['OA_ALLOW_TEST_RESET'] = '1'
            run([sys.executable, 'tests/generators/fixtures.py'])
            for script in ['integration.php', 'resolver.php', 'demo-bread.php', 'consumer.php', 'integrations.php', 'mailer.php', 'home-cloud.php', 'api-platform.php', 'security.php', 'readiness.php', 'marketplace.php', 'gs1-reference.php', 'api-reference-fixtures.php','external.php']:
                run([args.php, 'tests/' + script])
            log = (RUNTIME / 'logs/php-server.log').open('wb')
            server = subprocess.Popen([args.php, '-S', f'localhost:{port}', '-t', 'public', 'tools/dev/test-router.php'], cwd=ROOT, env=environment, stdout=log, stderr=log, creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0)
            for attempt in range(100):
                if server.poll() is not None:
                    raise RuntimeError('Local PHP test server exited; see tests/runtime/logs/php-server.log.')
                try:
                    with urllib.request.urlopen(base + '/acasa/', timeout=1) as response:
                        if response.status == 200:
                            break
                except (urllib.error.URLError, OSError):
                    pass
                time.sleep(.1)
            else:
                raise RuntimeError('Local PHP test server did not become ready.')
            for script in ['test_http.py', 'test_resolver_http.py', 'test_demo_bread.py', 'test_integrations_http.py', 'test_home_http.py', 'test_api_platform_http.py', 'test_security_http.py', 'test_api_reference_http.py', 'test_api_documentation.py', 'test_readiness_http.py','test_marketplace_http.py','test_gs1_compatibility_http.py','test_external_http.py']:
                if script=='test_api_reference_http.py':
                    run([args.php,'tests/api-reference-fixtures.php','--reset-rates'],'reset isolated reference rate counters')
                run([sys.executable, 'tests/' + script])
            run([sys.executable, 'tests/test_api_coverage.py'])
            run([sys.executable, 'tests/test_api_sdk.py'])
            run([args.node, 'tests/api-sdk.mjs'])
            run([args.node, 'tests/qr-matrices.cjs'])
            run([sys.executable, 'tests/test_qr.py'])
            run([sys.executable, 'tests/test_deploy_bridge.py'])
            if args.suite == 'browser':
                run([args.node, 'tests/consumer-browser.cjs'])
                run([args.node, 'tests/account-access-browser.cjs'])
                run([args.node, 'tests/marketplace-browser.cjs'])
                previous_browser_qr = environment.get('OA_TEST_BROWSER_QR')
                environment['OA_TEST_BROWSER_QR'] = '1'
                try:
                    run([sys.executable, 'tests/test_qr.py'], 'independent downloaded browser SVG decode')
                finally:
                    if previous_browser_qr is None:
                        environment.pop('OA_TEST_BROWSER_QR', None)
                    else:
                        environment['OA_TEST_BROWSER_QR'] = previous_browser_qr
                run([args.node, 'tests/first-visit-browser.cjs'])
                run([args.node, 'tests/external-browser.cjs'])
            run([args.php, 'tests/backup.php'])
    except (RuntimeError, OSError) as error:
        exit_code = 1
        print(str(error), file=sys.stderr)
    finally:
        if server is not None:
            server.terminate()
            try:
                server.wait(timeout=10)
            except subprocess.TimeoutExpired:
                server.kill()
                server.wait()
        if log is not None:
            log.close()
        report = {'executed_at': datetime.now(timezone.utc).isoformat(), 'environment': 'isolated local QA; no production access or real email', 'suite': args.suite, 'suites': results, 'failed_suites': sum(r['exit_code'] != 0 for r in results), 'completed': exit_code == 0}
        (RUNTIME / 'reports/qa-summary.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
    return exit_code


if __name__ == '__main__':
    raise SystemExit(main())
