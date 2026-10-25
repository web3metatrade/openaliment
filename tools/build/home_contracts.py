"""Generate the implemented private consumer API contract; no credentials or fixtures."""
import json
from pathlib import Path
from common import ROOT, OUT, save

ident={'type':'string','pattern':'^[a-f0-9]{32}$'}
token={'type':'string','pattern':'^[a-f0-9]{64}$','writeOnly':True}
stamp={'type':'string','format':'date-time'}
def obj(properties,required=None):return {'type':'object','properties':properties,'required':list(properties) if required is None else required,'additionalProperties':False}
def nullable(value):return {'anyOf':[value,{'type':'null'}]}
def ref(name):return {'$ref':'#/$defs/'+name}
item=obj({'id':ident,'revision':{'type':'integer','minimum':1,'maximum':2147483647},'profile':{'$ref':'consumer-product-profile.schema.json'},'code':{'type':'string','maxLength':4096},'name':{'type':'string','minLength':1,'maxLength':180},'photo_id':nullable(ident),'quantity':{'type':'number','exclusiveMinimum':0,'maximum':100000},'unit':{'enum':['pack','g','ml']},'location':{'enum':['home','fridge','freezer','pantry']},'state':{'enum':['available','consumed']},'added_at':stamp,'updated_at':stamp,'opened_at':nullable(stamp),'consumed_at':nullable(stamp),'expiry':nullable({'$ref':'food-date.schema.json'}),'imported':{'type':'boolean'},'receipt':obj({'receipt_id':ident,'line_id':{'type':'string','minLength':1,'maxLength':80},'unit_index':{'type':'integer','minimum':0,'maximum':2000}})},['id','revision','profile','name','quantity','unit','location','state','added_at','updated_at'])
history=obj({'id':ident,'item_id':ident,'type':{'enum':['add','open','consume','edit','undo']},'before':nullable(ref('Item')),'after':nullable(ref('Item')),'created_at':stamp,'undone_at':nullable(stamp),'undoes':ident},['id','item_id','type','before','after','created_at'])
base={'event_id':ident,'item_id':ident,'expected_revision':{'type':'integer','minimum':0}}
operation={'oneOf':[obj(dict(base,kind={'const':'put'},item=ref('Item'))),obj(dict(base,kind={'const':'delete'}))]}
snapshot=obj({'item_id':ident,'server_revision':{'type':'integer','minimum':0},'seq':{'type':'integer','minimum':0},'deleted':{'type':'boolean'},'item':nullable(ref('Item'))},['server_revision','seq','deleted','item'])
schema={'$schema':'https://json-schema.org/draft/2020-12/schema','$id':'https://openaliment.ro/api/schemas/home-sync.schema.json','title':'OpenAliment private household synchronization v1','description':'Only packages, private photos by ID and immutable history. Personal health preferences and shopping lists are not accepted. Device-supplied live safety claims are discarded.','type':'object','properties':{'operations':{'type':'array','maxItems':50,'items':ref('Operation')},'history':{'type':'array','maxItems':50,'items':ref('History')}},'required':['operations'],'additionalProperties':False,'$defs':{'Item':item,'History':history,'Operation':operation,'Snapshot':snapshot,'Response':obj({'accepted':{'type':'array','items':obj({'event_id':ident,'item_id':ident,'server_revision':{'type':'integer','minimum':1},'seq':{'type':'integer','minimum':1}})},'history_accepted':{'type':'array','items':ident},'conflicts':{'type':'array','items':obj({'event_id':ident,'item_id':ident,'current':ref('Snapshot')},['item_id','current'])},'cursor':{'type':'integer','minimum':0},'atomic':{'const':True}}),'Page':obj({'household_id':ident,'changes':{'type':'array','maxItems':100,'items':{'oneOf':[obj({'kind':{'const':'item'},'seq':{'type':'integer','minimum':1},'value':ref('Snapshot')}),obj({'kind':{'const':'history'},'seq':{'type':'integer','minimum':1},'value':ref('History')})]}},'cursor':{'type':'integer','minimum':0},'has_more':{'type':'boolean'}})}}
save('home-sync.schema.json',schema)
api={'openapi':'3.1.0','info':{'title':'OpenAliment Acasă — API privat','version':'1.0.0','description':'Conturi personale, familii și sincronizare opțională, separate de administrarea producătorilor. Sesiunea web folosește cookie și X-Home-CSRF pentru modificări de la aceeași origine. Legăturile de conectare și invitațiile transmit secretul în fragment, apoi în corpul POST. Inventarul local și citirea produselor funcționează fără AI.'},'servers':[{'url':'https://openaliment.ro'}],'security':[{'HomeCookie':[]}],'paths':{},'components':{'securitySchemes':{'HomeCookie':{'type':'apiKey','in':'cookie','name':'oa_home','description':'HttpOnly, Secure in production, SameSite=Lax, Path=/api/v2/home/, 90-day rolling expiry. Never copy into exports.'}},'schemas':{}}}
def body(value,media='application/json'):return {'required':True,'content':{media:{'schema':value}}}
def response(description,schema=None):return {'description':description,**({'content':{'application/json':{'schema':schema}}} if schema else {})}
def route(path,method,summary,request=None,result=None,anonymous=False,status='200',params=None):
 op={'summary':summary,'description':'Private response: Cache-Control no-store. Changes never grant producer privileges.','responses':{status:response('Success',result),'401':response('Consumer device is not connected'),'403':response('Origin or household membership denied'),'419':response('Missing or invalid X-Home-CSRF'),'422':response('Invalid bounded input'),'429':response('Rate limited'),'503':response('Feature or email unavailable; local inventory still works')}}
 if anonymous:op['security']=[]
 if request:op['requestBody']=request
 parameters=list(params or [])
 if method=='post' and not anonymous:parameters.append({'name':'X-Home-CSRF','in':'header','required':True,'schema':{'type':'string','pattern':'^[a-f0-9]{64}$'},'description':'Read from authenticated GET session; kept only in memory.'})
 if parameters:op['parameters']=parameters
 if '{id}' in path:op.setdefault('parameters',[]).append({'name':'id','in':'path','required':True,'schema':ident})
 api['paths'].setdefault('/api/v2/home/'+path,{})[method]=op
