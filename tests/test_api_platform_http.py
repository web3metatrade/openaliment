"""HTTP authorization, revocation, approved projections and OAuth; disposable localhost only."""
import base64,hashlib,http.cookiejar,json,os,re,urllib.error,urllib.parse,urllib.request,uuid
from pathlib import Path
from api_contract_probe import validate as validate_contract
ROOT=Path(__file__).resolve().parents[1]
BASE=os.environ.get('OA_TEST_BASE_URL','http://127.0.0.1:8088')
if urllib.parse.urlsplit(BASE).hostname not in ['127.0.0.1','localhost','::1']:raise RuntimeError('Local test host required')
f=json.loads((ROOT/'tests/runtime/api-platform-fixtures.json').read_text(encoding='utf-8')); checks=[]
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None
jar=http.cookiejar.CookieJar();browser=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),NoRedirect())
def check(name,ok):
 if not ok:raise AssertionError(name)
 checks.append({'name':name,'passed':True});print('PASS '+name)
def call(path,method='GET',data=None,token=None,headers=None,web=False,raw=None):
 h=dict(headers or {});body=raw
 if data is not None:body=json.dumps(data,ensure_ascii=False).encode();h['Content-Type']='application/json'
 if token:h['Authorization']='Bearer '+token
 req=urllib.request.Request(BASE+path,data=body,method=method,headers=h)
 try:r=(browser if web else urllib.request.build_opener(NoRedirect())).open(req,timeout=20)
 except urllib.error.HTTPError as e:r=e
 with r:
  b=r.read();ct=r.headers.get('Content-Type','');body=json.loads(b) if b and ('json' in ct) else b
  if method!='HEAD':validate_contract(path,method,r.status,r.headers,body)
  return r.status,r.headers,body
def post(path,data,token=None,key=None,web=False,csrf=None):
 h={'Idempotency-Key':key or 'api-http-'+uuid.uuid4().hex}
 if csrf:h['X-CSRF-Token']=csrf
 return call(path,'POST',data,token,h,web)
def created(path,data):
 s,h,b=post('/api/v2/manage/'+path,data,f['key']);check('Authorized mutation '+path,s==201);return b

s,h,manifest=call('/api/manifest.json',headers={'Origin':'https://any.example.invalid'});check('Discovery works without crawling or credentials',s==200 and h.get('Access-Control-Allow-Origin')=='*' and manifest['agent_use']['crawling_required'] is False)
check('Unsupported modules are explicitly absent',manifest['capabilities']['badge_assessment_api'] is False and manifest['capabilities']['external_product_sources'] is False)
s,h,spec=call('/api/platform-openapi.json');check('Platform contract downloadable',s==200 and spec['openapi']=='3.1.0')
ids=[op['operationId'] for v in spec['paths'].values() for op in v.values()];check('Every documented operation has a unique stable operationId',len(ids)==len(set(ids)))
for name in ['product-label','batch','ai-review']:
 s,h,b=call('/api/schemas/'+name+'.schema.json',headers={'Origin':'https://any.example.invalid'});check('Previously missing schema public '+name,s==200 and h.get('Access-Control-Allow-Origin')=='*')
check('Schema traversal blocked',call('/api/schemas/../../config/local.php')[0] in [400,403,404])
check('Credentials in query rejected',call('/api/v2/public/trade-items?api_key=fake')[0]==400)
check('Legacy public API remains accessible',call('/api/v1/trade-items/'+f['item_id'])[0]==200)
s,h,b=call('/api/v2/public/trade-items?limit=1');check('Public keyset pagination bounded',s==200 and len(b['items'])==1 and b['has_more'] and b['next_cursor'])
check('Malformed pagination rejected',call('/api/v2/public/trade-items?limit=100000')[0]==422)
check('Catalog cannot expose private product',call('/api/v2/public/products/'+f['foreign_product_id'])[0]==404)
check('Private tenant access denied',call('/api/v2/manage/products/'+f['foreign_product_id'],token=f['key'])[0]==404)
check('Private reads require authentication',call('/api/v2/manage/products')[0]==401)
s,h,b=call('/api/v2/manage/products',token='oa_'+('0'*64));check('Invalid key returns 401 and challenge',s==401 and 'WWW-Authenticate' in h)
check('Malformed Authorization never falls back to session',call('/api/v2/manage/products',headers={'Authorization':'Basic nope'})[0]==401)
check('Insufficient scope is 403',post('/api/v2/manage/products',{'name':'not allowed','category':'x'},f['read_key'])[0]==403)
check('Server-side idempotency required',call('/api/v2/manage/products','POST',{'name':'once','category':'x'},f['key'])[0]==422)
check('Cross-origin ERP key blocked by default',call('/api/v2/manage/products',token=f['key'],headers={'Origin':'https://evil.invalid'})[0]==403)
s,h,b=call('/api/v2/manage/products','OPTIONS',headers={'Origin':'https://developer.example.invalid','Access-Control-Request-Headers':'Authorization','Access-Control-Request-Method':'GET'});check('Registered OAuth web origin gets preflight, no cookies',s==204 and h.get('Access-Control-Allow-Origin')=='https://developer.example.invalid' and 'Access-Control-Allow-Credentials' not in h)
check('ERP key cannot borrow another client CORS origin',call('/api/v2/manage/products',token=f['key'],headers={'Origin':'https://developer.example.invalid'})[0]==403)
check('Unknown JSON fields rejected on write',post('/api/v2/manage/products',{'name':'test','category':'x','tenant_id':f['b']['tenant']},f['key'])[0]==422)

