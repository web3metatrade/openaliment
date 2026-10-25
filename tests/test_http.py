"""End-to-end HTTP checks against the isolated local PHP preview."""
import json
import hashlib
import re
import uuid
import urllib.request
import urllib.error
import urllib.parse
import http.cookiejar
import os
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
BASE = os.environ.get('OA_TEST_BASE_URL', 'http://127.0.0.1:8088')
fixture = json.loads((ROOT / 'tests/runtime/http-fixtures.json').read_text(encoding='utf-8'))
report = []

def check(name, condition):
    report.append({'name': name, 'passed': bool(condition)})
    if not condition:
        raise AssertionError(name)
    print('PASS', name)

def request(path, data=None, token=None, key=None, headers=None, opener=None, method=None):
    h = dict(headers or {})
    body = None
    if data is not None:
        body = json.dumps(data, ensure_ascii=False).encode('utf-8')
        h['Content-Type'] = 'application/json'
    if token:
        h['Authorization'] = 'Bearer ' + token
    if key:
        h['Idempotency-Key'] = key
    req = urllib.request.Request(BASE + path, body, h, method=method)
    try:
        response = (opener or urllib.request.build_opener()).open(req, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    raw = response.read().decode('utf-8')
    return response.status, response.headers, raw

token = fixture['token']
status, headers, home = request('/')
check('Homepage serves complete Romanian page', status == 200 and '<html lang="ro">' in home and 'openaliment' in home.lower())
check('Homepage does not start anonymous session', 'Set-Cookie' not in headers)
check('CSP and nosniff headers set', "default-src 'self'" in headers['Content-Security-Policy'] and headers['X-Content-Type-Options'] == 'nosniff')
check('Public API excludes unapproved pilot', fixture['pilot_id'] not in request('/api/v1/products')[2])
status, headers, batch = request('/b/' + fixture['batch_id'])
check('Batch page works with AI disabled', status == 200 and 'Lapte, culturi lactice.' in batch)
check('Batch page cannot be served from stale cache', 'no-store' in headers['Cache-Control'] and 'Set-Cookie' not in headers)
check('Batch page shows historical label', 'Lapte, culturi lactice, fructe.' not in batch)
check('Private recipe is absent in HTML', 'SECRET_RECIPE_A' not in batch and fixture['private_recipe_id'] not in batch)
check('Private recipe URL is not public', request('/p/' + fixture['product_id'] + '/v/R-private')[0] == 404)
check('Unknown batch is 404', request('/b/' + 'f' * 32)[0] == 404)
check('Invalid GTIN URL is 400', request('/01/09506000134353')[0] == 400)
check('Serial inherits product information without selecting a lot', request('/01/09506000134352/21/123')[0] == 200 and '/b/' not in request('/01/09506000134352/21/123')[2].split('<main')[0])
check('Resolver returns a machine-readable linkset', request('/01/09506000134352?linkType=linkset',headers={'Accept':'application/linkset+json'})[0] == 200)
check('Resolver descriptor declares supported GTIN profile', json.loads(request('/.well-known/gs1resolver')[2])['supportedPrimaryKeys'] == ['01'])
check('GS1 lot lookup decodes reserved characters once', request('/01/09506000134352/10/A%2FB%2B00%25%3F')[0] == 200)
check('Case-different unknown lot does not select nearest lot', 'nu are o pagină publicată' in request('/01/09506000134352/10/lota')[2])
check('Manipulated date does not change approved date', 'diferă de informația aprobată' in request('/01/09506000134352/10/A%2FB%2B00%25%3F?17=991231')[2])
check('Tenant B token cannot read tenant A private object', request('/api/v1/manage/recipes/' + fixture['private_recipe_id'], token=fixture['read_token'])[0] == 404)
check('Read-only token cannot create product', request('/api/v1/manage/products', {'name':'Attack','category':'x'}, fixture['read_token'], 'test-unauthorized')[0] == 403)
check('Unauthenticated private API is denied', request('/api/v1/manage/products')[0] == 401)
check('Cross-origin mutation denied', request('/api/v1/manage/products', {'name':'Attack','category':'x'}, token, 'test-origin', {'Origin':'https://example.invalid'})[0] == 403)
check('Missing idempotency key rejected', request('/api/v1/manage/products', {'name':'Attack','category':'x'}, token)[0] == 422)
namespace = uuid.uuid4().hex
def mutate(path, payload, suffix):
    status, _, raw = request('/api/v1/manage/' + path, payload, token, namespace + '-' + suffix)
    if status != 201:
        raise AssertionError((path, status, raw))
    return json.loads(raw)

product = mutate('products', {'name':'Suc de mere · verificare HTTP','category':'băuturi'}, 'product')
same = mutate('products', {'name':'Suc de mere · verificare HTTP','category':'băuturi'}, 'product')
check('HTTP ERP retry returns same product', same['id'] == product['id'])
check('HTTP ERP rejects reused key with different content', request('/api/v1/manage/products', {'name':'Different','category':'x'}, token, namespace + '-product')[0] == 409)
item = mutate('products/' + product['id'] + '/trade-items', {'name':'Sticlă 330 ml'}, 'item')
label = mutate('trade-items/' + item['id'] + '/labels', {'version_key':'E1','name':'Suc de mere · verificare HTTP','category':'băuturi','ingredients':'Mere.','allergens':'Conform informației aprobate a producătorului.','net_quantity':'330 ml','operator':'Operator de test local','description':'<script>privateMarker()</script>'}, 'label')
mutate('labels/' + label['id'] + '/publish', {}, 'publish-label')
lot = mutate('trade-items/' + item['id'] + '/batches', {'product_info_version_id':label['id'],'lot_code':'ERP-001','recipe_version_id':None}, 'batch')
mutate('batches/' + lot['id'] + '/publish', {}, 'publish-batch')
check('First complete API flow has no recipe', json.loads(request('/api/v1/batches/' + lot['id'])[2])['label']['category'] == 'băuturi' and lot['recipe_version_id'] is None)
page = request('/b/' + lot['id'])[2]
check('Public text is escaped against script injection', '&lt;script&gt;privateMarker()&lt;/script&gt;' in page and '<script>privateMarker()' not in page)
qr = json.loads(request('/api/v1/manage/qr/batch/' + lot['id'], token=token)[2])
check('QR destination opens exact ERP batch', qr['url'] == BASE + '/b/' + lot['id'] and request(qr['url'].removeprefix(BASE))[0] == 200)
alert = mutate('batches/' + lot['id'] + '/alerts', {'message':'Retragere de test HTTP','state':'recalled'}, 'alert')
status, headers, page = request('/b/' + lot['id'])
check('New recall visible immediately, before ingredient content', page.index('Retragere de test HTTP') < page.index('Ce conține') and 'no-store' in headers['Cache-Control'])
check('Public API has no private columns', all(key not in json.loads(request('/api/v1/batches/' + lot['id'])[2]) for key in ['tenant_id','recipe_version_id','approved_by']))
check('Export requires export scope', request('/api/v1/manage/export', token=fixture['read_token'])[0] == 403)
check('API documentation is available', request('/api/docs')[0] == 200)
check('Unsupported HTTP method is 405', request('/', method='DELETE')[0] == 405)
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
_, _, login = request('/login', opener=opener)
check('Anonymous visitor can open login form', '<h1 class="page-title">Intră în cont</h1>' in login and 'name="password"' in login)
csrf = re.search(r'name="_csrf" value="([a-f0-9]+)"', login).group(1)
body = urllib.parse.urlencode({'_csrf':csrf,'email':fixture['email'],'password':fixture['password']}).encode()
login_response = opener.open(urllib.request.Request(BASE + '/login', body), timeout=15)
dashboard = login_response.read().decode()
check('Login sets secure session and reaches dashboard', login_response.status == 200 and 'Produsele tale' in dashboard and any(cookie.name == 'oa_session' for cookie in jar))
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

session_opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect())
for auth_path in ['/login', '/register']:
    for auth_method in ['GET', 'HEAD']:
        status, headers, body = request(auth_path, opener=session_opener, method=auth_method)
        check('Authenticated ' + auth_method + ' ' + auth_path + ' redirects to own space', status == 303 and headers.get('Location') == '/manage' and 'name="password"' not in body)
