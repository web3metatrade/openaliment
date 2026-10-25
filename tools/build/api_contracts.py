"""Complete legacy specs and generate the routed platform contract without loading secrets."""
import copy
import html
import json
import os
import re
import shutil
import subprocess
from pathlib import Path
from urllib.parse import urlparse
from common import ROOT, OUT, save

php=os.environ.get('OA_PHP') or shutil.which('php') or str(ROOT/'.tools/php/php.exe')
routes=json.loads(subprocess.run([php,str(ROOT/'tools/build/api_registry.php')],cwd=ROOT,check=True,capture_output=True,encoding='utf-8').stdout)
def read(name):return json.loads((OUT/name).read_text(encoding='utf-8'))
def obj(properties,required=(),extra=False):return {'type':'object','properties':properties,'required':list(required),'additionalProperties':extra}
def text(n=180):return {'type':'string','maxLength':n,'minLength':1}
ID={'type':'string','pattern':'^[a-f0-9]{32}$'}
EMPTY=obj({})
legacy=read('openapi.json'); schemas=copy.deepcopy(legacy['components']['schemas'])
for name in ['Product','TradeItem','Alert','Resolution']:schemas[name]['additionalProperties']=False
schemas.update({
 'AccountEmail':obj({'email':{'type':'string','format':'email','maxLength':190}},['email']),
 'AccountToken':obj({'token':{'type':'string','pattern':'^[a-f0-9]{64}$','writeOnly':True}},['token']),
 'AccountReset':obj({'token':{'type':'string','pattern':'^[a-f0-9]{64}$','writeOnly':True},'password':{'type':'string','minLength':12,'maxLength':200,'writeOnly':True},'confirmation':{'type':'string','minLength':12,'maxLength':200,'writeOnly':True}},['token','password','confirmation']),
 'RecoveryCode':obj({'code':{'type':'string','maxLength':30,'writeOnly':True}},['code']),
 'PasskeyCreateOptions':obj({'name':text(120),'password':{'type':'string','maxLength':200,'writeOnly':True}},['name','password']),
 'PasskeyRegistration':obj({k:{'type':'string','maxLength':190000} for k in ['credentialId','clientDataJSON','attestationObject']},['credentialId','clientDataJSON','attestationObject']),
 'PasskeyAssertion':obj({k:{'type':'string','maxLength':190000} for k in ['credentialId','clientDataJSON','authenticatorData','signature']},['credentialId','clientDataJSON','authenticatorData','signature']),
 'VoteMutation':obj({'expected_mutation_version':{'type':'integer','minimum':0}}),
 'Identifier':obj({'code':text(4096)},['code']),
 'FoodLookups':obj({'codes':{'type':'array','minItems':1,'maxItems':20,'items':text(4096)}},['codes']),
 'Feedback':obj({'trade_item_id':ID,'product_id':ID,'product_info_version_id':ID,'batch_id':ID,'kind':{'type':'string','enum':['review','question','idea','quality']},'text':text(4000),'rating':{'type':['integer','null'],'minimum':1,'maximum':5},'criteria':obj({k:{'type':'integer','minimum':1,'maximum':5} for k in ['taste','texture','aroma','freshness','packaging']}),'consent':{'const':True,'type':'boolean'},'submission_key':{'type':'string','pattern':'^[A-Za-z0-9_.:-]{8,128}$'}},['text','consent','submission_key']),
 'Recipe':obj({'version_key':text(60),'snapshot':obj({'formula':{'type':['array','object']},'process':{'type':['array','object']}},['formula','process'],True),'visibility':{'type':'string','enum':['private','public']},'license_id':text(80),'rights_confirmed':{'type':'boolean'},'license_confirmed':{'type':'boolean'},'public_projection_confirmed':{'type':'boolean'}},['version_key','snapshot']),
 'Proposal':obj({'title':text(180),'body':text(5000),'public':{'type':'boolean'}},['title','body']),
 'Decision':obj({'state':{'type':'string','enum':['proposed','planned','accepted','rejected','tested']},'decision':text(3000)},['state','decision']),
 'DocumentPublication':obj({'rights_confirmed':{'type':'boolean','const':True},'public_projection_confirmed':{'type':'boolean','const':True},'license_id':text(80)},['rights_confirmed','public_projection_confirmed']),
 'Moderation':obj({'status':{'type':'string','enum':['published','restricted','rejected']},'public_text':text(4000),'reason':text(1500)},['status','reason']),
 'Import':obj({'dry_run':{'type':'boolean','default':True},'package':{'type':'object','additionalProperties':True}}),
 'ApiKey':obj({'name':text(120),'days':{'type':'integer','minimum':1,'maximum':365,'default':90},'scopes':{'type':'array','minItems':1,'uniqueItems':True,'items':{'type':'string','enum':['read','write','publish','alerts','export','import','community']}},'current_password':text(200)},['name','scopes']),
 'ApiKeyRotation':obj({'name':text(120),'days':{'type':'integer','minimum':1,'maximum':365},'current_password':text(200)}),
 'OAuthClient':obj({'name':text(120),'redirect_uris':{'type':'array','minItems':1,'maxItems':10,'items':{'type':'string','format':'uri','maxLength':2048}},'scopes':{'type':'array','minItems':1,'maxItems':10,'items':{'type':'string','enum':['read','write','publish','alerts','export','import','community','home:read','home:write','home:manage']}}},['name','redirect_uris']),
 'Login':obj({'email':{'type':'string','format':'email','maxLength':190},'password':text(200)},['email','password']),
 'PasswordChange':obj({'current_password':text(200),'new_password':{'type':'string','minLength':12,'maxLength':200},'confirmation':text(200)},['current_password','new_password','confirmation']),
 'AccessRevocation':obj({'current_password':text(200)},['current_password']),
 'Registration':obj({'email':{'type':'string','format':'email','maxLength':190},'password':{'type':'string','minLength':12,'maxLength':200},'display_name':text(120),'organization':text(180)},['email','password','display_name','organization']),
 'OrganizationSelection':obj({'organization_id':ID},['organization_id']),
 'AdminReview':obj({'evidence':text(5000),'state':{'type':'string','enum':['verified','disputed','withdrawn']}},['evidence']),
 'AiJob':obj({'kind':{'type':'string','enum':['feedback_triage']},'feedback_id':ID},['feedback_id']),
 'Upload':obj({'title':text(180),'document':{'type':'string','format':'binary'}},['title','document']),
 'Record':{'type':'object','description':'Authorized resource; immutable snapshots use snapshot plus snapshot_sha256. Private storage/credential fields are excluded.','additionalProperties':True},
 'Collection':obj({'items':{'type':'array','items':{'$ref':'#/components/schemas/Record'}},'next_cursor':{'type':['string','null']},'has_more':{'type':'boolean'},'limit':{'type':'integer','minimum':1,'maximum':100}},['items','next_cursor','has_more','limit']),
 'MutationResult':obj({'id':ID},[],True),
 'ApiKeyResult':obj({'id':ID,'token':text(100),'expires_at':text(30),'scopes':{'type':'array','items':text(30)},'secret_available':{'type':'boolean'}},['id','secret_available'],True),
 'PublicProjection':obj({'product_id':ID,'trade_item_id':ID,'producer':text(180),'gtin14':{'type':['string','null']},'identifier_status':text(30),'identity_notice':{'type':['string','null']},'product_info_version_id':ID,'version_key':text(60),'approved_at':text(30),'sha256':text(64),'label':{'type':'object'},'documents':{'type':'array','items':{'type':'object'}},'alerts':{'type':'array','items':{'type':'object'}},'batch':{'type':'object'}},['product_id','trade_item_id','product_info_version_id','label','alerts'],False),
})
from marketplace_reference import requests as marketplace_requests
schemas.update(marketplace_requests(obj,text,ID))
error_response={'description':'Eroare API: cod, mesaj și identificatorul cererii pentru suport.','content':{'application/json':{'schema':{'$ref':'#/components/schemas/Error'}}},'headers':{'X-Request-ID':{'schema':{'type':'string'},'description':'Correlation ID'},'Retry-After':{'schema':{'type':'integer'},'description':'Present on throttling, seconds until retry'}}}
def operation_id(prefix,method,path):return prefix+method.title()+''.join(x.title() for x in re.findall(r'[A-Za-z0-9]+',path))
missing=[
 ('get','/recipe-versions/{id}',None,None),('get','/feedback/{id}/status',None,None),('post','/feedback',None,'Feedback'),
 *[('get','/manage/'+r+'/{id}','read',None) for r in ['products','trade-items','labels','batches','recipes','documents']],
 ('post','/manage/products/{id}/recipes','write','Recipe'),('post','/manage/products/{id}/proposals','write','Proposal'),('post','/manage/proposals/{id}/decide','publish','Decision'),('post','/manage/documents/{id}/publish','publish','DocumentPublication'),('post','/manage/feedback/{id}/moderate','publish','Moderation'),('post','/manage/import','import','Import'),('post','/manage/import-pbt','import','Import')]
