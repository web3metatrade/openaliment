"""Build a public documentation portal from the maintained guide and actual OpenAPI."""
import html,json,os,re,shlex
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
OUT=Path(os.environ.get('OA_CONTRACTS_OUT',ROOT/'contracts'));BASE='https://openaliment.ro'
spec=json.loads((OUT/'platform-openapi.json').read_text(encoding='utf-8'))
spec_source_sha256=__import__('hashlib').sha256(json.dumps(spec,sort_keys=True,ensure_ascii=False,separators=(',',':')).encode()).hexdigest()
for methods in spec['paths'].values():
 for operation in methods.values():
  operation['responses']={code:spec['components']['responses'][response['$ref'].rsplit('/',1)[-1]] if response.get('$ref','').startswith('#/components/responses/') else response for code,response in operation['responses'].items()}
guide=(ROOT/'docs/api/DEVELOPER_GUIDE.md').read_text(encoding='utf-8')
LINKS={'../../contracts/platform-openapi.json':'/api/platform-openapi.json','../../tools/sdk/openaliment.py':'/api/sdk/python','../../tools/sdk/openaliment.mjs':'/api/sdk/javascript','../../tools/sdk/OpenAlimentClient.kt':'/api/sdk/kotlin','../INTEGRARI_PARTENERI.md':'/api/docs/guides/conectoare','../IMPORT_EXPORT.md':'/api/docs/guides/conectoare'}
GUIDES=[('acces','Acces și permisiuni'),('api-public','Citire publică'),('flux-erp','Primul produs și lot'),('alerte','Alerte și retrageri'),('documente-feedback','Documente și feedback'),('chei','Chei, rotație și revocare'),('oauth','OAuth pentru aplicații'),('acasa','Acasă și sincronizare'),('conectoare','Conectoare, export și import'),('limite','Limite și erori'),('ai','Integrare cu AI'),('compatibilitate','Compatibilitate și publicare')]
EXTRA_GUIDES=[('inregistrare-anaf','Înregistrare cu CUI și profil fiscal ANAF'),('surse-externe','Open Food Facts și proveniența datelor'),('modele-date','Modele de date și reguli de integrare'),('erori-recuperare','Erori, reîncercări și depanare'),('exemple-executabile','Integrare completă și exemple executabile'),('testare','Testarea integrării și mediile de lucru'),('protectia-contului','Protecția contului și confirmări sensibile'),('marketplace','Marketplace, serializare și linia de producție')]
GROUPS={'external':'Surse externe și Open Food Facts','marketplace':'Marketplace și echipamente','public':'API public','products':'Produse și articole','labels':'Versiuni de etichetă','batches':'Loturi și alerte','documents':'Documente și rețete','resolver':'Resolver GS1 Digital Link','community':'Feedback, propuneri și voturi','connectors':'Conectoare și bonuri','transfer':'Import și export','home':'Acasă și familii','keys':'Chei API','oauth':'OAuth și autorizări','identity':'Cont și organizații','ai':'Joburi AI','admin':'Administrarea platformei','discovery':'Manifest, scheme și SDK','legacy':'Compatibilitate API v1'}
def inline(text):
 text=html.escape(text)
 def link(m):
  target=LINKS.get(html.unescape(m[2]),html.unescape(m[2]));return '<a href="'+html.escape(target,quote=True)+'">'+m[1]+'</a>' if target.startswith(('https://','#')) or target.startswith('/') and not target.startswith('//') else m[1]
 text=re.sub(r'\[([^\]]+)\]\(([^)]+)\)',link,text)
 return re.sub(r'\*\*([^*]+)\*\*',r'<strong>\1</strong>',re.sub(r'`([^`]+)`',r'<code>\1</code>',text))
