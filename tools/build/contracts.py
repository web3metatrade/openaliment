"""Rebuild the active machine-readable contracts. Never modifies the handover."""
import json
import re
import hashlib
from pathlib import Path
from common import ROOT, OUT, save

def string(maximum, nullable=False):
    return {'type':['string','null'] if nullable else 'string','maxLength':maximum}

label = {'$schema':'https://json-schema.org/draft/2020-12/schema','title':'OpenAliment product label creation v1','type':'object','properties':{},'required':['version_key','name','category','ingredients','allergens','net_quantity','operator'],'additionalProperties':False}
for key, length in {'version_key':60,'name':180,'category':80,'description':2000,'ingredients':5000,'allergens':1500,'net_quantity':100,'operator':500,'storage':1500,'after_opening':1000,'origin':300,'nutrition':3000,'usage':2000}.items():
    label['properties'][key] = string(length, key not in label['required'])
label['properties']['document_ids'] = {'type':'array','maxItems':20,'items':{'type':'string','pattern':'^[a-f0-9]{32}$'}}
if (OUT/'food-data.schema.json').exists():
    label['properties']['food_data']={k:v for k,v in json.loads((OUT/'food-data.schema.json').read_text(encoding='utf-8')).items() if k!='$schema'}
save('product-label.schema.json',label)
batch = {'$schema':'https://json-schema.org/draft/2020-12/schema','title':'OpenAliment batch creation v1','type':'object','required':['product_info_version_id','lot_code'],'properties':{'product_info_version_id':{'type':'string','pattern':'^[a-f0-9]{32}$'},'recipe_version_id':{'type':['string','null'],'pattern':'^[a-f0-9]{32}$'},'lot_code':{'type':'string','minLength':1,'maxLength':120},'public_notes':string(2000,True)},'additionalProperties':False}
for field in ['production_date','best_before','use_by']:
    batch['properties'][field] = {'type':['string','null'],'format':'date'}
save('batch.schema.json',batch)
save('profiles/bread.json',{'schema_version':'openaliment.category_profile.v1','profile_id':'bread','version':'0.1','initiative':'OpenBread','scope':'optional_technical_documentation_and_assessment','applies_to_qr_service':False,'status':'proposed','legacy_standard_ref':'OB-STD-0.1','technical_fields':['hydration_pct_with_basis','preferment_composition','proofing_time_target_and_actual','dough_mass_g','bakers_percent_with_basis'],'pilot':{'project_key':'pbt','recipe_key':'R1','nominal_external_input_g':3790,'technical_status':'experimental'},'rules':['Nu se impun altor categorii.','Țintele de fermentare/coacere nu sunt rezultate măsurate.','Formula și procesul sunt opționale pentru serviciul QR.']})
save('ai-review.schema.json', json.loads((ROOT/'tools/build/templates/ai-review.schema.json').read_text(encoding='utf-8')))
sql=(ROOT/'database/migrations/001_initial.sql').read_text(encoding='utf-8')
tables=[]
migrations=[]
for migration in sorted((ROOT/'database/migrations').glob('*.sql')):
    relative=migration.relative_to(ROOT).as_posix()
    migrations.append({'path':relative,'sha256':hashlib.sha256(migration.read_bytes()).hexdigest()})
    for match in re.finditer(r'CREATE TABLE IF NOT EXISTS (\w+)\s*\((.*?)\) ENGINE=',migration.read_text(encoding='utf-8'),re.S):
        tables.append({'name':match.group(1),'definition_source':relative,'tenant_scoped':'tenant_id ' in match.group(2),'household_scoped':'household_id ' in match.group(2)})
