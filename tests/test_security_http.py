"""Authorization boundaries and account revocation, disposable localhost only."""
import http.cookiejar,json,os,urllib.error,urllib.parse,urllib.request,uuid
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];BASE=os.environ['OA_TEST_BASE_URL']
if urllib.parse.urlsplit(BASE).hostname not in ['127.0.0.1','localhost','::1']:raise RuntimeError('Local test host required')
f=json.loads((ROOT/'tests/runtime/security-fixtures.json').read_text(encoding='utf-8'));checks=[]
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
def browser():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect())
def call(path,method='GET',data=None,token=None,csrf=None,web=None,form=False):
    headers={};body=None
    if data is not None:headers['Content-Type']='application/x-www-form-urlencoded' if form else 'application/json';body=(urllib.parse.urlencode(data) if form else json.dumps(data)).encode()
    if token:headers['Authorization']='Bearer '+token
    if csrf:headers['X-CSRF-Token']=csrf
    try:r=(web or urllib.request.build_opener(NoRedirect())).open(urllib.request.Request(BASE+path,body,headers,method=method),timeout=20)
    except urllib.error.HTTPError as e:r=e
    with r:
        raw=r.read();return r.status,json.loads(raw) if raw and 'json' in r.headers.get('Content-Type','') else raw
def check(name,ok):
    if not ok:raise AssertionError(name)
    checks.append({'name':name,'passed':True});print('PASS '+name)
guest=browser();s,v=call('/api/v2/identity/session',web=guest);csrf=v['csrf'];check('Probe starts with an anonymous browser',s==200 and v['authenticated'] is False)
for path in ['/manage','/manage/export','/manage/api','/manage/security','/manage/developers','/manage/connectors','/manage/resolver/'+f['item_id']]:
    check('HTML rejects read Bearer '+path,call(path,token=f['read_key'],web=guest)[0]==403)
for action,id in [('product.create',''),('label.publish',f['label_id']),('alert.create','a'*32),('transfer.inspect','')]:
    check('HTML POST rejects read Bearer '+action,call('/manage','POST',{'_csrf':csrf,'_action':action,'_id':id,'name':'Forbidden','category':'lactate'},f['read_key'],web=guest,form=True)[0]==403)
check('API v2 read credential still cannot write',call('/api/v2/manage/products','POST',{'name':'Forbidden','category':'lactate'},f['read_key'])[0]==403)
check('API v1 read credential still cannot write',call('/api/v1/manage/products','POST',{'name':'Forbidden','category':'lactate'},f['read_key'])[0]==403)
check('Public approved page remains independent',call('/t/'+f['item_id'])[0]==200)
first=browser();second=browser()
def login(web):
    s,v=call('/api/v2/identity/session',web=web);s,v=call('/api/v2/identity/sessions','POST',{'email':f['email'],'password':f['password']},csrf=v['csrf'],web=web);check('Independent producer session established',s==200);return v['csrf']
c1=login(first);c2=login(second)
check('Mixed valid session and Bearer rejected',call('/manage',token=f['read_key'],web=first)[0]==403)
check('Security settings page is available to the account',call('/manage/security',web=first)[0]==200)
check('Missing CSRF cannot revoke sessions',call('/api/v2/identity/access-revocations','POST',{'current_password':f['password']},web=first)[0]==419)
s,v=call('/api/v2/identity/access-revocations','POST',{'current_password':f['password']},csrf=c1,web=first);check('Explicit access revocation rotates current session',s==200 and v['access_revoked'] and v['csrf']!=c1);c1=v['csrf']
check('Other browser loses its producer session',call('/api/v2/identity/session',web=second)[1]['authenticated'] is False)
check('Current browser stays connected',call('/api/v2/identity/session',web=first)[1]['authenticated'] is True)
check('Previously issued key loses access immediately',call('/api/v2/manage/products',token=f['read_key'])[0]==401)
new='New-synthetic-password-'+uuid.uuid4().hex
check('Password confirmation is enforced',call('/api/v2/identity/password','POST',{'current_password':f['password'],'new_password':new,'confirmation':'different'},csrf=c1,web=first)[0]==422)
s,v=call('/api/v2/identity/password','POST',{'current_password':f['password'],'new_password':new,'confirmation':new},csrf=c1,web=first);check('Password changes through the documented API',s==200 and v['access_revoked'])
third=browser();s,v=call('/api/v2/identity/session',web=third);check('Old password no longer authenticates',call('/api/v2/identity/sessions','POST',{'email':f['email'],'password':f['password']},csrf=v['csrf'],web=third)[0]==401)
s,v=call('/api/v2/identity/sessions','POST',{'email':f['email'],'password':new},csrf=v['csrf'],web=third);check('Replacement password authenticates',s==200 and v['authenticated'])
(ROOT/'tests/runtime/reports/security-http.json').write_text(json.dumps({'passed':len(checks),'failed':0,'checks':checks},indent=2),encoding='utf-8');print(json.dumps({'passed':len(checks),'failed':0}))
