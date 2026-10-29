"""Full registration and schema-checked ANAF APIs on disposable loopback only."""
import http.cookiejar,json,os,re,urllib.error,urllib.parse,urllib.request,uuid
from pathlib import Path
from api_contract_probe import validate,report
ROOT=Path(__file__).resolve().parents[1];BASE=os.environ['OA_TEST_BASE_URL']
assert urllib.parse.urlsplit(BASE).hostname in ['localhost','127.0.0.1','::1']
fixtures=json.loads((ROOT/'tests/runtime/anaf-http-fixtures.json').read_text(encoding='utf-8'))
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect())
web=client();other=client();admin=client();checks=[]
def check(name,ok):
 if not ok:raise AssertionError(name)
 checks.append({'name':name,'passed':True})
def call(agent,path,method='GET',data=None,csrf=None,token=None,expected=200,form=False,idempotency=None):
 headers={'Accept':'application/json'}
 if csrf:headers['X-CSRF-Token']=csrf
 if token:headers['Authorization']='Bearer '+token
 if data is not None:
  raw=urllib.parse.urlencode(data).encode() if form else json.dumps(data).encode()
  headers['Content-Type']='application/x-www-form-urlencoded' if form else 'application/json'
  headers['Idempotency-Key']=idempotency or 'anaf-'+uuid.uuid4().hex
 else:raw=None
 req=urllib.request.Request(BASE+path,data=raw,headers=headers,method=method)
 try:res=agent.open(req,timeout=20)
 except urllib.error.HTTPError as error:res=error
 with res:body=res.read();out=json.loads(body) if res.headers.get('Content-Type','').startswith('application/json') else body
 check(method+' '+path+' HTTP '+str(expected),res.status==expected)
 if path.startswith('/api/'):validate(path,method,res.status,res.headers,out)
 return out,res.headers
csrf=call(web,'/api/v2/identity/session')[0]['csrf'];other_csrf=call(other,'/api/v2/identity/session')[0]['csrf']
call(web,'/api/v2/identity/organization-lookups','POST',{'cui':'900000001'},expected=419)
first=call(web,'/api/v2/identity/organization-lookups','POST',{'cui':'RO 900000001'},csrf)[0]
check('Lookup contains all fiscal sections',first['state']=='found' and len(first['record'])==7)
again=call(web,'/api/v2/identity/organization-lookups/'+first['id'])[0];check('Result is bound to the same source snapshot',again['source']['sha256']==first['source']['sha256'])
call(other,'/api/v2/identity/organization-lookups/'+first['id'],expected=404)
missing=call(web,'/api/v2/identity/organization-lookups','POST',{'cui':'1234'},csrf)[0];check('Not-found is distinguished from ANAF downtime',missing['state']=='not_found' and missing['record'] is None)
call(web,'/api/v2/identity/organization-lookups/'+first['id'],expected=409)
call(web,'/api/v2/identity/organization-lookups','POST',{'cui':'1234567890123'},csrf,expected=422)
call(web,'/api/v2/identity/organization-lookups','POST',{'cui':'900000009'},csrf,expected=503)
correct=call(web,'/api/v2/identity/organization-lookups','POST',{'cui':'900000002'},csrf)[0]
account={'email':'anaf-http-'+uuid.uuid4().hex+'@example.invalid','password':'Synthetic-'+uuid.uuid4().hex,'display_name':'ANAF synthetic person','organization':'Name forged in client','organization_lookup_id':correct['id'],'cui':'900000001'}
call(web,'/api/v2/identity/registrations','POST',account,csrf,expected=422)
account['cui']='900000002';registered=call(web,'/api/v2/identity/registrations','POST',account,csrf,expected=201)[0];csrf=registered['csrf']
profile=call(web,'/api/v2/manage/organization/legal-profile')[0]
check('Registration saves the ANAF company rather than the forged name',profile['profile']['legal_name']=='Organizație sintetică ANAF B' and profile['profile']['cui']=='900000002')
check('ANAF does not grant representation or GS1 rights',profile['representation_verified_by_anaf'] is False and profile['gs1_rights_granted_by_anaf'] is False)
check('Both addresses and empty values survive HTTP and database storage',profile['profile']['record']['adresa_sediu_social']['scod_Postal']=='001234' and profile['profile']['record']['date_generale']['iban']=='')
history=call(web,'/api/v2/manage/organization/legal-profile/history?limit=1')[0];check('History is actually paginated',history['limit']==1 and len(history['items'])==1)
new=call(web,'/api/v2/manage/organization/legal-profile/checks','POST',{'cui':'900000002'},csrf)[0]
call(web,'/api/v2/manage/organization/legal-profile/confirm','POST',{'organization_lookup_id':new['id'],'expected_version_id':None},csrf,expected=409)
confirm_body={'organization_lookup_id':new['id'],'expected_version_id':profile['profile']['id']};confirm_key='anaf-confirm-'+uuid.uuid4().hex
updated=call(web,'/api/v2/manage/organization/legal-profile/confirm','POST',dict(confirm_body,current_password=account['password']),csrf,idempotency=confirm_key)[0]
replayed=call(web,'/api/v2/manage/organization/legal-profile/confirm','POST',confirm_body,csrf,idempotency=confirm_key)[0]
check('Confirmation replay excludes password material from the request hash',replayed['profile']['id']==updated['profile']['id'])
check('Confirm creates another immutable version',updated['profile']['id']!=profile['profile']['id'])
key=call(web,'/api/v2/manage/access-tokens','POST',{'name':'ANAF read-only test','days':1,'scopes':['read']},csrf,expected=201)[0]
plain=client();call(plain,'/api/v2/manage/organization/legal-profile',token=key['token'])
call(plain,'/api/v2/manage/organization/legal-profile/checks','POST',{'cui':'900000002'},token=key['token'],expected=403)
call(plain,'/api/v2/admin/organizations/'+registered['organization_id']+'/legal-profile',token=key['token'],expected=403)
admin_csrf=call(admin,'/api/v2/identity/session')[0]['csrf'];admin_csrf=call(admin,'/api/v2/identity/sessions','POST',{'email':fixtures['email'],'password':fixtures['password']},admin_csrf)[0]['csrf']
view=call(admin,'/api/v2/admin/organizations/'+registered['organization_id']+'/legal-profile')[0];check('Administrator can review the fiscal profile without confirming representation',view['profile']['cui']=='900000002')
call(other,'/api/v2/manage/organization/legal-profile',expected=401)
html=call(web,'/manage/organization')[0];check('Saved profile is visible after a new page load',b'900000002' in html and b'001234' in html)
export=call(web,'/api/v2/manage/export')[0];check('Transfer export includes the complete fiscal history',len(export['data']['organization_legal_history'])==2 and bool(export['data']['organization_legal_history'][0]['response_json']))
package=call(web,'/api/v2/manage/export?format=ndjson')[0];check('NDJSON includes fiscal history records',b'organization_legal_history' in package)
call(web,'/api/v2/manage/access-tokens/'+key['id'],'DELETE',csrf=csrf,expected=200)
call(plain,'/api/v2/manage/organization/legal-profile',token=key['token'],expected=401)
result={'passed':len(checks),'failed':0,'checks':checks,**report()}
(ROOT/'tests/runtime/reports/anaf-http.json').write_text(json.dumps(result,indent=2)+'\n',encoding='utf-8')
(ROOT/'tests/runtime/anaf-browser-fixtures.json').write_text(json.dumps({'email':account['email'],'password':account['password']}),encoding='utf-8')
print(json.dumps({k:result[k] for k in ['passed','failed','response_checks','successful_operations']}))
