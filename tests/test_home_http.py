"""Private consumer HTTP boundary; localhost test fixtures only, no SMTP send."""
import json,urllib.request,urllib.error
import os
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
BASE=os.environ.get('OA_TEST_BASE_URL', 'http://127.0.0.1:8088')
fixture=json.loads((ROOT/'tests/runtime/home-fixtures.json').read_text(encoding='utf-8'))
checks=[]
def check(name,ok):
 if not ok:raise AssertionError(name)
 checks.append({'name':name,'passed':True});print('PASS',name)
def call(path,method='GET',body=None,auth=False,csrf=False,headers=None):
 h=dict(headers or {})
 if auth:h['Cookie']='oa_home='+fixture['cookie']
 if csrf:h['X-Home-CSRF']=fixture['csrf']
 data=None
 if body is not None:data=json.dumps(body).encode();h['Content-Type']='application/json'
 try:r=urllib.request.urlopen(urllib.request.Request(BASE+path,method=method,data=data,headers=h),timeout=20)
 except urllib.error.HTTPError as e:r=e
 with r:return r.status,dict(r.headers),r.read()
s,h,b=call('/api/v2/home/session');j=json.loads(b);check('Guest has local use without authentication gate',s==200 and j['authenticated'] is False and j['available'] is True and 'Set-Cookie' not in h)
s,h,b=call('/api/v2/home/session',auth=True);j=json.loads(b);check('Private cookie authenticates only consumer principal',s==200 and j['session']['account_id']==fixture['account_id'] and j['session']['csrf']==fixture['csrf'] and fixture['cookie'] not in b.decode())
check('Private session never permits credentialed CORS','Access-Control-Allow-Origin' not in h and 'no-store' in h['Cache-Control'])
s,h,b=call('/api/v1/manage/products',auth=True);check('Consumer cookie cannot authenticate as producer',s==401)
s,h,b=call('/api/v2/home/changes');check('Anonymous user cannot list household',s==401)
s,h,b=call('/api/v2/home/changes?after=0&limit=1',auth=True);j=json.loads(b);check('Private changes are paginated',s==200 and j['household_id']==fixture['household_id'] and len(j['changes'])==1 and j['has_more'])
s,h,b=call('/api/v2/home/changes?after=-1',auth=True);check('Invalid cursor rejected',s==422)
s,h,b=call('/api/v2/home/sync','POST',{'operations':[]},auth=True);check('Cookie alone cannot mutate without separate CSRF',s==419)
s,h,b=call('/api/v2/home/sync','POST',{'operations':[]},True,True);check('Valid CSRF permits bounded sync',s==200 and json.loads(b)['conflicts']==[])
s,h,b=call('/api/v2/home/sync','POST',{'operations':[],'preferences':{'allergens':['milk']}},True,True);check('Health preferences cannot be uploaded through sync',s==422)
s,h,b=call('/api/v2/home/sync','POST',{'operations':[]},True,True,{'Origin':'https://evil.invalid'});check('Cross-origin household write blocked',s==403)
s,h,b=call('/api/v2/home/photos/'+fixture['photo_id'],auth=True);check('Authorized household can fetch private JPEG',s==200 and h['Content-Type']=='image/jpeg' and b.startswith(b'\xff\xd8') and 'no-store' in h['Cache-Control'])
s,h,b=call('/api/v2/home/photos/'+fixture['photo_id']);check('Anonymous user cannot fetch private photo',s==401)
s,h,b=call('/api/v2/home/login/preview','GET');check('Email scanner GET cannot consume a magic link',s in (401,404,405))
s,h,b=call('/api/v2/home/login/preview','POST',{'token':'0'*64});check('Invalid magic link returns generic expired response',s==410)
s,h,b=call('/api/v2/home/session','OPTIONS',headers={'Origin':'https://evil.invalid'});check('Unregistered origin cannot preflight private API',s==403 and 'Access-Control-Allow-Origin' not in h)
(ROOT/'tests/runtime/reports/home-cloud-http-report.json').write_text(json.dumps({'environment':'isolated local HTTP with private test cookies; no real email sent','passed':len(checks),'failed':0,'checks':checks},indent=2),encoding='utf-8')
print(json.dumps({'passed':len(checks),'failed':0}))
