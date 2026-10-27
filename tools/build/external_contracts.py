"""External catalog contracts. Producer FoodData and household v1 remain unchanged."""
from common import save


def obj(properties, required=None, extra=False):
    return {'type':'object','properties':properties,'required':list(properties) if required is None else required,'additionalProperties':extra}


def text(n=180):
    return {'type':'string','maxLength':n}


def nullable(value):
    return {'anyOf':[value,{'type':'null'}]}


ID={'type':'string','pattern':'^[a-f0-9]{32}$'}
GTIN={'type':'string','pattern':'^(?:[0-9]{8}|[0-9]{12}|[0-9]{13}|[0-9]{14})$'}
FIELDS=['name','ingredients','allergens','net_quantity','storage','nutrition']
SHARE_FIELDS=['name','category','ingredients','allergens','net_quantity','operator','storage','nutrition','origin']
NUTRIENTS=['energy_kj','energy_kcal','fat_g','saturates_g','carbohydrate_g','sugars_g','polyols_g','fibre_g','protein_g','salt_g']
ALLERGENS=['gluten','crustaceans','eggs','fish','peanuts','soy','milk','nuts','celery','mustard','sesame','sulphites','lupin','molluscs']
arr=lambda schema,maximum=100:{'type':'array','maxItems':maximum,'items':schema}
license_=obj({'id':text(60),'url':text(2048)})
licenses=obj({'database':license_,'contents':license_,'images':license_,'attribution':text(500),'source_url':text(2048)})
fact=obj({'provider':{'const':'openfoodfacts'},'basis':{'enum':['reported','derived','mixed','unknown']},'source_url':text(2048),'language':text(3)})
nutrition_set=obj({'basis':{'enum':['per_100_g','per_100_ml','per_portion']},'preparation':{'enum':['as_sold','as_prepared']},'values':obj({key:{'type':'number','minimum':0,'maximum':100000} for key in NUTRIENTS},[]),'modifiers':obj({key:text(10) for key in NUTRIENTS},[]),'basis_source':text(40),'input_index':{'type':'integer','minimum':0},'kind':{'enum':['reported','estimated']},'portion':{'type':'null'}})
image=obj({'kind':{'enum':['front','ingredients','nutrition','packaging']},'language':text(3),'url':text(2048),'source_asset_key':text(64),'license':license_,'credit':text(500),'id':nullable(ID),'local_url':nullable(text(2048))})
record=obj({'source_environment':{'enum':['production','staging']},'schema_version':{'const':'openaliment.external-food.v1'},'provider':{'const':'openfoodfacts'},'gtin14':{'type':'string','pattern':'^[0-9]{14}$'},'source_code':GTIN,'name':nullable(text()),'name_language':text(3),'brand':nullable(text(500)),'net_quantity':nullable(text(100)),'ingredients':nullable(text(5000)),'ingredients_language':text(3),'storage_text':nullable(text(1500)),'categories':arr(text(150)),'allergens':obj({'status':{'enum':['reported','derived','unknown']},'contains':arr({'enum':ALLERGENS},14),'may_contain':arr({'enum':ALLERGENS},14),'unmapped':arr(text(150),200),'negative_declaration':{'type':'boolean'},'source':{'const':'openfoodfacts'}}),'nutrition':obj({'status':{'enum':['available','unknown']},'sets':arr(nutrition_set,12)}),'scores':obj({'nutriscore':nullable(text(20)),'nova':nullable({'type':'integer'}),'environment':nullable(text(20))}),'origins':nullable(text(500)),'manufacturing_places':nullable(text(500)),'markets':arr(text(150)),'quality_errors':arr(text(150)),'images':arr(image,4),'source_url':text(2048),'licenses':licenses,'provenance':obj({key:fact for key in ['name','brand','net_quantity','ingredients','categories','allergens','nutrition','scores','origins','manufacturing_places']}),'id':ID,'external_product_id':ID,'revision':nullable(text(60)),'sha256':text(64),'fetched_at':text(40),'observed_at':text(40),'source_modified_at':nullable(text(40)),'source_updated_at':nullable(text(40)),'stale':{'type':'boolean'},'sensitive_usable':{'type':'boolean'}})
resolve=obj({'schema_version':{'const':'openaliment.consumer-resolution.v1'},'profile':{'$ref':'consumer-product-profile.schema.json'},'supplement':nullable({'$ref':'external-food-record.schema.json'}),'external_state':{'enum':['available','stale','queued','not_found','temporarily_unavailable','not_requested','not_applicable']},'policy_version':{'const':'openaliment.source-selection.v1'}})
manifest=obj({'schema_version':{'const':'openaliment.external-dataset.v1'},'provider':{'const':'openfoodfacts'},'license':{'const':'ODbL-1.0'},'source_environment':{'enum':['production','staging']},'sha256':{'type':'string','pattern':'^[a-f0-9]{64}$'},'record_count':{'type':'integer','minimum':1,'maximum':10000},'source_url':text(2048)},['provider','license','sha256','record_count'])


