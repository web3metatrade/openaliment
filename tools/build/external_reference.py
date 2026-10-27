"""Maintained OFF operation semantics and bounded response contracts."""
from external_contracts import record,resolve,licenses,FIELDS,SHARE_FIELDS
import copy

def install(add,fields,obj,arr,nullable,ref,S,B,N,ID,DATE):
    meanings={
      'source_environment':'production sau staging în infrastructura OFF. Datele de test nu sunt amestecate în catalogul de producție.',
      'api_usage_registered_confirmed':'Confirmarea operatorului că utilizarea API a fost înregistrată la OFF; obligatorie pentru activarea sursei OFF de producție.',
      'supplement':'Observație externă licențiată, separată de profilul aprobat și de datele lotului.',
      'external_state':'Disponibilitatea sursei externe; nu modifică publicarea produsului propriu.',
      'policy_version':'Versiunea regulilor de prioritate și selecție a surselor.',
      'basis_source':'Sursa setului nutritiv din OFF; packaging/manufacturer sunt raportări, estimate rămâne estimare.',
      'input_index':'Poziția setului nutritiv original, păstrată pentru trasabilitatea transformării.',
      'negative_declaration':'Declarație explicită în sursa externă, fără garanția absenței alergenilor pentru lot.',
      'sensitive_usable':'Vârsta observației permite afișarea orientativă; nu reprezintă validare medicală ori a lotului.',
      'observed_at':'Momentul ultimei citiri reușite a acestei versiuni curente; istoricul păstrează momentul său.',
      'external_sources':'Colecție separată de proveniență și observații licențiate în transferul privat; fără acorduri sau acreditări active.',
      'source_code':'Codul original OFF, distinct de GTIN-ul normalizat pe 14 cifre.',
      'source_asset_key':'Amprenta selecției și reviziei fotografiei oficiale, fără cale furnizată de utilizator.',
      'remote_write_available':'false: nu există scriere automată validată către OFF; pachetul producătorului este separat.',
      'structured_facts':'Datele structurate de sănătate necesită confirmarea producătorului înainte de publicare.',
      'external_product_id':'ID-ul fișei globale; nu acordă administrarea articolului comercial.',
      'revision_id':'ID-ul imuabil al observației externe folosite în comparație sau ciornă.',
      'remote_confirmed':'false pentru un pachet pregătit local; confirmarea OFF nu este presupusă.',
      'result_code':'Rezultatul semantic al jobului; found, not_found sau cauza unei erori/reîncercări.',
      'csv_unmapped_fields':'Câmpuri păstrate în JSON, care necesită mapare verificată în instrumentul OFF.',
      'expected_label_id':'Versiunea publicată citită înaintea comparației; null pentru primul import. O valoare veche produce 409.',
      'expected_version':'Versiunea acordului citit anterior; 0 pentru primul acord. Concurența produce 409.',
      'independently_verified':'Confirmare explicită că producătorul a verificat independent câmpurile importate, înaintea redistribuirii.',
      'policy_reviewed_at':'Momentul confirmării administrative a atribuirii și distribuției sub licențele sursei.',
    }
    meanings.update({
      'transport':'producer_package pregătește fișiere pentru import manual, fără autentificare sau scriere remote.',
      'provider':'Furnizorul observației externe; openfoodfacts este singurul adaptor activ implementat.',
      'categories':'Clasificările externe originale, distincte de profilul de categorie al platformei.',
      'licenses':'Licențele explicite pentru bază, conținut și fotografii, cu atribuirea sursei.',
      'fetched_at':'Momentul preluării inițiale a observației locale, distinct de modificarea la sursă.',
      'attribution':'Textul de atribuire obligatoriu pentru datele și contributorii sursei.',
      'retry_after':'Interval minim orientativ în secunde până la următoarea citire a stării jobului.',
      'current_label_id':'Eticheta aprobată folosită ca bază pentru comparație; null înainte de prima publicare.',
      'external':'Valoarea observată în fișa externă, propusă pentru comparație, fără aprobare implicită.',
      'last_seen_revision_id':'Ultima observație pentru care workerul a comparat diferențele asocierii.',
      'last_import_revision_id':'Observația exactă folosită la ultima ciornă importată prin această asociere.',
      'last_import_label_id':'Ciorna creată la ultimul import selectiv al organizației.',
      'current_revision_id':'Observația curentă a fișei globale; istoricul este păstrat separat.',
      'severity':'important pentru diferențe de ingrediente, alergeni sau nutriție; informational pentru celelalte.',
      'fingerprint':'SHA-256 al bazei locale, reviziei externe și diferențelor; deduplică aceeași propunere.',
      'differences':'Diferențele pe câmp între eticheta locală și observația externă.',
      'decision':'Motivul explicit al deciziei asupra diferenței; null înainte de analiză.',
      'direction':'inbound pentru o schimbare observată în OFF față de eticheta locală.',
      'decided_by':'Contul autorizat al organizației care a înregistrat decizia.',
      'decided_at':'Momentul deciziei consemnate în organizație; null cât timp diferența este deschisă.',
      'connection':'Conexiunea privată a organizației pentru pregătirea pachetelor OFF.',
      'supported_transports':'Transporturi validate local; producer_package nu execută scriere în OFF.',
      'remote_organization_ref':'Denumirea organizației declarată pentru pachet, fără verificare remote implicită.',
      'last_error_code':'Ultima cauză tehnică cunoscută, fără mesaje interne sau secrete.',
      'confirmed_at':'Momentul confirmării efective de către destinație; null pentru pachetele locale.',
      'comparison':'Comparația bazei ultimei confirmări cu valorile locale și observația externă.',
      'base_remote_revision':'Revizia OFF cunoscută la pregătire; nu este o garanție de scriere condițională.',
      'assets':'Referințe la imagini publice aprobate și licențele lor, selectate explicit în acord.',
      'base':'Valoarea ultimei contribuții confirmate, dacă există o confirmare reală.',
      'local':'Valoarea etichetei locale aprobate, limitată de acordul activ.',
      'remote':'Valoarea observației externe curente, folosită pentru detectarea conflictelor.',
      'remote_write_enabled':'false în transportul actual; nici activarea sursei nu permite scrieri remote.',
      'verification_scope':'producer_package_format_only: verificarea locală a formatului, fără verificare OFF.',
      'remote_organization_verified':'false: verificarea identității organizației în OFF nu este realizată de acest transport.',
      'installed':'Migrarea catalogului extern este aplicată în mediul curent.',
      'available':'Sursa este activată explicit și politica sa este confirmată în mediul curent.',
      'settings':'Cote și praguri de operare ale adaptorului, fără credentiale private.',
      'limits':'Bugete persistente pe furnizor și tip de operație, inclusiv cooldown.',
      'datasets':'Ultimele importuri și exporturi de date, cu starea și progresul lor.',
      'jobs':'Rezultatele porției de procesare limitate de timpul disponibil.',
      'dataset':'Progresul porției de import sau export executate de worker.',
      'accepted_count':'Numărul de înregistrări deja procesate cu checkpoint persistent.',
    })
    fields.update(meanings)
    # New terms have explicit, source-specific semantics; existing shared descriptions remain.
    names={'brand':'Marca raportată; nu este identitatea legală verificată a producătorului.','contains':'Clase de alergeni raportate sau derivate de OFF.','may_contain':'Posibile urme raportate de sursă.','unmapped':'Etichete de alergeni fără corespondent în cele 14 clase europene.','sets':'Seturi nutritive distincte după bază, preparare și proveniență.','modifiers':'Calificatori precum <; nu se folosesc drept valori exacte în calculator.','scores':'Scoruri calculate de OFF; separate de valorile declarate pe ambalaj.','images':'Selecții de fotografii cu licențe și proveniență pentru fiecare imagine.','nutrition':'Valori raportate și estimări păstrate în seturi separate; nu devin declarații aprobate.','contents':'Licența DbCL aplicabilă conținutului individual al fișei.','database':'Licența ODbL aplicabilă bazei de date externe.','credit':'Atribuirea fotografiei și a contributorilor sursei originale.','basis':'Baza cantitativă sau proveniența conform structurii părinte.','language':'Limba câmpului sau a fotografiei originale.','preparation':'Produs ca atare ori preparat; valorile nu se combină.','source_updated_at':'Momentul actualizării sursei, distinct de preluarea locală.','source_modified_at':'Momentul modificării sursei, dacă este furnizat.','name_language':'Limba denumirii selectate, cu preferință pentru română.','ingredients_language':'Limba listei de ingrediente selectate.','manufacturing_places':'Locuri de fabricație raportate de sursă, fără verificarea identității.','quality_errors':'Avertismentele de calitate raportate de OFF pentru această fișă.','markets':'Piețe raportate în sursă; nu demonstrează că formula unui lot este identică.','origins':'Originea raportată de OFF; separată de operatorul legal.','environment':'Scor de mediu calculat de sursă, dacă este disponibil.','nutriscore':'Scor nutrițional calculat de OFF; nu este recomandare medicală.','nova':'Clasificare calculată de sursă privind procesarea alimentului.','per_portion':'Bază per porție; porția necunoscută nu se transformă în grame.','local_url':'Endpoint same-origin al imaginii verificate și păstrate local; null până la preluare.','source_url':'Pagina sau documentația oficială din care provin datele.','mapping_version':'Versiunea transformării către modelul extern OpenAliment.','api_version':'Versiunea API a furnizorului consultat.','write_available':'false până la validarea unui transport autorizat de scriere.','attribution_url':'Pagina publică OpenAliment cu licențele și atribuirea sursei.','read_state':'disabled, paused sau public; sursa este oprită implicit la instalare.','organization_ref':'Denumirea organizației folosită în pachet, fără a pretinde verificare remote.','public_data_confirmed':'Acord explicit pentru distribuirea publică a câmpurilor selectate.','licenses_confirmed':'Confirmarea licențelor ODbL/DbCL și a efectelor distribuirii publice.','asset_ids':'Imagini publice eligibile, aprobate separat de text.','mode':'manual: se pregătește un pachet, fără expediere automată.','license_policy_confirmed':'Confirmarea operatorului privind atribuirea și exportul reutilizabil al datelor externe.','manifest':'Manifestul fix al pachetului; amprenta și numărul rândurilor sunt verificate înainte de scrieri.','content_base64':'NDJSON codificat base64, maximum 8 MiB după decodare.','record_count':'Numărul exact de înregistrări din pachet.','completion':'Câmpuri completate de producător pentru prima etichetă, fără publicare implicită.','storage_text':'Condiții de păstrare raportate pentru produs; nu stabilesc automat termenul lotului.','stale':'Observație mai veche decât pragul configurat; se afișează sursa și vârsta.'}
    for k,v in names.items():fields.setdefault(k,v)
    external=copy.deepcopy(record);add('ExternalFoodRecord',external)
    resolution=copy.deepcopy(resolve);resolution['properties']['profile']=ref('ConsumerProduct');resolution['properties']['supplement']=nullable(ref('ExternalFoodRecord'));add('ExternalResolution',resolution)
    lookup=obj({'id':ID,'state':S(30),'result_code':nullable(S(80)),'retry_after':N,'supplement':nullable(ref('ExternalFoodRecord'))},['state','result_code','supplement']);add('ExternalLookupResult',lookup)
    source=obj({'id':{'const':'openfoodfacts'},'name':S(180),'api_version':S(20),'mapping_version':S(80),'source_environment':{'enum':['production','staging']},'licenses':licenses,'write_available':{'const':False},'source_url':S(2048),'attribution_url':S(2048)})
    add('ExternalSourceList',obj({'schema_version':{'const':'openaliment.external-sources.v1'},'items':arr(source)}))
    state=obj({'id':ID,'state':S(30),'draft':obj({'id':ID,'state':{'const':'draft'},'revision_id':ID}),'visibility':S(30),'transport':S(30),'remote_write_available':{'const':False}},['id']);add('ExternalChanged',state)
    change=obj({'current':nullable(S(5000)),'external':S(5000)})
    add('ExternalImportPreview',obj({'writes':{'const':False},'revision_id':ID,'external_product_id':ID,'trade_item_id':ID,'current_label_id':nullable(ID),'fields':arr({'enum':FIELDS}),'changes':obj({k:change for k in FIELDS},[]),'licenses':licenses,'source_url':S(2048),'publication':{'const':'requires_separate_approval'},'structured_facts':{'const':'require_producer_confirmation'}}))
    add('ExternalDraftResult',obj({'id':ID,'state':{'const':'draft'},'revision_id':ID}))
    binding=obj({**{k:ID for k in ['id','tenant_id','trade_item_id','external_product_id','created_by']},**{k:nullable(ID) for k in ['last_seen_revision_id','last_import_revision_id','last_import_label_id','current_revision_id']},'state':S(30),'created_at':DATE,'gtin14':S(14),'name':S(180)})
    conflict=obj({**{k:ID for k in ['id','tenant_id','binding_id','external_product_id','trade_item_id','external_revision_id','local_label_id']},'severity':S(30),'fingerprint':S(64),'direction':{'const':'inbound'},'differences':obj({k:obj({'local':nullable(S(5000)),'external':nullable(S(5000))}) for k in FIELDS},[]),'state':S(30),'decision':nullable(S(1500)),'decided_by':nullable(ID),'created_at':DATE,'decided_at':nullable(DATE)})
    def page(item):return obj({'items':arr(item),'next_cursor':nullable(ID),'has_more':B,'limit':N})
    add('ExternalRevisionIndex',page(obj({'id':ID,'gtin14':S(14),'sha256':S(64),'fetched_at':DATE,'mapping_version':S(80)})))
    add('ExternalBindings',page(binding));add('ExternalConflicts',page(conflict))
    connection=obj({'id':ID,'remote_organization_ref':S(180),'transport':{'const':'producer_package'},'state':S(30),'verified_at':nullable(DATE),'last_error_code':nullable(S(80))})
    add('ExternalConnectionResult',obj({'connection':nullable(connection),'remote_write_available':{'const':False},'supported_transports':arr({'const':'producer_package'})}))
    # Private history rows are typed from the migration and remain tenant-scoped.
    # Shared migration parser is intentionally not required here: explicit row fields follow SQL.
    grant=obj({**{k:ID for k in ['id','tenant_id','connection_id','trade_item_id','grant_key','actor_id']},'previous_id':nullable(ID),'version':N,'action':{'enum':['grant','revoke']},'fields':arr({'enum':SHARE_FIELDS}),'asset_ids':arr(ID),'licenses':licenses,'mode':{'const':'manual'},'created_at':DATE})
    publication=obj({**{k:ID for k in ['id','trade_item_id','label_id','grant_id']},'state':S(30),'attempts':N,'confirmed_at':nullable(DATE),'created_at':DATE})
    add('ExternalGrants',page(grant));add('ExternalPublications',page(publication))
    field_values=obj({k:nullable(S(5000)) for k in SHARE_FIELDS},[])
    comparison=obj({'changes':field_values,'conflicts':obj({k:obj({'base':nullable(S(5000)),'local':nullable(S(5000)),'remote':nullable(S(5000))}) for k in SHARE_FIELDS},[])})
    add('ExternalPublicationPreview',obj({**{k:ID for k in ['trade_item_id','label_id','grant_id','connection_id']},'gtin14':S(14),'transport':{'const':'producer_package'},'fields':field_values,'comparison':comparison,'base_remote_revision':nullable(S(60)),'remote_write_available':{'const':False},'licenses':licenses,'assets':arr(obj({'id':ID,'url':S(2048),'sha256':S(64),'mime':S(80),'license':S(80)})),'writes':{'const':False}}))
    add('ExternalPackagePrepared',obj({'id':ID,'state':S(30),'remote_confirmed':{'const':False}},['id','state']))
    add('ExternalGrantResult',obj({'id':ID,'version':N,'state':{'const':'granted'}}))
    add('ExternalEnqueued',obj({'id':ID,'state':S(30),'record_count':N},['id']))
    add('ExternalDatasetPreview',obj({'writes':{'const':False},'record_count':N,'sha256':S(64)}))
    add('ExternalProviderState',obj({'state':{'enum':['disabled','paused','public']},'remote_write_enabled':{'const':False}}))
    add('ExternalVerification',obj({'id':ID,'verification_scope':{'const':'producer_package_format_only'},'remote_organization_verified':{'const':False},'remote_write_available':{'const':False}}))
    # Status/worker retain operational metadata but no credentials or tenant payloads.
    status=obj({'installed':B,'available':B,'provider':{'type':'object','additionalProperties':True},'settings':{'type':'object','additionalProperties':True},'products':N,'pending_jobs':N,'limits':arr({'type':'object','additionalProperties':True}),'remote_write_available':{'const':False},'datasets':arr({'type':'object','additionalProperties':True})},['installed','available','datasets']);add('ExternalProviderStatus',status)
    add('ExternalWorkerResult',obj({'state':S(30),'jobs':arr(obj({'state':S(30),'code':S(80)},['state'])),'dataset':obj({'id':ID,'state':S(30),'accepted_count':N},['state'])},['state']))
    entries={
      'revisionIndex':('Enumeră reviziile subsetului public OFF','ExternalRevisionIndex','Parcurge întregul subset folosind cursorul ID și descarcă fiecare observație prin endpointul reviziei. Ordinea ID-urilor nu este cronologică; pentru actualizări reia parcurgerea, fără a trata cursorul drept feed incremental.'),
      'datasetRetry':('Reia un import sau export eșuat','ExternalEnqueued','Exclusiv administrator cu CSRF. Reia de la checkpoint numai un run failed; integritatea manifestului și artifactului este reverificată. Un export invalidat prin retragere necesită un export nou.'),
      'resolve':('Rezolvă scanarea fără acces la rețeaua externă','ExternalResolution','Întoarce profilul nativ aprobat și suplimentul OFF separat. Nu trimite QR-ul, lotul sau seria către OFF. Lipsa sursei nu blochează inventarul ori QR-ul.'),
      'lookup':('Caută un GTIN în Open Food Facts','ExternalLookupResult','Consultă cache-ul înainte de admitere. Maximum 20 GTIN-uri distincte/IP/oră; buget OFF persistent, coadă finită, răspuns 202 cu Retry-After când este în așteptare. OFF primește numai GTIN-ul.'),
      'lookupStatus':('Citește rezultatul căutării externe','ExternalLookupResult','GET nu contactează OFF și nu pornește procesare. Respectă Retry-After; completed cu not_found este un rezultat distinct de o eroare temporară.'),
      'sources':('Listează sursele externe active','ExternalSourceList','Include numai furnizorii activați, licențele și versiunile adaptorului. Instalarea nu activează automat sursa; lista goală este validă.'),
      'revision':('Descarcă o observație externă imuabilă','ExternalFoodRecord','ID-ul fixează observația și proveniența, inclusiv valorile estimate distincte. Datele suspendate nu sunt publicate; arhivele sunt verificate prin SHA-256.'),
      'asset':('Descarcă imaginea externă verificată',None,'Servește numai octeții validați și păstrați local ai unei fișe publice. GET nu face proxy către un URL arbitrar. Fotografia păstrează atribuirea și licența CC BY-SA.'),
      'export':('Descarcă subsetul OFF reutilizabil',None,'Descarcă un artifact NDJSON pregătit în fundal și verificat prin hash. Include fișele și licențele, fără organizații, bindings, conflicte sau date personale. 404 până la primul export complet.'),
      'bindings':('Listează asocierile articolului cu OFF','ExternalBindings','Lista este privată organizației și paginată după ID. Asocierea nu acordă drepturi asupra GTIN și nu înlocuiește informațiile publicate.'),
      'bind':('Asociază articolul cu aceeași identitate externă','ExternalChanged','Permite asocierea numai pentru același GTIN normalizat. Necesită write și apartenența la organizație; nu publică date și nu transferă mandatul GS1.'),
      'disconnectBinding':('Oprește asocierea cu sursa externă','ExternalChanged','Verifică atât articolul părinte, cât și binding-ul din aceeași organizație. Păstrează istoricul importurilor și proveniența ciornelor create anterior.'),
      'preview':('Compară câmpurile externe fără scrieri','ExternalImportPreview','Selectează o revizie exactă și câmpuri permise. Returnează diferențele și licențele; nu creează etichetă, nu schimbă date aprobate și nu cere Idempotency-Key.'),
      'draft':('Precompletează o ciornă din OFF','ExternalDraftResult','Cere write, Idempotency-Key și un version_key nou. expected_label_id previne suprascrierea după o schimbare concurentă. Declarațiile structurate afectate devin unknown până la confirmarea producătorului.'),
      'conflicts':('Listează diferențele pentru organizație','ExternalConflicts','Diferențele sunt observații private față de eticheta publicată. Modificările OFF nu schimbă automat etichete, loturi istorice sau alerte. Lista folosește cursor ID.'),
      'decision':('Înregistrează decizia asupra diferenței','ExternalChanged','Necesită rol owner/quality și publish. Acceptarea creează doar o ciornă nouă; rejected și different_market păstrează motivul în istoric. O bază locală schimbată produce 409.'),
      'connection':('Citește conexiunea pentru pachete OFF','ExternalConnectionResult','Conexiunea este privată organizației. producer_package înseamnă pregătire pentru import manual; nu este acreditare OFF sau verificare a organizației remote.'),
      'connect':('Configurează conexiunea pentru producător','ExternalChanged','Necesită owner și sharing:manage. Sesiunea web confirmă recent autentificarea; un token trebuie să aibă scope explicit. Nu se stochează parole OFF și nu se activează scrierea automată.'),
      'disconnect':('Oprește distribuirea către OFF','ExternalChanged','Necesită owner și sharing:manage. Anulează pachetele neexpediate și păstrează istoricul. Nu promite retragerea copiilor deja distribuite sub licență deschisă.'),
      'grants':('Listează istoricul acordurilor de distribuire','ExternalGrants','Acordurile sunt versiuni imuabile, cu selecție de câmpuri, imagini și licențe. Numai ultima versiune grant activă poate permite un pachet nou.'),
      'grant':('Înregistrează acordul explicit pentru articol','ExternalGrantResult','Necesită owner, sharing:manage și mandat GTIN verificat. Confirmările privind drepturile și licențele sunt obligatorii; expected_version protejează actualizările concurente.'),
      'revoke':('Revocă acordul de distribuire','ExternalChanged','Adaugă o versiune de revocare și anulează pachetele pregătite în aceeași tranzacție. Necesită ultima versiune a acordului și nu șterge dovada consimțământului anterior.'),
      'publicationPreview':('Verifică o contribuție fără a o distribui','ExternalPublicationPreview','Folosește numai câmpurile aprobate selectate în acord. Datele preluate din OFF nu sunt retrimise fără confirmare independentă. Comparația în trei versiuni nu este o tranzacție distribuită.'),
      'publication':('Pregătește pachetul producătorului','ExternalPackagePrepared','Necesită sharing:write și rol owner/quality. Fixează eticheta publicată și acordul activ. package_ready nu înseamnă transmis ori confirmat; câmpurile fără mapare CSV rămân în JSON.'),
      'publications':('Listează istoricul pachetelor organizației','ExternalPublications','Lista este privată și paginată după ID. Descărcarea pachetului este separată de pregătire și reverifică mandatul, conexiunea, acordul și drepturile imaginilor.'),
      'package':('Descarcă pachetul autorizat pentru OFF',None,'ZIP cu products.csv, contribution.json și instrucțiuni de mapare. Numai coloanele documentate cu echivalent semantic intră automat în CSV. Revocarea sau pierderea mandatului blochează descărcarea.'),
      'state':('Activează, suspendă sau oprește sursa','ExternalProviderState','Exclusiv sesiune administrativă cu CSRF. Activarea cere confirmarea atribuirii și exportului licențiat. Oprirea nu afectează produsul, lotul, QR-ul sau inventarul personal.'),
      'status':('Citește starea operațională OFF','ExternalProviderStatus','Exclusiv administrator. Arată cote, cozi, bugete și starea importurilor, fără secrete sau inventare personale. Folosește acest rezultat pentru supravegherea workerului.'),
      'verify':('Consemnează verificarea formatului de pachet','ExternalVerification','Exclusiv administrator. Verificarea privește formatul producer_package și se auditează. Nu confirmă identitatea organizației în OFF și nu activează un transport remote.'),
      'visibility':('Suspendă sau restabilește o observație','ExternalChanged','Necesită motiv și sesiune administrativă cu CSRF. Suspendarea blochează citirea publică și invalidează exporturile anterioare; referințele istorice private rămân păstrate.'),
      'retry':('Reprogramează o căutare eșuată','ExternalEnqueued','Exclusiv administrator. Creează sau reutilizează jobul activ al aceluiași GTIN; nu ocolește bugetul persistent, cooldown-ul ori starea furnizorului.'),
      'datasetPreview':('Validează pachetul NDJSON fără import','ExternalDatasetPreview','Verifică manifestul, SHA-256, numărul rândurilor, schema și GTIN-urile. Maximum 8 MiB decodat; nu descarcă URL-uri din manifest și nu scrie produse.'),
      'dataset':('Programează importul verificat NDJSON','ExternalEnqueued','Exclusiv administrator. Revalidează întregul pachet, îl păstrează în stocarea privată și procesează câte zece înregistrări cu checkpoint. Reluarea deduplică observațiile.'),
      'datasetExport':('Programează exportul licențiat OFF','ExternalEnqueued','Fixează lista reviziilor eligibile într-un manifest imuabil. Workerul scrie incremental și verifică artifactul; publicul descarcă numai exporturi complete.'),
      'worker':('Procesează o porție limitată a cozii OFF','ExternalWorkerResult','Exclusiv administrator, pentru hosting fără cron configurat. Execută maximum zece secunde de joburi și o porție de dataset; nu oferă shell sau execuție arbitrară.'),
    }
    def metadata(path,method,handler):return entries.get(handler[9:]) if handler.startswith('external.') else None
    return metadata
