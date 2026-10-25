# Referința API aprofundată — 0.5.2

Stare: implementată și publicată la [openaliment.ro/api/docs](https://openaliment.ro/api/docs), 24 octombrie 2026. Actualizează portalul 0.5.1, păstrând rutele, identificatorii operationId și compatibilitatea aplicației. Nu necesită migrare DB.

## Probleme închise

| Observație inițială | Rezultat |
| --- | --- |
| Record, Collection și MutationResult nu explicau structurile returnate | Proiecții concrete pentru fiecare familie, inclusiv metadate private, liste, copii publice, OAuth și Acasă; tipurile generale rămân numai componente de compatibilitate nefolosite ca succes generic |
| Fără exemple explicite de succes în OpenAPI | 207 reprezentări de succes și toate cele 79 reprezentări de cerere au exemple; răspunsurile fără corp, binare și HTML sunt diferențiate |
| Descrieri și câmpuri prea generale | Semnificații menținute pentru toate câmpurile declarate, cu unități, necunoscut, null, valori implicite, enum și limite; build/test refuză lipsurile generice |
| Liste comune de erori fără remediere | Clase aplicabile, erori specifice operațiilor esențiale, corpuri protocolare și reguli de recuperare; conflictul sync 409 este distinct de eroarea standard |
| Lipsa unei integrări executabile complete | Exemplu Python fără dependențe, cu stare de operație fără secrete, produs lactate → etichetă → lot fără rețetă → QR → verificare publică; reluarea este testată |
| Explorare limitată | Exemple de succes/eroare în fiecare pagină; cereri reale pentru GET public și Bearer eligibil, cu confirmare la scrieri și fără cookie administrativ |
| Distribuție dificilă către instrumente | Colecție Postman completă cu exemple de răspuns; 17 ghiduri, 223 pagini, indexuri și Markdown canonic per pagină |

## Surse și arhitectură

`tools/build/api_reference.py` întreține explicațiile, proiecțiile și exemplele. Structurile SELECT sunt verificate în raport cu migrările și controllerele. Metadata independentă este în `docs/api/reference/metadata.json`. Ghidurile aprofundate sunt în `docs/api/pages/`; generatorul le publică și în ghidul complet.

Indexul documentației rămâne mic, iar cele 18 grupuri se încarcă la cerere din `contracts/api-docs/`. Colecția Postman se asamblează din 17 fragmente la descărcare. OpenAPI agregat este JSON compact; niciun fișier de transport al portalului nu depășește 2 MiB. Nu este necesară o nouă bibliotecă pe hosting.

Exploratorul execută numai către aceeași origine, cu credentials=omit, redirect=error, timeout 15 secunde și maximum 1 MiB pentru afișare. Tokenul propriu rămâne numai în pagină și este eliminat la ieșire. Copierea paginii și legăturile către AI folosesc Markdown-ul public, fără token sau rezultat privat. Fluxurile de sesiune, consimțământ, fișiere și trimiteri publice folosesc exemplele de client corespunzătoare.

## Corecție funcțională găsită la verificare

`ApiManage::get` trimitea importurile, auditul și joburile AI către lista de repository-uri permisă de Catalog, care nu includea acele tabele. Citirea individuală folosește acum mapa fixă ApiManage::TABLES și aceeași condiție id + tenant_id ca izolarea existentă. Nu există nume de tabel primit liber de la client. Probele verifică răspunsurile acestor rute, iar testele existente păstrează verificarea refuzului pentru altă organizație.

Contractul precizează și comportamente deja existente: feedback/înscriere întorc 201, rotația întoarce previous_id, vocabularul alergenilor este un dicționar, revoke OAuth are corp gol, iar v1 păstrează șirurile JSON. Acestea sunt corecții de documentație, fără schimbarea payload-urilor runtime.

## Verificări

- Suita locală completă: **34 suite, 9.805 verificări, zero eșecuri**. Baza este exclusiv openaliment_tests; transportul email este test_file.
- **206 operații distincte** au întors succes în proba HTTP locală, cu **210 răspunsuri verificate**. JSON este comparat cu schema publicată; probele existente verifică și descărcările binare, izolarea, revocarea, scopes și PKCE.
- Verificarea documentației: **7.116 verificări** pentru exemple, parametri, semnificația câmpurilor, referințe și limite de transport.
- Toate cele **223 pagini HTML/Markdown** și descărcările sunt verificate în **904 probe HTTP**, inclusiv paritatea Copy Page.
- Explorator: **14 probe de comportament**, inclusiv confirmarea scrierii, originea fixă, absența cookie-urilor/redirect-urilor, secret numai în antet, codificare unică și JSON invalid refuzat înainte de rețea.
- Publicare HTTPS: **42 probe numai de citire**, fără credentiale sau modificări de date de domeniu. Hashul OpenAPI public este comparat cu artefactul testat; colecția Postman descărcată conține toate cele 206 cereri.
- Browser: citire reală PBT cu HTTP 200 din explorator, navigare/căutare după product_info_version_id, filele de cod și raportarea succesului Clipboard API. Copy Page este verificat și prin paritatea template–Markdown; nu este pretins un test de lipire într-o aplicație externă.
- Telefon: viewport 390×844, lățime utilă 375 px cu scrollbar, fără overflow al documentului; tabelele sunt în containere proprii. Override-ul a fost resetat.

Admiterea jobului AI este verificată într-o configurație separată de server local, fără worker sau apel către furnizor. Fluxul ERP și QR sunt verificate cu AI dezactivat. Nu s-au trimis emailuri reale și nu s-au creat chei, aplicații sau produse demonstrative în conturile de producție.

Rapoartele reproductibile sunt în `tests/runtime/reports/`: qa-summary, api-reference-quality, api-reference-http, api-documentation-http, api-explorer și api-reference-production. Capturile sunt în `tests/runtime/screenshots/api-reference-live-pbt.png` și `api-reference-mobile.png`. Datele și credentialele fixture-urilor rămân ignorate de Git și excluse din pachete.

## Publicare și limite explicite

Actualizările au fost încărcate prin conexiunea HTTPS autorizată, după compararea cu baseline-ul publicat, cu backup și verificarea hashurilor fiecărui lot. Jurnalele și baseline-ul sunt private în `.tools/deploy/` și `.tools/baseline/2026-10-24-api-quality-published/`. Configurațiile DB/SMTP și documentele istorice PBT nu au fost înlocuite.

Exemplele sintetice nu sunt o bază demonstrativă publică ce se modifică și se resetează. O cerere reală operează în contul ales. Nu se declară certificare externă GS1, acceptare în orice POS, test de sarcină pentru milioane de scanări, compilare Android sau parteneriat comercial pe baza acestor teste. Aceste limite sunt explicate în portal, fără funcții prezentate ca implementate fictiv.