def requests():
    field_list=arr({'enum':FIELDS},6);field_list.update(minItems=1,uniqueItems=True)
    completion=obj({key:text(5000 if key=='ingredients' else 1500) for key in ['category','operator','ingredients','allergens','net_quantity']},[])
    return {
        'ExternalResolve':obj({'code':{'type':'string','minLength':1,'maxLength':4096}}),
        'ExternalLookup':obj({'gtin':GTIN}),
        'ExternalBinding':obj({'external_product_id':ID}),
        'ExternalImport':obj({'revision_id':ID,'fields':field_list,'version_key':text(60),'expected_label_id':nullable(ID),'completion':completion},['revision_id']),
        'ExternalDecision':obj({'state':{'enum':['accepted','rejected','different_market']},'reason':text(1500),'version_key':text(60),'fields':field_list,'completion':completion},['state','reason']),
        'ExternalSensitiveEmpty':obj({'current_password':text(200)},[]),
        'ExternalConnection':obj({'current_password':text(200),'organization_ref':text(180),'transport':{'const':'producer_package'}},['organization_ref']),
        'ExternalGrant':obj({'current_password':text(200),'trade_item_id':ID,'fields':{**arr({'enum':SHARE_FIELDS},9),'minItems':1,'uniqueItems':True},'asset_ids':{**arr(ID,10),'uniqueItems':True},'mode':{'const':'manual'},'expected_version':{'type':'integer','minimum':0},'public_data_confirmed':{'const':True},'licenses_confirmed':{'const':True}},['trade_item_id','fields','public_data_confirmed','licenses_confirmed']),
        'ExternalPublication':obj({'label_id':ID,'independently_verified':{'type':'boolean'}},[]),
        'ExternalState':obj({'state':{'enum':['disabled','paused','public']},'license_policy_confirmed':{'type':'boolean'},'api_usage_registered_confirmed':{'type':'boolean'}},['state']),
        'ExternalVisibility':obj({'visibility':{'enum':['public','suspended']},'reason':text(1000)}),
        'ExternalDataset':obj({'manifest':manifest,'content_base64':{'type':'string','minLength':1,'maxLength':11500000}}),
    }


if __name__=='__main__':
    for filename,schema in [('external-food-record.schema.json',record),('consumer-resolution.schema.json',resolve),('external-dataset-manifest.schema.json',manifest),('external-lookup.schema.json',requests()['ExternalLookup']),('external-import.schema.json',requests()['ExternalImport']),('external-conflict-decision.schema.json',requests()['ExternalDecision']),('external-sharing.schema.json',requests()['ExternalGrant'])]:
        save(filename,{'$schema':'https://json-schema.org/draft/2020-12/schema',**schema})

    import json,copy
    from common import OUT
    transfer=json.loads((OUT/'consumer-transfer.schema.json').read_text(encoding='utf-8'))
    transfer['title']='OpenAliment personal transfer v2 with licensed external observations'
    transfer['properties']['schema_version']={'const':'openaliment.home.transfer.v2'}
    transfer['properties']['data']['properties']['external_records']=arr({'$ref':'external-food-record.schema.json'},128)
    transfer['properties']['data']['required'].append('external_records')
    save('consumer-external-transfer.schema.json',transfer)
