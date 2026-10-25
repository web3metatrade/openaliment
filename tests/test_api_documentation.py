"""Every portal page and Markdown counterpart; test instance only, no credentials."""
import ast,html,json,os,re,urllib.error,urllib.parse,urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];BASE=os.environ['OA_TEST_BASE_URL']
if urllib.parse.urlsplit(BASE).hostname not in ['127.0.0.1','localhost','::1']:raise RuntimeError('Isolated local host required')
catalog=json.loads((ROOT/'contracts/api-documentation.json').read_text(encoding='utf-8'));checks=[]
for part in (ROOT/'contracts/api-docs').glob('*.json'):
 if not part.name.startswith('postman-'):catalog['pages'].update(json.loads(part.read_text(encoding='utf-8')))

def check(name,ok):
 if not ok:raise AssertionError(name)
 checks.append({'name':name,'passed':True})
def get(path,method='GET',headers=None):
 try:r=urllib.request.urlopen(urllib.request.Request(BASE+path,method=method,headers=headers or {}),timeout=15)
 except urllib.error.HTTPError as error:r=error
 with r:return r.status,r.headers,r.read().decode('utf-8')
for page in catalog['pages'].values():
 status,headers,body=get(page['path']);check('HTML '+page['id'],status==200 and 'data-docs-page="'+page['id']+'"' in body and html.escape(page['title'],quote=True) in body and 'Fatal error' not in body)
 status,headers,markdown=get(page['path']+'.md');check('Markdown '+page['id'],status==200 and headers.get_content_type()=='text/markdown' and '# '+page['title'] in markdown)
 embedded=re.search(r'<template id="api-page-markdown">(.*?)</template>',body,re.S);check('Copy Page matches Markdown '+page['id'],bool(embedded) and html.unescape(embedded[1])==markdown)
 match=re.search(r'href="(https://chatgpt.com/\?q=[^"]+)"',body);prompt=urllib.parse.parse_qs(urllib.parse.urlsplit(html.unescape(match[1])).query)['q'][0] if match else ''
 check('AI page context '+page['id'],page['path']+'.md' in prompt and 'OPENALIMENT_API_KEY' not in prompt and 'oa_session' not in prompt)
 if page['kind']=='operation':
  sample=page['examples'].get('python')
  if sample:ast.parse(sample)
check('Generated Python snippets parse',True)
status,headers,body=get('/api/docs/guides/chei',headers={'Accept':'text/markdown'});check('Content negotiation returns Markdown',status==200 and headers.get_content_type()=='text/markdown' and body.startswith('# '))
status,headers,body=get('/api/docs/guides/chei',headers={'Accept':'text/markdown;q=0, text/html;q=1'});check('Markdown q=0 is not selected',status==200 and headers.get_content_type()=='text/html')
status,headers,body=get('/api/docs/reference/manageRotateAccessToken.md','HEAD');check('Markdown HEAD has no body',status==200 and body=='')
status,headers,body=get('/api/docs/reference/manageRotateAccessToken','OPTIONS',{'Origin':'https://example.invalid'});check('Documentation CORS preflight',status==204 and headers.get('Access-Control-Allow-Origin')=='*')
status,headers,body=get('/api/docs/reference/manageRotateAccessToken','POST');check('Documentation refuses writes',status==405)
status,headers,body=get('/api/docs/reference/missing');check('Unknown operation 404',status==404)
status,headers,body=get('/api/docs/index.json');index=json.loads(body);check('Documentation index complete',status==200 and len(index['pages'])==len(catalog['pages']) and index['operation_count']==catalog['operation_count'])
status,headers,body=get('/llms.txt');check('llms index lists all page variants',status==200 and body.count('.md)')==len(catalog['pages']))
status,headers,body=get('/llms-full.txt');check('Full AI corpus includes all operations',status==200 and all('`'+p['id']+'`' in body for p in catalog['pages'].values() if p['kind']=='operation'))
status,headers,body=get('/api/guide.md');guide=json.loads((ROOT/'contracts/api-developer-guide.json').read_text(encoding='utf-8'))['markdown']
expected=re.sub(r'\]\((/(?!/)[^\s)]+)\)',lambda m:']('+BASE+m[1]+')',guide)
check('Combined Markdown keeps every guide and canonical public links',status==200 and body==expected)
status,headers,body=get('/api/docs/postman.json');collection=json.loads(body);check('Postman collection covers every operation',status==200 and sum(len(g['item']) for g in collection['item'])==catalog['operation_count'] and all(v['value']=='' for v in collection['variable'] if v['key']=='access_token'))
status,headers,body=get('/api/docs/examples/erp-workflow.py');ast.parse(body);check('Executable ERP workflow downloadable',status==200 and 'recipe_version_id' in body and headers.get_content_type()=='text/plain')
report={'passed':len(checks),'failed':0,'checks':checks};(ROOT/'tests/runtime/reports/api-documentation-http.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps({'passed':len(checks),'failed':0}))