for method,path,scope,body in missing:
 op={'summary':path,'security':[{'bearerAuth':[]}] if scope else [],'responses':{'200' if method=='get' or path.endswith(('/import','/import-pbt')) else '201':{'description':'Operation result','content':{'application/json':{'schema':{'$ref':'#/components/schemas/Record'}}}}}}
 if scope:op['x-required-scope']=scope
 if body:op['requestBody']={'required':True,'content':{'application/json':{'schema':{'$ref':'#/components/schemas/'+body}}}}
 if '{id}' in path:op['parameters']=[{'name':'id','in':'path','required':True,'schema':ID}]
 if path.endswith('/status'):op.setdefault('parameters',[]).append({'name':'X-Status-Token','in':'header','required':True,'schema':text(64)})
 if method=='post' and scope:op.setdefault('parameters',[]).append({'name':'Idempotency-Key','in':'header','required':not path.endswith(('/import','/import-pbt')),'schema':text(128),'description':'Required when dry_run=false; stable on retries.'})
 legacy['paths'].setdefault(path,{})[method]=op
legacy['components']['schemas']=schemas
save('openapi.json',legacy)

home=read('home-openapi.json')
for method,path,summary in [('get','/api/v2/home/grants','List own authorized applications, browser session only'),('post','/api/v2/home/grants/{id}/revoke','Revoke an own application grant, browser session only')]:
 home['paths'][path]={method:{'summary':summary,'responses':{'200':{'description':'OK'}}}}
