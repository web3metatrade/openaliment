"""HTTP resolver conformance matrix, official schemas, independent EPC encodings."""
import base64
import hashlib
import http.client
import json
import math
import urllib.error
import urllib.parse
import urllib.request
import uuid
import sys
import os
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
import jsonschema
BASE=os.environ.get('OA_TEST_BASE_URL', 'http://127.0.0.1:8088')
fixture=json.loads((ROOT/'tests/runtime/http-fixtures.json').read_text())
epc_fixture=json.loads((ROOT/'tests/runtime/resolver-fixtures.json').read_text())
checks=[]

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl):
        return None

opener=urllib.request.build_opener(NoRedirect())

def request(path,method='GET',headers=None,data=None):
    h=dict(headers or {})
    body=None
    if data is not None:
        body=json.dumps(data).encode();h.update({'Content-Type':'application/json','Authorization':'Bearer '+fixture['token'],'Idempotency-Key':uuid.uuid4().hex})
    req=urllib.request.Request(BASE+path,body,h,method=method)
    try:r=opener.open(req,timeout=20)
    except urllib.error.HTTPError as error:r=error
    return r.status,r.headers,r.read()

def check(name,passed):
    checks.append({'name':name,'passed':bool(passed)})
    if not passed:raise AssertionError(name)
    print('PASS',name,flush=True)

g='09506000134352';path='/01/'+g
status,headers,body=request('/.well-known/gs1resolver')
descriptor=json.loads(body)
jsonschema.Draft7Validator(json.loads((ROOT/'contracts/gs1/description-schema.json').read_text()),format_checker=jsonschema.FormatChecker()).validate(descriptor)
check('Descriptor validates against official GS1 schema',status==200 and descriptor['supportedPrimaryKeys']==['01'])
check('Descriptor and public response expose permissive read-only CORS',headers['Access-Control-Allow-Origin']=='*' and 'Access-Control-Allow-Credentials' not in headers)
check('Context is locally served JSON-LD',request('/resolver/linkset-context')[1]['Content-Type'].startswith('application/ld+json'))
for route in [path,'/.well-known/gs1resolver','/eh30164596f40c0e5cbe991a83']:
    status,headers,body=request(route,'OPTIONS',{'Origin':'https://example.net','Access-Control-Request-Method':'GET'})
    check('OPTIONS and CORS preflight '+route,status==204 and not body and all(m in headers['Access-Control-Allow-Methods'] for m in ['GET','HEAD','OPTIONS']))
status,headers,body=request(path)
check('Default GET redirects once to public product page',status==307 and headers['Location']==BASE+'/t/'+fixture['item_id'])
status,headers,body=request(path,'HEAD')
check('HEAD preserves redirect without response body',status==307 and not body and '/t/' in headers['Location'])
for route,expected in [(path+'/21/serial-unknown',307),(path+'/22/variant/10/unknown/21/serial',307),(path+'/235/tpx',307),(path+'/',307),('/01/09506000134353',400),('/01/'+g+'/21/S/10/L',400),('/01/'+g+'/10/L/235/T',400),('/01/09521234000006',404)]:
    status,headers,body=request(route)
    check('Identifier status '+route,status==expected and headers['Access-Control-Allow-Origin']=='*')
status,headers,body=request(path,'PUT')
check('Unsupported mutation returns 405 with Allow',status==405 and 'OPTIONS' in headers['Allow'])
schema=json.loads((ROOT/'contracts/gs1/linkset-schema.json').read_text())
for route,h in [(path+'?linkType=linkset',{'Accept':'application/json'}),(path,{'Accept':'application/linkset+json'}),(path+'?linkType=all',{'Accept':'application/json'})]:
    status,headers,body=request(route,headers=h);result=json.loads(body);jsonschema.validate(result,schema)
    check('Official linkset schema '+route,status==200 and result['linkset'][0]['anchor']==BASE+path)
    check('JSON-LD context header supplied '+route,'json-ld#context' in headers['Link'])
    check('Private recipe and tenant identifiers excluded '+route,all(marker not in body.decode() for marker in ['SECRET_RECIPE_A','tenant_id','password_hash']))
