"""Verify the user-selected illustrative homepage image and transition message."""
import datetime,hashlib,json,re,sys,urllib.request
from io import BytesIO
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
import zxingcpp
from PIL import Image
BASE=sys.argv[1] if len(sys.argv)>1 else 'https://openaliment.ro'
TARGET='https://openaliment.ro/01/09520000000011/10/DEMO-PBT-01'
checks=[]
def check(name,value):
    if not value:raise AssertionError(name)
    checks.append({'name':name,'passed':True})
with urllib.request.urlopen(BASE+'/',timeout=25) as r:
    html=r.read().decode('utf-8');headers=r.headers
check('Homepage replacement is the bread checkout scene','class="hero-checkout"' in html and 'class="food-pack"' not in html and 'class="hero-visual"' not in html)
check('Single H1 explains movement from barcode to QR',len(re.findall('<h1[ >]',html))==1 and 'De la codul de bare' in html and 'la QR-ul' in html)
check('Copy mentions compatible POS and coexistence','case de marcat compatibile' in html and 'EAN-ul și QR-ul pot coexista' in html)
check('Selected image explicitly presented as a concept','Concept de ambalaj · Imagine ilustrativă' in html)
check('Responsive hero is prioritized and reserves image space','srcset="/assets/pbt-checkout-selected-768.jpg 768w, /assets/pbt-checkout-selected-1200.jpg 1200w"' in html and 'width="1200" height="800" fetchpriority="high"' in html)
check('Scene links to real demo resolver',html.count('href="'+TARGET+'"')>=3)
check('Existing commercial title and integration remain','Alternativă GS1 Digital Link, QR și API | OpenAliment</title>' in html and 'id="integrare-erp"' in html)
check('Anonymous homepage does not create session','Set-Cookie' not in headers)
photo_qr=[]
for width in [768,1200]:
    path=f'/assets/pbt-checkout-selected-{width}.jpg'
    with urllib.request.urlopen(BASE+path,timeout=25) as r:
        body=r.read();h=r.headers
    check(str(width)+' image matches release',hashlib.sha256(body).digest()==hashlib.sha256((ROOT/'public'/path[1:]).read_bytes()).digest())
    check(str(width)+' asset is cacheable and lightweight','max-age=' in h.get('Cache-Control','') and len(body)<220000)
    img=Image.open(BytesIO(body))
    check(str(width)+' dimensions',img.size==(width,int(width*2/3)))
    photo_qr.append({'width':width,'decoded':[x.text for x in zxingcpp.read_barcodes(img)]})
check('Photograph is linked to actual separately hosted demo QR','>QR demonstrativ ' in html and '>Vezi exemplul ' in html)
report={'suite':'User-selected homepage concept image','base':BASE,'executed_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'passed':len(checks),'failed':0,'checks':checks,'photo_qr_assessment':photo_qr,'note':'The selected photograph is illustrative; functional QR assets are verified by test_demo_bread.py.'}
(ROOT/'tests/runtime/reports/selected-checkout-production-report.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'passed':len(checks),'failed':0}))
