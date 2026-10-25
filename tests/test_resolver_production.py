"""Read-only HTTPS transport checks. Never creates products or claims a GTIN."""
import datetime
import json
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
import jsonschema

BASE='https://openaliment.ro'
UNKNOWN='09521234000006'
checks=[]
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl):return None
opener=urllib.request.build_opener(NoRedirect())
def request(path,method='GET',headers=None):
    req=urllib.request.Request(BASE+path,headers=headers or {},method=method)
    try:r=opener.open(req,timeout=25)
    except urllib.error.HTTPError as error:r=error
    return r.status,r.headers,r.read()
def check(name,value):
    checks.append({'name':name,'passed':bool(value)})
    if not value:raise AssertionError(name)
    if len(checks)%25==0:print('HTTPS checks passed:',len(checks),flush=True)

status,h,body=request('/.well-known/gs1resolver')
description=json.loads(body)
jsonschema.validate(description,json.loads((ROOT/'contracts/gs1/description-schema.json').read_text()))
check('HTTPS resolver descriptor validates official GS1 schema',status==200 and description['supportedPrimaryKeys']==['01'] and description['resolverRoot']==BASE)
check('Public CORS is available without credentials',h.get('Access-Control-Allow-Origin')=='*' and not h.get('Access-Control-Allow-Credentials'))
check('Resolver marker identifies deployed implementation',h.get('X-OpenAliment-Resolver')=='1.2.1')
status,h,body=request('/.well-known/gs1resolver','HEAD')
check('HEAD descriptor has headers and no body',status==200 and not body and int(h['Content-Length'])>0)
status,h,body=request('/01/'+UNKNOWN,'OPTIONS',{'Origin':'https://example.com','Access-Control-Request-Method':'GET'})
check('Cross-origin preflight reaches application over TLS',status==204 and not body and h.get('Access-Control-Allow-Origin')=='*' and 'OPTIONS' in h.get('Access-Control-Allow-Methods',''))
check('Unknown syntactically valid GTIN returns 404',request('/01/'+UNKNOWN)[0]==404)
check('Invalid GTIN returns 400',request('/01/09506000134353')[0]==400)
check('Unsupported primary key returns 404',request('/414/9521234000008')[0]==404)
check('Malformed qualifier order returns 400',request('/01/'+UNKNOWN+'/21/S/10/L')[0]==400)
check('Encoded control characters return 400',request('/01/'+UNKNOWN+'/10/A%0AB')[0]==400)
check('Malformed percent encoding returns 400',request('/01/'+UNKNOWN+'/10/A%Q1')[0]==400)
charset='!"%&\'()*+,-./:;<=>?_0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'
for char in charset:
    value='A'+char+'00B'
    path='/01/'+UNKNOWN+'/10/'+urllib.parse.quote(value,safe='')
    status,h,body=request(path)
    check('Encoded GS1 X transport U+%04X'%ord(char),status==404 and h.get('X-OpenAliment-Resolver')=='1.2.1' and json.loads(body).get('error_code')=='not_found')
for value in ['000001','LotA','lota','A%2FB','A/B+00%?','A'*20]:
    status,h,body=request('/01/'+UNKNOWN+'/10/'+urllib.parse.quote(value,safe=''))
    check('Exact case/zero/literal-percent transport '+value,status==404 and h.get('X-OpenAliment-Resolver')=='1.2.1')
for suffix in ['/22/CPV/10/L/21/S','/235/TPX-1','/10/A%2FB/']:
    status,h,body=request('/01/'+UNKNOWN+suffix)
    check('Qualified GTIN transport '+suffix,status==404 and h.get('X-OpenAliment-Resolver')=='1.2.1')
for vector in json.loads((ROOT/'tests/runtime/reports/epc-vectors.json').read_text()):
    status,h,body=request(vector['path']+'?linkType=linkset',headers={'Accept':'application/linkset+json'})
    check('EPC transport '+vector['name']+' '+vector['path'][1:3],status==404 and h.get('X-OpenAliment-Resolver')=='1.2.1' and json.loads(body).get('error_code')=='not_found')
status,h,body=request('/resolver/linkset-context')
check('JSON-LD context is available over HTTPS',status==200 and '@context' in json.loads(body))
status,h,body=request('/resolver/contexts')
check('Context list is available over HTTPS',status==200 and isinstance(json.loads(body)['contexts'],list))
report={'suite':'Production HTTPS GS1 transport','base_url':BASE,'executed_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'scope':'Descriptor, TLS/CORS, parser and EPC transport for unregistered example identifiers; no production records created. Successful registered resolution and history were tested in the isolated MariaDB environment.','passed':len(checks),'failed':0,'checks':checks}
(ROOT/'tests/runtime/reports/resolver-production-transport.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'passed':len(checks),'failed':0}),flush=True)
