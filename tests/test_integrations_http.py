"""HTTP contract and isolation checks; localhost and disposable fixtures only."""
import hashlib,json,urllib.error,urllib.request,uuid
import os
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
BASE=os.environ.get('OA_TEST_BASE_URL', 'http://127.0.0.1:8088')
fixture=json.loads((ROOT/'tests/runtime/integration-fixtures.json').read_text(encoding='utf-8'))
checks=[]
def check(name,condition):
    if not condition:raise AssertionError(name)
    checks.append({'name':name,'passed':True});print('PASS',name)
def call(path,method='GET',data=None,private=False,headers=None):
    h=dict(headers or {})
    if private:h['Authorization']='Bearer '+fixture['api_token'];h['Idempotency-Key']='http-'+hashlib.sha256((path+json.dumps(data)).encode()).hexdigest()
    body=None
    if data is not None:body=json.dumps(data).encode();h['Content-Type']='application/json'
    try:r=urllib.request.urlopen(urllib.request.Request(BASE+path,data=body,method=method,headers=h),timeout=15)
    except urllib.error.HTTPError as e:r=e
    with r:return r.status,dict(r.headers),r.read()
for path in ['/api/v2/public/food?code=9520000000011','/api/v2/public/capabilities','/api/schemas/partner-mapping.schema.json','/api/integration-openapi.json']:
    status,h,b=call(path,headers={'Origin':'https://example.invalid'});check('Public CORS without credentials '+path,status==200 and h.get('Access-Control-Allow-Origin')=='*' and 'Access-Control-Allow-Credentials' not in h)
    status,h,b=call(path,'OPTIONS',headers={'Origin':'https://example.invalid','Access-Control-Request-Method':'GET'});check('Public preflight '+path,status==204 and b==b'' and 'OPTIONS' in h.get('Allow',''))
status,h,b=call('/api/v2/public/food','POST',{});check('Public read API rejects mutation with correct Allow',status==405 and h['Allow']=='GET, HEAD, OPTIONS')
status,h,b=call('/api/v2/public/food?code=1111111111111');check('Validation errors preserve public CORS',status==422 and h['Access-Control-Allow-Origin']=='*')
status,h,b=call('/api/v2/manage/connectors');check('Connector requires producer authentication',status==401 and 'Access-Control-Allow-Origin' not in h)
status,h,b=call('/api/v2/manage/connectors',private=True);check('Producer lists its connectors by scoped token',status==200 and len(json.loads(b)['items'])==2)
status,h,b=call('/api/v2/manage/connectors','POST',{'name':'foreign','kind':'catalog'},True,{'Origin':'https://evil.invalid'});check('Cross-origin producer mutation blocked',status==403)
status,h,b=call('/api/v2/home/receipts/preview','GET');check('Receipt capability never accepted in GET URL',status==405 and h['Allow']=='POST')
status,h,b=call('/api/v2/home/receipts/preview','POST',{'token':fixture['receipt_token']});receipt=json.loads(b);check('Guest can preview private receipt through POST',status==200 and receipt['id']==fixture['receipt']['id'] and 'no-store' in h['Cache-Control'] and 'Set-Cookie' not in h and 'Access-Control-Allow-Origin' not in h)
status,h,b=call('/api/v2/home/receipts/preview','POST',{'token':fixture['receipt_token']},headers={'Origin':'https://evil.invalid'});check('Receipt cross-origin lookup blocked',status==403)
status,h,b=call('/api/v2/home/receipts/preview','POST',{'token':'0'*64});check('Unknown capability is not enumerable',status==404)
status,h,b=call('/acasa/');check('Receipt app markup has one confirmation action',status==200 and b'id="receipt-add"' in b and b'Adaug\xc4\x83 acas\xc4\x83' in b)
report={'environment':'isolated local HTTP; simulated ERP/retailer','passed':len(checks),'failed':0,'checks':checks}
(ROOT/'tests/runtime/reports/partner-http-report.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
print(json.dumps({'passed':len(checks),'failed':0}))