def render(markdown):
 lines=markdown.splitlines();out=[];toc=[];i=0
 while i<len(lines):
  line=lines[i];i+=1
  if not line.strip() or line.startswith('# '):continue
  if line.startswith('```'):
   code=[];lang=line[3:]
   while i<len(lines) and not lines[i].startswith('```'):code.append(lines[i]);i+=1
   i+=1;out.append('<pre class="api-code" data-copy-code><code>'+html.escape('\n'.join(code))+'</code></pre>');continue
  if line.startswith(('## ','### ')):
   level=3 if line.startswith('### ') else 2;title=line[level+1:];anchor='section-'+str(len(toc)+1);toc.append({'id':anchor,'title':title,'level':level});out.append(f'<h{level} id="{anchor}">'+inline(title)+f'</h{level}>');continue
  if line.startswith('|'):
   rows=[line]
   while i<len(lines) and lines[i].startswith('|'):rows.append(lines[i]);i+=1
   out.append('<div class="api-table-wrap"><table><thead><tr>'+''.join('<th>'+inline(x.strip())+'</th>' for x in rows[0].strip('|').split('|'))+'</tr></thead><tbody>'+''.join('<tr>'+''.join('<td>'+inline(x.strip())+'</td>' for x in row.strip('|').split('|'))+'</tr>' for row in rows[2:])+'</tbody></table></div>');continue
  if line.startswith('- ') or re.match(r'^\d+\. ',line):
   ordered=not line.startswith('- ');pattern=r'^\d+\. ' if ordered else r'^- ';items=[re.sub(pattern,'',line)]
   while i<len(lines) and re.match(pattern,lines[i]):items.append(re.sub(pattern,'',lines[i]));i+=1
   tag='ol' if ordered else 'ul';out.append('<'+tag+'>'+''.join('<li>'+inline(x)+'</li>' for x in items)+'</'+tag+'>');continue
  paragraph=[line]
  while i<len(lines) and lines[i].strip() and not lines[i].startswith(('#','```','|','- ')) and not re.match(r'^\d+\. ',lines[i]):paragraph.append(lines[i]);i+=1
  out.append('<p>'+inline(' '.join(paragraph))+'</p>')
 return '\n'.join(out),toc
def group(path):
 if any(x in path for x in ['/sources','/external-revisions/','/external-assets/','/source-conflicts','/food/resolve','/food/lookups']):return 'external'
 if '/marketplace/' in path or '/printing/' in path or '/printing-agent/' in path:return 'marketplace'
 if path.startswith('/api/v1/'):return 'legacy'
 if path.startswith('/api/v2/public/'):return 'public'
 if path.startswith('/api/v2/home/'):return 'home'
 if '/access-tokens' in path or '/auth/token' in path:return 'keys'
 if path.startswith(('/oauth/','/api/v2/developer/','/.well-known/oauth')) or '/identity/grants' in path:return 'oauth'
 if '/identity/' in path or '/organization/legal-profile' in path:return 'identity'
 if '/admin/' in path:return 'admin'
 if '/community/' in path or re.search('/(?:feedback|proposals)(?:/|$)',path):return 'community'
 if '/connectors' in path:return 'connectors'
 if re.search('/(?:export|imports?|import-pbt)(?:/|$)',path):return 'transfer'
 if '/ai' in path:return 'ai'
 if '/resolver' in path or path.startswith(('/01/','/.well-known/gs1','/api/v2/tools/')):return 'resolver'
 if re.search('/(?:documents|recipes|recipe-versions)(?:/|$)',path):return 'documents'
 if re.search('/(?:batches|alerts)(?:/|$)',path) or '/qr/batch/' in path:return 'batches'
 if '/label' in path:return 'labels'
 if '/manage/' in path:return 'products'
 return 'discovery'
def resolve(schema):
 ref=schema.get('$ref','');return spec['components']['schemas'].get(ref.rsplit('/',1)[-1],schema) if ref.startswith('#/components/schemas/') else schema
