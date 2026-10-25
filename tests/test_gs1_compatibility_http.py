"""GS1 identity through real public reads and private inventory sync; loopback only."""
import json,os,urllib.parse,urllib.request,urllib.error,uuid
from datetime import datetime,timezone
from pathlib import Path
from api_contract_probe import validate
ROOT=Path(__file__).resolve().parents[1];BASE=os.environ['OA_TEST_BASE_URL'];checks=[]
if urllib.parse.urlsplit(BASE).hostname not in ['localhost','127.0.0.1','::1']:raise RuntimeError('Loopback only')
def call(path,method='GET',data=None,headers=None):
    h={'Accept':'application/json',**(headers or {})}
    if data is not None:h['Content-Type']='application/json'
    try:r=urllib.request.urlopen(urllib.request.Request(BASE+path,None if data is None else json.dumps(data).encode(),h,method=method),timeout=15)
    except urllib.error.HTTPError as e:r=e
    body=json.loads(r.read());validate(path,method,r.status,r.headers,body);return r.status,body
def check(name,passed):
    if not passed:raise AssertionError(name)
    checks.append({'name':name,'passed':True})
base=BASE+'/01/09520000000011'
code=base+'/22/2A/10/DEMO-PBT-01/21/A%2FB%2B%25?x=one;15=261030'
status,profile=call('/api/v2/public/food?'+urllib.parse.urlencode({'code':code}))
q={'22':'2A','10':'DEMO-PBT-01','21':'A/B+%'}
check('Public projection preserves all qualifiers once',status==200 and profile['gs1_qualifiers']==q)
check('Semicolon has the same date semantics over HTTP',profile['expiry']['value']=='2026-10-30' and profile['expiry']['kind']=='best_before')
for suffix in ['/21/S/10/L','/99/X','/10/L/235/T','/10/%20']:
    status,_=call('/api/v2/public/food?'+urllib.parse.urlencode({'code':base+suffix}));check('Malformed GS1 request rejected '+suffix,status==400)
home=json.loads((ROOT/'tests/runtime/home-fixtures.json').read_text());ident=uuid.uuid4().hex;now=datetime.now(timezone.utc).isoformat().replace('+00:00','Z')
item={'id':ident,'revision':1,'profile':profile,'code':code,'name':'GS1 HTTP fixture','photo_id':None,'quantity':1,'unit':'pack','location':'home','state':'available','added_at':now,'updated_at':now,'opened_at':None,'consumed_at':None,'expiry':profile['expiry']}
headers={'Cookie':'oa_home='+home['cookie'],'X-Home-CSRF':home['csrf']}
status,result=call('/api/v2/home/sync','POST',{'operations':[{'event_id':uuid.uuid4().hex,'item_id':ident,'expected_revision':0,'kind':'put','item':item}]},headers)
check('Private sync accepts additive GS1 metadata',status==200 and not result['conflicts'])
status,changes=call('/api/v2/home/changes?after=0&limit=100',headers=headers)
saved=next(c['value']['item'] for c in changes['changes'] if c['kind']=='item' and c['value']['item_id']==ident)
check('Private change feed retains serial CPV lot and original URL',saved['profile']['gs1_qualifiers']==q and saved['code']==code)
check('Observed date survives sync without becoming producer approved',saved['profile']['observed_dates']==profile['observed_dates'] and saved['profile']['observed_dates'][0]['source']=='barcode')
(ROOT/'tests/runtime/reports/gs1-compatibility-http.json').write_text(json.dumps({'passed':len(checks),'failed':0,'checks':checks},indent=2)+'\n')
print(json.dumps({'passed':len(checks),'failed':0}))