home['components']['securitySchemes']['HomeOAuth']={'type':'oauth2','flows':{'authorizationCode':{'authorizationUrl':'https://openaliment.ro/oauth/authorize','tokenUrl':'https://openaliment.ro/oauth/token','scopes':{'home:read':'Read the chosen household','home:write':'Update the chosen household','home:manage':'Manage the chosen household, owner only'}}}}
for path,values in home['paths'].items():
 for method,op in values.items():
  if method not in ['get','post']:continue
  if '/login/' not in path and not path.endswith('/invites/preview') and '/grants' not in path and '/account/action/' not in path:
   scope='home:read' if method=='get' else 'home:write'
   if path.endswith(('/invites','/rename','/remove')):scope='home:manage'
   op['security']=[{'HomeCookie':[]},{'HomeOAuth':[scope]}]
   op['description']=(op.get('description','')+' OAuth grants are fixed to one explicitly chosen household. join/switch require the browser flow and a new authorization; an app cannot revoke other devices.').strip()
save('home-openapi.json',home)

combined={'openapi':'3.1.0','info':{'title':'OpenAliment — API pentru integrări și aplicații','version':'2.4.1','description':'Informații publice aprobate, date private separate pe organizații, chei revocabile și autorizare OAuth cu PKCE. Include compatibilitatea v1. Consultă /api/manifest.json pentru capabilitățile active; integrarea folosește contractele API, fără scraping HTML.'},'servers':[{'url':'https://openaliment.ro'}],'paths':{},'components':{'schemas':copy.deepcopy(schemas),'securitySchemes':{'Bearer':{'type':'http','scheme':'bearer','description':'Producer API key or delegated opaque access token; never send in query.'},'ProducerSession':{'type':'apiKey','in':'cookie','name':'oa_session','description':'Trusted same-origin web only; X-CSRF-Token required for writes.'},'HomeCookie':{'type':'apiKey','in':'cookie','name':'oa_home'},'HomeOAuth':home['components']['securitySchemes']['HomeOAuth']}}}
for filename,prefix in [('openapi.json','v1'),('consumer-openapi.json','consumer'),('integration-openapi.json','integration'),('home-openapi.json','home')]:
 spec=read(filename);base=urlparse(spec['servers'][0]['url']).path.rstrip('/')
 for path,values in spec['paths'].items():
  for method,op in values.items():
   if method not in ['get','post','put','patch','delete','head','options']:continue
   op['operationId']=operation_id(prefix,method,path)
   for code in ['400','401','403','404','409','413','419','422','429','503']:op['responses'].setdefault(code,copy.deepcopy(error_response))
   copy_op=copy.deepcopy(op)
   shared=values.get('parameters',[])
   if shared:
    own={(p['name'],p['in']) for p in copy_op.get('parameters',[])}
    copy_op['parameters']=[copy.deepcopy(p) for p in shared if (p['name'],p['in']) not in own]+copy_op.get('parameters',[])
   for sec in copy_op.get('security',spec.get('security',[])):
    for name in list(sec):
     if name in ['bearerAuth','producerToken']:sec['Bearer']=sec.pop(name)
   if 'security' not in copy_op:copy_op['security']=[{'Bearer':[]}] if filename=='integration-openapi.json' and '/manage/' in path else []
   combined['paths'].setdefault(base+path,{})[method]=copy_op
 for name,value in spec.get('components',{}).get('schemas',{}).items():combined['components']['schemas'].setdefault(name,value)
 save(filename,spec)

