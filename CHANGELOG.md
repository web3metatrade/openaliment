# Istoricul versiunilor

## 0.7.1

Aliniază scanarea offline cu serverul pentru ordinea și setul calificatorilor, TPX, codificare unică, separatorul query `;` și segmentele punct interpretate înaintea normalizării browserului. Păstrează calificatorii observați și datele din cod în profil, inventar și sincronizare, fără acordarea unei aprobări. Filtrul scanărilor repetate diferențiază unitățile după GTIN + serie; cache-ul nu copiază seria sau data din codul altui ambalaj. Datele reprezentabile în fereastra GS1 nu mai au o restricție artificială la anii 20xx.

Șablonul ZPL de referință rezervă 600 × 650 puncte pentru QR și text, cu maximum 512 octeți în URI și fără trunchiere. Dimensiunea se verifică pe echipament înainte de utilizare. Contractul API 2.4.1 adaugă metadate opționale; rutele, baza de date, URI-urile deja tipărite și inventarul local rămân compatibile. PWA v8 păstrează aceeași bază IndexedDB. Adaugă 362 de cazuri comune PHP/JavaScript, probe HTTP și teste de browser pentru identitatea serializată.

## 0.7.0

Migrarea aditivă 008 adaugă marketplace cu oferte versionate, verificare separată a pachetelor, retragere și categorii extensibile. Pachetele gratuite sunt distribuite de dezvoltatori prin HTTPS, cu manifest, dimensiune și SHA-256. Ofertele comerciale sunt catalogate; plățile și comisioanele rămân dezactivate cât timp proiectul este în formare.

Adaugă echipamente cu credentiale dedicate revocabile și coadă de tipărire prin agenți locali. QR-ul conține URL-ul HTTPS GS1 Digital Link, cu GTIN, lot, serie, AI 11 și AI 15/17 aplicabile. Alocarea este atomică, comenzile sunt idempotente, snapshoturile și seriile sunt păstrate, iar confirmarea fizică este separată de trimiterea datelor. Un rezultat incert nu este retrimis automat.

Referința GS1 fixează patru ediții oficiale și hashurile lor; pagina publică trimite la PDF-urile GS1, iar administratorul poate păstra copii private verificate. Contractul API 2.4.0 folosește referințe OpenAPI pentru răspunsuri comune, pentru a păstra limita fișierelor pe hosting. QR-ul și resolverul rămân independente de marketplace și AI.

## 0.6.0

Migrarea aditivă 007 adaugă sesiuni revocabile, chei de acces WebAuthn și coduri de recuperare, recuperarea parolei cu păstrarea MFA, confirmări prin email pentru contul personal, voturi versionate și note distincte pe criterii. Sesiunile producătorilor sunt mutate în storage privat și cer o reconectare; Acasă rămâne independent. MFA devine obligatoriu pentru cont după înrolarea unei chei; obligativitatea pentru administratori se activează separat, după înrolare.

Documentele sunt deduplicate în interiorul organizației, cu proveniențe distincte și scanare privată înaintea unei publicări noi. Scanerul neconfigurat nu produce clean. Publicările istorice rămân accesibile; un rezultat malware le retrage. Importurile vechi rămân acceptate. Importul NDJSON validează digestul înainte de scriere, în limita de 24 MB; arhiva documentației deschise include numai materiale publice cu licențe explicite.

Adaugă worker cu heartbeat și blocare pe toată execuția, configurare privată de backup, ZIP AES-256, restaurare izolată cu revocarea accesurilor și reconcilierea ștergerilor, conexiune separată de migrare și monitor HTTPS independent. Instrumentele nu confirmă prin ele însele instalarea cronului, un motor antivirus, livrarea emailului în inbox sau existența copiei externe. Detaliile sunt în `docs/operations/PRODUCTION_RUNBOOK.md`.

Contractul API 2.3.0 și portalul documentează 229 operații și 18 ghiduri. Rutele și adresele QR existente sunt păstrate. Retragerea votului păstrează tombstone; reactivarea cere versiunea actuală, iar retry-urile vechi sunt refuzate. Interfața indică stadiul real al inițiativei: ONG în formare, fără operare comercială în această etapă.

## 0.5.5

Actualizează informațiile publice despre OpenAliment conform clarificării fondatorului: ONG în curs de înființare, Alex Patrut fondator și persoană de contact. Pagina de confidențialitate și API-ul public folosesc aceeași descriere și contactul configurat. Declarația acestui proiect se aplică numai originii configurate `https://openaliment.ro`; instalările independente își păstrează identitatea proprie. Nu schimbă schema API, configurațiile private sau datele producătorilor.

## 0.5.4

Separă ghidurile API publice de documentele interne de implementare și de rapoartele QA. Păstrează cele 208 operații și cele 17 ghiduri, cu instrucțiuni pentru integrare, exemple, limite reale și linkuri publice. Descărcarea Markdown completă conține aceleași ghiduri ca portalul; colecția Postman preia numărul operațiilor din contract.

