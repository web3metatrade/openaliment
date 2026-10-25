"""Read-only demo HTTP checks and independent decoding of actual served SVGs."""
import datetime, hashlib, json, re, sys, urllib.error, urllib.parse, urllib.request
import xml.etree.ElementTree as ET
import os
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
import zxingcpp, jsonschema
from PIL import Image, ImageDraw
BASE=sys.argv[1] if len(sys.argv)>1 else os.environ.get('OA_TEST_BASE_URL', 'http://127.0.0.1:8088')
G='09520000000011';LOT='DEMO-PBT-01';PAGE='/demo/paine-buna-tehnic'
checks=[]
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl):return None
opener=urllib.request.build_opener(NoRedirect())
def request(path,method='GET',headers=None):
    try:r=opener.open(urllib.request.Request(BASE+path,method=method,headers=headers or {}),timeout=25)
    except urllib.error.HTTPError as e:r=e
    return r.status,r.headers,r.read()
def check(name,value):
    if not value:raise AssertionError(name)
    checks.append({'name':name,'passed':True})
schema=json.loads((ROOT/'contracts/gs1/linkset-schema.json').read_text())
for batch in [False,True]:
    kind='lot' if batch else 'produs';uri='/01/'+G+('/10/'+LOT if batch else '');page=PAGE+('/lot/'+LOT if batch else '')
    status,h,b=request(uri)
    check(kind+' uses real resolver 307',status==307 and h['Location']==BASE+page and h.get('X-OpenAliment-Resolver')=='1.2.1' and h.get('X-OpenAliment-Demo')=='true')
    check(kind+' URI noindex', 'noindex' in h.get('X-Robots-Tag',''))
    status,h,b=request(uri+'?custom=A%2BB&x=%252F')
    check(kind+' exact raw query forwarding',h['Location']==BASE+page+'?custom=A%2BB&x=%252F')
    status,h,b=request(page);html=b.decode()
    check(kind+' public page clearly simulation',status==200 and 'DEMONSTRAȚIE · FĂRĂ VÂNZARE' in html and G in html and 'experimental' in html)
    check(kind+' SEO excludes simulation','noindex' in h.get('X-Robots-Tag','') and 'rel="canonical"' not in html and 'application/ld+json' not in html)
    check(kind+' anonymous page creates no session','Set-Cookie' not in h)
    check(kind+' SVG download linked','download="PBT-DEMO-'+kind+'-QR.svg"' in html)
    status,h,b=request(page+'/date.json');data=json.loads(b)
    check(kind+' machine data retains simulation status',status==200 and data['simulation'] is True and data['commercial_use'] is False and data['lot_code']==(LOT if batch else None) and data['recipe_published'] is False)
    status,h,b=request(uri+'?linkType=gs1:masterData')
    check(kind+' explicit masterData resolution',status==307 and h['Location'].startswith(BASE+page+'/date.json?'))
    status,h,b=request(uri,headers={'Accept':'application/linkset+json'});linkset=json.loads(b)
    jsonschema.validate(linkset,schema)
    check(kind+' linkset uses uncompressed anchor',status==200 and linkset['linkset'][0]['anchor']==BASE+uri)
    check(kind+' linkset includes only truthful resources',set(linkset['linkset'][0])=={'anchor','description','https://ref.gs1.org/voc/pip','https://ref.gs1.org/voc/defaultLink','https://ref.gs1.org/voc/masterData'})
    status,h,b=request(uri+'?linkType=gs1:allergenInfo',headers={'Accept':'application/json'})
    check(kind+' missing allergen evidence returns 404',status==404)
    status,h,b=request('/assets/demo/pbt-'+kind+'-qr.svg')
    check(kind+' actual SVG matches release',status==200 and hashlib.sha256(b).digest()==hashlib.sha256((ROOT/'public/assets/demo'/('pbt-'+kind+'-qr.svg')).read_bytes()).digest())
    root=ET.fromstring(b);size=int(root.attrib['viewBox'].split()[-1]);coords=[]
    for el in root.iter():
        if el.tag.endswith('path'):coords+=re.findall(r'M(\d+),(\d+)h1v1h-1z',el.attrib.get('d',''))
    check(kind+' quiet zone and print dimension',all(4<=int(x)<size-4 and 4<=int(y)<size-4 for x,y in coords) and abs(float(root.attrib['width'][:-2])-size*0.6)<.001)
    for scale in [4,8]:
        img=Image.new('L',(size*scale,size*scale),255);draw=ImageDraw.Draw(img)
        for x,y in coords:
            x=int(x)*scale;y=int(y)*scale;draw.rectangle((x,y,x+scale-1,y+scale-1),fill=0)
        for angle in [0,90]:
            result=zxingcpp.read_barcode(img.rotate(angle))
            check(kind+f' independent SVG decode scale {scale} angle {angle}',bool(result) and result.text=='https://openaliment.ro'+uri)
    status,h,b=request(uri,'HEAD');check(kind+' HEAD has no body',status==307 and not b)
    status,h,b=request(page+'/date.json','HEAD');check(kind+' JSON HEAD has no body',status==200 and not b)
status,h,b=request('/01/'+G+'/10/A%2FB%2B00%25%3F')
check('Unknown lot remains explicitly unknown',status==307 and h['Location']==BASE+PAGE+'/lot-necunoscut')
status,h,b=request(PAGE+'/lot-necunoscut');check('Unknown lot page explains product fallback',status==200 and 'nu este înregistrat' in b.decode())
status,h,b=request('/01/09521234000006');check('Other GS1 demo identifiers are not registered',status==404)
status,h,b=request('/01/01111111111111');check('Invalid repeated digit identifier remains invalid',status==400)
status,h,b=request(PAGE+'/etichete');check('Printable sheet contains two real symbols',status==200 and b.count(b'class="demo-print-qr"')==2 and '100%' in b.decode() and 'FĂRĂ VÂNZARE' in b.decode())
status,h,b=request('/gs1-digital-link');check('Public guide uses actual demo QR instead of placeholder',status==200 and '{GTIN}' not in b.decode() and '/assets/demo/pbt-lot-qr.svg' in b.decode())
status,h,b=request(PAGE+'/lot/OTHER');check('Unregistered demo page not fabricated',status==404)
status,h,b=request(PAGE,'POST');check('Demo has no public write operation',status in [403,405])
report={'suite':'PBT demo HTTP and independent served SVG decoding','base':BASE,'executed_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'decoder':'zxing-cpp','passed':len(checks),'failed':0,'checks':checks}
destination=ROOT/'tests/runtime/reports'/('demo-bread-production-report.json' if BASE.startswith('https:') else 'demo-bread-local-report.json');destination.write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'passed':len(checks),'failed':0,'base':BASE}))