idem='once-'+uuid.uuid4().hex;payload={'name':'Suc de mere API','category':'bauturi'};s,h,p=post('/api/v2/manage/products',payload,f['key'],idem);check('Create non-bread product',s==201)
check('Repeat request returns same product',post('/api/v2/manage/products',payload,f['key'],idem)[2]['id']==p['id'])
check('Changed payload with same key conflicts',post('/api/v2/manage/products',{'name':'changed','category':'x'},f['key'],idem)[0]==409)
t=created('products/'+p['id']+'/trade-items',{'name':'Sticla 330 ml'})
label_data={'version_key':'E1','name':'Suc de mere API','category':'bauturi','ingredients':'Mere.','allergens':'Declaratie sintetica de test.','net_quantity':'330 ml','operator':'Operator API test'}
l=created('trade-items/'+t['id']+'/labels',label_data)
check('Unapproved label not public',call('/api/v2/public/labels/'+l['id'])[0]==404)
created('labels/'+l['id']+'/publish',{})
batch=created('trade-items/'+t['id']+'/batches',{'product_info_version_id':l['id'],'recipe_version_id':None,'lot_code':'API-'+uuid.uuid4().hex[:12]})
created('batches/'+batch['id']+'/publish',{})
s,h,b=call('/api/v2/public/batches/'+batch['id']);check('Public lot works with no recipe or badge',s==200 and b['product_info_version_id']==l['id'] and 'recipe_version_id' not in b)
l2=created('trade-items/'+t['id']+'/labels',dict(label_data,version_key='E2',ingredients='Eticheta noua.'));created('labels/'+l2['id']+'/publish',{})
check('Old lot retains applicable approved label',call('/api/v2/public/batches/'+batch['id'])[2]['product_info_version_id']==l['id'])
alert=created('batches/'+batch['id']+'/alerts',{'message':'Alerta sintetica API','state':'recalled'})
check('Current alert overlays historical snapshot',call('/api/v2/public/batches/'+batch['id']+'/alerts')[2]['items'][0]['message']=='Alerta sintetica API')
s,h,profile=call('/api/v2/public/food?'+urllib.parse.urlencode({'code':BASE+'/b/'+batch['id']}));check('QR information independent from disabled AI',s==200 and profile['batch_state']=='recalled')
check('No private columns in public projection',not any(k in b for k in ['tenant_id','approved_by','storage_key','claim_evidence']))
s,h,b=post('/api/v2/public/food-lookups',{'codes':['9520000000011','1111111111111']});check('Batch lookup separates valid demo and invalid input',s==200 and b['items'][0]['data']['simulation'] and b['items'][1]['error'])
check('Batch lookup maximum enforced',post('/api/v2/public/food-lookups',{'codes':['9520000000011']*21})[0]==422)
s,h,b=post('/api/v2/tools/identifiers/parse',{'code':'https://openaliment.ro/01/09520000000011/10/A%2FB%2B00%25%3F'});check('Identifier parsing preserves lot characters once',s==200 and b['identity']['lot_code']=='A/B+00%?' and b['rights_verified'] is False)