Clarifică textele de pe landing, stările goale, datele alimentare necompletate, verificarea organizațiilor și activarea funcțiilor administrative. Exemplul de adresă din landing folosește identificatorul demonstrativ Pâine Bună Tehnic. Etichetele de stare sunt afișate în română. Documentele istorice OpenBread și avertizările despre simulări, rezultate experimentale și funcții indisponibile sunt păstrate. Adaugă verificări pentru a împiedica reapariția notelor interne în paginile publice. Nu schimbă rute, payload-uri, permisiuni sau schema bazei de date.

## 0.5.3

Corectează ocolirea scopes prin formularele HTML și verifică permisiunile și în comenzile de domeniu. Adaugă parole Argon2id cu fallback sigur, expirare absolută a sesiunilor producător, schimbarea parolei/retragerea accesurilor și două API-uri de cont documentate. Sesiunile producător existente cer o singură reconectare; Acasă rămâne persistent.

Migrarea aditivă `006_security` pregătește curățarea limitată a datelor temporare, separată de AI. Rezultatele generale de idempotentă au retenție de 90 de zile. Sunt întărite bugetele anti-abuz, jurnalizarea fără secrete, formatele HTTP, fișierele publicabile și confidențialitatea metadatelor fotografiilor noi. HSTS pornește cu o zi, fără preload. Sunt adăugate verificări de surse/versiuni pentru CI și este actualizat Pillow local la 13.0.0. Procedura și controalele operaționale încă necesare sunt în `docs/security/SECURITY_OPERATIONS.md`.

## 0.5.2

Referința celor 206 operații primește proiecții de răspuns specifice, exemple sintetice de cerere/răspuns validate, explicații de câmpuri, coduri HTTP corectate și recuperare din erori. Portalul are 17 ghiduri, colecție Postman și exemplu ERP executabil cu reluare fără duplicate. Exploratorul distinge exemplele de cererile reale, folosește tokenul propriu numai în memorie și cere confirmare pentru scrieri; nu utilizează sesiunea administrativă.

Documentația se încarcă pe grupuri pentru a reduce memoria și dimensiunea actualizărilor pe hosting. URL-urile, operationId, payload-urile și permisiunile existente se păstrează. Citirea individuală a importurilor, evenimentelor de audit și joburilor AI este corectată folosind același filtru de organizație ca listările. Nu este necesară o migrare DB.

## 0.5.1

Documentația API devine un portal cu navigare pe resurse, căutare, 13 ghiduri și pagini proprii pentru toate cele 206 operații. Fiecare pagină oferă copiere Markdown, deschidere în ChatGPT/Claude și versiune `.md`. Indexurile `llms.txt`, `llms-full.txt` și JSON descriu documentația publică. Exemplele de cod sunt copiabile și au file pe limbaj; PBT are un apel public demonstrativ. Rutele vechi, contractele și regulile de autorizare se păstrează. Nu se aplică o migrare DB pentru reorganizarea documentației.

## 0.5.0

API public cu date aprobate și cote, API privat pentru funcțiile existente, chei revocabile cu expirare și rotație, autorizare OAuth cu PKCE pentru aplicații web/mobile și acces Acasă delegat unei familii. Migrarea aditivă `005_api` adaugă aplicații, granturi, tokenuri hash-uite, metadatele cheilor și indexul căutării publice. API v1 și URL-urile existente rămân disponibile.

Contractul agregat OpenAPI, identificatorii stabili ai operațiilor, manifestul fără crawling, ghidul cu exemple, SDK-urile minimale Python/JavaScript și exemplul Android/Kotlin sunt disponibile în proiect și prin pagina `/api`. Cotele, CORS, CSRF, idempotency, izolarea organizațiilor, revocarea și refresh-ul sunt testate pe instanța locală izolată.

Publicarea directă a rețetelor și propunerilor cere acum și scope `publish`, pe lângă `write` și rolul autorizat. Integrările afectate necesită o cheie nouă sau reautorizare; permisiunile existente nu sunt extinse. Funcțiile de domeniu încă inexistente sunt marcate ca indisponibile în manifest. Exemplul Kotlin nu reprezintă un APK testat sau publicat.

## 0.4.0

Platforma include produs/lot/QR, profilul resolverului GS1 documentat, API pentru producători, documentație opțională, comunitate, fluxul web Acasă, OCR local, conectoare și bonuri simulate, identitate personală și sincronizare opțională.

Pregătirea pentru contribuții adaugă licența MIT pentru codul propriu, reguli de colaborare, manifeste de dezvoltare, runner portabil, fixtures sintetice, CI și pachete construite dintr-un manifest. Generatoarele au implementările în `tools/build/`, păstrând comenzile vechi. Autoload-ul, inițializarea și helper-ele de view sunt separate.

Modelul logic generat trece la `openaliment.logical_model.v1.2`, enumeră toate cele patru migrări și nu mai declară o stare universală a instalării pe producție. Schemele operaționale API/transfer și rutele existente rămân compatibile. Nu se adaugă o migrare DB pentru reorganizare.

Planul integral de produs are în continuare criterii deschise. Simulările ERP/retailer nu sunt probe POS reale, iar configurarea CI nu este o execuție GitHub înaintea publicării repository-ului.
