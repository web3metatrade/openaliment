"""Exercise documented resource families against disposable local HTTP, never production."""
import http.cookiejar
import importlib.util
import json
import os
import re
import socket
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path
from api_contract_probe import SPEC, validate, report as contract_report

ROOT = Path(__file__).resolve().parents[1]
BASE = os.environ['OA_TEST_BASE_URL']
if urllib.parse.urlsplit(BASE).hostname not in ['127.0.0.1', 'localhost', '::1']:
    raise RuntimeError('Isolated loopback required')
f = json.loads((ROOT / 'tests/runtime/api-platform-fixtures.json').read_text(encoding='utf-8'))
d = json.loads((ROOT / 'tests/runtime/api-reference-fixtures.json').read_text(encoding='utf-8'))
checks = []


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


jar = http.cookiejar.CookieJar()
web = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect())
plain = urllib.request.build_opener(NoRedirect())


def check(name, ok):
    if not ok:
        raise AssertionError(name)
    checks.append({'name': name, 'passed': True})


def call(path, method='GET', payload=None, token=None, session=False, home=False, raw=None, headers=None, expected=None):
    h = dict(headers or {})
    if token:
        h['Authorization'] = 'Bearer ' + token
    if home:
        h['Cookie'] = 'oa_home=' + d['home']['secret']
    body = raw
    if payload is not None:
        body = json.dumps(payload, ensure_ascii=False).encode()
        h['Content-Type'] = 'application/json'
    if method in ['POST', 'PUT', 'DELETE']:
        h.setdefault('Idempotency-Key', 'reference-' + uuid.uuid4().hex)
        if session:
            h['X-CSRF-Token'] = csrf
        if home:
            h['X-Home-CSRF'] = home_csrf
    request = urllib.request.Request(BASE + path, data=body, headers=h, method=method)
    try:
        response = (web if session else plain).open(request, timeout=30)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        content = response.read()
        value = json.loads(content) if content and 'json' in response.headers.get('Content-Type', '') else content
        validate(path, method, response.status, response.headers, value)
        if expected is not None:
            check(method + ' ' + urllib.parse.urlsplit(path).path + ' HTTP ' + str(response.status), response.status in expected)
        return response.status, response.headers, value


csrf = call('/api/v2/identity/session', session=True)[2]['csrf']
csrf = call('/api/v2/identity/sessions', 'POST', {'email': f['email'], 'password': f['password']}, session=True, expected=[200])[2]['csrf']
home_csrf = call('/api/v2/home/session', home=True)[2]['session']['csrf']

# Every read route, including legacy shapes, nested lists, admin, metadata and binaries.
resource_ids = {'products': d['pid'], 'trade-items': d['item'], 'labels': d['label'], 'batches': d['batch'], 'recipes': d['recipe'], 'recipe-versions': d['recipe'], 'documents': d['doc'], 'feedback': d['feedback']['id'], 'proposals': d['proposal'], 'imports': d['import'], 'audit-events': d['audit'], 'ai-jobs': d['ai'], 'connectors': d['connector']}
for path, methods in SPEC['paths'].items():
    if 'get' not in methods or path.endswith('/authorize') or '/photos/' in path or any(family in path for family in ['/marketplace/', '/printing/', '/standards/']):
        continue
    if methods['get']['operationId'].startswith('external'):continue  # Dedicated OFF suite validates these source-specific fixtures.
    operation = methods['get']
    actual = path
    if '{id}' in actual:
        parts = path.split('/')
        parent = next((parts[i - 1] for i, part in enumerate(parts) if part == '{id}'), '')
        identity = d['batch'] if '/qr/batch/' in path else d['item'] if '/qr/item/' in path else resource_ids.get(parent, d['item'])
        actual = actual.replace('{id}', identity)
    actual = actual.replace('{connector_id}', d['connector'])
    actual = actual.replace('{gtin}', '09520000000011').replace('{lot}', 'DEMO-PBT-01').replace('{variant}', '01').replace('{serial}', 'EXEMPLU').replace('{tpx}', 'EXEMPLU')
    query = {'code': '9520000000011'} if path == '/api/v2/public/food' else {'linkType': 'linkset'} if path.startswith('/01/') else {}
    if query:
        actual += '?' + urllib.parse.urlencode(query)
    security = operation.get('security', [])
    session = any('ProducerSession' in scheme for scheme in security) and not any('Bearer' in scheme for scheme in security)
    is_home = path.startswith('/api/v2/home/')
    public = not security
    headers = {'X-Status-Token': d['feedback']['status_token']} if '/feedback/{id}/status' in path else {}
    token = f['key'] if not public and not session and not is_home else None
    call(actual, token=token, session=session or path == '/api/v2/identity/session', home=is_home, headers=headers, expected=[200])

