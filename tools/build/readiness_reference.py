"""Concrete contracts for account protection and operations (migration 007)."""
def install(add, fields, obj, arr, nullable, ref, S, B, N, ID, DATE):
    fields.update({
        'account_id':'ID-ul contului personal Acasă; distinct de contul de producător.',
        'completed':'Operațiunea confirmată a fost aplicată; efectele nu sunt declanșate de preview.',
        'extensions':'Extensiile PHP observate în procesul curent, fiecare cu disponibilitate booleană.',
        'fileinfo':'Extensia PHP pentru identificarea tipului MIME din conținut.',
        'mbstring':'Extensia PHP pentru tratarea textului Unicode.',
        'openssl':'Extensia PHP pentru criptografie și conexiuni securizate.',
        'pdo_mysql':'Driverul PDO pentru conexiunea MySQL sau MariaDB.',
        'zip':'Extensia PHP pentru arhive; suportul AES se verifică separat la backup.',
        'redirect':'Adresă locală aleasă de server după confirmarea accesului.',
        'timeout':'Durata sugerată browserului pentru confirmarea WebAuthn, în milisecunde.',
        'id_map':'Asocierea ID-urilor din sursă cu ID-urile noi, atribuite în organizația țintă; prezentă numai după aplicare.',
        'history_preserved_in_import':'Istoricul pachetului este păstrat în importul privat; nu republică automat feedbackul și propunerile.',
        'account_security_api':'Migrarea pentru chei de acces, recuperare și sesiuni individuale este activă.',
        'personal_account_controls':'Confirmările sensibile sunt active pentru contul personal; accesul real cere cookie și CSRF.',
        'versioned_votes':'Starea voturilor păstrează versiuni și refuză cereri vechi după retragere.',
        'review_criteria':'Sunt active notele opționale pe criterii și agregatele recenziilor publicate.',
        'ndjson_import':'Importul NDJSON cu digest final este implementat, în limitele contractului.',
        'licensed_archive_download':'Descărcarea documentației publice licențiate în ZIP este disponibilă în runtime.',
        'mfa_required':'Parola a fost verificată, dar cheia de acces trebuie confirmată înaintea operațiilor producătorului.',
        'verification_url':'Adresa locală a confirmării suplimentare; null când nu este necesară.',
        'current_session_id':'Sesiunea browserului curent; ID-ul nu permite autentificarea.',
        'sessions':'Sesiunile proprii încă valabile; secretul cookie-ului nu este expus.',
        'passkeys':'Cheile de acces proprii, fără materialul criptografic privat.',
        'mfa_enabled':'Contul cere confirmarea unei chei de acces după parolă.',
        'device_name':'Denumire orientativă derivată din browser, fără identificare garantată a dispozitivului.',
        'authenticated_at':'Momentul verificării parolei în UTC.',
        'backup_eligible':'Indicator WebAuthn BE al autentificatorului, 0 sau 1.',
        'backed_up':'Indicator WebAuthn BS al autentificatorului, 0 sau 1.',
        'expires_in_minutes':'Valabilitatea legăturii de confirmare, în minute.',
        'passkey_preserved':'Resetarea parolei păstrează cerința de verificare a cheii de acces.',
        'login_url':'Adresa locală pentru conectarea explicită după schimbarea parolei.',
        'changed':'Operația a schimbat parola contului și a retras accesurile vechi.',
        'created':'Cheia de acces a fost înregistrată și verificată de server.',
        'verified':'Confirmarea criptografică a accesului a reușit.',
        'recovery_codes':'Coduri confidențiale, afișate o singură dată; fiecare poate fi folosit o singură dată.',
        'publicKey':'Opțiuni WebAuthn standard pentru navigator.credentials; câmpurile binare sunt base64url.',
        'challenge':'Provocare aleatoare, de unică folosință, legată de cont și sesiune.',
        'rp':'Identitatea relying party pentru înregistrarea WebAuthn.',
        'rpId':'Domeniul relying party verificat de autentificator.',
        'displayName':'Denumirea lizibilă a contului, afișată de autentificator.',
        'pubKeyCredParams':'Algoritmii COSE acceptați pentru cheia publică.',
        'alg':'Identificator numeric al algoritmului COSE, conform WebAuthn.',
        'authenticatorSelection':'Cerințele de verificare a persoanei și de stocare a cheii.',
        'userVerification':'required cere verificarea persoanei pe autentificator.',
        'residentKey':'Preferința de stocare a credentialului pe autentificator.',
        'requireResidentKey':'Compatibilitate WebAuthn pentru cerința de cheie rezidentă.',
        'authenticatorAttachment':'Preferință de autentificator integrat sau extern.',
        'attestation':'none evită solicitarea unei atestări identificabile a dispozitivului.',
        'excludeCredentials':'Chei deja înregistrate, excluse de la o nouă înregistrare.',
        'allowCredentials':'Cheile contului acceptate pentru această confirmare.',
        'transports':'Transporturi WebAuthn sugerate browserului; nu acordă acces prin ele însele.',
        'password_confirmation':'Repetarea exactă a parolei noi, verificată înainte de aplicare.',
        'new_password':'Parola nouă; nu trimite și nu păstra valoarea în jurnale.',
        'clientDataJSON':'Octeții clientDataJSON ai răspunsului WebAuthn, codificați base64url.',
        'attestationObject':'Obiectul CBOR WebAuthn de înregistrare, codificat base64url.',
        'credentialId':'Identificatorul binar al cheii WebAuthn, codificat base64url.',
        'authenticatorData':'Datele semnate de autentificator, codificate base64url.',
        'signature':'Semnătura WebAuthn, codificată base64url; serverul verifică cheia și contorul.',
        'mutation_version':'Versiunea stării votului; retragerea păstrează o stare versionată.',
        'expected_mutation_version':'Versiunea citită înaintea modificării; o versiune veche produce 409.',
        'criteria':'Note opționale pe criterii, distincte de nota generală; omiterea nu înseamnă zero.',
        'overall':'Nota generală agregată exclusiv din recenziile publicate pentru versiunea și lotul selectate.',
        'responses':'Numărul recenziilor cu o notă pe acest criteriu; nu totalul vizitelor sau cumpărătorilor.',
        'average':'Media aritmetică rotunjită la două zecimale; null dacă nu există note.',
        'distribution':'Numărul notelor pentru fiecare valoare 1–5; o notă neacordată nu intră în distribuție.',
        'taste':'Nota pentru gust, număr întreg între 1 și 5.',
        'texture':'Nota pentru textură, număr întreg între 1 și 5.',
        'aroma':'Nota pentru aromă, număr întreg între 1 și 5.',
        'freshness':'Nota pentru prospețime, număr întreg între 1 și 5.',
        'packaging':'Nota pentru ambalaj, număr întreg între 1 și 5.',
        'engine':'Motorul care a verificat fișierul; null dacă verificarea nu a avut loc.',
        'source_sha256':'Amprenta SHA-256 a obiectului JSON de proveniență, păstrată separat de amprenta fișierului.',
        'sources':'Înregistrările independente ale provenienței documentului; același fișier poate avea mai multe surse.',
        'scan':'Starea verificării de securitate la încărcare; încărcarea nu acordă publicarea.',
        'scanned_at':'Momentul verificării fișierului în UTC; null pentru fișiere neverificate.',
        'receipt_id':'ID-ul dovezii de închidere a contului, fără adresa de email în clar.',
        'shared_contributions':'Regula de păstrare a contribuțiilor din familiile altor persoane.',
        'files':'Numărul fișierelor private eliminate din coada de ștergere.',
        'php':'Versiunea PHP observată în procesul care execută diagnosticul.',
        'minimum':'Versiunea PHP minimă acceptată de această versiune a aplicației.',
        'supported_runtime':'Procesul curent folosește o versiune PHP acceptată.',
        'filesystem_free_bytes':'Spațiul liber observat pe filesystem; nu reprezintă cota contului cPanel.',
        'account_quota_observed':'false: cota contului de hosting nu a fost măsurată de aplicație.',
        'runtime_database_ddl_privileges':'Detectarea privilegiilor DDL ale conexiunii runtime; null dacă observația nu a reușit.',
        'migration_connection_configured':'Există o configurație distinctă pentru conexiunea de migrare.',
        'smtp_configured':'Setările de email sunt completate; nu confirmă livrarea în inbox.',
        'document_scanner_configured':'Un transport de scanare este configurat; verifică separat rezultatul scanării.',
        'checks':'Rezultatele private ale operațiilor observate, cu moment și vârstă.',
        'check_key':'Identificator stabil al verificării operaționale.',
        'age_seconds':'Secunde de la ultima observație înregistrată.',
        'details':'Detalii specifice verificării, fără parole sau tokenuri.',
        'readiness_installed':'Migrarea aditivă 007 a fost înregistrată în această bază de date.',
        'backup_external_verified':'Există o probă înregistrată a copiei externe; o copie locală nu o înlocuiește.',
        'backup_configured':'Există un fișier privat de configurare; verificarea arhivei și copia externă sunt probe separate.',
        'pending_private_file_deletions':'Fișiere private încă aflate în coada de ștergere.',
    })
    session = obj({'id':ID,'device_name':S(120),'authenticated_at':DATE,'verified_at':nullable(DATE),'last_seen_at':DATE,'created_at':DATE})
    key = obj({'id':ID,'name':S(120),'created_at':DATE,'last_used_at':nullable(DATE),'backup_eligible':N,'backed_up':N})
    add('AccountAccessStatus',obj({'sessions':arr(session),'current_session_id':nullable(ID),'passkeys':arr(key),'mfa_enabled':B}))
    add('AccountRecoveryRequested',obj({'message':S()}))
    add('AccountRecoveryPreview',obj({'valid':{'const':True},'expires_in_minutes':N,'passkey_preserved':{'const':True}}))
    add('AccountPasswordReset',obj({'changed':{'const':True},'login_url':S(200),'passkey_preserved':{'const':True}}))
    credential=obj({'type':{'const':'public-key'},'id':S(200000),'transports':arr(S(30))},['type','id'])
    options=obj({'challenge':S(1000),'rp':obj({'name':S(200),'id':S(253)}),'rpId':S(253),'user':obj({'id':S(1000),'name':S(254),'displayName':S(200)}),'timeout':N,'userVerification':{'const':'required'},'pubKeyCredParams':arr(obj({'type':{'const':'public-key'},'alg':{'type':'integer'}})),'authenticatorSelection':obj({'userVerification':{'const':'required'},'residentKey':S(30),'requireResidentKey':B,'authenticatorAttachment':S(30)},[]),'attestation':S(30),'excludeCredentials':arr(credential),'allowCredentials':arr(credential)},['challenge','timeout'])
    add('PasskeyOptions',obj({'publicKey':options}))
    add('PasskeyCreated',obj({'created':{'const':True},'id':ID,'recovery_codes':arr(S(40)),'csrf':S(64),'redirect':S(2048)}))
    add('PasskeyVerified',obj({'verified':{'const':True},'csrf':S(64),'redirect':S(2048)}))
    add('RecoveryCodes',obj({'recovery_codes':arr(S(40))}))
    add('PasskeyRemoved',obj({'removed':{'const':True}}))
    add('DocumentScanStatus',obj({'state':{'enum':['pending','clean','rejected','error','legacy_unverified']},'engine':nullable(S(100)),'scanned_at':nullable(DATE)},['state']))
    add('OperationsStatus',obj({'php':obj({'version':S(30),'minimum':S(30),'supported_runtime':B}),'extensions':obj({k:B for k in ['pdo_mysql','mbstring','fileinfo','openssl','zip']}),'filesystem_free_bytes':nullable({'type':'number'}),'account_quota_observed':{'const':False},'runtime_database_ddl_privileges':nullable(B),'migration_connection_configured':B,'smtp_configured':B,'document_scanner_configured':B,'checks':arr(obj({'check_key':S(120),'state':S(30),'checked_at':DATE,'age_seconds':N,'details':{'type':'object','additionalProperties':True}})),'readiness_installed':B,'backup_external_verified':B,'backup_configured':B,'pending_private_file_deletions':nullable(N)}))
    add('HomeActionRequested',obj({'message':S()}))
    score=obj({'responses':N,'average':nullable({'type':'number','minimum':1,'maximum':5})})
    criterion=obj(dict(score['properties'],distribution=obj({str(n):dict(N,description='Numărul recenziilor cu nota '+str(n)+' pentru acest criteriu.') for n in range(1,6)})))
    add('PublicRatings',obj({'product_info_version_id':ID,'batch_id':nullable(ID),'overall':score,'criteria':obj({key:criterion for key in ['taste','texture','aroma','freshness','packaging']})}))
    payload=obj({'action':{'enum':['erase','transfer','leave']},'household_id':ID,'account_id':ID},['action'])
    add('HomeActionPreview',obj({'action':{'enum':['erase','transfer','leave']},'description':S(),'payload':payload}))
    add('HomeActionCompleted',obj({'completed':{'const':True},'action':{'enum':['erase','transfer','leave']},'receipt_id':ID,'shared_contributions':S(),'files':{'const':'queued_for_private_deletion'}},['completed','action']))

    def metadata(path, method, handler):
        if handler=='public.archive':
            return ('Descarcă arhiva documentației deschise',None,'Răspuns ZIP autonom cu README.md, snapshotul exact recipe.json, manifest de amprente și documentele publice cu licență explicită. Exclude materialele private, fără licență și originalele deteriorate. Limita este 100 documente și 50 MB. Licența și stadiul experimental se păstrează; arhiva nu acordă o siglă sau certificare alimentară.')
        if handler=='manage.importStream':
            return ('Verifică și importă un flux NDJSON','ImportReport','Trimite application/x-ndjson cu antet openaliment.transfer.stream.v1, rânduri table/row și final end/records/sha256. Digestul acoperă octeții exacți, inclusiv newline, înainte de linia finală. Limita cererii este 24 MB, maximum 10.000 rânduri pe colecție. dry_run=true este implicit; false cere Idempotency-Key. Validarea integrală precede orice scriere. Importul rămâne privat și cere reverificarea drepturilor.')
        if handler=='public.ratings':
            return ('Citește notele publicate ale articolului','PublicRatings','Agregă exclusiv recenzii publicate pentru eticheta curentă sau label_id. batch_id restrânge calculul la lotul public al aceleiași versiuni. O notă absentă nu înseamnă zero; fiecare criteriu are număr de răspunsuri, medie și distribuție proprie. Participarea voluntară nu dovedește cumpărarea.')
        if handler=='account.security':
            return ('Citește sesiunile și cheile proprii','AccountAccessStatus','Necesită sesiunea proprie și o confirmare recentă a accesului. Nu acceptă chei API sau OAuth și nu expune secretele sesiunilor ori ale autentificatoarelor.')
        if handler=='account.recovery':
            action=path.rsplit('/',1)[-1]
            return ({'request':'Solicită recuperarea parolei','preview':'Verifică legătura de recuperare','reset':'Schimbă parola prin legătura confirmată'}[action],{'request':'AccountRecoveryRequested','preview':'AccountRecoveryPreview','reset':'AccountPasswordReset'}[action], 'Cerere POST din aceeași origine, fără autentificare. Solicitarea dă același mesaj pentru cont cunoscut și necunoscut. Tokenul secret este primit prin email, expiră în 15 minute și este consumat numai de reset. Cheile de acces rămân obligatorii; sesiunile și accesurile vechi sunt revocate.')
        if handler in ['account.passkey','account.session']:
            name='PasskeyOptions' if path.endswith('/options') else 'PasskeyCreated' if '/create/' in path else 'RecoveryCodes' if path.endswith('/recovery-codes') else 'PasskeyRemoved' if path.endswith('/remove') else 'Revoked' if handler=='account.session' else 'PasskeyVerified'
            return ('Confirmă și administrează accesul propriu',name,'Exclusiv sesiunea browserului și CSRF. WebAuthn verifică originea exactă, challenge single-use, prezența și verificarea persoanei. Înregistrarea cere parola actuală; operațiile sensibile cer confirmare recentă. Codurile de recuperare sunt returnate o singură dată și păstrate numai ca hash.')
        if handler=='manage.documentScan':
            return ('Verifică securitatea documentului','DocumentScanStatus','Documentul trebuie să aparțină organizației. POST cere rol de aprobare și scope publish; GET cere read. Fișierul este verificat prin motorul privat configurat. Motor indisponibil sau neconfigurat nu produce clean și nu permite publicarea unui document nou.')
        if handler=='admin.operations':
            return ('Citește diagnosticul operațional privat','OperationsStatus','Exclusiv sesiunea administratorului platformei, cu verificarea cheii de acces când este activă. Datele descriu observații reale ale procesului și workerului; nu confirmă cota cPanel, livrarea emailului în inbox sau copia externă prin simpla configurare.')
        if '/home/account/action/' in path:
            action=path.rsplit('/',1)[-1]
            return ('Confirmă o operație sensibilă a contului personal',{'request':'HomeActionRequested','preview':'HomeActionPreview','confirm':'HomeActionCompleted'}[action], 'Exclusiv sesiunea personală a browserului, aceeași origine și X-Home-CSRF. Solicitarea trimite email; preview nu schimbă date. Confirmarea consumă tokenul legat de cont și dispozitiv. Transferul cere un editor existent; ștergerea cere transferul prealabil al familiilor cu alți membri și păstrează contribuțiile din familiile altora.')
        return None
    return metadata