def schema_text(schema):return json.dumps(resolve(schema),ensure_ascii=False,indent=2)
def field_rows(schema,prefix='',depth=0):
 schema=resolve(schema)
 branches=schema.get('oneOf',schema.get('anyOf'))
 if branches:
  merged=[]
  for branch in branches:
   if branch.get('type')!='null':merged+=field_rows(branch,prefix,depth)
  return list({row['name']:row for row in merged}.values())
 rows=[]
 for name,field in schema.get('properties',{}).items():
  value=resolve(field);variants=value.get('anyOf',value.get('oneOf',[]));nonnull=[resolve(x) for x in variants if x.get('type')!='null'];base=nonnull[0] if nonnull else value
  kind=base.get('type','valoare fixă' if 'const' in base else 'variantă' if 'oneOf' in base else 'obiect');kind=' / '.join(kind) if isinstance(kind,list) else kind
  if any(x.get('type')=='null' for x in variants):kind+=' / null'
  rules=[]
  for key,label in [('minimum','minim'),('maximum','maxim'),('exclusiveMinimum','mai mare decât'),('minLength','lungime minimă'),('maxLength','lungime maximă'),('minItems','elemente minim'),('maxItems','elemente maxim'),('format','format'),('pattern','format regex')]:
   if key in base:rules.append(label+': '+str(base[key]))
  if 'enum' in base:rules.append('valori: '+', '.join(str(x) for x in base['enum']))
  if 'const' in base:rules.append('valoare: '+json.dumps(base['const'],ensure_ascii=False))
  if 'default' in base:rules.append('implicit: '+json.dumps(base['default'],ensure_ascii=False))
  rows.append({'name':prefix+name,'type':kind,'required':name in schema.get('required',[]),'description':field.get('description',base.get('description','')),'rules':'; '.join(rules)})
  if depth<3:
   if base.get('type')=='array':rows+=field_rows(base.get('items',{}),prefix+name+'[].',depth+1)
   else:rows+=field_rows(base,prefix+name+'.',depth+1)
 return rows
def fields_markdown(schema):
 rows=field_rows(schema)
 if not rows:return ''
 out='| Câmp | Tip | Obligatoriu | Semnificație și limite |\n| --- | --- | --- | --- |\n'
 for r in rows:
  detail=(r['description']+' '+r['rules']).replace('|',' / ').replace('\n',' ')
  out+='| `'+r['name']+'` | '+r['type']+' | '+('Da' if r['required'] else 'Nu / după variantă')+' | '+detail+' |\n'
 return out+'\n'
