"""Full program use cases and every new operation, schema-checked over isolated HTTP."""
import base64,hashlib,http.cookiejar,json,os,re,urllib.error,urllib.parse,urllib.request,uuid
from pathlib import Path
from api_contract_probe import validate,report
ROOT=Path(__file__).resolve().parents[1];BASE=os.environ['OA_TEST_BASE_URL']
assert urllib.parse.urlsplit(BASE).hostname in ['localhost','127.0.0.1','::1']
F=json.loads((ROOT/'tests/runtime/program-http-fixtures.json').read_text('utf8'));checks=[]
CUI_FIRST=os.environ.get('OA_PROGRAM_CUI_FIRST','900001001');CUI_SECOND=os.environ.get('OA_PROGRAM_CUI_SECOND','900001002')
assert CUI_FIRST in [str(x) for x in range(900001001,900001007)] and CUI_SECOND in [str(x) for x in range(900001001,900001007)] and CUI_FIRST!=CUI_SECOND
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None
class Client:
 def __init__(self):self.web=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect());self.csrf=None
 def call(self,path,method='GET',data=None,expected=200,token=None,key=None,form=False,no_csrf=False):
  headers={'Accept':'application/json'}
  if self.csrf and not no_csrf:headers['X-CSRF-Token']=self.csrf
  if token:headers['Authorization']='Bearer '+token
  raw=None
  if data is not None:
   raw=urllib.parse.urlencode(data).encode() if form else json.dumps(data,ensure_ascii=False).encode()
   headers['Content-Type']='application/x-www-form-urlencoded' if form else 'application/json'
   headers['Idempotency-Key']=key or 'program-'+uuid.uuid4().hex
  try:res=self.web.open(urllib.request.Request(BASE+path,data=raw,headers=headers,method=method),timeout=30)
  except urllib.error.HTTPError as error:res=error
  with res:body=res.read();value=json.loads(body) if res.headers.get('Content-Type','').startswith('application/json') else body
  if res.status!=expected:
   (ROOT/'tests/runtime/program-http-failure.private.json').write_text(json.dumps({'status':res.status,'body':value if isinstance(value,dict) else body.decode('utf8','replace')}),encoding='utf8')
  check(method+' '+path.split('?')[0]+' HTTP '+str(expected),res.status==expected)
  if path.startswith('/api/'):
   try:validate(path,method,res.status,res.headers,value)
   except Exception as error:
    (ROOT/'tests/runtime/program-contract-failure.private.txt').write_text(str(error),encoding='utf8')
    raise AssertionError('Program response contract failed; details remain in private runtime.') from None
  return value,res.headers
 def get(self,path,**kwargs):return self.call(path,**kwargs)[0]
 def post(self,path,data=None,**kwargs):return self.call(path,'POST',{} if data is None else data,**kwargs)[0]
 def put(self,path,data,**kwargs):return self.call(path,'PUT',data,**kwargs)[0]
 def delete(self,path,data=None,**kwargs):return self.call(path,'DELETE',{} if data is None else data,**kwargs)[0]
 def session(self):self.csrf=self.get('/api/v2/identity/session')['csrf'];return self.csrf
 def login(self,user):self.session();self.csrf=self.post('/api/v2/identity/sessions',{'email':user['email'],'password':user['password']})['csrf']
def check(name,ok):
 if not ok:raise AssertionError(name)
 checks.append({'name':name,'passed':True})
def mail_token(email):
 for file in sorted((ROOT/'tests/runtime/storage/private/mail-test').glob('*.json'),key=lambda p:p.stat().st_mtime_ns,reverse=True):
  data=json.loads(file.read_text('utf8'));match=re.search(r'#token=([a-f0-9]{64})',data['text'])
  if data['recipient']==email and match:return match[1]
 raise AssertionError('Private test proof missing')