check('Cookie session mutation without CSRF is rejected', request('/api/v1/manage/products', {'name':'Attack','category':'x'}, key='csrf-test-0001', opener=opener)[0] == 419)
check('QR generator page available to owner', request('/manage/qr/batch/' + lot['id'], opener=opener)[0] == 200)
for path in ['/manage/products/' + product['id'], '/manage/items/' + item['id'], '/manage/labels/' + label['id'], '/manage/batches/' + lot['id'], '/manage/api', '/manage/import', '/manage/feedback', '/admin']:
    check('Owner page ' + '/'.join(path.split('/')[1:3]), request(path, opener=opener)[0] == 200)
status, headers, stream = request('/api/v1/manage/export?format=ndjson', token=token)
lines = stream.splitlines(keepends=True)
end = json.loads(lines[-1])
check('Streaming export has a verified terminal digest', status == 200 and end.get('end') is True and end['sha256'] == hashlib.sha256(''.join(lines[:-1]).encode('utf-8')).hexdigest())
check('Streaming export includes private PBT provenance without credentials', 'openbread.product_seed.v0.1' in stream and 'SECRET_RECIPE_A' in stream and 'password_hash' not in stream and fixture['token'] not in stream)
check('Streaming export row count matches terminal record', end['records'] == len(lines) - 2)
admin_csrf = re.search(r'name="_csrf" value="([a-f0-9]+)"', dashboard).group(1)
def admin_post(data):
    data['_csrf'] = admin_csrf
    r = opener.open(urllib.request.Request(BASE + '/admin', urllib.parse.urlencode(data).encode()), timeout=15)
    return r.status