def examples(path,method,op):
 from urllib.parse import quote,urlencode
 target=path
 for param in op.get('parameters',[]):
  if param['in']=='path':target=target.replace('{'+param['name']+'}',quote(str(param.get('example','a'*32)),safe=''))
 query={p['name']:p['example'] for p in op.get('parameters',[]) if p['in']=='query' and p.get('required') and 'example' in p}
 if path.startswith('/01/'):query['linkType']='linkset'
 if query:target+='?'+urlencode(query)
 mime,body=next(iter(op.get('requestBody',{}).get('content',{}).items()),(None,None));payload=body.get('example') if body else None
 auth=op.get('x-auth-context','');cookie=auth.endswith('session') or auth in ['session','owner-session','admin-session','session-optional'] and not path.startswith('/api/v2/account/password/');home_cookie=auth=='home' and not any('HomeOAuth' in s for s in op.get('security',[]));cookie=cookie or home_cookie;secured=bool(op.get('security')) and not cookie
 success=next((r for status,r in op['responses'].items() if status.startswith('2')),{});response_mime=next(iter(success.get('content',{})),'text/plain');json_response='json' in response_mime
 headers={'Accept':response_mime}
 if secured:headers['Authorization']='Bearer YOUR_ACCESS_TOKEN'
 if cookie and method not in ['get','head']:headers['X-Home-CSRF' if home_cookie else 'X-CSRF-Token']='CSRF_FROM_YOUR_OWN_SESSION'
 for p in op.get('parameters',[]):
  if p['in']=='header' and p.get('required') and p['name'] not in headers:headers[p['name']]=str(p.get('example','VALUE_FROM_YOUR_OWN_ACCOUNT'))
 if any(p['name']=='Idempotency-Key' for p in op.get('parameters',[])):headers['Idempotency-Key']='exemplu-'+op['operationId']+'-001'
 curl=['curl --fail-with-body -X '+method.upper()+' '+shlex.quote(BASE+target)]
 for k,v in headers.items():curl.append('-H '+shlex.quote(k+': '+v))
 if cookie:curl+=['--cookie "$OPENALIMENT_COOKIE_FILE"','--cookie-jar "$OPENALIMENT_COOKIE_FILE"']
 if mime=='multipart/form-data':curl+=['-F '+shlex.quote('title=Fișă produs'),'-F '+shlex.quote('document=@fisa.pdf;type=application/pdf')]
 elif body:
  headers['Content-Type']=mime;curl+=['-H '+shlex.quote('Content-Type: '+mime)]
  if mime=='application/x-www-form-urlencoded' and payload is not None:
   curl += ['--data-urlencode '+shlex.quote(k+'='+str(v)) for k,v in payload.items()]
  elif mime=='image/jpeg':curl.append('--data-binary @fotografie.jpg')
  elif mime=='application/x-ndjson':curl.append('--data-binary '+shlex.quote(payload))
  elif payload is not None:curl.append('--data '+shlex.quote(json.dumps(payload,ensure_ascii=False,separators=(',',':'))))
  else:curl.append('--data @cerere.json')
 js="const response = await fetch("+json.dumps(BASE+target)+", {\n  method: "+json.dumps(method.upper())+",\n  credentials: "+json.dumps('same-origin' if cookie else 'omit')+",\n  headers: "+json.dumps(headers,ensure_ascii=False,indent=2)
 if body and mime=='image/jpeg':js+=",\n  body: jpegFile // File JPEG selectat de utilizator."
 elif body and mime=='application/x-ndjson':js+=",\n  body: "+json.dumps(payload,ensure_ascii=False)
 elif body and mime=='application/x-www-form-urlencoded' and payload is not None:js+=",\n  body: new URLSearchParams("+json.dumps(payload,ensure_ascii=False,indent=2)+")"
 elif body and payload is not None:js+=",\n  body: JSON.stringify("+json.dumps(payload,ensure_ascii=False,indent=2)+")"
 elif body:js+=",\n  body: requestBody // Corpul cererii conform schemei de mai jos."
 js+="\n});\nconst result = await response."+('json' if json_response else 'blob' if response_mime in ['application/octet-stream','application/zip'] or response_mime.startswith('image/') else 'text')+"();\nif (!response.ok) throw new Error("+('result.message || result.error_description || (\'HTTP \' + response.status)' if json_response else "'HTTP ' + response.status")+");\nconsole.log(result);"
 py="import json, urllib.request, urllib.parse, urllib.error\n\nheaders = "+repr(headers)+"\n"
 if body and mime=='image/jpeg':py+='data = open("fotografie.jpg", "rb").read()\n'
 elif body and mime=='application/x-ndjson':py+='data = '+repr(payload)+'.encode("utf-8")\n'
 elif body and mime=='application/x-www-form-urlencoded' and payload is not None:py+='payload = json.loads('+repr(json.dumps(payload,ensure_ascii=False))+')\ndata = urllib.parse.urlencode(payload).encode("utf-8")\n'
 elif body and payload is not None:py+='payload = json.loads('+repr(json.dumps(payload,ensure_ascii=False))+')\ndata = json.dumps(payload).encode("utf-8")\n'
 elif body:py+='data = open("cerere.json", "rb").read()\n'
 else:py+='data = None\n'
 if cookie:py+='# Operație de sesiune: folosește sesiunea proprie și CSRF în clientul web.\n'
 py+='request = urllib.request.Request('+repr(BASE+target)+', data=data, headers=headers, method='+repr(method.upper())+')\ntry:\n    with urllib.request.urlopen(request, timeout=15) as response:\n        print('+('json.load(response)' if json_response else 'response.read()')+')\nexcept urllib.error.HTTPError as error:\n    print(error.code, error.read().decode("utf-8", errors="replace"))\n    raise'
 if mime=='multipart/form-data':return {'curl':' \\\n  '.join(curl)}
 if cookie:return {'javascript':js,'curl':' \\\n  '.join(curl)}
 return {'curl':' \\\n  '.join(curl),'javascript':js,'python':py}