# Shared mutation implementations through BOTH v1 and v2 paths.
for version in ['v1', 'v2']:
    base = '/api/' + version + '/manage/'
    def create(path, payload):
        return call(base + path, 'POST', payload, f['key'], expected=[201])[2]
    product = create('products', {'name': 'Exemplu ' + version, 'category': 'lactate'})
    item = create('products/' + product['id'] + '/trade-items', {'name': 'Pahar 200 g'})
    label = create('trade-items/' + item['id'] + '/labels', {'version_key': 'E1', 'name': 'Iaurt', 'category': 'lactate', 'ingredients': 'Lapte.', 'allergens': 'Lapte.', 'net_quantity': '200 g', 'operator': 'Operator sintetic'})
    create('labels/' + label['id'] + '/publish', {})
    batch = create('trade-items/' + item['id'] + '/batches', {'lot_code': 'REF-' + uuid.uuid4().hex[:12], 'product_info_version_id': label['id'], 'recipe_version_id': None})
    create('batches/' + batch['id'] + '/publish', {})
    create('batches/' + batch['id'] + '/release', {})
    alert = create('batches/' + batch['id'] + '/alerts', {'message': 'Alertă sintetică', 'state': 'on_hold'})
    create('alerts/' + alert['id'] + '/resolve', {'resolution': 'Clarificare sintetică'})
    create('products/' + product['id'] + '/recipes', {'version_key': 'R1', 'snapshot': {'formula': [], 'process': []}})
    proposal = create('products/' + product['id'] + '/proposals', {'title': 'Propunere', 'body': 'Explicație sintetică'})
    create('proposals/' + proposal['id'] + '/decide', {'state': 'planned', 'decision': 'Plan de test'})
    call('/api/v2/manage/documents/'+d['doc']+'/scan','POST',{},f['key'],expected=[200])
    create('documents/' + d['doc'] + '/publish', {'rights_confirmed': True, 'public_projection_confirmed': True})
    create('feedback/' + d['feedback']['id'] + '/moderate', {'status': 'published', 'public_text': 'Copie publică sintetică.', 'reason': 'Revizie'})
    link = create('trade-items/' + d['item'] + '/resolver-links', {'href': 'https://example.invalid/' + version, 'title': 'Destinație sintetică', 'types': ['gs1:pip']})
    create('resolver-links/' + link['id'] + '/publish', {'public_projection_confirmed': True})
    create('resolver-links/' + link['id'] + '/withdraw', {'reason': 'Retragere de test'})
    call(base + 'import', 'POST', {'dry_run': True, 'package': d['package']}, f['key'], expected=[200])
    call(base + 'import-pbt', 'POST', {'dry_run': True}, f['key'], expected=[200])
    feedback_path = '/api/v1/feedback' if version == 'v1' else '/api/v2/community/feedback'
    call(feedback_path, 'POST', {'trade_item_id': item['id'], 'text': 'Feedback sintetic', 'consent': True, 'submission_key': 'ref-feedback-' + uuid.uuid4().hex}, expected=[201])