for r in routes:
 method=r['method'].lower();path=r['path'];handler=r['handler'];public=r['auth']=='public'
 op={'operationId':r['id'],'summary':r['summary'],'tags':[handler.split('.')[0].split(':')[0]],'security':[] if public or r['auth']=='session-optional' else ([{'Bearer':[]},{'ProducerSession':[]}] if r['auth']=='producer' else [{'ProducerSession':[]}]),'x-required-scope':r['scope'],'x-auth-context':r['auth'],'parameters':[],'responses':{}}
 op['description']='Datele publice sunt aprobate explicit. O valoare necunoscută nu înseamnă zero. Rețetele private nu intră în răspunsurile publice.' if public else 'Fiecare cerere verifică organizația, resursa, apartenența și rolul curent. Modificările din sesiunea web cer și X-CSRF-Token. Cheile API nu pot emite alte chei și nu acordă administrarea platformei.'
 if '{id}' in path:op['parameters'].append({'name':'id','in':'path','required':True,'schema':ID})
 paged=handler in ['manage.list','manage.children','public.catalog','public.feedback'] or handler=='public.children' and '/products/' in path or handler=='community.votes' or handler=='admin.list' or handler=='keys.list' or handler=='manage.connector' and path.endswith('/events')
 if handler in ['marketplace.jobs','marketplace.catalog','marketplace.releases','marketplace.units','marketplace.selections']:paged=True
 if paged:
  op['parameters'] += [{'name':'cursor','in':'query','schema':ID,'description':'Last id from prior page. Omit on first request.'},{'name':'limit','in':'query','schema':{'type':'integer','minimum':1,'maximum':100,'default':20}}]
 if handler=='marketplace.catalog':op['parameters'] += [{'name':x,'in':'query','schema':text(120),'description':'q caută prefixul numelui; category selectează categoria exactă.'} for x in ['q','category']]
 if handler=='public.catalog':
  op['parameters'] += [{'name':x,'in':'query','schema':text(120),'description':'Prefix search for q; exact match for the other fields.'} for x in ['q','category','producer','gtin']]
 if handler=='public.feedback':op['parameters'] += [{'name':x,'in':'query','schema':ID} for x in ['label_id','batch_id']]
 if handler=='manage.list':
  resource=path.split('/')[4];filters={'products':['category'],'trade-items':['product_id','gtin14'],'labels':['product_id','trade_item_id','state'],'batches':['product_id','trade_item_id','lot_code','state'],'recipes':['product_id'],'documents':['product_id'],'proposals':['product_id'],'feedback':['product_id','batch_id','status','kind'],'ai-jobs':['feedback_id','state'],'audit-events':['object_id','action']}.get(resource,[])
  op['parameters'] += [{'name':x,'in':'query','schema':text(190)} for x in filters]
 if handler=='manage.export':op['parameters'].append({'name':'format','in':'query','schema':{'type':'string','enum':['json','ndjson']}})
 if handler=='manage.connector' and path.endswith('/bindings'):op['parameters'] += [{'name':'external_id','in':'query','schema':text(120),'description':'Exclusive cursor in next_external_id, rather than an internal id.'},{'name':'limit','in':'query','schema':{'type':'integer','minimum':1,'maximum':100}}]
 idempotent=handler.startswith(('mutation:','keys.create','keys.rotate')) or handler in ['manage.upload','manage.import','manage.importStream','manage.ai'] and method=='post' or handler=='developer.apps' and method=='post' or handler.startswith('admin:')
 if handler in ['marketplace.select','marketplace.createJob','marketplace.cancel','marketplace.reprint']:idempotent=True
 if idempotent:op['parameters'].append({'name':'Idempotency-Key','in':'header','required':handler not in ['manage.import','manage.importStream'],'schema':{'type':'string','pattern':'^[A-Za-z0-9_.:-]{8,128}$'},'description':'Same key and payload on retries. Import dry_run=true does not require it. Key issuance replays metadata only, never the secret.'})
 if method in ['post','put','delete'] and not public and r['auth']!='session-optional':op['parameters'].append({'name':'X-CSRF-Token','in':'header','required':r['auth'] in ['owner-session','session','admin-session'],'schema':text(64),'description':'Obligatoriu pentru sesiunea web. Nu înlocuiește autentificarea Bearer la operațiile care permit tokenuri.'})
 if r['auth']=='print-agent':
  op['security']=[{'Bearer':[]}];op['parameters']=[p for p in op['parameters'] if p['name']!='X-CSRF-Token']
 if r['body']:op['requestBody']={'required':True,'content':{'multipart/form-data' if r['body']=='Upload' else 'application/json':{'schema':{'$ref':'#/components/schemas/'+r['body']}}}}
 if handler=='manage.importStream':
  header=json.dumps({'schema_version':'openaliment.transfer.stream.v1','source_base_url':'https://exemplu.example','exported_at':'2026-10-25T00:00:00Z'},separators=(',',':'))+'\n'
  stream=header+json.dumps({'end':True,'records':0,'sha256':__import__('hashlib').sha256(header.encode()).hexdigest()},separators=(',',':'))+'\n'
  op['parameters'].append({'name':'dry_run','in':'query','schema':{'type':'boolean','default':True},'example':True,'description':'true verifică fără scriere; false aplică importul atomic și cere Idempotency-Key.'})
  op['requestBody']={'required':True,'content':{'application/x-ndjson':{'schema':{'type':'string','maxLength':24000000},'example':stream}}}
 response_schema='Collection' if paged else 'Record' if method=='get' else 'ApiKeyResult' if handler in ['keys.create','keys.rotate'] else 'MutationResult'
 if handler=='public.get' and path.split('/')[4] in ['products','trade-items','batches']:response_schema='PublicProjection'
 success='202' if handler=='manage.ai' and method=='post' else '201' if idempotent and handler not in ['manage.import','manage.importStream'] and not handler.startswith('admin:') else '200'
 content={'application/json':{'schema':{'$ref':'#/components/schemas/'+response_schema}}}
 if handler.endswith('.download'):content={'application/octet-stream':{'schema':{'type':'string','format':'binary'}}};success='200'
 if handler=='public.archive':content={'application/zip':{'schema':{'type':'string','format':'binary'}}};success='200'
 if handler=='manage.export':content['application/x-ndjson']={'schema':{'type':'string','description':'Header, authorized records, final SHA-256 digest record.'}}
 if handler.startswith('marketplace.'):success='200'
 op['responses'][success]={'description':'Authorized result.','content':content,'headers':{'X-Request-ID':{'schema':text(64)},'RateLimit-Limit':{'schema':{'type':'integer'}},'RateLimit-Remaining':{'schema':{'type':'integer'}},'RateLimit-Reset':{'schema':{'type':'integer'},'description':'Seconds until this rate window resets.'}}}
 for code in ['400','401','403','404','405','409','413','415','419','422','429','503']:op['responses'][code]=copy.deepcopy(error_response)
 if handler.startswith('account.'):op['responses']['410']=copy.deepcopy(error_response)
 if handler=='marketplace.download':op['responses']={'303':{'description':'Pachet gratuit distribuit prin HTTPS de dezvoltator. Verifică SHA-256 după descărcare.','headers':{'Location':{'schema':{'type':'string','format':'uri'},'description':'Adresa HTTPS aprobată a pachetului publicat.'}}}}
 if handler=='marketplace.download':op['responses']['409']=copy.deepcopy(error_response)
 combined['paths'].setdefault(path,{})[method]=op