boundary='oa-'+uuid.uuid4().hex;content=b'PRIVATE API UPLOAD TEST '+uuid.uuid4().hex.encode()
multipart=(f'--{boundary}\r\nContent-Disposition: form-data; name="title"\r\n\r\nAPI document\r\n--{boundary}\r\nContent-Disposition: form-data; name="document"; filename="test.txt"\r\nContent-Type: text/plain\r\n\r\n'.encode()+content+f'\r\n--{boundary}--\r\n'.encode())
upload_key='upload-'+uuid.uuid4().hex
s,h,doc=call('/api/v2/manage/products/'+p['id']+'/documents','POST',token=f['key'],headers={'Content-Type':'multipart/form-data; boundary='+boundary,'Idempotency-Key':upload_key},raw=multipart);check('Multipart upload through API stays private',s==201 and doc['visibility']=='private')
check('Public cannot fetch private metadata',call('/api/v2/public/documents/'+doc['id'])[0]==404)
s,h,b=call('/api/v2/manage/documents/'+doc['id']+'/content',token=f['read_key']);check('Authorized binary download has MIME and exact bytes',s==200 and b==content and h.get('Content-Type','').split(';')[0]=='text/plain')
check('Public cannot download private document',call('/api/v2/public/documents/'+doc['id']+'/content')[0]==404)
s,h,scan=call('/api/v2/manage/documents/'+doc['id']+'/scan','POST',{},token=f['key']);check('Document receives explicit isolated scan before publication',s==200 and scan['state']=='clean' and scan['engine']=='isolated-test-only')
created('documents/'+doc['id']+'/publish',{'rights_confirmed':True,'public_projection_confirmed':True})
check('Public hosting does not imply open licence',call('/api/v2/public/documents/'+doc['id'])[2]['license_id'] is None)
s,h,b=call('/api/v2/public/documents/'+doc['id']+'/content');check('Approved public download exact bytes',s==200 and b==content and h['Access-Control-Allow-Origin']=='*')

s,h,feedback=post('/api/v2/community/feedback',{'trade_item_id':t['id'],'product_id':p['id'],'product_info_version_id':l2['id'],'kind':'review','text':'Experienta sintetica API.','rating':4,'consent':True,'submission_key':'feedback-'+uuid.uuid4().hex});check('Public feedback enters pending ('+str(s)+' '+str(feedback.get('error_code','ok'))+')',s==201 and feedback.get('status')=='pending')
created('feedback/'+feedback['id']+'/moderate',{'status':'published','public_text':'Copie publica revizuita.','reason':'Revizie de test'})
check('Public list exposes moderated copy only',call('/api/v2/public/trade-items/'+t['id']+'/feedback')[2]['items'][0]['public_text']=='Copie publica revizuita.')
check('Private moderation read available',call('/api/v2/manage/feedback/'+feedback['id'],token=f['key'])[2]['original_text']=='Experienta sintetica API.')
s,h,b=call('/api/v2/community/proposals/'+f['proposal_id']+'/vote','PUT',{},f['key']);check('Verified account votes through API',s==200 and b['voted'])
check('Vote removal idempotent',call('/api/v2/community/proposals/'+f['proposal_id']+'/vote','DELETE',token=f['key'])[2]['voted'] is False)

s,h,session=call('/api/v2/identity/session',web=True);csrf=session['csrf']
s,h,session=post('/api/v2/identity/sessions',{'email':f['email'],'password':f['password']},web=True,csrf=csrf);check('Trusted web session created without redirect HTML',s==200 and session['authenticated']);csrf=session['csrf']
new_key_data={'name':'Once only HTTP key','days':1,'scopes':['read']};issue_id='key-'+uuid.uuid4().hex
s,h,new_key=post('/api/v2/manage/access-tokens',new_key_data,web=True,csrf=csrf,key=issue_id);check('Owner issues one-time key',s==201 and new_key['secret_available'] and new_key['token'])
s,h,replay=post('/api/v2/manage/access-tokens',new_key_data,web=True,csrf=csrf,key=issue_id);check('Key issuance retry never stores or replays secret',s==201 and replay['id']==new_key['id'] and replay['secret_available'] is False and 'token' not in replay)
check('Token cannot administer key collection',call('/api/v2/manage/access-tokens',token=f['key'])[0]==403)
s,h,rot=post('/api/v2/manage/access-tokens/'+new_key['id']+'/rotations',{},web=True,csrf=csrf);check('Owner rotates key over HTTP',s==201 and rot['previous_revoked'])
check('Rotated old key fails immediately',call('/api/v2/manage/products',token=new_key['token'])[0]==401)
s,h,b=call('/api/v2/manage/access-tokens/'+rot['id'],'DELETE',headers={'X-CSRF-Token':csrf},web=True);check('Owner revokes replacement over HTTP',s==200 and b['revoked'])
check('Revoked replacement fails immediately',call('/api/v2/manage/products',token=rot['token'])[0]==401)
check('Key cannot access platform administration',call('/api/v2/admin/organizations',token=f['key'])[0]==403)
check('Admin session has paginated review list',call('/api/v2/admin/organizations?limit=1',web=True)[0]==200)

