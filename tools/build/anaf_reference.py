"""ANAF request schemas, complete source field types and operation semantics."""
import copy,json
from common import ROOT

def requests(obj,text,ID):
    return {
      'OrganizationLookup':obj({'cui':dict(text(32),description='CUI românesc ca șir, cu prefix RO opțional. Nu trimite CNP.')},['cui']),
      'OrganizationLegalConfirmation':obj({'organization_lookup_id':ID,'expected_version_id':{'type':['string','null'],'pattern':'^[a-f0-9]{32}$'},'current_password':dict(text(200),writeOnly=True)},['organization_lookup_id','expected_version_id']),
    }

def install(add,fields,obj,arr,nullable,ref,S,B,N,ID,DATE):
    DATE={'type':'string','format':'date-time','description':'Moment UTC ISO 8601; data de referință ANAF este separată.'}
    definition=json.loads((ROOT/'contracts/anaf-fields.json').read_text(encoding='utf-8'))
    props={group:{} for group in definition['groups']}
    period={}
    sample={group:{} for group in definition['groups']}
    sample_period={}
    for field in definition['fields']:
        group=field['path'].split('.')[0];key=field['path'].split('.')[-1]
        schema={'type':['boolean','null']} if field['type']=='boolean' else {'type':['string','null'],'maxLength':20000}
        if key=='cui':schema={'type':['integer','string'],'description':'CUI returnat de ANAF; identitatea se compară cu CUI-ul cerut.'}
        schema['description']=field['label']+'. Valoarea lipsă/null/șir gol rămâne nefurnizată.'
        if '[]' in field['path']:
            period[key]=schema;sample_period[key]=''
        else:
            props[group][key]=schema;sample[group][key]=None if field['type']=='boolean' else ''
    props['inregistrare_scop_Tva']['perioade_TVA']={'oneOf':[{'type':'array','maxItems':1000,'items':obj(period,[],extra=True)},obj(period,[],extra=True),{'type':'null'}],'description':'Lista perioadelor TVA. Unele surse pot furniza un singur obiect; forma sursei este păstrată.'}
    sample['inregistrare_scop_Tva']['perioade_TVA']=[sample_period]
    sample['date_generale'].update({'cui':900000001,'data':'2026-10-28','denumire':'Organizație sintetică ANAF','nrRegCom':'J2026000000001'})
    sample['inregistrare_scop_Tva']['scpTVA']=True
    sample_period['data_inceput_ScpTVA']='2026-01-01'
    record=obj({group:obj(values,[],extra=True) if group=='date_generale' else nullable(obj(values,[],extra=True)) for group,values in props.items()},['date_generale'],extra=True)
    for group,value in record['properties'].items():value['description']=definition['groups'][group]+', exact din răspunsul ANAF; grupul poate lipsi la sursă.'
    add('AnafRecord',record,sample)
    source=obj({'provider':{'const':'anaf'},'api_version':{'const':'v9'},'mapping_version':{'const':'openaliment.anaf.v1'},'endpoint':{'const':'https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva'},'queried_for':{'type':'string','format':'date'},'fetched_at':DATE,'sha256':{'type':'string','pattern':'^[a-f0-9]{64}$'}})
    source['required']=list(source['properties'])
    source['properties']['provider']['description']='Registrul public ANAF este furnizorul observației fiscale.'
    source['properties']['endpoint']['description']='Adresa fixă HTTPS a serviciului ANAF v9; nu este un URL introdus de utilizator.'
    sample_source={'provider':'anaf','api_version':'v9','mapping_version':'openaliment.anaf.v1','endpoint':'https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva','queried_for':'2026-10-28','fetched_at':'2026-10-28T12:00:00+00:00','sha256':'a'*64}
    add('AnafSource',source,sample_source)
    add('AnafTransferVersion',obj({'id':ID,'origin':{'enum':['anaf','imported']},'confirmed_at':{'type':'string','description':'Momentul confirmării la sursă, UTC în format SQL YYYY-MM-DD HH:mm:ss.'},'cui':S(10),'queried_for':{'type':'string','format':'date'},'response_json':{'type':'string','maxLength':1048576,'description':'Răspunsul ANAF original integral, păstrat ca șir pentru verificarea exactă a octeților.'},'response_sha256':{'type':'string','pattern':'^[a-f0-9]{64}$','description':'SHA-256 al octeților response_json, verificat la import.'},'fetched_at':{'type':'string','description':'Momentul preluării la sursă, UTC în format SQL YYYY-MM-DD HH:mm:ss.'}}))
    scalar={'type':['string','boolean','integer','number','null']}
    lookup_props={'id':ID,'cui':S(10),'state':{'enum':['found','not_found']},'record':nullable(ref('AnafRecord')),'warnings':arr(S(1000)),'source':ref('AnafSource'),'expires_at':DATE,'expected_version_id':nullable(ID),'differences':arr(obj({'path':S(2048),'before':scalar,'after':scalar},['path','before','after']))}
    lookup=obj(lookup_props,list(lookup_props))
    lookup_example={'id':'1'*32,'cui':'900000001','state':'found','record':sample,'warnings':[],'source':sample_source,'expires_at':'2026-10-28T12:20:00+00:00','expected_version_id':None,'differences':[]}
    add('AnafLookupResult',lookup,lookup_example)
    version_props={key:value for key,value in lookup_props.items() if key not in ['expires_at','expected_version_id','differences']}
    version_props.update({'state':{'const':'found'},'record':ref('AnafRecord'),'legal_name':S(10000),'origin':{'enum':['anaf','imported']},'confirmed_at':DATE,'directly_verified':B})
    version_example={k:v for k,v in lookup_example.items() if k in version_props}
    version_example.update({'legal_name':sample['date_generale']['denumire'],'origin':'anaf','confirmed_at':'2026-10-28T12:01:00+00:00','directly_verified':True})
    add('OrganizationLegalVersion',obj(version_props,list(version_props)),version_example)
    profile_props={'profile':nullable(ref('OrganizationLegalVersion')),'representation_verified_by_anaf':{'const':False},'gs1_rights_granted_by_anaf':{'const':False}}
    add('OrganizationLegalProfileResult',obj(profile_props,list(profile_props)),{'profile':version_example,'representation_verified_by_anaf':False,'gs1_rights_granted_by_anaf':False})
    add('OrganizationLegalHistoryResult',obj({'items':arr(ref('OrganizationLegalVersion')),'next_cursor':nullable(ID),'has_more':B,'limit':{'type':'integer','minimum':1,'maximum':100}},['items','next_cursor','has_more','limit']),{'items':[version_example],'next_cursor':None,'has_more':False,'limit':20})
    fields.update({'organization_legal_history':'Versiuni fiscale complete cu proveniență în transferul privat; importarea nu acordă mandat sau verificare directă ANAF.', 'record':'Fișa completă a firmei furnizată de ANAF, cu valori goale și câmpuri viitoare păstrate; null pentru CUI negăsit.','legal_name':'Denumirea oficială integrală a firmei din observația fiscală, distinctă de numele de afișare al spațiului.','path':'Pointer JSON către valoarea ANAF schimbată; segmentele folosesc escaparea ~0 și ~1, iar listele au indici.','warnings':'Avertismente de situație fiscală pentru data consultată; nu acordă sau retrag automat drepturi GS1.','cui':'Codul unic de identificare fiscală românesc; prefixul RO opțional este eliminat numai pentru consultare.', 'queried_for':'Ziua de referință a registrului ANAF, stabilită în Europe/Bucharest, distinctă de momentul preluării.', 'representation_verified_by_anaf':'false: consultarea registrului nu verifică mandatul reprezentantului.', 'gs1_rights_granted_by_anaf':'false: registrul fiscal nu acordă drepturi de utilizare a identificatorilor GS1.', 'organization_lookup_id':'Referința serverului la verificarea CUI-ului; legată de sesiunea/ownerul propriu, expiră în 20 de minute și se consumă o singură dată.','expected_version_id':'Versiunea profilului citită înainte de reverificare; null dacă profilul nu există. O versiune schimbată produce 409.','directly_verified':'true numai pentru date consultate direct prin integrarea ANAF; importurile rămân false până la reverificare.'})
    descriptions={
      'anaf.lookup':('Consultă CUI-ul pentru înregistrare','AnafLookupResult','Cere mai întâi GET /api/v2/identity/session și păstrează cookie-ul oa_session și csrf. Trimite X-CSRF-Token. Înscrierile și migrarea ANAF trebuie să fie active. Nu acceptă chei ERP/Bearer. Consultă un singur CUI; rezultatul not_found este HTTP 200 și nu înseamnă indisponibilitatea ANAF. Fiecare nouă verificare invalidează rezultatul anterior din aceeași sesiune.'),
      'anaf.result':('Recitește verificarea ANAF proprie','AnafLookupResult','Necesită același cookie de sesiune care a inițiat verificarea. ID-ul singur nu acordă acces. 404 pentru altă sesiune; 409 pentru rezultat înlocuit, consumat sau expirat. Nu interoghează din nou ANAF.'),
      'anaf.profile':('Citește profilul fiscal al organizației','OrganizationLegalProfileResult','Date private ale tenantului curent. profile este null pentru conturi vechi fără profil fiscal. ANAF nu acordă mandat, verificare email, drepturi GTIN sau sigle. origin=imported și directly_verified=false indică o copie de transfer care necesită reverificare.'),
      'anaf.history':('Listează istoricul profilului fiscal','OrganizationLegalHistoryResult','Versiuni imuabile cu datele complete și proveniența. Paginare exclusivă prin ID; ordinea ID-urilor nu este cronologică. Nu rescrie etichetele sau loturile istorice.'),
      'anaf.check':('Reverifică datele fiscale fără aplicare','AnafLookupResult','Exclusiv owner cu scope write. Pentru un profil existent, CUI-ul trebuie să rămână același. Pentru primul profil al unui cont vechi, furnizează CUI. Creează verificarea și diferențele, fără a înlocui profilul. Nu necesită Idempotency-Key și nu ține o tranzacție SQL în timpul apelului ANAF.'),
      'anaf.confirm':('Confirmă versiunea profilului fiscal','OrganizationLegalProfileResult','Exclusiv owner cu scope write. Trimite verificarea proprie și expected_version_id primit anterior. Necesită Idempotency-Key. Cu cookie, confirmă current_password dacă sesiunea are peste 15 minute; un Bearer owner cu write constituie autorizarea delegată. Nu schimbă CUI-ul unui profil existent și nu acordă drepturi GS1.'),
      'anaf.admin-profile':('Consultă profilul fiscal pentru verificare','OrganizationLegalProfileResult','Exclusiv sesiunea administratorului platformei; cheile ERP și granturile OAuth nu oferă acces administrativ. Separă datele fiscale de probele mandatului. ID inexistent produce 404.'),
    }
    def metadata(path,method,handler):
        entry=descriptions.get(handler)
        return (entry[0],ref(entry[1]),entry[2]) if entry else None
    return metadata