P='/api/v2/program';A='/api/v2/admin/program';U='/api/v2/public/program'
web=Client();admin=Client();other=Client();delegate=Client();plain=Client();anon=Client()
admin.login(F['users']['admin']);other.login(F['users']['outsider']);delegate.login(F['users']['delegate']);web.session();anon.session()
email='program-http-'+uuid.uuid4().hex+'@example.invalid';password='Synthetic-'+uuid.uuid4().hex
anon.post(P+'/registrations',{'display_name':'Refuz CSRF sintetic','email':email,'password':password,'policy_accepted':True},no_csrf=True,expected=419)
anon.post(P+'/verifications/preview',{'token':'0'*64},no_csrf=True,expected=419)
registered=web.post(P+'/registrations',{'display_name':'Cursant HTTP sintetic','email':email,'password':password,'policy_accepted':True});web.session()
user=web.get(P+'/me');check('Personal registration has no producer tenancy requirement',user['id']==registered['id'] and not user['is_admin'])
web.post(P+'/me/verifications',{'purpose':'personal_email'});proof=mail_token(email)
anon.post(P+'/verifications/preview',{'token':proof})
check('Preview leaves personal email unverified',web.get(P+'/me')['email_verified'] is False)
web.post(P+'/verifications/confirm',{'token':proof,'accepted':True});web.post(P+'/verifications/confirm',{'token':proof,'accepted':True},expected=410)
check('Personal email proof is persisted',web.get(P+'/me')['email_verified'])
# OAuth without a producer membership; code and tokens stay only in local test memory.
scopes=['learning:read','learning:write','partners:read','partners:write','contributors:read','contributors:write','assets:read','assets:write']
client=web.post(P+'/me/applications',{'name':'Aplicație sintetică program','redirect_uris':['https://program.example.invalid/callback'],'scopes':scopes})
web.get(P+'/me/applications')
default_client=web.post(P+'/me/applications',{'name':'Client sintetic cu permisiune implicită','redirect_uris':['https://program.example.invalid/default-callback']})
check('Personal client defaults to program read rather than ERP or household access',default_client['scopes']==['learning:read'])
web.delete(P+'/me/applications/'+default_client['id'])
verifier=uuid.uuid4().hex+uuid.uuid4().hex;challenge=base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).decode().rstrip('=')
query=urllib.parse.urlencode({'client_id':client['id'],'response_type':'code','redirect_uri':'https://program.example.invalid/callback','scope':' '.join(scopes),'state':'program-state-'+uuid.uuid4().hex,'code_challenge':challenge,'code_challenge_method':'S256'})
html=web.get('/oauth/authorize?'+query);request_id=re.search(rb'name="request_id" value="([a-f0-9]{32})"',html)[1].decode()
_,headers=web.call('/oauth/authorize','POST',{'_csrf':web.csrf,'request_id':request_id,'resource_id':user['id'],'decision':'approve'},expected=303,form=True)
code=urllib.parse.parse_qs(urllib.parse.urlsplit(headers['Location']).query)['code'][0]
tokens=plain.post('/oauth/token',{'grant_type':'authorization_code','client_id':client['id'],'redirect_uri':'https://program.example.invalid/callback','code_verifier':verifier,'code':code})
plain.get(P+'/me',token=tokens['access_token']);plain.get(P+'/me',token=F['erp_key'],expected=401)
plain.get(A+'/courses',token=tokens['access_token'],expected=403)
# Public material and editable immutable editions.
for path in [U+'/contributors',U+'/partners',U+'/courses']:plain.get(path)
admin.get(A+'/courses');admin.get(A+'/banks')
course=admin.post(A+'/courses',{'slug':'http-'+uuid.uuid4().hex,'manifest':F['manifest']});admin.get(A+'/courses/'+course['id'])
manifest=dict(F['manifest'],version='http-2')
course=admin.put(A+'/courses/'+course['id'],{'expected_revision':course['revision'],'manifest':manifest})
course=admin.post(A+'/courses/'+course['id']+'/publish',{'expected_revision':course['revision'],'rights_confirmed':True})
plain.get(U+'/courses/'+course['id']);plain.get(U+'/course-releases/'+course['release_id'])
lesson=course['manifest']['modules'][0]['lessons'][0];plain.get(U+'/course-releases/'+course['release_id']+'/lessons/'+lesson['id'])
bank=admin.post(A+'/banks',{'bank':F['bank']});admin.get(A+'/banks/'+bank['id'])
updated_bank=dict(F['bank'],version='http-2');bank=admin.put(A+'/banks/'+bank['id'],{'expected_revision':bank['revision'],'bank':updated_bank})
edition=admin.post(A+'/banks/'+bank['id']+'/publish',{'expected_revision':bank['revision'],'course_release_id':course['release_id'],'reviewed':True,'rights_confirmed':True})
enroll_key='enroll-'+uuid.uuid4().hex
enrollment=plain.post(P+'/me/enrollments',{'release_id':course['release_id'],'specialization':'erp-integration','policy_accepted':True},token=tokens['access_token'],key=enroll_key)
replayed=plain.post(P+'/me/enrollments',{'policy_accepted':True,'specialization':'erp-integration','release_id':course['release_id']},token=tokens['access_token'],key=enroll_key)
check('JSON object key ordering preserves idempotent retry',replayed['id']==enrollment['id'])
plain.post(P+'/me/enrollments',{'release_id':course['release_id'],'specialization':'printing-integration','policy_accepted':True},token=tokens['access_token'],key=enroll_key,expected=409)
web.get(P+'/me/enrollments');web.get(P+'/me/enrollments/'+enrollment['id']);other.get(P+'/me/enrollments/'+enrollment['id'],expected=404)
web.post(P+'/me/attempts',{'enrollment_id':enrollment['id']},no_csrf=True,expected=419)
web.post(P+'/me/attempts',{'enrollment_id':enrollment['id']},expected=409)
progress=web.post(P+'/me/enrollments/'+enrollment['id']+'/lessons/'+lesson['id']+'/practice',{'expected_revision':0,'answer':lesson['practice']['answer']})
progress=web.put(P+'/me/enrollments/'+enrollment['id']+'/lessons/'+lesson['id'],{'expected_revision':progress['revision'],'completed':True,'position_key':'fin'})
admin.get(A+'/enrollments');admin.post(A+'/enrollments/'+enrollment['id']+'/accommodations',{'expected_revision':1,'assessment_seconds':3600,'reason':'Adaptare sintetică, fără date medicale.'})
attempt=web.post(P+'/me/attempts',{'enrollment_id':enrollment['id']});web.get(P+'/me/attempts/'+attempt['id'])
admin.get(A+'/assessment-releases/'+edition['id'])
check('Resume does not reset the deadline',web.post(P+'/me/attempts',{'enrollment_id':enrollment['id']})['due_at']==attempt['due_at'])
plain.get(P+'/me/attempts/'+attempt['id'],token=tokens['access_token'],expected=403)
check('Candidate has no private marking key',all('marking' not in i and 'explanation' not in i['question'] for i in attempt['items']))
by_stem={q['stem']:q for q in F['bank']['questions']}
for item in attempt['items']:
 web.put(P+'/me/attempts/'+attempt['id']+'/answers/'+item['id'],{'expected_revision':0,'answer':by_stem[item['question']['stem']]['answer']})
