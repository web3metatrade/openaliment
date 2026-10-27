"""Every OFF operation through real loopback HTTP; upstream networking is disabled."""
import base64,hashlib,http.cookiejar,io,json,os,urllib.error,urllib.parse,urllib.request,uuid,zipfile
from pathlib import Path
from api_contract_probe import validate,report
ROOT=Path(__file__).resolve().parents[1];BASE=os.environ['OA_TEST_BASE_URL']
if urllib.parse.urlsplit(BASE).hostname not in ['localhost','127.0.0.1','::1']:raise RuntimeError('Loopback only')
f=json.loads((ROOT/'tests/runtime/external-fixtures.json').read_text(encoding='utf-8'));checks=[];csrf=''
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*a,**k):return None
web=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect());plain=urllib.request.build_opener(NoRedirect())
def check(name,ok):
    if not ok:raise AssertionError(name)
    checks.append({'name':name,'passed':True})
def call(path,method='GET',data=None,session=True,expected=None,key=None):
    if expected is None:expected=201 if method=='POST' and path.startswith('/api/v2/manage/') and not path.endswith('/preview') else 200
    headers={'Accept':'application/json'}
    if method=='POST':
        headers.update({'Content-Type':'application/json','Idempotency-Key':key or uuid.uuid4().hex})
        if session:headers['X-CSRF-Token']=csrf
    req=urllib.request.Request(BASE+path,None if data is None else json.dumps(data).encode(),headers,method=method)
    try:r=(web if session else plain).open(req,timeout=20)
    except urllib.error.HTTPError as e:r=e
    raw=r.read();body=json.loads(raw) if r.headers.get('Content-Type','').startswith('application/json') else raw
    if r.status!=expected:raise AssertionError(f'{method} {path}: {r.status}, expected {expected}; {str(body)[:250]}')
    validate(path,method,r.status,r.headers,body);return body,r

session,_=call('/api/v2/identity/session');csrf=session['csrf'];login,_=call('/api/v2/identity/sessions','POST',{'email':f['email'],'password':f['password']});csrf=login['csrf']
admin='/api/v2/admin/sources/openfoodfacts';manage='/api/v2/manage';item=manage+'/items/'+f['item']+'/sources';sharing=manage+'/sources/openfoodfacts'
call(admin+'/state','POST',{'state':'public','license_policy_confirmed':True})
call(admin+'/status');call('/api/v2/public/sources/openfoodfacts/revisions',session=False);sources,_=call('/api/v2/public/sources',session=False);check('Public attribution lists the active licensed source',sources['items'][0]['licenses']['database']['id']=='ODbL-1.0')
code='https://example.org/01/'+f['gtin']+'/10/Lot%2FA%2B01/21/0007?17=270228'
resolved,_=call('/api/v2/public/food/resolve','POST',{'code':code},session=False)
check('External composition preserves GS1 lot and serial without an external recipe',resolved['profile']['lot_code']=='Lot/A+01' and resolved['profile']['gs1_qualifiers']['21']=='0007' and resolved['profile']['food_data']['nutrition']['status']=='unknown')
call('/api/v2/public/food/lookups','POST',{'gtin':f['gtin']},session=False)
call('/api/v2/public/food/lookups/'+f['job'],session=False)
record,_=call('/api/v2/public/external-revisions/'+f['revision'],session=False);check('Public observation has source and explicit licenses',record['provider']=='openfoodfacts' and record['licenses']['images']['id']=='CC-BY-SA-3.0')
image,r=call('/api/v2/public/external-assets/'+f['asset'],session=False);check('Image endpoint returns validated binary, not a URL redirect',image.startswith(b'\x89PNG') and 'rel="license"' in r.headers['Link'])
call(item);binding,_=call(item,'POST',{'external_product_id':f['external_product_id']});call(item+'/'+binding['id']+'/disconnect','POST',{});call(item,'POST',{'external_product_id':f['external_product_id']})
payload={'revision_id':f['revision'],'fields':['ingredients'],'version_key':'HTTP-OFF-'+uuid.uuid4().hex[:10],'expected_label_id':f['label']}
preview,_=call(item+'/openfoodfacts/preview','POST',payload);check('HTTP preview never writes approved data',preview['writes'] is False)
key=uuid.uuid4().hex;draft,_=call(item+'/openfoodfacts/drafts','POST',payload,key=key);again,_=call(item+'/openfoodfacts/drafts','POST',payload,key=key);check('HTTP retries do not duplicate imported drafts',draft==again and draft['state']=='draft')
conflicts,_=call(manage+'/source-conflicts');opened=next(row for row in conflicts['items'] if row['state']=='open');call(manage+'/source-conflicts/'+opened['id']+'/decisions','POST',{'state':'different_market','reason':'Synthetic HTTP decision'})
call(sharing+'/connection');call(sharing+'/connection','POST',{'organization_ref':'Synthetic OFF manufacturer','transport':'producer_package'})
connection,_=call(sharing+'/connection');call(admin+'/connections/'+connection['connection']['id']+'/verify','POST',{})
grants,_=call(sharing+'/sharing-grants');version=max(g['version'] for g in grants['items'] if g['trade_item_id']==f['item'])
grant,_=call(sharing+'/sharing-grants','POST',{'trade_item_id':f['item'],'fields':['name','ingredients'],'expected_version':version,'public_data_confirmed':True,'licenses_confirmed':True})
call(item+'/openfoodfacts/publications/preview','POST',{})
publication,_=call(item+'/openfoodfacts/publications','POST',{});call(sharing+'/publications');package,r=call(sharing+'/publications/'+publication['id']+'/package')
with zipfile.ZipFile(io.BytesIO(package)) as z:
    check('Producer package has no misleading nutrition/operator CSV mapping',set(z.namelist())=={'products.csv','contribution.json','README.txt'} and b'customer_service' not in z.read('products.csv'))