status,_,body=request(path+'?linkType=linkset',headers={'Accept':'text/html'})
check('Human-readable linkset includes hyperlinks and JSON-LD',status==200 and b'<a ' in body and b'application/ld+json' in body)
status,_,body=request(path+'?linkType=linkset',headers={'Accept':'application/ld+json'})
check('Explicit JSON-LD embeds context',status==200 and '@context' in json.loads(body))
status,headers,body=request(path+'?linkType=linkset',headers={'Accept':'application/json;q=0.2,application/ld+json;q=0.9'})
check('Linkset representation honors media preference quality',status==200 and headers['Content-Type'].startswith('application/ld+json'))
status,_,body=request(path+'?linkType=linkset','HEAD',{'Accept':'application/linkset+json'})
check('HEAD linkset has no body',status==200 and not body)
check('Unavailable requested relation returns 404',request(path+'?linkType=gs1:notAvailable')[0]==404)
check('q=0 linkset preference does not force linkset',request(path,headers={'Accept':'application/linkset+json;q=0,text/html'})[0]==307)
lot=urllib.parse.quote('A/B+00%?',safe='')
status,headers,_=request(path+'/10/'+lot)
check('Lot redirect retains exact historical batch',status==307 and headers['Location']==BASE+'/b/'+fixture['batch_id'])
query='17=261101&custom=A%2BB&repeated=1&repeated=2'
check('All query values and duplicate extension keys are forwarded exactly',request(path+'?'+query)[1]['Location'].endswith('?'+query))
status,_,body=request(path+'/10/never-published?linkType=linkset',headers={'Accept':'application/json'})
check('Unknown lot inherits product links without selecting another batch',status==200 and not any('/b/' in entry['href'] for key,entries in json.loads(body)['linkset'][0].items() if key.startswith('http') for entry in entries))
token=fixture['token'];item=fixture['item_id']
payload={'href':'https://example.com/data?existing=1#info','title':'API controlled destination','types':['gs1:traceability'],'qualifiers':{'21':'ERP-SERIAL'}}
status,_,body=request('/api/v1/manage/trade-items/'+item+'/resolver-links','POST',data=payload);draft=json.loads(body)
check('ERP API creates a private resolver draft',status==201 and draft['state']=='draft')
route=path+'/21/ERP-SERIAL?linkType=gs1:traceability'
check('Draft cannot influence public resolution',request(route)[0]==404)
status,_,body=request('/api/v1/manage/resolver-links/'+draft['id']+'/publish','POST',data={'public_projection_confirmed':True})
check('ERP API approves resolver destination separately',status==201 and json.loads(body)['state']=='published')
status,headers,_=request(route+'&17=261101')
check('Specific serial relation resolves with target query and fragment intact',status==307 and headers['Location']=='https://example.com/data?existing=1&linkType=gs1:traceability&17=261101#info')
other_headers={'Authorization':'Bearer '+fixture['read_token']}
check('Other tenant cannot read owner resolver history',request('/api/v1/manage/trade-items/'+item+'/resolver-links',headers=other_headers)[0]==404)
status,_,body=request('/api/v1/manage/resolver-links/'+draft['id']+'/withdraw','POST',data={'reason':'Isolated HTTP test complete'})
check('ERP API withdrawal preserves history and removes active destination',status==201 and request(route)[0]==404)

def bits(n,width):return format(n,'0'+str(width)+'b')
def bcd(value):return ''.join(bits(int(c),4) for c in value)
def compact(value,encoding):
    alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_'
    if encoding==0:payload=bits(int(value),math.ceil(len(value)*math.log2(10)))
    elif encoding in (1,2):payload=''.join(bits(int(c,16),4) for c in value)
    elif encoding==3:payload=''.join(bits(alphabet.index(c),6) for c in value)
    elif encoding==4:payload=''.join(bits(ord(c),7) for c in value)
    else:
        abc=' ABCDEFGHIJKLMNOPQRSTUVWXYZ-.:0123456789';padded=value+' '*((-len(value))%3)
        payload=''.join(bits(1600*abc.index(padded[i])+40*abc.index(padded[i+1])+abc.index(padded[i+2])+1,16) for i in range(0,len(padded),3))
    return bits(encoding,3)+bits(len(value),5)+payload
def compressed(payload,form):
    if form=='eh':return 'eh'+format(int(payload+'0'*((-len(payload))%4),2),'0'+str(math.ceil(len(payload)/4))+'x')
    abc='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_'; padded=payload+'0'*((-len(payload))%6)
    return 'ex'+''.join(abc[int(padded[i:i+6],2)] for i in range(0,len(padded),6))

gtin=epc_fixture['epc_gtin']; vectors=[]
for partition,(cb,cl,ib,il) in enumerate([(40,12,4,1),(37,11,7,2),(34,10,10,3),(30,9,14,4),(27,8,17,5),(24,7,20,6),(20,6,24,7)]):
    prefix=gtin[1:1+cl];ref=gtin[0]+gtin[1+cl:13]
    core=bits(0x30,8)+'000'+bits(partition,3)+bits(int(prefix),cb)+bits(int(ref),ib)+bits(123456789123,38)
    vectors.append(('SGTIN-96 partition '+str(partition),core,'123456789123'))
serial='ABC/def+%'
prefix=gtin[1:8];ref=gtin[0]+gtin[8:13]
vectors.append(('SGTIN-198',bits(0x36,8)+'000101'+bits(int(prefix),24)+bits(int(ref),20)+''.join(bits(ord(c),7) for c in serial)+'0'*(7*(20-len(serial))),serial))
for enc,value in [(0,'000123456789'),(1,'ABCDEF123'),(2,'abcdef123'),(3,'a-B_012'),(4,'A/B+%"'),(5,'ABC-123')]:
    vectors.append(('SGTIN+ encoding '+str(enc),bits(0xF7,8)+'0000'+bcd(gtin)+compact(value,enc),value))