call('/api/v2/manage/label-validations', 'POST', {'version_key': 'E-VALID', 'name': 'Iaurt', 'category': 'lactate', 'ingredients': 'Lapte.', 'allergens': 'Lapte.', 'net_quantity': '200 g', 'operator': 'Operator'}, f['key'], expected=[200])
call('/api/v2/tools/identifiers/parse', 'POST', {'code': '9520000000011'}, expected=[200])
call('/api/v2/tools/identifiers/validate', 'POST', {'code': 'https://openaliment.ro/01/09520000000011/10/A%2FB%2B00%25%3F'}, expected=[200])
call('/api/v2/public/food-lookups', 'POST', {'codes': ['9520000000011', '1111111111111']}, expected=[200])
call('/api/v2/community/proposals/' + d['proposal'] + '/vote', 'PUT', {}, f['key'], expected=[200])
call('/api/v2/community/proposals/' + d['proposal'] + '/vote', 'DELETE', token=f['key'], expected=[200])
key = call('/api/v2/manage/access-tokens', 'POST', {'name': 'Reference key', 'scopes': ['read'], 'days': 1}, session=True, expected=[201])[2]
replacement = call('/api/v2/manage/access-tokens/' + key['id'] + '/rotations', 'POST', {}, session=True, expected=[201])[2]
call('/api/v2/manage/access-tokens/' + replacement['id'], 'DELETE', session=True, expected=[200])
self_key = call('/api/v2/manage/access-tokens', 'POST', {'name': 'Reference self-revoke', 'scopes': ['read']}, session=True, expected=[201])[2]
call('/api/v2/auth/token', 'DELETE', token=self_key['token'], expected=[200])
client = call('/api/v2/developer/apps', 'POST', {'name': 'Reference app', 'redirect_uris': ['https://developer.example.invalid/callback'], 'scopes': ['read', 'home:read']}, session=True, expected=[201])[2]
params = {'client_id': f['client_id'], 'redirect_uri': 'https://developer.example.invalid/callback', 'response_type': 'code', 'state': 'state-' + uuid.uuid4().hex, 'code_challenge': f['challenge'], 'code_challenge_method': 'S256', 'scope': 'read'}
html = call('/oauth/authorize?' + urllib.parse.urlencode(params), session=True, expected=[200])[2]
rid = re.search(rb'name="request_id" value="([a-f0-9]{32})"', html)[1].decode()
form = urllib.parse.urlencode({'_csrf': csrf, 'request_id': rid, 'resource_id': f['a']['tenant'], 'decision': 'approve'}).encode()
location = call('/oauth/authorize', 'POST', session=True, raw=form, headers={'Content-Type': 'application/x-www-form-urlencoded'}, expected=[303])[1]['Location']
code = urllib.parse.parse_qs(urllib.parse.urlsplit(location).query)['code'][0]
oauth = call('/oauth/token', 'POST', {'grant_type': 'authorization_code', 'client_id': f['client_id'], 'redirect_uri': params['redirect_uri'], 'code': code, 'code_verifier': f['verifier']}, expected=[200])[2]
call('/oauth/revoke', 'POST', {'client_id': f['client_id'], 'token': oauth['access_token']}, expected=[200])
call('/api/v2/identity/grants/' + oauth['grant_id'], 'DELETE', session=True, expected=[200])
params['scope'] = 'home:read'
call('/api/v2/home/oauth/authorize?' + urllib.parse.urlencode(params), session=True, expected=[200])
call('/api/v2/developer/apps/' + client['id'] + '/suspend', 'POST', {}, session=True, expected=[200])
call('/api/v2/identity/organization-selection', 'POST', {'organization_id': f['a']['tenant']}, session=True, expected=[200])
for path, payload in [('organizations/' + f['a']['tenant'] + '/identity-reviews', {'evidence': 'Probă sintetică în baza de test.'}), ('users/' + f['a']['user'] + '/contact-reviews', {'evidence': 'Probă sintetică de contact.'}), ('gtin-claims/' + d['item'] + '/decisions', {'evidence': 'Mandat exclusiv simulat.', 'state': 'verified'})]:
    call('/api/v2/admin/' + path, 'POST', payload, session=True, expected=[200])

