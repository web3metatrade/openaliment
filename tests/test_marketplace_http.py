"""Real isolated HTTP for marketplace, printer agents and the pinned standards catalog."""
import http.cookiejar,json,os,urllib.error,urllib.parse,urllib.request,uuid
from pathlib import Path
from api_contract_probe import validate,report

ROOT=Path(__file__).resolve().parents[1];BASE=os.environ['OA_TEST_BASE_URL']
if urllib.parse.urlsplit(BASE).hostname not in ['localhost','127.0.0.1','::1']:raise RuntimeError('Loopback tests only')
fixture=json.loads((ROOT/'tests/runtime/marketplace-fixtures.json').read_text());checks=[]
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
jar=http.cookiejar.CookieJar();web=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),NoRedirect());plain=urllib.request.build_opener(NoRedirect());csrf=''
def check(name,condition):
    if not condition:raise AssertionError(name)
    checks.append({'name':name,'passed':True})
def call(path,method='GET',data=None,token=None,session=True,expected=200,key=None):
    headers={'Accept':'application/json'}
    if token:headers['Authorization']='Bearer '+token
    if method not in ['GET','HEAD']:
        headers.update({'Content-Type':'application/json','Idempotency-Key':key or uuid.uuid4().hex})
        if session:headers['X-CSRF-Token']=csrf
    request=urllib.request.Request(BASE+path,None if data is None else json.dumps(data).encode(),headers,method=method)
    try:response=(web if session else plain).open(request,timeout=15)
    except urllib.error.HTTPError as error:response=error
    raw=response.read();body=json.loads(raw) if response.headers.get('Content-Type','').startswith('application/json') else raw
    if response.status!=expected:raise AssertionError(f'{method} {path}: {response.status}, expected {expected}; {str(body)[:250]}')
    validate(path,method,response.status,response.headers,body)
    return body,response

session,_=call('/api/v2/identity/session');csrf=session['csrf'];login,_=call('/api/v2/identity/sessions','POST',{'email':fixture['email'],'password':fixture['password']});csrf=login['csrf']
listing={'slug':'http-market-'+uuid.uuid4().hex,'name':'HTTP synthetic connector','summary':'Synthetic integration for isolated HTTP verification.','description':'Plain text <script>alert(1)</script>','publisher':'Synthetic publisher','category':'new-community-category','website':'https://developer.example/','support_url':'https://developer.example/support','license':'MIT','license_url':'https://developer.example/license','capabilities':['printing','erp'],'platforms':['windows-x64'],'compatibility':['api'],'pricing':'free'}
app,_=call('/api/v2/developer/marketplace/apps','POST',listing);app_id=app['id']
call('/api/v2/developer/marketplace/apps');call('/api/v2/public/marketplace/apps/'+app_id,session=False,expected=404)
call('/api/v2/developer/marketplace/apps/'+app_id,'PUT',dict(listing,expected_revision=1));call('/api/v2/developer/marketplace/apps/'+app_id+'/submit','POST',{})
call('/api/v2/admin/marketplace/apps');call('/api/v2/admin/marketplace/apps/'+app_id+'/decisions','POST',{'decision':'approve_listing','expected_revision':2,'reason':'Synthetic metadata review'})
release,_=call('/api/v2/developer/marketplace/apps/'+app_id+'/releases','POST',{'version':'1.0.0','artifact_url':'https://developer.example/connector.zip','sha256':'a'*64,'size_bytes':12345,'platforms':['windows-x64'],'changelog':'Synthetic release'})
call('/api/v2/admin/marketplace/apps/'+app_id+'/decisions','POST',{'decision':'approve_release','release_id':release['id'],'artifact_verified':True,'reason':'Synthetic review evidence, not a real binary assessment'})
catalog,_=call('/api/v2/public/marketplace/apps',session=False);public,_=call('/api/v2/public/marketplace/apps/'+app_id,session=False)
check('Only approved manifest appears publicly',public['releases'][0]['id']==release['id'])
_,download=call('/api/v2/public/marketplace/releases/'+release['id']+'/download',session=False,expected=303)
check('Download redirects without fetching or running vendor software',download.headers['Location']=='https://developer.example/connector.zip')
call('/api/v2/manage/marketplace/apps/'+app_id+'/selection','POST',{'active':True})
call('/api/v2/manage/marketplace/selections')
call('/api/v2/developer/marketplace/apps/'+app_id,'PUT',dict(listing,pricing='paid',expected_revision=2))
call('/api/v2/developer/marketplace/apps/'+app_id+'/submit','POST',{})
call('/api/v2/admin/marketplace/apps/'+app_id+'/decisions','POST',{'decision':'approve_listing','expected_revision':3,'reason':'Synthetic commercial metadata review'})
paid_release,_=call('/api/v2/developer/marketplace/apps/'+app_id+'/releases','POST',{'version':'1.1.0','artifact_url':'https://developer.example/paid-connector.zip','sha256':'b'*64,'size_bytes':54321,'platforms':['windows-x64'],'changelog':'Synthetic commercial release'})
call('/api/v2/admin/marketplace/apps/'+app_id+'/decisions','POST',{'decision':'approve_release','release_id':paid_release['id'],'artifact_verified':True,'reason':'Synthetic commercial version review'})
paid,_=call('/api/v2/public/marketplace/apps/'+app_id,session=False)
check('Inactive commerce hides paid artifact destination',next(r for r in paid['releases'] if r['id']==paid_release['id'])['artifact_url'] is None)
check('Original free release keeps its original license and distribution',next(r for r in paid['releases'] if r['id']==release['id'])['pricing']=='free')
call('/api/v2/public/marketplace/releases/'+paid_release['id']+'/download',session=False,expected=409)
call('/api/v2/public/marketplace/releases/'+release['id']+'/download',session=False,expected=303)
device,_=call('/api/v2/manage/printing/devices','POST',{'name':'HTTP line','format':'zpl'});device_id=device['id']
call('/api/v2/manage/printing/devices');rotation,_=call('/api/v2/manage/printing/devices/'+device_id+'/rotate','POST',{})
call('/api/v2/printing-agent/claims','POST',{},token=device['token'],session=False,expected=401);agent_token=rotation['token']
data={'device_id':device_id,'batch_id':fixture['batch_id'],'quantity':2};job_key='print-http-'+uuid.uuid4().hex
job,_=call('/api/v2/manage/printing/jobs','POST',data,key=job_key);again,_=call('/api/v2/manage/printing/jobs','POST',data,key=job_key)
check('Retry does not allocate duplicate serials',job==again)
call('/api/v2/manage/printing/jobs');call('/api/v2/manage/printing/units');detail,_=call('/api/v2/manage/printing/jobs/'+job['id']);check('GS1 dates are inside printed QR URI','11=261025' in detail['units'][0]['digital_link'] and '15=261030' in detail['units'][0]['digital_link'])
claim,_=call('/api/v2/printing-agent/claims','POST',{},token=agent_token,session=False)
ack,_=call('/api/v2/printing-agent/jobs/'+job['id']+'/acknowledgements','POST',{'claim_token':claim['claim_token'],'state':'dispatched','evidence':'Synthetic simulator; no actual printer contacted.'},token=agent_token,session=False)
check('HTTP dispatch is distinct from physical confirmation',ack['physical_print_confirmed'] is False)
replacement,_=call('/api/v2/manage/printing/jobs/'+job['id']+'/reprint','POST',{'reason':'Replace synthetic labels','same_serials_confirmed':True})
call('/api/v2/manage/printing/jobs/'+replacement['id']+'/cancel','POST',{'reason':'Synthetic cancellation'})
call('/api/v2/manage/printing/devices/'+device_id+'/revoke','POST',{})
call('/api/v2/printing-agent/claims','POST',{},token=agent_token,session=False,expected=401)
call('/api/v2/public/standards/gs1',session=False);call('/api/v2/admin/standards/gs1')
call('/api/v2/admin/standards/gs1/copies','POST',{'document_id':'uri-syntax'})
call('/api/v2/public/standards/gs1',session=False)
for path,text in [('/marketplace/'+listing['slug'],'HTTP synthetic connector'),('/developers/marketplace/'+app_id,'Administrează integrarea'),('/admin/marketplace','Oferte și versiuni'),('/manage/printing?job='+job['id'],'De la lot la ambalaj.'),('/standarde/gs1','Standarde GS1')]:
    request=urllib.request.Request(BASE+path);response=web.open(request,timeout=15);html=response.read().decode();check('Rendered page '+path,response.status==200 and text in html and '<script>alert(1)</script>' not in html)
