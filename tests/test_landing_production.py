"""Read-only production checks for marketing navigation and technical SEO."""
import datetime
import hashlib
import json
import re
import urllib.error
import urllib.request
import xml.etree.ElementTree as ET
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
BASE='https://openaliment.ro'
checks=[]
def check(name,passed):
    checks.append({'name':name,'passed':bool(passed)})
    if not passed:raise AssertionError(name)
def request(path,method='GET'):
    try:r=urllib.request.urlopen(urllib.request.Request(BASE+path,method=method),timeout=25)
    except urllib.error.HTTPError as error:r=error
    return r.status,r.headers,r.read()
status,h,body=request('/?utm_source=verification');html=body.decode()
check('Romanian commercial landing served',status==200 and '<html lang="ro">' in html and 'Integrare ERP' in html and 'Resolverul' in html)
check('Unique H1 and relevant title',len(re.findall('<h1[ >]',html))==1 and 'Alternativă GS1 Digital Link, QR și API | OpenAliment</title>' in html)
check('Canonical drops tracking and uses configured HTTPS authority','rel="canonical" href="https://openaliment.ro/"' in html)
check('Production home is indexable','name="robots" content="index, follow"' in html and 'noindex' not in h.get('X-Robots-Tag',''))
check('Anonymous landing creates no session','Set-Cookie' not in h)
graph=json.loads(re.search(r'<script type="application/ld\+json">(.*?)</script>',html,re.S)[1])
check('Structured data describes truthful service and provider',graph['@context']=='https://schema.org' and {n['@type'] for n in graph['@graph']}=={'Organization','WebSite','Service'})
check('Open Graph image has fixed dimensions','property="og:image:width" content="1200"' in html and 'twitter:card' in html)
check('Commercial contact uses configured public address','mailto:alex@patrut.com?subject=' in html)
ids=set(re.findall(r'\bid="([^"]+)"',html))
anchors=[x for x in re.findall(r'href="([^"]+)"',html) if x.startswith(('#','/#'))]
check('All landing anchor destinations exist',all(x.split('#',1)[1] in ids for x in anchors))
for path in ['/gs1-digital-link','/despre','/produse','/api/docs','/resolver/profile','/confidentialitate']:
    status,h,b=request(path);text=b.decode()
    check('Public landing destination '+path,status==200 and 'name="robots" content="index, follow"' in text)
    check('Unique canonical '+path,'rel="canonical" href="'+BASE+path+'"' in text)
status,h,b=request('/login')
check('Login remains noindex with anonymous form',status==200 and 'noindex' in h.get('X-Robots-Tag','') and 'name="password"' in b.decode())
status,h,b=request('/not-an-existing-page')
check('Missing page retains 404 and noindex',status==404 and 'noindex' in h.get('X-Robots-Tag',''))
status,h,b=request('/robots.txt');robots=b.decode()
check('Robots points at sitemap and protects private routes',status==200 and BASE+'/sitemap.xml' in robots and 'Disallow: /manage' in robots and 'Disallow: /assets' not in robots)
status,h,b=request('/sitemap.xml');tree=ET.fromstring(b)
locations=[e.text for e in tree.findall('{*}url/{*}loc')]
check('Sitemap XML lists public pages only',status==200 and BASE+'/' in locations and BASE+'/api/docs' in locations and all('/manage' not in x and '/login' not in x for x in locations))
for path in ['/assets/app.css?v=5','/assets/mark.svg','/assets/openaliment-social.jpg','/assets/digital-link-journey.svg','/assets/digital-link-guide.js?v=1']:
    status,h,b=request(path)
    check('Static asset deployed and cacheable '+path,status==200 and 'max-age=' in h.get('Cache-Control','') and hashlib.sha256(b).digest()==hashlib.sha256((ROOT/'public'/path[1:].split('?')[0]).read_bytes()).digest())
status,h,b=request('/api/openapi.json');api=json.loads(b)
check('Downloadable OpenAPI includes resolver lifecycle',status==200 and all(p in api['paths'] for p in ['/manage/trade-items/{id}/resolver-links','/manage/resolver-links/{id}/publish','/manage/resolver-links/{id}/withdraw']))
status,h,b=request('/','HEAD')
check('HEAD homepage has no response body',status==200 and not b)
report={'suite':'Production landing and SEO','executed_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'passed':len(checks),'failed':0,'checks':checks}
(ROOT/'tests/runtime/reports/landing-production-report.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'passed':len(checks),'failed':0}))
