"""007 account, transfer and public contracts on isolated loopback only."""
import hashlib
import http.cookiejar
import io
import json
import os
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import uuid
import zipfile
from pathlib import Path
from api_contract_probe import validate, report as contract_report

ROOT=Path(__file__).resolve().parents[1]
BASE=os.environ['OA_TEST_BASE_URL']
if urllib.parse.urlsplit(BASE).hostname not in ['127.0.0.1','localhost','::1']:
    raise RuntimeError('Isolated loopback only')
f=json.loads((ROOT/'tests/runtime/readiness-fixtures.json').read_text())
erp=json.loads((ROOT/'tests/runtime/api-platform-fixtures.json').read_text())
reference=json.loads((ROOT/'tests/runtime/api-reference-fixtures.json').read_text())
checks=[]
jar=http.cookiejar.CookieJar()
web=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
csrf=''

def check(name,ok):
    if not ok:raise AssertionError(name)
    checks.append({'name':name,'passed':True})


def call(path,method='GET',payload=None,token=None,raw=None,headers=None):
    h=dict(headers or {})
    if payload is not None:h['Content-Type']='application/json';raw=json.dumps(payload).encode()
    if token:h['Authorization']='Bearer '+token
    if method!='GET':h.setdefault('X-CSRF-Token',csrf)
    request=urllib.request.Request(BASE+path,data=raw,headers=h,method=method)
    try:response=web.open(request,timeout=30)
    except urllib.error.HTTPError as error:response=error
    with response:
        body=response.read();result=json.loads(body) if 'json' in response.headers.get('Content-Type','') else body
        validate(path,method,response.status,response.headers,result)
        return response.status,result


def helper(payload):
    process=subprocess.run([os.environ['OA_PHP'],'tests/readiness-tools.php'],cwd=ROOT,input=json.dumps(payload),text=True,capture_output=True,check=True)
    return json.loads(process.stdout)


status,result=call('/api/v2/identity/session');csrf=result['csrf']
status,result=call('/api/v2/identity/sessions','POST',{'email':f['email'],'password':f['password']});csrf=result['csrf']
check('Password login explicitly reports pending MFA',status==200 and result['authenticated'] is False and result['mfa_required'] is True and result['verification_url']=='/account/verify')
check('Pending MFA cannot open producer data',call('/api/v2/manage/dashboard')[0]==403)
check('Pending MFA cannot inspect sessions',call('/api/v2/account/security')[0]==403)
status,result=call('/api/v2/account/passkeys/recover','POST',{'code':f['recovery_code']});csrf=result['csrf']
check('Recovery code verifies MFA and rotates CSRF',status==200 and result['verified'])
check('Verified MFA opens producer data',call('/api/v2/manage/dashboard')[0]==200)
status,result=call('/api/v2/account/security');session_id=result['current_session_id']
check('Account metadata excludes secrets',status==200 and result['passkeys'] and all('session_hash' not in s for s in result['sessions']))
status,options=call('/api/v2/account/passkeys/create/options','POST',{'name':'HTTP EC authenticator','password':f['password']})
proof=helper({'mode':'create','challenge':options['publicKey']['challenge']})
status,created=call('/api/v2/account/passkeys/create/confirm','POST',proof['payload']);csrf=created['csrf']
check('HTTP registration verifies CBOR and registers the real EC public key',status==200 and created['created'])
status,options=call('/api/v2/account/passkeys/get/options','POST',{})
proof_get=helper({'mode':'get','fixture':proof['fixture'],'challenge':options['publicKey']['challenge']})
status,result=call('/api/v2/account/passkeys/get/confirm','POST',proof_get['payload']);csrf=result['csrf']
check('HTTP assertion verifies EC signature and rotates the session',status==200 and result['verified'])
check('Recovery code regeneration returns one-time codes',len(call('/api/v2/account/passkeys/recovery-codes','POST',{})[1]['recovery_codes'])==10)
check('An enrolled replacement permits explicit key removal',call('/api/v2/account/passkeys/'+created['id']+'/remove','POST',{})[0]==200)
status,result=call('/api/v2/account/security');session_id=result['current_session_id']
check('Unknown session cannot be revoked',call('/api/v2/account/sessions/'+uuid.uuid4().hex+'/revoke','POST',{})[0]==404)
check('Public recovery rejects unexpected input',call('/api/v2/account/password/request','POST',{'email':'x@example.invalid','is_admin':True})[0]==422)
check('Cross-origin recovery rejected',call('/api/v2/account/password/request','POST',{'email':'x@example.invalid'},headers={'Origin':'https://untrusted.example.invalid'})[0]==403)
check('Invalid reset token is not consumed or accepted',call('/api/v2/account/password/preview','POST',{'token':'0'*64})[0]==410)
check('Revoking current session succeeds',call('/api/v2/account/sessions/'+session_id+'/revoke','POST',{})[0]==200)
check('Revoked session cannot inspect account',call('/api/v2/account/security')[0]==401)
check('Password recovery request has uniform acceptance',call('/api/v2/account/password/request','POST',{'email':f['email']})[0]==200)
reset=helper({'mode':'reset-token','email':f['email']})['token']
check('Password recovery preview is read-only',call('/api/v2/account/password/preview','POST',{'token':reset})[0]==200)
status,result=call('/api/v2/account/password/reset','POST',{'token':reset,'password':f['password']+'-http','confirmation':f['password']+'-http'})
check('HTTP password reset preserves second factor',status==200 and result['passkey_preserved'])