oauth_token=obj({'grant_type':{'type':'string','enum':['authorization_code','refresh_token']},'client_id':ID,'code':text(64),'redirect_uri':text(2048),'code_verifier':{'type':'string','minLength':43,'maxLength':128},'refresh_token':text(100),'scope':text(500)},['grant_type','client_id'])
combined['components']['schemas']['OAuthTokenRequest']=oauth_token
combined['components']['schemas']['OAuthTokenResponse']=obj({'access_token':text(100),'token_type':{'type':'string','const':'Bearer'},'expires_in':{'type':'integer'},'refresh_token':text(100),'refresh_expires_in':{'type':'integer'},'scope':text(500),'grant_id':ID},['access_token','token_type','expires_in','refresh_token','scope','grant_id'])
for path,summary,schema in [('/oauth/token','Exchange code + PKCE or rotate refresh token','OAuthTokenRequest'),('/oauth/revoke','Revoke entire token grant; unknown token also returns 200',None)]:
 combined['paths'][path]={'post':{'operationId':'oauth'+path.split('/')[-1].title(),'summary':summary,'security':[],'requestBody':{'required':True,'content':{mime:{'schema':{'$ref':'#/components/schemas/'+schema} if schema else obj({'client_id':ID,'token':text(180)},['client_id','token'])} for mime in ['application/json','application/x-www-form-urlencoded']}},'responses':{'200':{'description':'Token response or empty successful revocation.','content':{'application/json':{'schema':{'$ref':'#/components/schemas/OAuthTokenResponse'} if schema else EMPTY}}},'400':{'description':'OAuth error with error, error_description and request_id'},'429':copy.deepcopy(error_response)}}}