check('Organization verification has an explicit administrative action', admin_post({'action':'organization.verify','id':fixture['tenant_a']['tenant'],'evidence':'Dovadă exclusiv de test local.'}) == 200)
check('GS1 right can be reviewed separately from label publication', admin_post({'action':'gtin.review','id':fixture['item_id'],'state':'disputed','evidence':'Dispută exclusiv de test local.'}) == 200)
status, _, disputed = request('/01/09506000134352/10/A%2FB%2B00%25%3F')
check('Previously issued GS1 URI survives rights dispute with notice', status == 200 and 'Dreptul de administrare' in disputed)
check('Administrative GS1 decision can restore verified rights', admin_post({'action':'gtin.review','id':fixture['item_id'],'state':'verified','evidence':'Mandat exclusiv de test, fără drept comercial real.'}) == 200)
check('Contact verification is separate from registration', admin_post({'action':'contact.verify','id':fixture['tenant_a']['user'],'evidence':'Contact fictiv verificat exclusiv în test.'}) == 200)
_, _, vote_page = request('/t/' + fixture['item_id'], opener=opener)
vote_csrf = re.search(r'name="_csrf" value="([a-f0-9]+)"', vote_page).group(1)
for _ in range(2):
    body = urllib.parse.urlencode({'_csrf':vote_csrf,'vote':'1'}).encode()
    r = opener.open(urllib.request.Request(BASE + '/proposals/' + fixture['proposal_id'] + '/vote',body),timeout=15)
    r.read()
_, _, vote_page = request('/t/' + fixture['item_id'], opener=opener)
check('Duplicate verified votes count once', '>1 vot</span>' in vote_page)
body = urllib.parse.urlencode({'_csrf':vote_csrf,'vote':'0'}).encode()
r = opener.open(urllib.request.Request(BASE + '/proposals/' + fixture['proposal_id'] + '/vote',body),timeout=15)
r.read()
check('Verified vote can be withdrawn', '>0 voturi</span>' in request('/t/' + fixture['item_id'],opener=opener)[2])
(ROOT / 'tests/runtime/http-report.json').write_text(json.dumps({'suite':'HTTP end-to-end','passed':len(report),'failed':0,'checks':report}, ensure_ascii=False, indent=2), encoding='utf-8')
print(json.dumps({'passed':len(report),'failed':0}))
