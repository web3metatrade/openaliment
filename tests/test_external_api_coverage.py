"""Map OFF page actions to runtime routes, authorization, contracts and portal pages."""
import json
import os
import re
import shutil
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
php = os.environ.get('OA_PHP') or shutil.which('php') or str(ROOT / '.tools/php/php.exe')
registry = json.loads(subprocess.run([php, 'tools/build/api_registry.php'], cwd=ROOT, check=True, capture_output=True, encoding='utf-8').stdout)
routes = {r['id']: r for r in registry if r['handler'].startswith('external.')}
spec = json.loads((ROOT / 'contracts/platform-openapi.json').read_text(encoding='utf-8'))
pages = json.loads((ROOT / 'contracts/api-docs/external.json').read_text(encoding='utf-8'))
coverage = json.loads((ROOT / 'docs/api/external-action-coverage.json').read_text(encoding='utf-8'))
checks = []


def check(name, condition):
    if not condition:
        raise AssertionError(name)
    checks.append(name)


mapped = set(coverage['api_and_client_operations'])
for view in (ROOT / 'app/Views').glob('external*.php'):
    has_actions = 'name="action"' in view.read_text(encoding='utf-8')
    check('External forms cannot be omitted from the action matrix: ' + view.name, not has_actions or view.name in coverage['web_actions'])
for ids in coverage['web_reads'].values():
    mapped.update(ids)
for view, actions in coverage['web_actions'].items():
    source = (ROOT / 'app/Views' / view).read_text(encoding='utf-8')
    actual = set(re.findall(r'name="action"[^>]*?value="([A-Za-z_]+)"', source))
    excluded = {action for action, exception in coverage['operator_exceptions'].items() if exception['view'] == view}
    check('Every form action has an explicit API or operator classification: ' + view, actual == set(actions) | excluded)
    mapped.update(actions.values())
check('Every external route has an action or client use in the maintained matrix', mapped == set(routes))
check('Every external route has a generated reference page', set(pages) == set(routes))
controller = (ROOT / 'app/Controllers/ExternalSourcesController.php').read_text(encoding='utf-8')
controller_actions = set(re.findall(r"\$action==='([A-Za-z]+)'", controller))
known_actions = {action for actions in coverage['web_actions'].values() for action in actions} | {'disconnectBinding'}
check('Every web controller action is reviewed in the action matrix', controller_actions <= known_actions)
guide = (ROOT / 'docs/api/pages/surse-externe.md').read_text(encoding='utf-8')
guide_operations = set(re.findall(r'/api/docs/reference/(external[A-Za-z]+)', guide))
check('The public guide links every external operation', guide_operations == set(routes))
postman = json.loads((ROOT / 'contracts/api-postman-collection.json').read_text(encoding='utf-8'))
postman_operations = set()
for fragment in postman['x-item-files']:
    folder = json.loads((ROOT / 'contracts/api-docs' / fragment).read_text(encoding='utf-8'))
    for item in folder['item']:
        postman_operations.update(re.findall(r'operationId: (external[A-Za-z]+)', item['request']['description']))
check('Postman includes every external operation', postman_operations == set(routes))

for oid, route in routes.items():
    operation = spec['paths'][route['path']][route['method'].lower()]
    check('Stable operation matches runtime: ' + oid, operation['operationId'] == oid)
    check('Scope matches runtime: ' + oid, operation['x-required-scope'] == route['scope'])
    check('Authentication matches runtime: ' + oid, operation['x-auth-context'] == route['auth'])
    check('Reference and Markdown are complete: ' + oid, operation['x-reference-complete'] and pages[oid]['markdown'] and pages[oid]['examples'])
    if route['auth'] == 'producer':
        check('Partner Bearer transport is documented: ' + oid, {'Bearer': []} in operation['security'] and bool(route['scope']))
    if route['auth'] == 'admin-session':
        check('Administration is not delegated to ERP keys: ' + oid, operation['security'] == [{'ProducerSession': []}])
    if route['handler'] in ['external.organizationBindings', 'external.bindings', 'external.conflicts', 'external.grants', 'external.publications', 'external.revisionIndex']:
        check('List pagination is documented: ' + oid, {'cursor', 'limit'} <= {p['name'] for p in operation['parameters']})

check('External state fields never describe producer batch state', 'Publicarea lotului și eliberarea sa' not in pages['externalRunWorker']['markdown'])
report = {'passed': len(checks), 'failed': 0, 'external_operations': len(routes), 'form_actions': sum(len(a) for a in coverage['web_actions'].values()), 'operator_exceptions': coverage['operator_exceptions'], 'checks': checks}
destination = ROOT / 'tests/runtime/reports/external-api-coverage.json'
destination.parent.mkdir(parents=True, exist_ok=True)
destination.write_text(json.dumps(report, ensure_ascii=True, indent=2) + '\n', encoding='utf-8')
print(json.dumps({k: report[k] for k in ['passed', 'failed', 'external_operations', 'form_actions']}))