for path,id_ in [('/oauth/authorize','oauthAuthorize'),('/api/v2/home/oauth/authorize','oauthAuthorizeHome')]:
 combined['paths'][path]={'get':{'operationId':id_,'summary':'Open explicit user consent in the system browser, not an API password flow.','security':[],'parameters':[{'name':k,'in':'query','required':True,'schema':text(2048)} for k in ['client_id','redirect_uri','response_type','state','code_challenge','code_challenge_method','scope']],'responses':{'200':{'description':'Consent HTML or login choice'},'303':{'description':'Validated redirect to the appropriate realm'},'400':copy.deepcopy(error_response)}}}
for path,id_ in [('/api/manifest.json','apiManifest'),('/.well-known/oauth-authorization-server','oauthMetadata'),('/api/v2/public/status','publicServiceStatus')]:combined['paths'][path]={'get':{'operationId':id_,'summary':'Public service discovery metadata.','security':[],'responses':{'200':{'description':'Public JSON metadata','content':{'application/json':{'schema':{'type':'object'}}}}}}}
for path,id_ in [('/api/guide.md','developerTextGuide'),('/api/sdk/python','developerPythonClient'),('/api/sdk/javascript','developerJavascriptClient'),('/api/sdk/kotlin','developerKotlinExample')]:combined['paths'][path]={'get':{'operationId':id_,'summary':'Download the developer guide or small client without runtime dependencies.','security':[],'responses':{'200':{'description':'UTF-8 source, never executed by the server','content':{'text/plain':{'schema':{'type':'string'}}}}}}}
for suffix,name in [('', 'Product'),('/22/{variant}','Variant'),('/10/{lot}','Lot'),('/22/{variant}/10/{lot}','VariantLot'),('/21/{serial}','Serial'),('/235/{tpx}','Tpx')]:
 path='/01/{gtin}'+suffix
 op={'operationId':'gs1Resolve'+name,'summary':'Resolve the declared GTIN profile; use linkType=linkset for machine discovery.','security':[],'tags':['GS1 resolver'],'parameters':[{'name':'gtin','in':'path','required':True,'schema':{'type':'string','pattern':'^[0-9]{14}$'},'example':'09520000000011'},{'name':'linkType','in':'query','schema':text(200),'example':'linkset'},{'name':'context','in':'query','schema':text(500)},{'name':'Accept','in':'header','schema':text(100),'example':'application/linkset+json'},{'name':'Accept-Language','in':'header','schema':text(100),'example':'ro'}],'responses':{'200':{'description':'Public linkset','content':{'application/linkset+json':{'schema':{'type':'object','required':['linkset'],'properties':{'linkset':{'type':'array','items':{'type':'object'}}}}}}},'307':{'description':'Selected destination; do not forward credentials on redirects','headers':{'Location':{'schema':{'type':'string','format':'uri'}}}},'300':{'description':'Equivalent choices; HTML or linkset according to negotiation'},'400':copy.deepcopy(error_response),'404':copy.deepcopy(error_response),'429':copy.deepcopy(error_response),'503':copy.deepcopy(error_response)}}
 for var in re.findall(r'\{(\w+)\}',suffix):op['parameters'].append({'name':var,'in':'path','required':True,'schema':text(28 if var=='tpx' else 20),'description':'Original GS1 value encoded exactly once in the path.'})
 combined['paths'][path]={'get':op}
for path,id_ in [('/.well-known/gs1resolver','gs1Description'),('/resolver/contexts','gs1Contexts'),('/resolver/linkset-context','gs1JsonLdContext')]:combined['paths'][path]={'get':{'operationId':id_,'summary':'Read the declared resolver profile metadata.','security':[],'responses':{'200':{'description':'Public JSON/JSON-LD','content':{'application/json':{'schema':{'type':'object'}}}}}}}