date=bits(26,7)+bits(10,4)+bits(23,5)
vectors.append(('DSGTIN+',bits(0xFB,8)+'0000'+'0100'+date+bcd(gtin)+compact('SERIAL',4),'SERIAL'))
host='openaliment.ro';host_plain='1'+bits(len(host),6)+''.join(bits(ord(c),7) for c in host)
vectors.append(('SGTIN++ ASCII hostname',bits(0xFD,8)+'0000'+bcd(gtin)+compact('00123',0)+host_plain,'00123'))
optimized='id.example.com';host_opt='1'+bits(9,6)+'0100000'+''.join(bits(ord(c),7) for c in 'example')+'1011011'
vectors.append(('SGTIN++ optimized hostname',bits(0xFD,8)+'0000'+bcd(gtin)+compact('HOST',4)+host_opt,'HOST'))
host_upper='EXAMPLE.COM';abc=' ABCDEFGHIJKLMNOPQRSTUVWXYZ-.:0123456789';padded=host_upper+' '*((-len(host_upper))%3)
host40='0'+bits(len(host_upper),6)+''.join(bits(1600*abc.index(padded[i])+40*abc.index(padded[i+1])+abc.index(padded[i+2])+1,16) for i in range(0,len(padded),3))
vectors.append(('DSGTIN++ Code40 hostname',bits(0xFC,8)+'0000'+'0100'+date+bcd(gtin)+compact('HOST40',4)+host40,'HOST40'))
vectors.append(('SGTIN+ plus data',bits(0xF7,8)+'1000'+bcd(gtin)+compact('PLUS',4)+bcd('10')+compact('LOT-1',4)+bcd('17')+date,'PLUS'))
zero_day=bits(26,7)+bits(10,4)+bits(0,5)
vectors.append(('DSGTIN+ unspecified day',bits(0xFB,8)+'0000'+'0100'+zero_day+bcd(gtin)+compact('DATE0',4),'DATE0'))
extra_cases=[
 ('fixed numeric','3103',bits(12345,20)),
 ('fixed numeric long','4309',bits(12345678901234567890,67)),
 ('fixed BCD','7241',bcd('01')),
 ('delimited numeric','8004',bcd('12345678')+'1111'),
 ('mixed numeric alphanumeric','8004',bcd('123')+'1110'+compact('AB',4)),
 ('single bit','4321','1'),
 ('date','17',zero_day),
 ('date time midnight','7003',date+bits(24,5)+bits(0,6)),
 ('date range','7007','1'+date+zero_day),
 ('variable precision date','7011','11'+date),
 ('variable precision hour','8008','00'+date+bits(24,5)),
 ('variable precision minute','8008','01'+date+bits(12,5)+bits(45,6)),
 ('variable precision seconds','8008','10'+date+bits(12,5)+bits(45,6)+bits(59,6)),
 ('country','4307',bits(17,6)+bits(14,6)),
 ('variable numeric','30',bits(8,4)+bits(12345678,math.ceil(8*math.log2(10)))),
 ('optional negative sign','4330',bits(12345,20)+'1'),
 ('sequence indicator','7258',bits(1,4)+bits(9,4)),
]
for name,ai,data in extra_cases:vectors.append(('SGTIN+ attribute '+name,bits(0xF7,8)+'1000'+bcd(gtin)+compact('ATTR',4)+bcd(ai)+data,'ATTR'))
(ROOT/'tests/runtime/reports/epc-vectors.json').write_text(json.dumps([{'name':name,'path':'/'+compressed(payload,form),'gtin':gtin,'serial':serial} for name,payload,serial in vectors for form in ['eh','ex']],indent=2),encoding='utf-8')
for name,payload,serial in vectors:
    for form in ['eh','ex']:
        route='/'+compressed(payload,form)+'?linkType=linkset'
        status,_,body=request(route,headers={'Accept':'application/linkset+json'})
        if status!=200:raise AssertionError((name,form,status,body.decode()))
        parsed=json.loads(body);jsonschema.validate(parsed,schema)
        check(name+' '+form+' resolves to decompressed identity',('/01/'+gtin) in parsed['linkset'][0]['anchor'] and ('/21/'+urllib.parse.quote(serial,safe='')) in parsed['linkset'][0]['anchor'])
        if 'optimized hostname' in name:check('Custom EPC hostname retained in uncompressed anchor '+form,parsed['linkset'][0]['anchor'].startswith('https://id.example.com/'))

report={'suite':'GS1 resolver HTTP conformance','standard':'GS1-Conformant Resolver 1.2.1; GTIN profile; EPC TDS 2.3','passed':len(checks),'failed':0,'checks':checks}
(ROOT/'tests/runtime/reports/resolver-http-report.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'passed':len(checks),'failed':0}))