status,result=call('/api/v2/public/trade-items/'+f['item_id']+'/ratings')
check('Public ratings use only approved criterion responses',status==200 and result['criteria']['taste']['average']==5 and result['criteria']['packaging']['average'] is None)
header=json.dumps({'schema_version':'openaliment.transfer.stream.v1','source_base_url':'https://example.invalid/'+uuid.uuid4().hex,'exported_at':'2026-10-25T00:00:00Z'},separators=(',',':'))+'\n'
stream=header+json.dumps({'end':True,'records':0,'sha256':hashlib.sha256(header.encode()).hexdigest()},separators=(',',':'))+'\n'
status,result=call('/api/v2/manage/import-stream','POST',token=erp['key'],raw=stream.encode(),headers={'Content-Type':'application/x-ndjson'})
check('NDJSON preview validates exact digest before writes',status==200 and result['publication']=='private')
status,result=call('/api/v2/manage/import-stream?dry_run=false','POST',token=erp['key'],raw=stream.encode(),headers={'Content-Type':'application/x-ndjson','Idempotency-Key':'stream-'+uuid.uuid4().hex})
check('NDJSON application is private and keeps provenance',status==200 and result['verification']=='requires_reverification')
bad=stream.replace('https://example.invalid/','https://changed.invalid/')
check('Changed NDJSON transport fails final checksum',call('/api/v2/manage/import-stream','POST',token=erp['key'],raw=bad.encode(),headers={'Content-Type':'application/x-ndjson'})[0]==422)
check('Truncated NDJSON cannot import',call('/api/v2/manage/import-stream','POST',token=erp['key'],raw=header.encode(),headers={'Content-Type':'application/x-ndjson'})[0]==422)

status,archive=call('/api/v2/public/recipe-versions/'+reference['recipe']+'/archive')
with zipfile.ZipFile(io.BytesIO(archive)) as z:
    manifest=json.loads(z.read('manifest.json'))
    check('Licensed archive has standalone reading copy',status==200 and 'README.md' in z.namelist() and 'recipe.json' in z.namelist())
    check('Archive excludes public unlicensed documents',all(reference['doc'] not in name for name in z.namelist()))
    check('Archive preserves exact recipe snapshot digest',hashlib.sha256(z.read('recipe.json')).hexdigest()==manifest['recipe']['snapshot_sha256'])
    check('Archive does not invent validated results',b'experimental' in z.read('README.md'))
email='erase-http-'+uuid.uuid4().hex+'@example.invalid'
call('/api/v2/home/login/request','POST',{'email':email})
token=helper({'mode':'home-token','email':email,'kind':'login'})['token']
call('/api/v2/home/login/confirm','POST',{'token':token})
home_csrf=call('/api/v2/home/session')[1]['session']['csrf']
headers={'X-Home-CSRF':home_csrf}
check('Personal sensitive action sends confirmation separately',call('/api/v2/home/account/action/request','POST',{'action':'erase'},headers=headers)[0]==200)
token=helper({'mode':'home-token','email':email,'kind':'action'})['token']
check('Personal erasure preview does not consume confirmation',call('/api/v2/home/account/action/preview','POST',{'token':token},headers=headers)[0]==200)
check('Personal erasure confirms once over HTTP',call('/api/v2/home/account/action/confirm','POST',{'token':token},headers=headers)[0]==200)
report={'passed':len(checks),'failed':0,'checks':checks,'environment':'isolated HTTP; no production, no real SMTP',**contract_report()}
(ROOT/'tests/runtime/reports/readiness-http.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps({'passed':len(checks),'failed':0}))