# Connector branches: product/batch mapping plus a confidential simulated receipt.
connector = call('/api/v2/manage/connectors', 'POST', {'name':'Reference ERP','kind':'catalog'}, f['key'], expected=[201])[2]
fields = {'/name':{'value':'Produs ERP'},'/category':{'value':'lactate'},'/trade_name':{'value':'Pahar ERP'},'/label/version_key':{'value':'E1'},'/label/name':{'value':'Iaurt ERP'},'/label/category':{'value':'lactate'},'/label/ingredients':{'value':'Lapte.'},'/label/allergens':{'value':'Lapte.'},'/label/net_quantity':{'value':'200 g'},'/label/operator':{'value':'Operator sintetic'}}
mapping = call('/api/v2/manage/connectors/'+connector['id']+'/mappings','POST',{'version_key':'M1','mapping':{'schema_version':'openaliment.mapping.v1','fields':fields}},f['key'],expected=[201])[2]
event = {'mapping_id':mapping['id'],'operation':'product','payload':{}}
call('/api/v2/manage/connectors/'+connector['id']+'/preview','POST',event,f['key'],expected=[200])
event.update(event_key='product-'+uuid.uuid4().hex,external_id='ERP-REF-001')
call('/api/v2/manage/connectors/'+connector['id']+'/ingest','POST',event,f['key'],expected=[201])
receipt = json.loads((ROOT/'tests/runtime/integration-fixtures.json').read_text(encoding='utf-8'))
call('/api/v2/home/receipts/preview','POST',{'token':receipt['receipt_token']},expected=[200])

# Real multipart upload, not a fabricated metadata-only response.
boundary='reference-'+uuid.uuid4().hex
multipart=(f'--{boundary}\r\nContent-Disposition: form-data; name="title"\r\n\r\nReference file\r\n--{boundary}\r\nContent-Disposition: form-data; name="document"; filename="reference.txt"\r\nContent-Type: text/plain\r\n\r\nSynthetic reference file\r\n--{boundary}--\r\n').encode()
call('/api/v2/manage/products/'+d['pid']+'/documents','POST',token=f['key'],raw=multipart,headers={'Content-Type':'multipart/form-data; boundary='+boundary},expected=[201])

# Magic links use the private test_file transport; nothing leaves localhost.
call('/api/v2/home/login/request','POST',{'email':d['home']['email']},expected=[200])
call('/api/v2/home/login/preview','POST',{'token':d['preview_token']},expected=[200])
new_device=call('/api/v2/home/login/confirm','POST',{'token':d['preview_token'],'device_name':'Reference second device'},expected=[200])[2]
call('/api/v2/home/sync','POST',{'operations':[],'history':[]},home=True,expected=[200])
photo=uuid.uuid4().hex
call('/api/v2/home/photos/'+photo,'POST',home=True,raw=(ROOT/'tests/runtime/consumer-no-code.jpg').read_bytes(),headers={'Content-Type':'image/jpeg'},expected=[200])
call('/api/v2/home/photos/'+photo,home=True,expected=[200])
invitation=call('/api/v2/home/invites','POST',{},home=True,expected=[201])[2]
invite_token=urllib.parse.parse_qs(urllib.parse.urlsplit(invitation['url']).fragment)['familie'][0]
call('/api/v2/home/invites/preview','POST',{'token':invite_token},expected=[200])
call('/api/v2/home/join','POST',{'token':invite_token},home=True,expected=[200])
call('/api/v2/home/switch','POST',{'household_id':d['home']['household_id']},home=True,expected=[200])
call('/api/v2/home/rename','POST',{'name':'Familie sintetică'},home=True,expected=[200])
call('/api/v2/home/members/'+uuid.uuid4().hex+'/remove','POST',{},home=True,expected=[200])
call('/api/v2/home/devices/'+new_device['device_id']+'/revoke','POST',{},home=True,expected=[200])
call('/api/v2/home/grants/'+d['home_grant']+'/revoke','POST',{},home=True,expected=[200])
call('/api/v2/home/logout','POST',{},home=True,expected=[200])