call('/api/v2/developer/marketplace/apps/'+app_id+'/releases')
call('/api/v2/developer/marketplace/apps/'+app_id+'/withdraw','POST',{'release_id':release['id'],'reason':'Synthetic publisher withdrawal'})
call('/api/v2/admin/marketplace/apps/'+app_id+'/decisions','POST',{'decision':'withdraw_release','release_id':release['id'],'reason':'Synthetic withdrawal'})
call('/api/v2/public/marketplace/releases/'+release['id']+'/download',session=False,expected=404)
call('/api/v2/admin/marketplace/apps/'+app_id+'/decisions','POST',{'decision':'suspend','reason':'Synthetic suspension'})
call('/api/v2/public/marketplace/apps/'+app_id,session=False,expected=404)
call('/api/v2/developer/marketplace/apps',session=False,expected=401)
check('Marketplace and printing APIs require explicit credentials',True)
call('/api/v2/developer/marketplace/apps/'+app_id,'PUT',dict(listing,expected_revision=3))
call('/api/v2/developer/marketplace/apps/'+app_id+'/submit','POST',{})
call('/api/v2/admin/marketplace/apps/'+app_id+'/decisions','POST',{'decision':'approve_listing','expected_revision':4,'reason':'Restore synthetic browser fixture'})
call('/api/v2/admin/marketplace/apps/'+app_id+'/decisions','POST',{'decision':'approve_release','release_id':release['id'],'artifact_verified':True,'reason':'Synthetic version for browser fixture only'})
(ROOT/'tests/runtime/marketplace-browser-fixtures.json').write_text(json.dumps({'slug':listing['slug'],'app_id':app_id,'job_id':job['id'],'digital_link':detail['units'][0]['digital_link']}))
summary={'passed':len(checks),'failed':0,**report()};(ROOT/'tests/runtime/reports/marketplace-http.json').write_text(json.dumps(summary,indent=2)+'\n');print(json.dumps({'passed':len(checks),'failed':0,'validated_responses':summary['response_checks'],'successful_operations':len(summary['successful_operations'])}))
