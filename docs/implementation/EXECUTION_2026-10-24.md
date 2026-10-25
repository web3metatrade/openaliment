# OpenAliment — execuția planului complet

Început: 24 octombrie 2026. Plan: OPENALIMENT-FULL-2026-10-24. Stare: în implementare, fără acceptare globală.

## Clarificarea proprietarului din 24 octombrie

Proprietarul a cerut simularea conexiunii ERP și a magazinului partener. Nu există încă un partener sau ERP fix. Arhitectura trebuie să accepte conectoare/agenți și mapări diferite. Pentru această execuție, probele contractelor ERP/bon pot fi închise prin simulatoare explicite; nu vor fi prezentate comercial ca integrare reală cu un retailer. Probele fizice de tipar și POS rămân distincte și neexecutate până la accesul efectiv.

Nu există conturi Apple Developer/Google Play. Codul, buildurile și pregătirea distribuției pot fi construite; publicarea efectivă rămâne dependentă de conturile operatorului și de aprobările magazinelor. Se va utiliza SMTP printr-o bibliotecă PHP, cu un cont pe domeniu creat de proprietar în cPanel. Parolele și cheile nu se înscriu în documentație.

Aceste precizări prevalează pentru cerințele de partener concret din WP13 și E05/E06. Nu transformă testele simulate în probe reale și nu declară celelalte dependențe închise.

## Baza de lucru

Inventar local al codului și copie în directorul privat .tools/baseline/2026-10-24-plan-start. Copia conține codul și contractele, nu o restaurare probată a bazei de producție. Conexiunea HTTPS de mentenanță a fost verificată prin operațiunea de citire a stării. MariaDB și serverul PHP locale folosesc exclusiv baza izolată openaliment_tests.

## Construit în incrementul Acasă

- Date alimentare opționale versionate: alergeni declarați/posibile urme/necunoscuți, nutriție cu bază/unități/jurisdicție, porții și instrucțiuni după deschidere. Etichetele vechi nu sunt rescrise.
- Proiecție publică pentru aplicații, cu sursă, versiune, termen cunoscut și alerte actuale. Nu citește rețete private. Linksetul poate descoperi profilul fără modificarea selecției legacy masterData.
- Interfață web/PWA Acasă cu IndexedDB, fără cont, salvare persistentă pe dispozitiv, ambalaje separate, deschidere/consum/anulare, preferințe locale de alergeni și listă de cumpărături.
- Scanner de coduri cu componentă locală de rezervă, fotografii personale și citirea locală a datelor cu Tesseract, încărcat la cerere. Rezultatele OCR sunt propuneri care se confirmă.
- Copie personală JSON cu istoric, fotografii și SHA-256; import verificat, fără dublarea identităților. Limite actuale: 2.000 ambalaje, 50.000 evenimente importate, copie de maximum 64 MB. Acesta nu încheie încă portabilitatea completă a producătorilor din WP18.
- Pagini și acțiuni de bază offline; datele și alertele publice nu sunt cache-uite ca informație curentă. Informația ambalajului este separată de suprapunerea alertelor curente.
- Administrare a câmpurilor structurate și instalare controlată a celor două resurse OCR mari, cu surse și hashuri fixate. Nicio funcție de shell sau download arbitrar.

## Dovezi

Rapoartele executate sunt păstrate în docs/verification, iar capturile locale în docs/previews. Numerele și mediile sunt cele din rapoarte, nu se presupune trecerea întregii matrice de 132 de scenarii.

Rămân deschise: acceptarea completă a sincronizării/gospodăriilor, livrarea emailului în inbox, aplicațiile native și distribuția, notificările de fundal, sursele externe, finalizarea B/C și AI/RAG, domeniile, testele fizice, auditul, capacitatea și stabilizarea. Ordinea și dependențele continuă în backlog. Niciun pachet complet nu este acceptat doar prin această notă.

## Publicare și continuarea execuției

Fluxul Acasă și OCR sunt publicate pe openaliment.ro/acasa/. Resursele OCR au fost instalate din sesiunea administrativă și verificate prin SHA-256 la 24 octombrie. Conectoarele, mapările imuabile, previzualizarea, importul ERP în ciornă și bonurile digitale sunt publicate; migrarea aditivă 003_integrations a fost aplicată prin panoul administrativ. Cele două formate de partener au fost simulate numai în baza izolată, fără introducerea de produse fictive în catalogul de producție. Rapoarte: consumer-production-assets-report.json, partner-production-report.json, partner-simulator-report.json, partner-http-report.json și receipt-client-report.json.

Publicat: identitate personală prin email, cookie persistent separat de conturile producătorilor, familii, fotografii private, sincronizare cu idempotență și conflicte explicite, istoric și audit de acces. Migrarea aditivă 004_home a fost aplicată din sesiunea administrativă; mesajul de succes a fost observat. 32 de verificări de domeniu, 14 scenarii ale clientului și 16 verificări HTTP au trecut local, împreună cu regresiile existente. Testarea vizuală locală a confirmat legătura simulată, confirmarea explicită, persistența conectării, sincronizarea celor trei ambalaje și denumirea familiei. Conturile și alimentele fictive nu au fost introduse în producție.

Publicare HTTPS: 29 de fișiere, 209.120 octeți, loturile 993dea40ac124af58daf6b120f112124 și 260524032e25449a817d3aa108603dff, cu verificare de hash. Informarea factuală despre datele Acasă a fost publicată separat în două fișiere. Configurațiile private nu sunt în aceste loturi. Noua bază verificată este .tools/baseline/2026-10-24-home-privacy-published, construită din chitanțele de publicare, nu din fișiere locale nepublicate.

Proprietarul a completat parola direct pe hosting în config/mail.local.php. TLS cu verificarea certificatului și autentificarea SMTP au reușit prin PHPMailer pe producție. Nu a fost trimis niciun email, iar parola nu a fost citită sau afișată. Raport: smtp-authentication-report.json. Livrarea efectivă în inbox rămâne neprobată; testele locale folosesc numai outbox privat de test.

Sincronizarea este o alegere explicită; nu transmite preferințele personale de alergeni. Lista de cumpărături nu este încă sincronizată. Ștergerea integrală automată a contului, retenția automată și transferul proprietății familiei nu sunt finalizate. Retragerea accesului nu șterge copii deja descărcate. Aceste limite sunt documentate fără a declara închiderea globală a WP04/WP05/WP14/WP21.