pages={};navigation=[]
overview=(ROOT/'docs/api/pages/overview.md').read_text(encoding='utf-8')
# Only explicitly maintained public integration content belongs in the portal.
# Internal implementation reports contain deployment instructions and dated plans.
DETAILS={'flux-erp':'resolver','acasa':'sincronizare','conectoare':'transfer-conectoare','compatibilitate':'compatibilitate-v1'}
for slug,title,markdown in [('overview','Prezentare generală',overview)]+[(slug,title,'# '+title+'\n\n'+section.split('\n',1)[1]) for (slug,title),section in zip(GUIDES,re.split(r'^## ',guide,flags=re.M)[1:])]:
 if slug in DETAILS:
  markdown+='\n\n'+(ROOT/'docs/api/sections'/(DETAILS[slug]+'.md')).read_text(encoding='utf-8')
 rendered,toc=render(markdown);path='/api/docs' if slug=='overview' else '/api/docs/guides/'+slug
 pages[slug]={'id':slug,'title':title,'path':path,'kind':'guide','markdown':markdown,'html':rendered,'toc':toc}
 navigation.append({'id':slug,'title':title,'path':path,'kind':'guide','group':'guides','search':title+' '+' '.join(re.findall(r'[^\W\d_]{3,}',markdown))[:6000]})
assert len(re.split(r'^## ',guide,flags=re.M)[1:])==len(GUIDES),'Guide headings changed: update the explicit portal mapping'
for slug,title in EXTRA_GUIDES:
 markdown=(ROOT/'docs/api/pages'/f'{slug}.md').read_text(encoding='utf-8');rendered,toc=render(markdown);path='/api/docs/guides/'+slug
 pages[slug]={'id':slug,'title':title,'path':path,'kind':'guide','markdown':markdown,'html':rendered,'toc':toc}
 navigation.append({'id':slug,'title':title,'path':path,'kind':'guide','group':'guides','search':title+' '+' '.join(re.findall(r'[^\W\d_]{3,}',markdown))[:6000]})
for path,methods in spec['paths'].items():
 for method,op in methods.items():
  oid=op['operationId'];category=group(path);title=op.get('summary') or method.upper()+' '+path;access=op.get('x-auth-context') or ('autorizat' if op.get('security') else 'public / flux de autorizare');samples=examples(path,method,op)
  md='# '+title+'\n\n`'+method.upper()+' '+BASE+path+'`\n\n'+op.get('description','')+'\n\n## Acces\n\nContext: `'+access+'`. '
  if op.get('x-required-scope'):md+='Permisiune necesară: `'+op['x-required-scope']+'`. '
  md+='Operație: `'+oid+'`.\n\n'
  if access.endswith('session') or access in ['session','owner-session','admin-session']:md+='Folosește sesiunea web proprie; o cheie ERP nu înlocuiește sesiunea sau CSRF. Nu trimite cookie-uri, parole ori tokenuri către un asistent AI.\n\n'
  if op.get('parameters'):
   md+='## Parametri\n\n| Nume | Unde | Obligatoriu | Tip / descriere |\n| --- | --- | --- | --- |\n'
   for param in op['parameters']:
    p=resolve(param.get('schema',{}));kind=p.get('type','');kind=', '.join(kind) if isinstance(kind,list) else kind;description=(kind+' · '+param.get('description','')).strip(' ·').replace('|','/').replace('\n',' ')
    md+='| `'+param['name']+'` | '+param['in']+' | '+('Da' if param.get('required') else 'Nu')+' | '+description+' |\n'
   md+='\n'
  for mime,content in op.get('requestBody',{}).get('content',{}).items():
   md+='## Corpul cererii\n\nTip: `'+mime+'`.\n\n'
   if 'example' in content:md+='### Exemplu de cerere\n\n```'+('text' if mime=='application/x-ndjson' else 'json')+'\n'+(content['example'] if mime=='application/x-ndjson' else json.dumps(content['example'],ensure_ascii=False,indent=2))+'\n```\n\n'
   md+='### Câmpuri și reguli\n\n'+fields_markdown(content.get('schema',{}))
   md+='### Schema cererii\n\n```json\n'+schema_text(content.get('schema',{}))+'\n```\n\n'
  md+='## Exemple de integrare\n\nValorile sunt demonstrative. Înlocuiește ID-urile și folosește doar propriile permisiuni.\n\n'
  for lang,code in samples.items():md+='### '+{'curl':'cURL','javascript':'JavaScript','python':'Python'}[lang]+'\n\n```'+('sh' if lang=='curl' else lang)+'\n'+code+'\n```\n\n'
  md+='## Răspunsuri\n\n'
  for status,response in op['responses'].items():
   md+='### '+status+' — '+response.get('description','Răspuns')+'\n\n'
   for mime,content in response.get('content',{}).items():
    md+='`'+mime+'`\n\n'
    if 'example' in content:md+='Exemplu sintetic:\n\n```'+('json' if 'json' in mime and mime!='application/x-ndjson' else 'text')+'\n'+(json.dumps(content['example'],ensure_ascii=False,indent=2) if not isinstance(content['example'],str) else content['example'])+'\n```\n\n'
    for name,example in content.get('examples',{}).items():md+=example.get('summary',name)+'\n\n```json\n'+json.dumps(example['value'],ensure_ascii=False,indent=2)+'\n```\n\n'
    if status.startswith('2'):md+=fields_markdown(content.get('schema',{}))
    md+='Schema:\n\n```json\n'+schema_text(content.get('schema',{}))+'\n```\n\n'
   if response.get('headers'):
    md+='Antete: '+', '.join('`'+name+'`: '+meta.get('description','Conform schemei.') for name,meta in response['headers'].items())+'\n\n'
  md+='## Documentație asociată\n\n- [Contract OpenAPI]('+BASE+'/api/platform-openapi.json)\n- [Acces și permisiuni]('+BASE+'/api/docs/guides/acces)\n- [Limite și erori]('+BASE+'/api/docs/guides/limite)\n'
  pages[oid]={'id':oid,'title':title,'path':'/api/docs/reference/'+oid,'kind':'operation','group':category,'method':method.upper(),'endpoint':path,'markdown':md,'examples':samples,'request_fields':{mime:field_rows(content.get('schema',{})) for mime,content in op.get('requestBody',{}).get('content',{}).items()},'response_fields':{status:{mime:field_rows(content.get('schema',{})) for mime,content in response.get('content',{}).items()} for status,response in op['responses'].items() if status.startswith('2')}}
  nav={k:pages[oid][k] for k in ['id','title','path','kind','group','method','endpoint']}
  nav['search']=(title+' '+op.get('description','')+' '+' '.join(p['name'] for p in op.get('parameters',[]))+' '+' '.join(row['name'] for rows in pages[oid]['request_fields'].values() for row in rows))[:2400]
  navigation.append(nav)
