"""Marketplace and GS1 production-line contracts; examples contain no real credentials."""

def requests(obj, text, ID):
    codes={'type':'array','maxItems':20,'uniqueItems':True,'items':{'type':'string','pattern':'^[a-z0-9][a-z0-9._:-]{0,63}$'}}
    listing=obj({**{k:text(n) for k,n in {'slug':100,'name':120,'summary':240,'description':10000,'publisher':180,'category':64,'license':120,'website':2048,'support_url':2048,'license_url':2048}.items()},**{k:codes for k in ['tags','capabilities','platforms','compatibility']},'pricing':{'enum':['free','paid','quote']},'oauth_client_id':{'anyOf':[ID,{'type':'null'},{'const':''}]},'expected_revision':{'type':'integer','minimum':1}},['name','summary','description','publisher','category','license','website','support_url','license_url'])
    listing['example']={'slug':'conector-erp-exemplu','name':'Conector ERP exemplu','summary':'Sincronizare prin API pentru produse și loturi.','description':'Agent local cu mapare declarativă și autorizare explicită.','publisher':'Dezvoltator exemplu','category':'erp','license':'MIT','website':'https://developer.example/','support_url':'https://developer.example/support','license_url':'https://developer.example/license','tags':['erp'],'capabilities':['catalog-sync'],'platforms':['windows-x64'],'compatibility':['api'],'pricing':'free','expected_revision':1}
    result = {
        'Gs1Preserve':obj({'document_id':{'enum':['uri-syntax','resolver','epc-compression','general-specifications']}},['document_id']),
        'MarketplaceWithdrawal':obj({'reason':text(1500),'release_id':ID},['reason']),
        'MarketplaceListing':listing,
        'MarketplaceRelease':obj({'version':{'type':'string','pattern':'^[0-9]+\\.[0-9]+\\.[0-9]+(?:-[a-zA-Z0-9.-]+)?$','maxLength':40},'artifact_url':text(2048),'sha256':{'type':'string','pattern':'^[a-f0-9]{64}$'},'size_bytes':{'type':'integer','minimum':1,'maximum':2147483647},'platforms':codes,'changelog':text(10000),'signature_url':{'type':['string','null'],'maxLength':2048}},['version','artifact_url','sha256','size_bytes','changelog']),
        'MarketplaceReview':obj({'decision':{'enum':['approve_listing','approve_release','withdraw_release','suspend']},'reason':text(1500),'expected_revision':{'type':'integer','minimum':1},'release_id':ID,'artifact_verified':{'type':'boolean'}},['decision','reason']),
        'MarketplaceSelection':obj({'active':{'type':'boolean'}},['active']),
        'PrintDevice':obj({'name':text(120),'format':{'enum':['json','zpl']},'current_password':text(200)}),
        'PrintJob':obj({'device_id':ID,'batch_id':ID,'quantity':{'type':'integer','minimum':1,'maximum':500},'serials':{'type':'array','minItems':1,'maxItems':500,'uniqueItems':True,'items':text(20)}},['device_id','batch_id','quantity']),
        'PrintReason':obj({'reason':text(1000)},['reason']),
        'PrintReprint':obj({'reason':text(1000),'same_serials_confirmed':{'const':True},'device_id':ID},['reason','same_serials_confirmed']),
        'PrintAcknowledgement':obj({'claim_token':{'type':'string','pattern':'^[a-f0-9]{64}$','writeOnly':True},'state':{'enum':['dispatched','confirmed','failed','uncertain']},'evidence':text(1500)},['claim_token','state','evidence']),
    }

    result['MarketplaceRelease']['example']={'version':'1.0.0','artifact_url':'https://developer.example/releases/connector-1.0.0.zip','sha256':'a'*64,'size_bytes':12345,'platforms':['windows-x64'],'changelog':'Prima versiune a conectorului.','signature_url':None}
    result['MarketplaceReview']['example']={'decision':'approve_listing','reason':'Verificarea dezvoltatorului și a descrierii a fost consemnată.','expected_revision':1}
    result['PrintDevice']['example']={'name':'Linia de etichetare 1','format':'json'}
    return result