result=web.post(P+'/me/attempts/'+attempt['id']+'/submit');web.get(P+'/me/results');web.get(P+'/me/results/'+result['id'])
check('Theory success does not bypass practical review',result['passed'] and web.get(P+'/me/credentials')['items']==[])
web.post(P+'/me/results/'+result['id']+'/appeals',{'reason':'Contestație sintetică pentru verificarea istoricului.'})
admin.get(A+'/results');admin.get(A+'/results/'+result['id']);admin.get(A+'/attempts/'+attempt['id'])
result=admin.post(A+'/results/'+result['id']+'/decision',{'reason':'Reevaluare sintetică: răspunsurile și rezultatul sunt confirmate.','invalid_question_ids':[]})
check('Reevaluation creates another immutable result',result['version']==2)
# Media: scoped OAuth access to own files, no cross-user leakage.
avatar=plain.post(P+'/assets',{'purpose':'avatar','content_base64':F['png'],'rights_confirmed':True},token=tokens['access_token'])
plain.get(P+'/assets/'+avatar['id'],token=tokens['access_token']);binary=plain.get(P+'/assets/'+avatar['id']+'/content',token=tokens['access_token'])
check('Media content matches the normalized hash',hashlib.sha256(base64.b64decode(binary['content_base64'])).hexdigest()==avatar['sha256'])
other.get(P+'/assets/'+avatar['id'],expected=404);plain.get('/program/imagini/'+avatar['id'],expected=404)
logo=web.post(P+'/assets',{'purpose':'logo','content_base64':F['png'],'rights_confirmed':True})
# Contributor self service, curate/consent, publication, revisions and withdrawal.
contribution={'name':'Contribuitor HTTP sintetic','slug':'http-contributor-'+uuid.uuid4().hex,'summary':'Testare locală a fluxului de publicare.','categories':['testing'],'links':[],'asset_id':avatar['id'],'consent':True}
c=web.post(P+'/me/contributors',contribution);web.get(P+'/me/contributors');web.get(P+'/me/contributors/'+c['id']);web.get(P+'/me/contributors/'+c['id']+'/export')
c=web.put(P+'/me/contributors/'+c['id'],dict(contribution,expected_revision=c['revision']))
c=web.post(P+'/me/contributors/'+c['id']+'/submit',{'expected_revision':c['revision'],'consent':True})
admin.get(A+'/contributors');admin.get(A+'/contributors/'+c['id']);admin.post(A+'/contributors/'+c['id']+'/review',{'expected_revision':c['revision'],'action':'approve','reason':'Contribuție sintetică verificată.'})
plain.get(U+'/contributors/'+c['id']);plain.get('/program/imagini/'+avatar['id'])
curated=admin.post(A+'/contributors',dict(contribution,slug='curated-'+uuid.uuid4().hex,asset_id=None))
curated=admin.put(A+'/contributors/'+curated['id'],dict(contribution,slug=curated['slug'],asset_id=None,expected_revision=curated['revision']))
recipient='curated-'+uuid.uuid4().hex+'@example.invalid'
admin.post(P+'/me/verifications',{'purpose':'contributor_consent','object_id':curated['id'],'email':recipient});curated_proof=mail_token(recipient)
anon.post(P+'/verifications/preview',{'token':curated_proof});anon.post(P+'/verifications/confirm',{'token':curated_proof,'accepted':True})
admin.post(A+'/contributors/'+curated['id']+'/review',{'expected_revision':curated['revision'],'action':'approve','reason':'Acord pentru revizia curentă, verificat sintetic.'})
# Company selection and correction; source data and drafts stay private.
first=web.post(P+'/me/partners/company-checks',{'cui':CUI_SECOND});web.get(P+'/me/partners/company-checks/'+first['id'])
correct=web.post(P+'/me/partners/company-checks',{'cui':'RO'+CUI_FIRST});web.get(P+'/me/partners/company-checks/'+first['id'],expected=409)
web.post(P+'/me/partners/applications',{'company_check_id':correct['id'],'cui':first['cui'],'company_confirmed':True},expected=422)
app=web.post(P+'/me/partners/applications',{'company_check_id':correct['id'],'cui':correct['cui'],'company_confirmed':True})
web.get(P+'/me/partners/applications');web.get(P+'/me/partners/applications/'+app['id']);other.get(P+'/me/partners/applications/'+app['id'],expected=404)
app=web.put(P+'/me/partners/applications/'+app['id'],{'expected_revision':app['revision'],'description':'Companie și servicii exclusiv sintetice pentru HTTP local.','services':['Integrare ERP'],'regions':['Test local'],'languages':['ro'],'asset_id':logo['id']})
company_email='company-http-'+uuid.uuid4().hex+'@example.invalid'
app=web.post(P+'/me/partners/applications/'+app['id']+'/contacts',{'expected_revision':app['revision'],'kind':'email','value':company_email,'public_consent':True})
contact=app['contacts'][0];web.post(P+'/me/verifications',{'purpose':'partner_contact','object_id':contact['id']});web.post(P+'/verifications/confirm',{'token':mail_token(company_email),'accepted':True})
app=web.post(P+'/me/partners/applications/'+app['id']+'/contacts',{'expected_revision':app['revision'],'kind':'phone','value':'+40000000001','public_consent':True})
phone=next(x for x in app['contacts'] if x['kind']=='phone');admin.post(A+'/contacts/'+phone['id']+'/verify',{'expected_revision':phone['revision'],'reason':'Verificare telefon sintetică locală.'})
app=web.post(P+'/me/partners/applications/'+app['id']+'/submit',{'expected_revision':app['revision'],'policy_accepted':True})
admin.get(A+'/partners');admin.get(A+'/partners/'+app['id'])
app=admin.post(A+'/partners/'+app['id']+'/review',{'expected_revision':app['revision'],'action':'approve','reason':'Mandat și prezentare sintetice, evaluate independent.','representation_verified':True})
plain.get(U+'/partners/'+app['profile_id'],expected=404)
check('Approval does not publish a company without qualification','qualified_affiliate_required' in app['requirements'])
# Practical qualification: automatic validation cannot grant the credential itself.
for scenario in ['P01','P02','P03']:
 lab=web.post(P+'/me/labs',{'enrollment_id':enrollment['id'],'scenario':scenario});web.get(P+'/me/labs/'+lab['id'])
 lab=web.put(P+'/me/labs/'+lab['id'],{'expected_revision':lab['revision'],'payload':F['labs'][scenario]})
 lab=web.post(P+'/me/labs/'+lab['id']+'/submit');admin.get(A+'/labs');admin.get(A+'/labs/'+lab['id'])
 admin.post(A+'/labs/'+lab['id']+'/review',{'identity_points':25,'history_points':25,'security_points':25,'explanation_points':25,'mandatory_checks':True,'reason':'Evaluare independentă simulată, numai în testul local.'})