out={'schema_version':'openaliment.api-documentation.v1','guides':len(GUIDES)+len(EXTRA_GUIDES)+1,'operation_count':sum(p['kind']=='operation' for p in pages.values()),'groups':GROUPS,'pages':pages,'navigation':navigation,'legacy_anchors':{'chei':'chei','oauth':'oauth','acasa':'acasa','conectoare':'conectoare','referinta':None},'source_sha256':spec_source_sha256,'downloads':{'erp_workflow':(ROOT/'tools/examples/erp_workflow.py').read_text(encoding='utf-8')}}
parts=OUT/'api-docs';parts.mkdir(exist_ok=True)
grouped={}
for key,page_data in pages.items():grouped.setdefault(page_data.get('group','guides'),{})[key]=page_data
for category,values in grouped.items():(parts/(category+'.json')).write_text(json.dumps(values,ensure_ascii=False,separators=(',',':'))+'\n',encoding='utf-8')
out['pages']={key:{k:v for k,v in page_data.items() if k in ['id','title','path','kind','group','method','endpoint']} for key,page_data in pages.items()}
out['page_storage']='grouped-v1'
(OUT/'api-documentation.json').write_text(json.dumps(out,ensure_ascii=False,separators=(',',':'))+'\n',encoding='utf-8')
postman={'info':{'name':'OpenAliment Platform API','description':str(out['operation_count'])+' operații. Exemplele folosesc date demonstrative. Configurează variabilele în mediul privat al clientului și folosește v2 pentru integrări noi. Scrierile modifică datele contului din mediul selectat.','schema':'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'},'variable':[{'key':'base_url','value':BASE},{'key':'access_token','value':''}],'item':[]}
folders={};variables=set()
for path,methods in spec['paths'].items():
 for method,op in methods.items():
  target=re.sub(r'\{([^}]+)\}',lambda m:'{{'+m[1]+'}}',path)
  for name in re.findall(r'\{([^}]+)\}',path):variables.add(name)
  parameters=op.get('parameters',[]);query=[{'key':p['name'],'value':str(p.get('example','')),'disabled':not p.get('required',False),'description':p.get('description','')} for p in parameters if p['in']=='query']
  headers=[{'key':'Accept','value':'application/linkset+json' if path.startswith('/01/') else 'application/json'}]
  headers+=[{'key':p['name'],'value':'{{'+p['name']+'}}' if p['name'] in ['X-CSRF-Token','X-Home-CSRF','X-Status-Token'] else str(p.get('example','')),'description':p.get('description','')} for p in parameters if p['in']=='header' and p['name']!='Accept']
  media,content=next(iter(op.get('requestBody',{}).get('content',{}).items()),(None,None));body=None
  if media=='multipart/form-data':body={'mode':'formdata','formdata':[{'key':'title','value':'Fișă produs','type':'text'},{'key':'document','type':'file','src':[]}]}
  elif media=='image/jpeg':body={'mode':'file','file':{}};headers.append({'key':'Content-Type','value':media})
  elif media=='application/x-ndjson':body={'mode':'raw','raw':content['example'],'options':{'raw':{'language':'text'}}};headers.append({'key':'Content-Type','value':media})
  elif media:
   body={'mode':'raw','raw':json.dumps(content['example'],ensure_ascii=False,indent=2),'options':{'raw':{'language':'json'}}};headers.append({'key':'Content-Type','value':media})
  auth={'type':'bearer','bearer':[{'key':'token','value':'{{access_token}}','type':'string'}]} if any('Bearer' in s or 'HomeOAuth' in s for s in op.get('security',[])) else {'type':'noauth'}
  request={'method':method.upper(),'header':headers,'url':{'raw':'{{base_url}}'+target+('?'+'&'.join(q['key']+'='+q['value'] for q in query if not q['disabled']) if any(not q['disabled'] for q in query) else ''),'host':['{{base_url}}'],'path':target.lstrip('/').split('/'),'query':query},'description':op['description']+'\n\noperationId: '+op['operationId']+'\nDocumentație: '+BASE+'/api/docs/reference/'+op['operationId'],'auth':auth}
  if body:request['body']=body
  category=GROUPS[group(path)]
  if category not in folders:folders[category]={'name':category,'item':[]};postman['item'].append(folders[category])
  responses=[]
  for code,response in op['responses'].items():
   for mime,media in response.get('content',{}).items():
    values=[('Exemplu sintetic',media['example'])] if 'example' in media else [(e.get('summary',name),e['value']) for name,e in media.get('examples',{}).items()]
    for label,value in values:responses.append({'name':code+' · '+label,'originalRequest':request,'status':response['description'],'code':int(code),'header':[{'key':'Content-Type','value':mime}],'body':value if isinstance(value,str) else json.dumps(value,ensure_ascii=False,indent=2)})
  folders[category]['item'].append({'name':op['summary'],'request':request,'response':responses})
for name in sorted(variables):postman['variable'].append({'key':name,'value':{'gtin':'09520000000011','lot':'DEMO-PBT-01'}.get(name,'')})
for name in ['X-CSRF-Token','X-Home-CSRF','X-Status-Token']:postman['variable'].append({'key':name,'value':''})
postman_files=[]
for i,folder in enumerate(postman['item']):
 name='postman-'+str(i+1).zfill(2)+'.json';postman_files.append(name)
 (parts/name).write_text(json.dumps(folder,ensure_ascii=False,separators=(',',':'))+'\n',encoding='utf-8')
postman['item']=[];postman['x-item-files']=postman_files
(OUT/'api-postman-collection.json').write_text(json.dumps(postman,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
guide_file=OUT/'api-developer-guide.json';complete=json.loads(guide_file.read_text(encoding='utf-8'))
# The combined download includes the same public content as all portal guides.
complete['markdown']='# OpenAliment API — ghid de integrare\n\n'+'\n\n'.join(re.sub(r'^(#{1,5}) ',r'\1# ',page['markdown'],flags=re.M) for page in pages.values() if page['kind']=='guide')
guide_file.write_text(json.dumps(complete,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(json.dumps({'documentation_pages':len(pages)}))