def external(name):return {'$ref':'/api/schemas/home-sync.schema.json#/$defs/'+name}
route('session','get','Availability, optional private consumer principal, CSRF and own devices',anonymous=True)
route('login/request','post','Request a 15-minute single-use email link; account created only after confirmation',body(obj({'email':{'type':'string','format':'email','maxLength':190}})),anonymous=True)
for path in ['login/preview','invites/preview']:route(path,'post','Read-only preview; never consumes the token',body(obj({'token':token})),anonymous=True)
route('login/confirm','post','Consume link once and connect this device',body(obj({'token':token,'device_name':{'type':'string','maxLength':120}},['token'])),anonymous=True)
for path in ['login/preview','login/confirm','invites/preview']:api['paths']['/api/v2/home/'+path]['post']['responses']['410']=response('Link expired, already used or invalid')
route('changes','get','Paginated current package snapshots, tombstones and immutable history',result=external('Page'),params=[{'name':'after','in':'query','schema':{'type':'integer','minimum':0,'default':0}},{'name':'limit','in':'query','schema':{'type':'integer','minimum':1,'maximum':100,'default':50}}])
route('sync','post','Atomic batch with optimistic revisions and stable idempotency event IDs',body({'$ref':'/api/schemas/home-sync.schema.json'}),external('Response'))
api['paths']['/api/v2/home/sync']['post']['responses']['409']=response('Revision conflicts: no new operations/history committed; previously accepted retries are returned. Reused IDs with different data return the standard error envelope.',{'oneOf':[external('Response'),{'type':'object','required':['error_code','message','request_id']}]})
route('photos/{id}','post','Store an immutable private JPEG; maximum 1 MB, 6000×6000; 32 MB per household',body({'type':'string','format':'binary'},'image/jpeg'))
route('photos/{id}','get','Read a private household photo')
api['paths']['/api/v2/home/photos/{id}']['get']['responses']['200']={'description':'Private JPEG, no-store','content':{'image/jpeg':{'schema':{'type':'string','format':'binary'}}}}
for path in ['members','households']:route(path,'get','List accessible '+path)
route('invites','post','Create one-use editor invitation valid 7 days; owner only',body(obj({})),status='201')
route('join','post','Accept invitation once; joining never transfers ownership',body(obj({'token':token})))
route('switch','post','Select a household where the account already has membership',body(obj({'household_id':ident})))
route('rename','post','Rename current household; owner only',body(obj({'name':{'type':'string','minLength':1,'maxLength':120}})))
for path,title in [('members/{id}/remove','Remove member; owner only; existing downloaded copies cannot be recalled'),('devices/{id}/revoke','Revoke an own device immediately'),('logout','Revoke current device and expire its cookie')]:route(path,'post',title,body(obj({})))
route('account/action/request','post','Request email confirmation for own account erasure, ownership transfer or leaving a household; browser only',body(obj({'action':{'enum':['erase','transfer','leave']},'payload':obj({'household_id':ident,'account_id':ident},[])},['action'])))
for path in ['account/action/preview','account/action/confirm']:route(path,'post','Preview or explicitly confirm the single-use, account/device-bound sensitive action; browser only',body(obj({'token':token})))
for path in ['account/action/preview','account/action/confirm']:api['paths']['/api/v2/home/'+path]['post']['responses']['410']=response('Confirmation expired, already used or invalid')
save('home-openapi.json',api)
print(json.dumps({'generated':['home-sync.schema.json','home-openapi.json'],'paths':len(api['paths'])}))