save('data_model.json',{'artifact_type':'active_implementation_model','schema_version':'openaliment.logical_model.v1.2','created_on':'2026-10-23','updated_on':'2026-10-24','source_of_truth':'database/migrations/*.sql','source_sha256':hashlib.sha256(json.dumps(migrations,sort_keys=True,separators=(',',':')).encode()).hexdigest(),'source_sha256_basis':'canonical JSON of ordered migration paths and SHA-256 hashes','id_representation':'128 random bits, 32 lower-case hexadecimal characters','modules':{'A':'product + trade item + immutable label + nullable recipe + batch + QR + ERP API','B':'optional explicit licensed documentation','C':'retained specification, assessment not issued in release 1'},'batch_contract':{'required':['tenant_id','product_id','trade_item_id','product_info_version_id','lot_code'],'optional':['recipe_version_id'],'historical_label_immutable':True,'alerts_current_overlay':True,'publication_releases_batch':False},'gs1_level':'gs1_conformant_resolver_1.2.1','gs1_profile':{'primary_keys':['01'],'qualifiers':['22','10','21','235'],'epc':['SGTIN-96','SGTIN-198','SGTIN+','DSGTIN+','SGTIN++','DSGTIN++'],'evidence':'docs/GS1_IMPLEMENTATION.md'},'migrations':migrations,'category_profiles':['bread'],'tables':tables,'future_specification_source':'OpenBread_Codex_v1_2026-10-23/06_contracte/data_model.json','future_specification_overrides':['OpenAliment / openaliment.ro and all food categories','Independent A/B/C modules','batches.recipe_version_id nullable','Mandatory immutable product_info_version_id independent from recipe','Bread-only technical fields are category profile','No implicit open licence, badge or GS1 rights']})
schemas = {'Product':{'type':'object','required':['name','category'],'properties':{'name':string(180),'category':string(80)}},'TradeItem':{'type':'object','required':['name'],'properties':{'name':string(180),'gtin':string(14,True),'claim_evidence':string(5000,True)}},'Label':{k:v for k,v in label.items() if k!='$schema'},'Batch':{k:v for k,v in batch.items() if k!='$schema'},'Alert':{'type':'object','required':['message','state'],'properties':{'message':string(3000),'state':{'type':'string','enum':['on_hold','recalled']}}},'Resolution':{'type':'object','required':['resolution'],'properties':{'resolution':string(3000)}},'Empty':{'type':'object','additionalProperties':False},'Error':{'type':'object','required':['error_code','message','request_id'],'properties':{k:{'type':'string'} for k in ['error_code','message','request_id']}}}
resolver_properties={
    'href':{'type':'string','format':'uri','maxLength':2048},'title':string(180),
    'types':{'type':'array','minItems':1,'maxItems':12,'items':{'type':'string'}},
    'is_default':{'type':'boolean'},
    'qualifiers':{'type':'object','additionalProperties':False,'properties':{a:string(28 if a=='235' else 20) for a in ['22','10','21','235']}},
    'hreflang':{'type':'array','items':{'type':'string'}},'type':{'type':'string','description':'Tip media înregistrat IANA'},
    'context':{'type':'array','items':{'type':'string'}},'link_key':{'type':'string','pattern':'^[a-f0-9]{32}$'},'expected_version':{'type':'integer','minimum':1}}
schemas.update({'ResolverLink':{'type':'object','required':['href','title','types'],'properties':resolver_properties},'ResolverApproval':{'type':'object','required':['public_projection_confirmed'],'properties':{'public_projection_confirmed':{'const':True}}},'ResolverWithdrawal':{'type':'object','required':['reason'],'properties':{'reason':{'type':'string','minLength':1,'maxLength':1000}}}})
paths={}
for path,scope,schema in [('/manage/products','write','Product'),('/manage/products/{id}/trade-items','write','TradeItem'),('/manage/trade-items/{id}/labels','write','Label'),('/manage/labels/{id}/publish','publish','Empty'),('/manage/trade-items/{id}/batches','write','Batch'),('/manage/batches/{id}/publish','publish','Empty'),('/manage/batches/{id}/release','publish','Empty'),('/manage/batches/{id}/alerts','alerts','Alert'),('/manage/alerts/{id}/resolve','alerts','Resolution'),('/manage/trade-items/{id}/resolver-links','write','ResolverLink'),('/manage/resolver-links/{id}/publish','publish','ResolverApproval'),('/manage/resolver-links/{id}/withdraw','publish','ResolverWithdrawal')]:
    params=[{'name':'Idempotency-Key','in':'header','required':True,'schema':{'type':'string','minLength':8,'maxLength':128}}]
    if '{id}' in path:params.append({'name':'id','in':'path','required':True,'schema':{'type':'string','pattern':'^[a-f0-9]{32}$'}})
    paths[path]={'post':{'summary':scope+': '+schema,'security':[{'bearerAuth':[]}],'x-required-scope':scope,'parameters':params,'requestBody':{'required':True,'content':{'application/json':{'schema':{'$ref':'#/components/schemas/'+schema}}}},'responses':{'201':{'description':'Created or idempotent replay'},'401':{'description':'Authentication required'},'403':{'description':'Scope or role denied'},'409':{'description':'Conflict'},'422':{'description':'Validation error'}}}}
for path,scope in [('/products',None),('/products/{id}',None),('/trade-items/{id}',None),('/batches/{id}',None),('/manage/products','read'),('/manage/qr/item/{id}','read'),('/manage/qr/batch/{id}','read'),('/manage/export','export'),('/manage/trade-items/{id}/resolver-links','read')]:
    op={'summary':'Public projection' if scope is None else scope+': authorized data','security':[] if scope is None else [{'bearerAuth':[]}],'responses':{'200':{'description':'OK'},'404':{'description':'Not found or not public'}}}
    if '{id}' in path:op['parameters']=[{'name':'id','in':'path','required':True,'schema':{'type':'string'}}]
    if scope:op['x-required-scope']=scope
    paths.setdefault(path,{})['get']=op
save('openapi.json',{'openapi':'3.1.0','info':{'title':'OpenAliment ERP API','version':'1.1.0','description':'Produse, loturi, QR, resolver GS1 Digital Link, alerte și transfer de date. Ghid public: https://openaliment.ro/api/docs'},'servers':[{'url':'https://openaliment.ro/api/v1'}],'paths':paths,'components':{'securitySchemes':{'bearerAuth':{'type':'http','scheme':'bearer'}},'schemas':schemas}})
print('Active contracts rebuilt without modifying the handover.')