# Registration/logout operate on a separate synthetic account at the end.
account_password=uuid.uuid4().hex
registration=call('/api/v2/identity/registrations','POST',{'email':'reference-'+uuid.uuid4().hex+'@example.invalid','password':account_password,'display_name':'Reference account','organization':'Reference organization'},session=True,expected=[201])[2]
csrf=registration['csrf']
revoked=call('/api/v2/identity/access-revocations','POST',{'current_password':account_password},session=True,expected=[200])[2]
csrf=revoked['csrf']
replacement_password=uuid.uuid4().hex
changed=call('/api/v2/identity/password','POST',{'current_password':account_password,'new_password':replacement_password,'confirmation':replacement_password},session=True,expected=[200])[2]
csrf=changed['csrf']
call('/api/v2/identity/sessions/current','DELETE',session=True,expected=[200])

# Exercise queue admission in a separate TEST-ONLY configuration; no worker or AI call.
check('Primary HTTP environment has AI disabled',call('/api/v2/manage/ai/capabilities',token=f['key'])[2]['enabled'] is False)
with socket.socket() as reservation:
    reservation.bind(('127.0.0.1',0));queue_port=reservation.getsockname()[1]
original_base=BASE
queue_env=dict(os.environ,OA_TEST_QUEUE_AI='1',OA_TEST_BASE_URL=f'http://127.0.0.1:{queue_port}')
with (ROOT/'tests/runtime/logs/reference-queue-http.log').open('wb') as queue_log:
    server=subprocess.Popen([os.environ.get('OA_PHP',str(ROOT/'.tools/php/php.exe')),'-S',f'127.0.0.1:{queue_port}','-t','public','tools/dev/test-router.php'],cwd=ROOT,env=queue_env,stdout=queue_log,stderr=queue_log,creationflags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0)
    try:
        BASE=queue_env['OA_TEST_BASE_URL']
        for attempt in range(50):
            try:
                with urllib.request.urlopen(BASE+'/api/v2/public/status',timeout=1):break
            except OSError:time.sleep(.05)
        else:raise RuntimeError('Local queue probe did not start')
        call('/api/v2/manage/ai-jobs','POST',{'feedback_id':d['feedback']['id']},f['key'],expected=[202])
    finally:
        BASE=original_base;server.terminate();server.wait(timeout=5)

# Execute the downloadable workflow twice: real HTTP, stable resume, no duplicate jobs.
module_spec = importlib.util.spec_from_file_location('erp_example', ROOT / 'tools/examples/erp_workflow.py')
module = importlib.util.module_from_spec(module_spec);module_spec.loader.exec_module(module)
state = ROOT / 'tests/runtime' / ('erp-example-' + uuid.uuid4().hex + '.json')
first = module.run(BASE, f['key'], state, True)
again = module.run(BASE, f['key'], state, True)
check('Executable workflow resumes without duplicate product or batch', first == again and first['recipe_required'] is False)
check('Workflow state never contains credentials', f['key'] not in state.read_text(encoding='utf-8'))
summary = {'passed': len(checks), 'failed': 0, **contract_report()}
expected_operations={op['operationId'] for methods in SPEC['paths'].values() for op in methods.values() if isinstance(op,dict) and 'operationId' in op}
summary['remaining_operations_for_family_suites']=sorted(expected_operations-set(summary['successful_operations']))
summary['passed']=len(checks)
(ROOT / 'tests/runtime/reports/api-reference-http.json').write_text(json.dumps(summary, indent=2), encoding='utf-8')
print(json.dumps({'passed': len(checks), 'failed': 0, 'validated_responses': summary['response_checks'], 'successful_operations': len(summary['successful_operations'])}))
