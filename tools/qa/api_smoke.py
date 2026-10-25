"""Bounded, read-only HTTPS smoke test; no login, real keys, email or production writes."""
import argparse,hashlib,json,ssl,urllib.error,urllib.request
from datetime import datetime,timezone
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None
def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--pending-schema',action='store_true');args=parser.parse_args()
 opener=urllib.request.build_opener(NoRedirect(),urllib.request.HTTPSHandler(context=ssl.create_default_context()));checks=[]
 def read(path,method='GET',headers=None):
  request=urllib.request.Request('https://openaliment.ro'+path,method=method,headers={'User-Agent':'OpenAliment-ReadOnly-QA/0.5',**(headers or {})})
  try:r=opener.open(request,timeout=20)
  except urllib.error.HTTPError as error:r=error
  with r:
   data=r.read(4*1024*1024+1)
   if len(data)>4*1024*1024:raise RuntimeError('Response exceeds smoke-test budget')
   return r.status,r.headers,data
 def check(name,ok):
  checks.append({'name':name,'passed':bool(ok)})
  if not ok:raise RuntimeError('Failed: '+name)
 status,headers,raw=read('/api/manifest.json',headers={'Origin':'https://example.invalid'});manifest=json.loads(raw)
 check('Public discovery and CORS',status==200 and headers.get('Access-Control-Allow-Origin')=='*')
 expected=json.loads((ROOT/'contracts/api-documentation.json').read_text(encoding='utf-8'))['operation_count']
 check('Documented operation count and no crawling required',manifest['operation_count']==expected and manifest['agent_use']['crawling_required'] is False)
 check('OAuth migration state',manifest['authentication']['oauth']['available'] is (not args.pending_schema))
 status,headers,raw=read('/api/platform-openapi.json');check('Published contract hash matches tested artifact',status==200 and hashlib.sha256(raw).digest()==hashlib.sha256((ROOT/'contracts/platform-openapi.json').read_bytes()).digest())
 status,headers,raw=read('/api/docs');check('Developer portal includes search and revocation',status==200 and b'id="api-search"' in raw and b'manageRotateAccessToken' in raw)
 status,headers,raw=read('/api/guide.md');check('Human and AI text guide',status==200 and b'PKCE' in raw and b'Idempotency-Key' in raw)
 guide=json.loads((ROOT/'contracts/api-developer-guide.json').read_text(encoding='utf-8'))
 for lang in ['python','javascript','kotlin']:
  status,headers,raw=read('/api/sdk/'+lang);check('Exact SDK source '+lang,status==200 and raw==guide['sdk'][lang].encode())
 status,headers,raw=read('/api/schemas/product-label.schema.json');check('Schema downloadable',status==200 and json.loads(raw)['$schema'].startswith('https://json-schema.org/'))
 status,headers,raw=read('/api/v2/public/vocabularies/allergens');check('Public allergen vocabulary',status==200 and bool(json.loads(raw)['items']))
 status,headers,raw=read('/api/v2/manage/products');check('Anonymous producer access rejected',status==401 and json.loads(raw)['error_code']=='authentication')
 status,headers,raw=read('/api/v2/manage/products',headers={'Authorization':'Bearer oa_'+('0'*64)});check('Invalid key rejected, no HTML login',status==401 and json.loads(raw)['error_code']=='invalid_token')
 status,headers,raw=read('/api/v2/public/trade-items?limit=1');check('Public catalog schema state',status==(503 if args.pending_schema else 200))
 status,headers,raw=read('/api/v2/public/trade-items','DELETE');check('Read-only public catalog',status==405)
 status,headers,raw=read('/api/v2/public/vocabularies/allergens','HEAD');check('HEAD no body, bounded read headers',status==200 and not raw and headers.get('RateLimit-Limit') is not None)
 status,headers,raw=read('/api/v2/manage/products','OPTIONS',{'Origin':'https://unregistered.example.invalid','Access-Control-Request-Method':'GET'});check('Unregistered private browser origin refused',status==403)
 status,headers,raw=read('/api/v2/public/food?code=9520000000011');check('Demonstration bread lookup works without AI',status==200 and b'private_recipe' not in raw)
 status,headers,raw=read('/01/09520000000011/10/DEMO-PBT-01?linkType=linkset',headers={'Accept':'application/linkset+json'});check('GS1 demonstration linkset preserved',status==200 and 'linkset' in json.loads(raw))
 status,headers,raw=read('/acasa/');check('Consumer application publishes updated cache generation',status==200 and b'/assets/home/app.js?v=6' in raw)
 status,headers,raw=read('/.well-known/oauth-authorization-server');check('OAuth metadata declares PKCE and exact live origin',status==200 and json.loads(raw)['issuer']=='https://openaliment.ro' and json.loads(raw)['code_challenge_methods_supported']==['S256'])
 report={'executed_at':datetime.now(timezone.utc).isoformat(),'environment':'production HTTPS, read-only; no credentials or user data retained','pending_schema':args.pending_schema,'passed':len(checks),'failed':0,'checks':checks}
 target=ROOT/'tests/runtime/reports/api-production-smoke.json';target.parent.mkdir(parents=True,exist_ok=True);target.write_text(json.dumps(report,indent=2)+'\n')
 print(json.dumps({'passed':len(checks),'failed':0,'pending_schema':args.pending_schema}))
if __name__=='__main__':main()