def install(add,fields,obj,arr,nullable,ref,S,B,N,ID,DATE):
    fields.update({'marketplace':'Marketplace cu oferte și versiuni aprobate separat.','serialized_printing':'Tipărire cu serii GS1 individuale și agenți locali dedicați.','marketplace_payments':'false: plățile și comisioanele nu sunt procesate prin platformă.','created_by':'Contul autorizat care a creat comanda de tipărire.','license':'Licența software declarată pentru integrare, independentă de licența platformei.'})
    fields.update({'release_id':'ID-ul versiunii exacte a integrării; dacă lipsește la retragere, se retrage întreaga ofertă.'})
    fields.update({'summary':'Prezentare scurtă a integrării, publicată numai după aprobare.','last_seen_at':'Ultima comunicare a agentului cu platforma, în UTC; null înainte de conectare.','events':'Istoricul stărilor comenzii, cu dovezile raportate și momentul UTC.'})
    fields.update({
        'slug':'Adresă persistentă a integrării, cu litere mici, cifre și liniuțe.',
        'publisher':'Numele public al dezvoltatorului, verificat odată cu oferta.',
        'pricing':'free: pachet gratuit; paid: licență comercială; quote: ofertă la cerere. Tranzacțiile sunt dezactivate.',
        'website':'Adresa HTTPS publică a dezvoltatorului; nu se accesează automat de către server.',
        'support_url':'Pagina HTTPS de suport sau contact a dezvoltatorului.',
        'license_url':'Termenii licenței software declarate pentru integrare; independenți de licența platformei.',
        'license_scope':'Licența aplicabilă se stabilește pentru fiecare ofertă, fără licențiere implicită.',
        'tags':'Etichete extensibile declarate de dezvoltator, fără privilegii implicite.',
        'platforms':'Platforme declarate, de exemplu windows-x64; nu reprezintă o certificare hardware.',
        'compatibility':'Produse, protocoale sau echipamente declarate compatibile de dezvoltator.',
        'capabilities':'Funcții declarate ale integrării; nu acordă acces la organizații sau date.',
        'oauth_client_id':'ID-ul unei aplicații OAuth active deținute de autor; autorizarea utilizatorului rămâne separată.',
        'expected_revision':'Revizia ciornei citite anterior; o valoare veche produce 409 fără suprascriere.',
        'submitted_revision':'Revizia exactă trimisă spre verificare; null când nu există o trimitere curentă.',
        'approved_revision':'Revizia exactă aprobată public; ciornele ulterioare nu o înlocuiesc.',
        'approved_json':'Snapshot aprobat al ofertei; modificările private rămân separate.',
        'draft':'Ciorna autorizată a dezvoltatorului, exclusă din API-ul public.',
        'owner_user_id':'Contul dezvoltatorului care poate administra oferta; nu este organizația producătorului.',
        'releases':'Versiuni imuabile ale integrării, filtrate după aprobarea și vizibilitatea solicitată.',
        'artifact_url':'Destinația HTTPS publică a pachetului la dezvoltator. Nu conține credentiale; null pentru ofertele comerciale inactive.',
        'signature_url':'Adresa HTTPS a semnăturii publicate de dezvoltator; null dacă lipsește.',
        'changelog':'Modificările declarate pentru versiunea exactă a integrării.',
        'manifest_json':'Manifest imuabil codificat ca text JSON; nu se execută pe hosting.',
        'manifest_sha256':'SHA-256 al octeților UTF-8 ai manifestului stocat exact; nu al proiecției publice redactate.',
        'review_json':'Motivul și dovezile verificării administrative, în JSON; null înaintea verificării.',
        'reviewed_at':'Momentul verificării administrative în UTC; nu este o certificare independentă.',
        'artifact_verified':'Confirmare explicită a administratorului că a verificat pachetul și dovezile consemnate.',
        'commerce_enabled':'false: nu sunt procesate plăți, comisioane sau achiziții prin platformă.',
        'distribution':'publisher_https: fișierul se distribuie de pe infrastructura dezvoltatorului.',
        'authorization':'Accesul la date cere autorizare OAuth explicită ori o cheie API proprie cu scopes limitate.',
        'api_access_granted':'false: selectarea unei integrări nu creează credentiale sau granturi API.',
        'device_id':'Echipamentul înregistrat în aceeași organizație, cu un credential dedicat și revocabil.',
        'format':'json: contract neutru pentru adaptoare; zpl: date JSON plus comenzi ZPL pentru un echipament compatibil.',
        'serials':'Serii GS1 AI (21), distincte per GTIN și niciodată realocate. Dacă lipsesc, platforma le rezervă atomic.',
        'serial':'Seria GS1 AI (21) a ambalajului, maximum 20 caractere din setul permis.',
        'quantity':'Numărul de ambalaje din comandă, între 1 și 500; o serie distinctă pentru fiecare.',
        'units':'Ambalajele comenzii în ordinea de tipărire, cu identități și snapshoturi imuabile.',
        'digital_link':'URL HTTPS GS1 Digital Link: /01/GTIN/10/lot/21/serie și atributele 11/15/17 aplicabile în query.',
        'reprint_of':'ID-ul comenzii originale la retipărire; null pentru ambalaje noi.',
        'same_serials_confirmed':'Confirmare că retipărirea înlocuiește etichete ale acelorași ambalaje, fără creare de identități noi.',
        'claim_token':'Secret unic al preluării, returnat agentului o singură dată; păstrează-l privat pentru confirmare.',
        'claimed_at':'Momentul preluării de către agent; o preluare fără răspuns devine incertă după 5 minute.',
        'physical_print_confirmed':'true numai când agentul raportează confirmarea echipamentului; expedierea datelor nu este confirmare fizică.',
        'zpl':'Comenzi ZPL cu URL-ul Digital Link în QR Model 2 și câmpuri codificate hexadecimal; profilul fizic cere probă pe echipament.',
        'job':'Comanda rezervată acestui echipament sau null când nu există una disponibilă.',
        'evidence':'Descrierea rezultatului observat, fără parole, tokenuri, rețete private sau date nerelevante.',
    })
    # Existing shared terms retain their maintained descriptions.
    listing=ref('MarketplaceListing')
    release=ref('MarketplaceRelease')
    public_release=obj({'schema_version':S(80),'version':S(40),'license':S(120),'license_url':S(2048),'pricing':{'enum':['free','paid','quote']},'publisher':S(180),'listing_revision':N,'artifact_url':nullable(S(2048)),'sha256':S(64),'size_bytes':N,'platforms':arr(S(64)),'changelog':S(10000),'signature_url':nullable(S(2048)),'id':ID,'manifest_sha256':S(64),'reviewed_at':DATE})
    fields.update({'listing_revision':'Revizia ofertei din care s-au fixat licența, dezvoltatorul și tipul ofertei pentru această versiune; nu se modifică retroactiv.'})
    add('MarketPublicRelease',public_release)
    public_listing=obj({**requests(obj,S,ID)['MarketplaceListing']['properties'],'id':ID,'revision':N,'releases':arr(ref('MarketPublicRelease'))},[k for k in requests(obj,S,ID)['MarketplaceListing']['properties'] if k not in ['expected_revision']]+['id','revision','releases'])
    add('MarketPublicApp',public_listing)
    add('MarketCatalog',obj({'items':arr(ref('MarketPublicApp')),'next_cursor':nullable(ID),'has_more':B,'limit':N,'commerce_enabled':{'const':False},'distribution':{'const':'publisher_https'},'authorization':S(80),'license_scope':S(80)}))
    db_release=obj({'id':ID,'version':S(40),'manifest_json':S(30000),'state':S(30),'manifest_sha256':S(64),'review_json':nullable(S(2000))})
    owned=obj({'id':ID,'owner_user_id':ID,'slug':S(100),'state':S(20),'revision':N,'submitted_revision':nullable(N),'approved_revision':nullable(N),'created_at':DATE,'updated_at':DATE,'draft':listing,'releases':arr(db_release)})
    add('MarketOwnApps',obj({'items':arr(owned)}))
    add('MarketOwnReleases',obj({'items':arr(db_release),'next_cursor':nullable(ID),'has_more':B,'limit':N}))
    add('MarketWithdrawn',obj({'id':ID,'release_id':nullable(ID),'state':{'const':'withdrawn'}}))
    add('MarketSaved',obj({'id':ID,'revision':N}))
    add('MarketSubmitted',obj({'id':ID,'submitted_revision':N}))
    add('MarketReleaseSaved',obj({'id':ID,'state':S(20),'manifest_sha256':S(64)}))
    add('MarketDecision',obj({'id':ID,'decision':S(30)}))
    add('MarketSelection',obj({'id':ID,'active':B,'api_access_granted':{'const':False}}))
    device=obj({'id':ID,'name':S(120),'format':S(20),'created_at':DATE,'last_seen_at':nullable(DATE),'revoked_at':nullable(DATE)})
    add('PrintDevicesResult',obj({'items':arr(device)}))
    add('PrintDeviceSecret',obj({'id':ID,'token':S(100),'secret_available':{'const':True}}))
    add('PrintDeviceRevoked',obj({'id':ID,'revoked':{'const':True}}))
    summary=obj({'id':ID,'device_id':ID,'batch_id':ID,'state':S(20),'quantity':N,'format':S(20),'reprint_of':nullable(ID),'created_at':DATE,'updated_at':DATE})
    add('PrintJobsResult',obj({'items':arr(summary),'next_cursor':nullable(ID),'has_more':B,'limit':N}))
    unit_snapshot=obj({'name':S(180),'gtin14':S(14),'lot_code':S(20),'serial':S(20),'product_info_version_id':ID,'production_date':nullable(S(10)),'best_before':nullable(S(10)),'use_by':nullable(S(10))})
    unit=obj({'id':ID,'serial':S(20),'digital_link':S(2048),'snapshot':unit_snapshot})
    detail=obj({**summary['properties'],'tenant_id':ID,'created_by':ID,'reason':nullable(S(1000)),'claimed_at':nullable(DATE),'units':arr(unit),'events':arr(obj({'state':S(20),'evidence':S(1500),'created_at':DATE})),'zpl':S(2000000)},[k for k in summary['properties']]+['tenant_id','created_by','reason','claimed_at','units','events'])
    add('PrintJobDetail',detail)
    add('PrintUnitsResult',obj({'items':arr(obj({**unit['properties'],'trade_item_id':ID,'batch_id':ID,'gtin14':S(14),'created_at':DATE})),'next_cursor':nullable(ID),'has_more':B,'limit':N}))
    fields.update({'app_id':'ID-ul ofertei marketplace selectate de organizație; nu acordă acces API.'})
    add('MarketSelections',obj({'items':arr(obj({'id':ID,'app_id':ID,'active':B,'created_at':DATE,'updated_at':DATE,'state':S(20),'name':S(120)})),'next_cursor':nullable(ID),'has_more':B,'limit':N}))
    add('PrintJobCreated',obj({'id':ID,'state':S(20),'quantity':N}))
    add('PrintJobCancelled',obj({'id':ID,'state':S(20)}))
    add('PrintJobReprinted',obj({'id':ID,'state':S(20),'reprint_of':ID}))
    add('PrintClaimResult',{'oneOf':[obj({'job':{'type':'null'},'reason':S(50)}),obj({'job':ref('PrintJobDetail'),'claim_token':S(64),'physical_print_confirmed':{'const':False}})]})
    add('PrintAckResult',obj({'id':ID,'state':S(20),'physical_print_confirmed':B}))
    responses={'catalog':'MarketCatalog','publicApp':'MarketPublicApp','own':'MarketOwnApps','queue':'MarketOwnApps','save':'MarketSaved','submit':'MarketSubmitted','release':'MarketReleaseSaved','review':'MarketDecision','select':'MarketSelection','devices':'PrintDevicesResult','device':'PrintDeviceSecret','revoke':'PrintDeviceRevoked','jobs':'PrintJobsResult','job':'PrintJobDetail','createJob':'PrintJobCreated','cancel':'PrintJobCancelled','reprint':'PrintJobReprinted','claim':'PrintClaimResult','ack':'PrintAckResult','download':None}
    standard_document=obj({k:S(n) for k,n in {'id':80,'title':180,'version':40,'source_url':2048,'pdf_url':2048,'filename':180,'sha256':64,'downloaded_at':50,'summary':500,'publisher':80,'rights':300,'public_distribution':80}.items()}|{'size_bytes':N})
    standard_root=obj({'schema_version':S(80),'verified_on':S(10),'authoritative_format':S(40),'rights_url':S(2048),'local_copies':S(80),'documents':arr(standard_document)})
    fields.update({'verified_on':'Ziua verificării edițiilor în directorul oficial GS1, în format YYYY-MM-DD.','authoritative_format':'official_pdf: PDF-ul GS1 prevalează asupra prezentărilor web în caz de diferențe.','rights_url':'Notificarea oficială GS1 aplicabilă standardelor, separată de licența MIT.','local_copies':'Copiile de referință sunt private și nemodificate; nu sunt resurse publice MIT.','title':'Titlul oficial al documentului sau al resursei indicate.','source_url':'Adresa oficială a ediției din directorul GS1; nu este un proxy arbitrar.','pdf_url':'Adresa HTTPS oficială GS1 a PDF-ului autoritativ, fixată în catalog.','filename':'Numele fix al copiei PDF private; nu reprezintă o cale primită de la utilizator.','downloaded_at':'Momentul UTC al descărcării originale pentru verificare și proveniență.','rights':'Notificarea drepturilor GS1 asupra documentului; nu se aplică MIT.','public_distribution':'official_gs1_url: lectura publică folosește PDF-ul distribuit de GS1.','document_id':'Identificatorul unei ediții din catalogul fix, fără URL sau cale arbitrară.'})
    add('Gs1StandardsCatalog',standard_root)
    standard_state=obj({'id':S(80),'version':S(40),'verified':B})
    add('Gs1StandardsStatus',obj({'items':arr(standard_state)}))
    add('Gs1StandardsPreserved',standard_state)
    def metadata(path,method,handler):
        if handler.startswith('standards.'):
            return ('Edițiile oficiale GS1',{'standards.catalog':'Gs1StandardsCatalog','standards.status':'Gs1StandardsStatus','standards.preserve':'Gs1StandardsPreserved'}[handler],'Catalogul fixează ediția, sursa HTTPS oficială, dimensiunea și SHA-256. Copiile originale sunt păstrate privat, fără modificare sau relicențiere MIT. Descărcarea administrativă acceptă numai documentele din catalog și refuză un răspuns care nu corespunde hashului; nu accesează URL-uri furnizate de client.')
        if not handler.startswith('marketplace.'):return None
        name=handler.split('.',1)[1]
        if name=='units':return ('Ambalajele serializate și exportul lor','PrintUnitsResult','Parcurge toate identitățile originale ale organizației, inclusiv URL-ul GS1 Digital Link, snapshotul istoric și legăturile către produs și lot. Folosește next_cursor până când has_more=false; nu sunt exportate credentiale sau secrete de preluare. Aceste înregistrări completează exportul alimentar existent.')
        if name=='selections':return ('Integrările selectate de organizație','MarketSelections','Listează selecțiile proprii, inclusiv cele inactive sau aferente ofertelor retrase. Selecția nu este o achiziție sau autorizare API; granturile OAuth și cheile sunt administrate și revocate separat.')
        if name=='releases':return ('Versiunile integrării','MarketOwnReleases','Listează toate versiunile proprii, inclusiv cele retrase sau în verificare, cu cursor și maximum 100 rezultate. Istoricul este păstrat; o versiune existentă nu poate fi rescrisă.')
        if name=='withdraw':return ('Retrage o integrare proprie','MarketWithdrawn','Retrage imediat oferta proprie sau versiunea indicată de release_id, cu motiv păstrat în istoric. Descărcările noi prin marketplace sunt oprite, iar copiile deja descărcate nu sunt șterse. Licențele și granturile OAuth sunt independente.')
        return ('Marketplace și tipărire — '+name,responses[name],
            'Ofertele și versiunile sunt aprobate separat, iar licențele software nu acordă acces la date. Conectorii rulează local și folosesc autorizare explicită. Comenzile de tipărire păstrează GTIN, lot, serie și datele GS1 în URL-ul codificat în QR. Expedierea datelor și confirmarea fizică sunt stări distincte; un rezultat incert nu este retrimis automat. Consultă ghidul /api/docs/guides/marketplace pentru flux, limite și exemple.')
    return metadata