examples={
 'Product':{'name':'Iaurt natural','category':'lactate'},'TradeItem':{'name':'Pahar 200 g'},
 'Label':{'version_key':'E1','name':'Iaurt natural','category':'lactate','ingredients':'Lapte, culturi lactice.','allergens':'Lapte.','net_quantity':'200 g','operator':'Denumirea și adresa operatorului'},
 'Batch':{'lot_code':'EXEMPLU-001','product_info_version_id':'a'*32,'recipe_version_id':None},
 'Alert':{'message':'Mesajul aprobat al producătorului','state':'on_hold'},'Resolution':{'resolution':'Motivul documentat al închiderii.'},
 'ResolverLink':{'href':'https://exemplu.example/produs','title':'Informații despre produs','types':['gs1:pip'],'qualifiers':{},'is_default':True},
 'ResolverApproval':{'public_projection_confirmed':True},'ResolverWithdrawal':{'reason':'Destinația a fost retrasă de producător.'},
 'Identifier':{'code':'https://openaliment.ro/01/09520000000011/10/DEMO-PBT-01'},'FoodLookups':{'codes':['9520000000011']},
 'Feedback':{'trade_item_id':'a'*32,'product_info_version_id':'b'*32,'kind':'review','text':'Observație despre produs.','rating':4,'consent':True,'submission_key':'exemplu-feedback-001'},
 'Recipe':{'version_key':'R1','visibility':'private','snapshot':{'formula':[],'process':[]}},
 'Proposal':{'title':'Încercare nouă','body':'Ipoteză, dovezi și test propus.','public':False},'Decision':{'state':'planned','decision':'Plan de test aprobat.'},
 'DocumentPublication':{'rights_confirmed':True,'public_projection_confirmed':True},'Moderation':{'status':'published','public_text':'Copia publicabilă verificată.','reason':'Revizuire înainte de publicare.'},
 'Import':{'dry_run':True,'package':{}},'ApiKey':{'name':'ERP propriu','days':90,'scopes':['read','write']},'ApiKeyRotation':{'days':90},
 'OAuthClient':{'name':'Aplicația mea','redirect_uris':['https://aplicatia.example/callback','ro.exemplu.aplicatie:/callback'],'scopes':['home:read','home:write']},
 'Login':{'email':'producator@example.invalid','password':'PAROLA_DIN_CONT'},'Registration':{'email':'producator@example.invalid','password':'PAROLA_LUNGA_ALEASA_DE_PERSOANA','display_name':'Reprezentant','organization':'Producător exemplu'},
 'OrganizationSelection':{'organization_id':'a'*32},'AdminReview':{'evidence':'Referințele probelor verificate de administrator.','state':'verified'},'AiJob':{'kind':'feedback_triage','feedback_id':'a'*32},'Empty':{},
 'OAuthTokenRequest':{'grant_type':'authorization_code','client_id':'a'*32,'redirect_uri':'ro.exemplu.aplicatie:/callback','code':'b'*64,'code_verifier':'v'*64},
}
empty_transfer={name:[] for name in ['products','trade_items','product_info_versions','recipe_versions','batches','alerts','documents']}
examples['Import']={'dry_run':True,'package':{'schema_version':'openaliment.transfer.v1','source_base_url':'https://exemplu.example','exported_at':'2026-10-24T00:00:00Z','data':empty_transfer,'data_sha256':__import__('hashlib').sha256(json.dumps(empty_transfer,ensure_ascii=False,separators=(',',':')).encode()).hexdigest()}}
for spec_file in ['openapi.json','consumer-openapi.json','integration-openapi.json','home-openapi.json']:
 spec=read(spec_file)
 for values in spec['paths'].values():
  for op in values.values():
   if not isinstance(op,dict):continue
   for content in op.get('requestBody',{}).get('content',{}).values():
    name=content.get('schema',{}).get('$ref','').split('/')[-1]
    if name in examples:content['example']=examples[name]
 save(spec_file,spec)
for values in combined['paths'].values():
 for op in values.values():
  for content in op.get('requestBody',{}).get('content',{}).values():
   name=content.get('schema',{}).get('$ref','').split('/')[-1]
   if name in examples:content['example']=examples[name]
  for parameter in op.get('parameters',[]):
   if parameter.get('name')=='id':parameter['example']='a'*32
   if parameter.get('name')=='Idempotency-Key':parameter['example']='exemplu-'+op['operationId']+'-001'
ids=[op['operationId'] for values in combined['paths'].values() for op in values.values()]
assert len(ids)==len(set(ids)),[i for i in ids if ids.count(i)>1]
from api_reference import enrich
enrich(combined,routes,OUT)
# Standard OpenAPI response references keep the complete contract within cPanel's file budget.
response_groups={}
for methods in combined['paths'].values():
 for operation in methods.values():
  for code,response in operation['responses'].items():
   if code.startswith(('4','5')):
    fingerprint=json.dumps(response,ensure_ascii=False,sort_keys=True,separators=(',',':'))
    response_groups.setdefault(fingerprint,[]).append((operation,code))
combined['components']['responses']={}
for serialized,uses in response_groups.items():
 if len(uses)<2:continue
 name='Error'+uses[0][1]+'_'+__import__('hashlib').sha256(serialized.encode()).hexdigest()[:12]
 combined['components']['responses'][name]=json.loads(serialized)
 for operation,code in uses:operation['responses'][code]={'$ref':'#/components/responses/'+name}