web.get(P+'/me/labs');credential=web.get(P+'/me/credentials')['items'][0]
web.put(P+'/me/credentials/'+credential['id']+'/visibility',{'public_name':True});public_credential=plain.get(U+'/credentials/'+credential['public_id'])
check('Public name appears only after explicit choice',public_credential['name']=='Cursant HTTP sintetic')
partner=plain.get(U+'/partners/'+app['profile_id']);plain.get(U+'/partners');authorization=partner['authorizations'][0];plain.get(U+'/authorizations/'+authorization['id'])
check('Qualification completes company publication automatically',authorization['state']=='active')
web.get(P+'/me/partners/affiliations');members=web.get(P+'/me/partners/profiles/'+app['profile_id']+'/members');web.post(P+'/me/partners/profiles/'+app['profile_id']+'/refresh')
web.post(P+'/me/verifications',{'purpose':'partner_invite','object_id':app['profile_id'],'email':F['users']['outsider']['email']})
other.post(P+'/verifications/confirm',{'token':mail_token(F['users']['outsider']['email']),'accepted':True})
check('Program invitation does not grant producer access',other.get(P+'/me')['id']==F['users']['outsider']['id'])
exported=web.get(P+'/me/export');web.post(P+'/me/import-preview',{'package':exported});imported=web.post(P+'/me/imports',{'package':exported})
check('Import only restores reading positions',imported['credentials_accepted']==0 and not imported['completion_accepted'])
web.get(P+'/me/partners/applications/'+app['id']+'/export');web.post(P+'/me/partners/applications/'+app['id']+'/appeals',{'reason':'Contestație de probă privind dosarul propriu.'})
admin.post(A+'/events',{'object_kind':'partner_application','object_id':app['id']})
admin.get(A+'/credentials');admin.post(A+'/credentials/'+credential['id']+'/decision',{'action':'suspend','reason':'Suspendare sintetică temporară.'})
plain.get(U+'/partners/'+app['profile_id'],expected=404);admin.post(A+'/credentials/'+credential['id']+'/decision',{'action':'restore','reason':'Restabilire după verificarea sintetică.'})
admin.post(A+'/authorizations/'+authorization['id']+'/decision',{'action':'suspend','reason':'Suspendare sintetică de probă.'})
plain.get(U+'/authorizations/'+authorization['id']);admin.post(A+'/authorizations/'+authorization['id']+'/decision',{'action':'restore','reason':'Condiții sintetice verificate.'})
admin.get(A+'/staff');grant=admin.post(A+'/staff',{'user_id':F['users']['delegate']['id'],'role':'community_editor'})
delegate.get(A+'/contributors');audit_key='delegated-audit-'+uuid.uuid4().hex
delegate.post(A+'/events',{'object_kind':'contributor','object_id':c['id']},key=audit_key)
admin.delete(A+'/staff/'+grant['id'],{'reason':'Retragere sintetică.'});delegate.get(A+'/contributors',expected=403)
delegate.post(A+'/events',{'object_kind':'contributor','object_id':c['id']},key=audit_key,expected=403)
admin.post(A+'/worker')
grants=web.get(P+'/me/grants');web.delete(P+'/me/grants/'+next(g['id'] for g in grants['items'] if g['client_id']==client['id']))
plain.get(P+'/me',token=tokens['access_token'],expected=401);web.delete(P+'/me/applications/'+client['id'])
app=web.get(P+'/me/partners/applications/'+app['id']);web.post(P+'/me/partners/applications/'+app['id']+'/withdraw',{'expected_revision':app['revision']})
affiliation=next(a for a in members['items'] if a['user_id']==user['id']);web.delete(P+'/me/partners/affiliations/'+affiliation['id'])
web.post(P+'/me/partners/applications/'+app['id']+'/withdraw',{'expected_revision':app['revision']},expected=403)
web.post(P+'/me/contributors/'+c['id']+'/withdraw');plain.get(U+'/contributors/'+c['id'],expected=404);web.delete(P+'/assets/'+avatar['id'])
admin.post(A+'/assessment-releases/'+edition['id']+'/retire',{'reason':'Sfârșitul probei sintetice.'});admin.post(A+'/courses/'+course['id']+'/retire',{'reason':'Sfârșitul probei sintetice.'})
new_password='Synthetic-updated-'+uuid.uuid4().hex
security=web.post(P+'/me/password',{'current_password':password,'new_password':new_password,'confirmation':new_password});web.csrf=security['csrf']
security=web.post(P+'/me/access-revocations',{'current_password':new_password});web.csrf=security['csrf']
result={'passed':len(checks),'failed':0,'checks':checks,**report()}
expected={r['id'] for r in json.loads((ROOT/'contracts/program/routes.json').read_text('utf8'))}
missing=sorted(expected-set(result['successful_operations']))
result['failed']=len(missing);result['missing']=missing
(ROOT/'tests/runtime/reports/program-http.json').write_text(json.dumps(result,indent=2)+'\n',encoding='utf8')
check('Every program operation has an actual HTTP success',not missing)
print(json.dumps({'passed':len(checks),'failed':0,'response_checks':result['response_checks'],'program_operations':len(expected)-len(missing),'missing':missing}))