state='browser-state-'+uuid.uuid4().hex;params={'client_id':f['client_id'],'redirect_uri':'https://developer.example.invalid/callback','response_type':'code','state':state,'code_challenge':f['challenge'],'code_challenge_method':'S256','scope':'read'}
s,h,consent=call('/oauth/authorize?'+urllib.parse.urlencode(params),web=True);check('OAuth requires explicit consent in browser',s==200 and b'Permite accesul' in consent)
rid=re.search(rb'name="request_id" value="([a-f0-9]{32})"',consent).group(1).decode()
form=urllib.parse.urlencode({'_csrf':csrf,'request_id':rid,'resource_id':f['a']['tenant'],'decision':'approve'}).encode()
s,h,b=call('/oauth/authorize','POST',headers={'Content-Type':'application/x-www-form-urlencoded'},raw=form,web=True);check('Consent returns only code to exact registered URI',s==303 and h['Location'].startswith('https://developer.example.invalid/callback?code='))
query=urllib.parse.parse_qs(urllib.parse.urlsplit(h['Location']).query);check('OAuth state and issuer retained',query['state']==[state] and query['iss']==[BASE])
exchange={'grant_type':'authorization_code','client_id':f['client_id'],'redirect_uri':params['redirect_uri'],'code':query['code'][0],'code_verifier':f['verifier']}
s,h,delegated=post('/oauth/token',exchange);check('PKCE exchange issues bounded bearer token',s==200 and delegated['expires_in']<=900 and delegated['token_type']=='Bearer')
s,h,b=call('/api/v2/manage/products',token=delegated['access_token'],headers={'Origin':'https://developer.example.invalid'});check('Delegated browser origin can use own grant',s==200 and h.get('Access-Control-Allow-Origin')=='https://developer.example.invalid')
check('Scope remains enforced for delegated app',post('/api/v2/manage/products',{'name':'bad','category':'x'},delegated['access_token'])[0]==403)
s,h,b=call('/api/v2/identity/grants/'+delegated['grant_id'],'DELETE',headers={'X-CSRF-Token':csrf},web=True);check('User retracts app authorization',s==200 and b['revoked'])
check('Retracted app loses access immediately',call('/api/v2/manage/products',token=delegated['access_token'])[0]==401)
s,h,b=post('/oauth/token',exchange);check('OAuth errors are protocol JSON',s==400 and b['error']=='invalid_grant' and 'request_id' in b)
check('Unknown-token revocation does not disclose token existence',post('/oauth/revoke',{'client_id':f['client_id'],'token':'oa_at_'+('0'*64)})[0]==200)

s,h,b=call('/api/v2/home/session',token=f['home_token']);check('Native consumer token works without cookies',s==200 and b['authenticated'] and b['session']['household_id']==f['household_id'])
check('Grant exposes only its chosen household',len(call('/api/v2/home/households',token=f['home_token'])[2]['items'])==1)
s,h,b=post('/api/v2/home/sync',{'operations':[]},f['home_token']);check('Native home writes need bearer, no browser CSRF',s==200 and b['conflicts']==[])
check('Home app cannot exceed consent to manage family',post('/api/v2/home/rename',{'name':'not consented'},f['home_token'])[0]==403)
check('Home app cannot switch outside its grant',post('/api/v2/home/switch',{'household_id':f['household_id']},f['home_token'])[0]==403)
check('Home token cannot read producer data',call('/api/v2/manage/products',token=f['home_token'])[0]==401)
check('Producer key cannot read household inventory',call('/api/v2/home/changes',token=f['key'])[0]==401)
check('Cookie mutation still needs CSRF',call('/api/v2/manage/products','POST',{'name':'bad','category':'x'},web=True)[0]==419)
check('HEAD has no response body',call('/api/v2/public/trade-items','HEAD')[2]==b'')
check('Unsupported API method returns 405',call('/api/v2/public/trade-items','DELETE')[0]==405)
report={'suite':'API platform HTTP and OAuth','passed':len(checks),'failed':0,'checks':checks};(ROOT/'tests/runtime/reports/api-platform-http.json').write_text(json.dumps(report,indent=2)+'\n',encoding='utf-8');print(json.dumps({'passed':len(checks),'failed':0}))
