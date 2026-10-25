"""Bounded public HTTPS checks for the editorial release; no credentials or writes."""
import html,json,re,urllib.request
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
BASE='https://openaliment.ro'
checks=[]
def check(name,ok):
 if not ok:raise AssertionError(name)
 checks.append(name)
def get(path):
 request=urllib.request.Request(BASE+path,headers={'User-Agent':'OpenAliment-Public-Content-QA/0.5.4'})
 with urllib.request.urlopen(request,timeout=30) as response:
  data=response.read(12000001)
  check('HTTPS '+path,response.status==200 and len(data)<=12000000)
  return data.decode('utf-8')
pages={}
for path in (ROOT/'contracts/api-docs').glob('*.json'):
 if not path.name.startswith('postman-'):pages.update(json.loads(path.read_text(encoding='utf-8')))
corpus=get('/llms-full.txt')
for page in pages.values():
 expected=re.sub(r'\]\((/(?!/)[^\s)]+)\)',lambda m:']('+BASE+m[1]+')',page['markdown'])
 check('Complete published Markdown '+page['id'],expected in corpus)
for slug in ['flux-erp','acasa','conectoare','compatibilitate','testare']:
 body=get('/api/docs/guides/'+slug)
 template=re.search(r'<template id="api-page-markdown">(.*?)</template>',body,re.S)
 check('HTML embeds current canonical guide '+slug,bool(template) and html.unescape(template[1]) in corpus)
 check('Public guide contains no internal setup notes '+slug,not any(s in body for s in ['docs/development/README.md','tests/config.example.php','004_home.sql','Acest increment','etapă ulterioară']))
complete=get('/api/guide.md')
source=json.loads((ROOT/'contracts/api-developer-guide.json').read_text(encoding='utf-8'))['markdown']
expected=re.sub(r'\]\((/(?!/)[^\s)]+)\)',lambda m:']('+BASE+m[1]+')',source)
check('Combined guide matches maintained public content',complete==expected)
home=get('/')
check('Landing uses the actual demonstration identifier','openaliment.ro/01/09520000000011/10/DEMO-PBT-01' in home and '{GTIN}' not in home and '{LOT}' not in home)
check('Landing describes the published commercial offer','Conectează-le cu clienții.' in home)
check('Demonstration remains marked as illustrative','Concept de ambalaj' in home)
report={'environment':'production HTTPS read-only; no accounts or data changes','pages_in_corpus':len(pages),'passed':len(checks),'failed':0,'checks':checks}
(ROOT/'tests/runtime/reports').mkdir(parents=True,exist_ok=True)
(ROOT/'tests/runtime/reports/public-content-production.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(json.dumps({k:v for k,v in report.items() if k not in ['checks','environment']}))