call(sharing+'/sharing-grants/'+grant['id']+'/revoke','POST',{})
call(sharing+'/publications/'+publication['id']+'/package',expected=404)
call(sharing+'/connection/disconnect','POST',{})
call(admin+'/revisions/'+f['revision']+'/visibility','POST',{'visibility':'suspended','reason':'Synthetic withdrawal'})
call('/api/v2/public/external-revisions/'+f['revision'],session=False,expected=404)
call(admin+'/revisions/'+f['revision']+'/visibility','POST',{'visibility':'public','reason':'Synthetic reinstatement'})
call(admin+'/jobs/'+f['job']+'/retry','POST',{})
content=(json.dumps(f['product'],ensure_ascii=False,separators=(',',':'))+'\n').encode()
dataset={'manifest':{'provider':'openfoodfacts','license':'ODbL-1.0','sha256':hashlib.sha256(content).hexdigest(),'record_count':1},'content_base64':base64.b64encode(content).decode()}
retry=json.loads((ROOT/'tests/runtime/external-retry-fixture.json').read_text());call(admin+'/datasets/'+retry['run']+'/retry','POST',{})
call(admin+'/datasets/preview','POST',dataset);call(admin+'/datasets','POST',dataset);call(admin+'/exports','POST',{})
for _ in range(4):call(admin+'/worker','POST',{})
artifact,_=call('/api/v2/public/sources/openfoodfacts/export',session=False);lines=artifact.splitlines(keepends=True);end=json.loads(lines[-1]);check('Licensed export has a verified final digest',end['sha256']==hashlib.sha256(b''.join(lines[:-1])).hexdigest())
for path,needle in [('/surse/open-food-facts','Open Food Facts'),('/admin/sources','Surse externe'),('/manage/sources','Informații'),('/manage/items/'+f['item']+'/sources','Open Food Facts'),('/manage/sources/openfoodfacts/sharing','Distribuire'),('/api/docs/guides/surse-externe','Open Food Facts')]:
    body,_=call(path);check('UI renders '+path,needle.casefold() in body.decode().casefold())
    if path=='/admin/sources':
        html=body.decode()
        check('Installed admin catalog exposes source controls without the installation button','Actualizează starea' in html and 'Procesează operațiile disponibile' in html and 'Instalează catalogul extern' not in html)
call(admin+'/state','POST',{'state':'paused'});call('/api/v2/public/food/lookups','POST',{'gtin':f['gtin']},session=False,expected=503)
fallback,_=call('/api/v2/public/food/resolve','POST',{'code':code},session=False);check('Paused provider never blocks a valid GS1 scan',fallback['profile']['gtin14']==f['gtin'] and fallback['supplement'] is None)
call(admin+'/state','POST',{'state':'disabled'})
result={'passed':len(checks),'failed':0,**report()};(ROOT/'tests/runtime/reports/external-http.json').write_text(json.dumps(result,indent=2),encoding='utf-8');print(json.dumps({'passed':len(checks),'failed':0,'successful_operations':len(result['successful_operations'])}))