save('platform-openapi.json',combined)
# Compact the large aggregate for shared-hosting transport; JSON semantics are unchanged.
(OUT/'platform-openapi.json').write_text(json.dumps(combined,ensure_ascii=False,separators=(',',':'))+'\n',encoding='utf-8')
save('api-operation-catalog.json',{'schema_version':'openaliment.api-operations.v1','routes':routes,'contract':'platform-openapi.json','operations':len(ids)})
guide=(ROOT/'docs/api/DEVELOPER_GUIDE.md').read_text(encoding='utf-8')
links={'../../contracts/platform-openapi.json':'/api/platform-openapi.json','../../tools/sdk/openaliment.py':'/api/sdk/python','../../tools/sdk/openaliment.mjs':'/api/sdk/javascript','../../tools/sdk/OpenAlimentClient.kt':'/api/sdk/kotlin','../INTEGRARI_PARTNERI.md':'/api/docs#conectoare','../INTEGRARI_PARTENERI.md':'/api/docs#conectoare','../IMPORT_EXPORT.md':'/api/docs#conectoare'}
def inline(s):
 s=html.escape(s)
 def link(m):
  target=links.get(m[2],m[2]);return '<a href="'+target+'">'+m[1]+'</a>' if target.startswith(('https://','/api/','#')) else m[1]
 s=re.sub(r'\[([^\]]+)\]\(([^)]+)\)',link,s)
 s=re.sub(r'`([^`]+)`',r'<code>\1</code>',s);s=re.sub(r'\*\*([^*]+)\*\*',r'<strong>\1</strong>',s)
 return s
lines=guide.splitlines();render=[];at=0
while at<len(lines):
 line=lines[at];at+=1
 if not line.strip():continue
 if line.startswith('# '):continue
 if line.startswith('```'):
  code=[]
  while at<len(lines) and not lines[at].startswith('```'):code.append(lines[at]);at+=1
  at+=1;render.append('<pre class="code-block">'+html.escape('\n'.join(code))+'</pre>');continue
 if line.startswith('## '):
  title=line[3:];anchor='conectoare' if title.startswith('Conectoare') else 'acasa' if title.startswith('Acces personal') else 'oauth' if title.startswith('OAuth') else 'chei' if title.startswith('Emitere') else 'sectiune-'+str(len(render))
  render.append('<h2 id="'+anchor+'">'+inline(title)+'</h2>');continue
 if line.startswith('|'):
  rows=[line]
  while at<len(lines) and lines[at].startswith('|'):rows.append(lines[at]);at+=1
  head=[x.strip() for x in rows[0].strip('|').split('|')];table='<div class="table-scroll"><table class="api-table"><thead><tr>'+''.join('<th>'+inline(x)+'</th>' for x in head)+'</tr></thead><tbody>'
  for row in rows[2:]:table+='<tr>'+''.join('<td>'+inline(x.strip())+'</td>' for x in row.strip('|').split('|'))+'</tr>'
  render.append(table+'</tbody></table></div>');continue
 if line.startswith('- ') or re.match(r'^\d+\. ',line):
  numbered=not line.startswith('- ');items=[re.sub(r'^(?:- |\d+\. )','',line)]
  pattern=r'^\d+\. ' if numbered else r'^- '
  while at<len(lines) and re.match(pattern,lines[at]):items.append(re.sub(pattern,'',lines[at]));at+=1
  tag='ol' if numbered else 'ul';render.append('<'+tag+'>'+''.join('<li>'+inline(x)+'</li>' for x in items)+'</'+tag+'>');continue
 paragraph=[line]
 while at<len(lines) and lines[at].strip() and not lines[at].startswith(('#','```','|','- ')) and not re.match(r'^\d+\. ',lines[at]):paragraph.append(lines[at]);at+=1
 render.append('<p>'+inline(' '.join(paragraph))+'</p>')
save('api-developer-guide.json',{'schema_version':'openaliment.developer-guide.v1','markdown':guide,'html':'\n'.join(render),'sdk':{'python':(ROOT/'tools/sdk/openaliment.py').read_text(encoding='utf-8'),'javascript':(ROOT/'tools/sdk/openaliment.mjs').read_text(encoding='utf-8'),'kotlin':(ROOT/'tools/sdk/OpenAlimentClient.kt').read_text(encoding='utf-8')}})
print(json.dumps({'platform_operations':len(ids),'routed_new_operations':len(routes),'legacy_completed_operations':sum(len(v) for v in legacy['paths'].values())}))
