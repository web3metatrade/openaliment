"""Real HTTP checks for the dependency-free Python client; loopback fixtures only."""
import importlib.util,json,os
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('openaliment_sdk',ROOT/'tools/sdk/openaliment.py');sdk=importlib.util.module_from_spec(spec);spec.loader.exec_module(sdk)
base=os.environ['OA_TEST_BASE_URL'];f=json.loads((ROOT/'tests/runtime/api-platform-fixtures.json').read_text());checks=[]
def check(name,value):
 if not value:raise AssertionError(name)
 checks.append({'name':name,'passed':True});print('PASS '+name)
api=sdk.OpenAliment(base,allow_insecure_loopback=True)
check('Python SDK resolves real demo profile',api.food('9520000000011')['simulation'])
private=sdk.OpenAliment(base,f['read_key'],allow_insecure_loopback=True)
check('Python SDK pages own authorized resources',any(p['id']==f['product_id'] for p in private.collection('/api/v2/manage/products?limit=1')))
try:sdk.OpenAliment(base)
except ValueError:check('Python SDK requires explicit loopback exception',True)
else:raise AssertionError('Insecure default')
try:private.request('GET','//evil.invalid/data')
except ValueError:check('Python SDK cannot forward token to external origin',True)
else:raise AssertionError('External origin')
try:sdk.OpenAliment(base,'oa_'+'0'*64,allow_insecure_loopback=True).request('GET','/api/v2/manage/products')
except sdk.ApiError as e:check('Python SDK retains status and request ID without logging credentials',e.status==401 and e.request_id and 'oa_' not in str(e))
else:raise AssertionError('Expected authentication failure')
params,verifier,state=sdk.pkce_parameters('a'*32,'ro.exemplu.aplicatie:/callback');check('Python SDK generates fresh PKCE and state',params['code_challenge_method']=='S256' and len(params['code_challenge'])==43 and len(verifier)>=43 and state==params['state'])
(ROOT/'tests/runtime/reports/api-sdk-python.json').write_text(json.dumps({'passed':len(checks),'failed':0,'checks':checks},indent=2)+'\n');print(json.dumps({'passed':len(checks),'failed':0}))
