# OpenAliment — inventarul acțiunilor și API-urilor candidate

Data: **24 octombrie 2026**. Bază: sursele proiectului din acest director, cele patru contracte OpenAPI și planul existent. Acesta este un inventar pentru proiectarea API-ului, nu o implementare. Nu schimbă rutele, funcționarea, permisiunile sau starea producției.

Obiectivul este ca o aplicație Android/iOS, o integrare ERP sau un agent AI să poată realiza operațiunile permise fără să parseze formulare HTML. Acțiunile locale rămân locale când aceasta este soluția mai simplă și mai privată. Același endpoint poate susține mai multe câmpuri/butoane; numărul acțiunilor nu este numărul endpointurilor de construit.

Catalogul structurat, cu ID-uri stabile, pagini, surse și amprente: [API_ACTION_INVENTORY_2026-10-24.json](API_ACTION_INVENTORY_2026-10-24.json). ID-urile sunt unice în acest inventar; nu sunt încă `operationId` publicate.

## 1. Acoperire și rezultat

Au fost inventariate **431 acțiuni în 31 grupe**, cu acoperire pentru **14 controllere și toate cele 23 șabloane de pagină**. Sunt urmărite și acțiunile generate din JavaScript, operațiile API fără buton, instalarea, mentenanța și funcțiile încă absente din planul extins.

| Stare | Număr acțiuni | Semnificație |
| --- | ---: | --- |
| `EXISTENT` | 118 | API existent |
| `PARTIAL` | 56 | Acoperire parțială / limită de integrare |
| `MISSING` | 49 | API lipsă pentru funcție/suprafață existentă |
| `LOCAL` | 70 | Acțiune locală / SDK, fără HTTP obligatoriu |
| `STATIC` | 14 | Pagină, document sau resursă statică; HTTP existent reutilizabil |
| `OPS` | 26 | Operație de operator/admin; nu API public de integrare |
| `PLANNED` | 98 | Funcție propusă sau prevăzută în plan, încă neimplementată în această formă |

În surse sunt **64 operații HTTP API principale (metodă + rută)** în familiile `/api/v1` și `/api/v2`. Dintre acestea, **48 sunt enumerate în OpenAPI**, iar **16 nu sunt enumerate**. Niciuna dintre cele **48 operații documentate** nu are `operationId`. Numărul include transportul binar al fotografiilor, dar nu variantele de query, HEAD/OPTIONS, resolverul, fișierele statice, endpointurile HTML sau bridge-ul privat.

„Existent” înseamnă găsit în sursă. Nu este o afirmație că am retestat acum fiecare cerere pe hosting. Revizia a fost exclusiv de citire a codului; au fost create doar documentele acestui inventar și indexul lor.

### Constatări care influențează integrarea

- Fluxurile de creare/publicare produs, etichetă, lot, alertă și destinație resolver au deja API. Lipsesc multe liste paginate, uploadul inițial de documente, accesul la moderare în citire, voturile JSON și administrarea accesului prin API.
- Acasă are API JSON privat funcțional pentru web, cu cookie `oa_home` și `X-Home-CSRF`. Nu are încă grant Bearer delegat pentru o aplicație terță. Nu trebuie promovat ca API nativ complet doar fiindcă întoarce JSON.
- Tokenurile producătorilor sunt Bearer, legate de organizație și rolul actual. Nu oferă acces la inventarele clienților și nu pot verifica mandatul GS1 sau crea alte tokenuri.
- Scanarea/OCR, preferințele de alergeni, calculele, backupul și cumpărăturile sunt în mare parte locale. Compatibilitatea mobilă cere contracte/SDK și vectori comuni, nu upload obligatoriu de fotografii sau preferințe.
- `robots.txt` din producție nu blochează întregul site: interzice zonele de administrare, instalare, API manage v1 și bridge. Acasă adaugă `noindex,nofollow`; aceasta privește indexarea și nu este autentificare.
- Actualizările de hosting, dovezile de identitate, SMTP și operațiile de deploy rămân acces de operator separat de API-ul dezvoltatorilor. Nu se adaugă instrumente AI pentru SQL, shell sau fișiere arbitrare.

## 2. Reguli de citire și compatibilitate

Coloana **Astăzi** descrie ce există efectiv. **API/contract necesar** păstrează endpointurile bune și propune completări; rutele noi sunt orientative și nu sunt rezervate sau funcționale prin simpla apariție aici. `PLANNED` indică fie o cerință existentă în backlog, fie o completare recomandată, diferențiată prin nota rândului. Variantele opționale sunt marcate explicit.

Permisiunile din titlul fiecărei grupe se aplică tuturor rândurilor și se restrâng suplimentar unde nota o cere. Scopes implementate acum pentru producători: `read`, `write`, `publish`, `alerts`, `export`, `import`. La scriere contează și rolul: owner/editor/quality pot crea; owner/quality aprobă. Citirea de documente private și datele de familie se verifică la fiecare cerere. Noile scopes pentru OAuth, AI, administrare sau consumatori necesită contract și implementare separată.

Pentru un formular cu mai multe câmpuri se folosește un request structurat, nu un API pe câmp. Pentru publicare, alerte, verificări de drepturi și siglă se păstrează comenzi explicite, audit și autoritate distinctă. Etichetele, rețetele și destinațiile au versiuni; loturile nu își pierd informația istorică. Licențele și drepturile nu se acordă prin import, creare pagină sau emitere QR.

Păstrăm v1 și API-urile v2 deja folosite. Completările sunt aditive; orice schimbare de tip, autorizare sau comportament incompatibil trebuie versionată și migrată. Exportul NDJSON existent nu implică un import NDJSON existent. Validarea GTIN nu este verificarea dreptului GS1. Capabilitățile detectorului optic nu sunt automat capabilitățile parserului sau POS-ului.

Contractele finale trebuie să enumere exact limitele: feedbackul curent are 6 trimiteri/IP/oră, mutațiile producătorilor sunt în mod obișnuit limitate la 120/minut/principal, iar autentificarea și invitațiile au limite proprii. `sameOrigin()` respinge origini externe explicite și `Sec-Fetch-Site: cross-site`; lipsa CORS privat nu este un control suficient de acces. Invitațiile și bonurile folosesc capability tokens private; transmiterea lor este o acțiune intenționată, nu crawling.

Pentru fișiere: upload document curent de maximum 15 MB, whitelist MIME și aprobare ulterioară; fotografie personală sincronizată JPEG de maximum 1 MB; copia personală de maximum 64 MB. Limitele PHP/cPanel pot fi mai restrictive. SDK-ul trebuie să ofere o eroare utilizabilă înainte de upload și serverul verifică din nou. Nu se includ în JavaScript public sau APK un token ERP comun ori un secret de client confidențial; aplicația folosește tokenuri delegate individuale, păstrate în stocarea sigură a dispozitivului.

## 3. Harta tuturor paginilor și suprafețelor

| Pagină/suprafață | Șablon | Grupe de acțiuni |
| --- | --- | --- |
| / | `home.php` | [PUB](#pub), [DEM](#dem) |
| layout comun | `layout.php` | [PUB](#pub), [IDN](#idn) |
| /gs1-digital-link | `digital-link-guide.php` | [PUB](#pub), [DEM](#dem), [RES](#res) |
| /despre | `about.php` | [PUB](#pub) |
| /confidentialitate | `privacy.php` | [PUB](#pub), [FUT](#fut) |
| /api/docs | `api-docs.php` | [PUB](#pub), [DEV](#dev) |
| /produse | `catalog.php` | [CAT](#cat) |
| /p/{id}; /t/{id}; /b/{id} | `product.php` | [CAT](#cat), [HOM](#hom) |
| /p/{id}/v/{version} | `recipe.php` | [CAT](#cat), [DOC](#doc) |
| /demo/paine-buna-tehnic; /lot/DEMO-PBT-01; /lot-necunoscut | `demo-bread.php` | [DEM](#dem) |
| /demo/paine-buna-tehnic/etichete | `demo-bread-print.php` | [DEM](#dem) |
| /resolver/profile | `resolver-profile.php` | [RES](#res) |
| Digital Link: redirect sau alegeri/linkset | `resolver-linkset.php` | [RES](#res) |
| /login; /register; /setup | `auth.php` | [IDN](#idn), [OPS](#ops) |
| /manage | `manage.php` | [IDN](#idn), [PRD](#prd) |
| /manage/products/{id} | `manage.php` | [PRD](#prd), [DOC](#doc) |
| /manage/items/{id} | `manage.php` | [PRD](#prd), [LBL](#lbl), [BAT](#bat) |
| /manage/labels/{id} | `manage.php` | [LBL](#lbl) |
| subformular date structurate etichetă | `food-data-form.php` | [LBL](#lbl) |
| previzualizare date structurate | `food-data-preview.php` | [LBL](#lbl) |
| /manage/batches/{id} | `manage.php` | [BAT](#bat) |
| /manage/qr/item/{id}; /manage/qr/batch/{id} | `manage.php` | [QRC](#qrc) |
| /manage/api | `manage.php` | [TOK](#tok) |
| /manage/import; /manage/export | `manage.php` | [IMP](#imp) |
| /manage/feedback | `manage.php` | [FBK](#fbk) |
| /manage/resolver/{id} | `resolver-manage.php` | [RLK](#rlk) |
| /manage/connectors; /manage/connectors/{id} | `connectors.php` | [CON](#con) |
| /acasa/: Ce am, Terminate, details/editor | `consumer-home.php` | [HOM](#hom), [SCN](#scn) |
| /acasa/: De cumpărat, Pentru mine | `consumer-home.php` | [LOC](#loc), [BKP](#bkp) |
| /acasa/: conectare, familie, dispozitive, conflicte | `consumer-home.php` | [HID](#hid), [FAM](#fam), [SYN](#syn) |
| /acasa/: bon digital | `consumer-home.php` | [RCP](#rcp) |
| /admin | `admin.php` | [ADM](#adm) |
| /admin/updates | `updates.php` | [OPS](#ops) |
| mesaj/confirmare/eroare reutilizabilă | `message.php` | [PUB](#pub) |
| /hosting-check.php | `public/hosting-check.php` | [OPS](#ops) |
| rute API fără ecran dedicat | `controllers` | [CAT](#cat), [DOC](#doc), [CON](#con), [SYN](#syn), [DEV](#dev) |
| bridge privat / worker CLI / resurse SEO și PWA | `infrastructură` | [OPS](#ops), [PUB](#pub), [SCN](#scn) |

Legăturile comune din antet/subsol, butoanele de închidere, ancorele, `details`, schimbarea filtrelor, focusul, starea de trimitere și mesajele sunt acoperite în PUB/LOC/SCN. Nu sunt ignorate, dar nici transformate în sute de endpointuri fără efect de domeniu.

## 4. Inventarul complet pe pagină și funcție

<a id="pub"></a>
### PUB — Navigare comună, landing, conținut și politici

**Suprafețe:** `/`; `/gs1-digital-link`; `/despre`; `/confidentialitate`; `/api/docs`.

**Acces:** public. **Prioritate orientativă:** P1.

**Surse:** [app/Views/layout.php](../../app/Views/layout.php), [app/Views/home.php](../../app/Views/home.php), [app/Views/about.php](../../app/Views/about.php), [app/Views/privacy.php](../../app/Views/privacy.php), [app/Views/api-docs.php](../../app/Views/api-docs.php), [app/Controllers/PublicController.php](../../app/Controllers/PublicController.php), [app/Views/message.php](../../app/Views/message.php), [public/assets/app.js](../../public/assets/app.js), [app/Core/Seo.php](../../app/Core/Seo.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **PUB-001** Deschide prima pagină, sigla sau legătura de întoarcere | `STATIC` | GET / | Reutilizare URL canonic | HTML public și metadate SEO. Nu este necesar un endpoint pentru fiecare legătură repetată. |
| **PUB-002** Navighează la Soluții, ERP, pași, întrebări și contact | `LOCAL` | Ancore /#solutii /#integrare-erp /#cum-functioneaza /#intrebari /#contact | Fără HTTP suplimentar | Secțiuni și FAQ din landing. Navigare în pagină, inclusiv expandarea details. |
| **PUB-003** Solicită o discuție despre integrare | `STATIC` | mailto: contact configurat; alternativă din home.php | Opțional POST /api/v2/public/contact-requests | Subiect, mesaj, canal de răspuns, acord; ID solicitare. Astăzi deschide contactul, nu există CRM sau trimitere API; nu trimite automat mesaje în numele vizitatorului. |
| **PUB-004** Deschide catalogul Alimente | `EXISTENT` | GET /api/v1/products | Păstrează API; adaugă filtre și paginare stabilă | Listă de articole publicate, nume, categorie, URL. Pagina HTML /produse limitează la 60; API page/limit până la 100. |
| **PUB-005** Deschide Spațiul meu sau cont producător | `MISSING` | GET /manage; GET /login | GET /api/v2/identity/session | Autentificat, organizații, roluri, capabilități, destinație sigură. Legătura din meniu folosește sesiunea PHP; lipsă bootstrap JSON pentru clienți. |
| **PUB-006** Deschide Acasă | `STATIC` | GET /acasa/ | Reutilizare URL și manifest PWA | Aplicație personală fără cont obligatoriu. Operațiunile aplicației sunt inventariate separat. |
| **PUB-007** Citește ghidul Digital Link și exemplele | `STATIC` | GET /gs1-digital-link | Conținut static; opțional GET /api/v2/public/content/gs1-digital-link | Titlu, text, exemple, surse, actualizare. Nu este obligatoriu un CMS/API pentru textul comercial. |
| **PUB-008** Citește Despre platformă și inițiativa OpenBread | `STATIC` | GET /despre | Conținut static; opțional GET /api/v2/public/content/about | Scop, separarea găzduirii de licență și evaluare. Nicio siglă acordată implicit. |
| **PUB-009** Citește confidențialitatea și contactează operatorul | `STATIC` | GET /confidentialitate; mailto: contact | GET /api/v2/public/policies/privacy | Versiune, text, data aplicării, contact public. Endpoint JSON propus pentru afișare în aplicații; pagina depinde de configurația publică. |
| **PUB-010** Citește documentația de integrare | `STATIC` | GET /api/docs | GET /api/v2/public/developer-resources | Index de contracte, exemple și ghiduri. HTML nu trebuie parsată ca protocol de integrare. |
| **PUB-011** Descarcă specificațiile OpenAPI | `EXISTENT` | GET /api/openapi.json; GET /api/consumer-openapi.json; GET /api/integration-openapi.json; GET /api/home-openapi.json | Aceleași URL-uri; index unificat versionat | Specificații JSON și amprente. Inventarul operațiilor documentate și lacunele sunt în anexă. |
| **PUB-012** Descarcă schemele publicate pentru aplicații | `EXISTENT` | GET /api/schemas/{name}.schema.json | Index complet de scheme și versiuni | food-data, food-date, consumer-product-profile, consumer-inventory, consumer-transfer, partner-mapping, digital-receipt, home-sync. Lista permisă este în PublicController; nu acceptă orice nume. |
| **PUB-013** Citește robots și sitemap | `STATIC` | GET /robots.txt; GET /sitemap.xml | Păstrează resursele SEO | Politică de crawling și URL-uri publice. Nu reprezintă autentificare și nici catalog complet de operații. |
| **PUB-014** Deschide surse GS1 și alte destinații externe | `LOCAL` | Legături externe din ghid și profil | Fără endpoint nou | URL și titlu. Clientul decide navigarea; nu executa fetch arbitrar pe server. |
| **PUB-015** Sari la conținut, deschide FAQ/details și urmărește starea formularului | `LOCAL` | Ancore, HTML details, assets/app.js | Fără endpoint nou | Stare UI, validare și aria-busy. Comportamente comune tuturor paginilor, nu mutații de date. |
| **PUB-016** Primește pagină de confirmare sau eroare | `STATIC` | Views/message.php; răspunsuri API JSON | Păstrează erori JSON; adaugă catalog de coduri | error_code, message, request_id, câmpuri invalide. O pagină de mesaj nu este o resursă editabilă separată. |

<a id="cat"></a>
### CAT — Catalog, produs, lot și participare publică

**Suprafețe:** `/produse`; `/p/{id}`; `/t/{id}`; `/b/{id}`; `/p/{id}/v/{version}`.

**Acces:** public. **Prioritate orientativă:** P1.

**Surse:** [app/Controllers/PublicController.php](../../app/Controllers/PublicController.php), [app/Controllers/ApiController.php](../../app/Controllers/ApiController.php), [app/Views/catalog.php](../../app/Views/catalog.php), [app/Views/product.php](../../app/Views/product.php), [app/Views/recipe.php](../../app/Views/recipe.php), [app/Domain/Catalog.php](../../app/Domain/Catalog.php), [app/Domain/Community.php](../../app/Domain/Community.php), [public/assets/home/entry.js](../../public/assets/home/entry.js).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **CAT-001** Listează alimentele publicate | `EXISTENT` | GET /api/v1/products?page=1&limit=20 | GET /api/v2/public/trade-items?cursor=&limit= | Articole publicate, categorie, producător și next_cursor. Păstrează v1; v2 aditiv pentru listă predictibilă, fără pagini lipsă la trafic mare. |
| **CAT-002** Caută după nume, categorie, producător sau GTIN | `PLANNED` | — | GET /api/v2/public/trade-items?q=&category=&producer=&gtin= | Filtre și rezultate publicabile. Aceste filtre nu există în catalogul curent. |
| **CAT-003** Citește produsul logic | `EXISTENT` | GET /api/v1/products/{id} | Păstrează; explică selecția articolului | Proiecția publică a unui articol al produsului. Nu reprezintă lista tuturor variantelor; endpoint separat de variante necesar. |
| **CAT-004** Listează variantele publice ale produsului | `MISSING` | HTML alege articolul în proiecție | GET /api/v2/public/products/{id}/trade-items | Variante și URL-uri publice. Nu expune variante private. |
| **CAT-005** Citește articolul și eticheta aprobată curentă | `EXISTENT` | GET /api/v1/trade-items/{id} | Păstrează; profil normalizat v2 | Ingrediente, alergeni, nutriție, operator, păstrare, cantitate, proveniență. Nu include rețete private. |
| **CAT-006** Citește lotul și versiunea istorică aplicabilă | `EXISTENT` | GET /api/v1/batches/{id} | Păstrează | Lot, starea curentă, snapshot de etichetă și date. Nu înlocui snapshotul cu eticheta curentă a articolului. |
| **CAT-007** Citește profilul normalizat după cod sau URL | `EXISTENT` | GET /api/v2/public/food?code= | Păstrează | consumer-product-profile cu necunoscute explicite, sursă și alerte. URL-ul se transmite ca valoare codificată a parametrului code. |
| **CAT-008** Distinge QR generic de lot cunoscut sau necunoscut | `EXISTENT` | GET /api/v2/public/food?code=; GS1 /01/{gtin}/10/{lot} | Păstrează semantica în SDK | gtin14, lot_code, batch_id, alert_scope și notices. Lipsa lotului public nu confirmă existența sau termenul lui. |
| **CAT-009** Verifică nepotrivirea datei din QR față de lot | `PARTIAL` | GET /b/{id}?11=&15=&17=; GET /api/v2/public/food?code= | Contract explicit de scanare și diagnostice v2 | Date decodate, date aprobate și coduri de conflict. Nu echivala afișajul HTML cu un endpoint JSON cu aceiași parametri. |
| **CAT-010** Citește alertele curente ale lotului | `EXISTENT` | GET /api/v1/batches/{id}; GET /api/v2/public/food?code= | GET /api/v2/public/batches/{id}/alerts, reutilizând proiecția | Alerte active, sursă, moment verificare. Endpoint dedicat propus pentru polling eficient; informația există deja în proiecții. |
| **CAT-011** Citește o versiune istorică publicată de etichetă | `MISSING` | HTML lot și proiecția lui păstrează versiunea | GET /api/v2/public/labels/{id} | Snapshot aprobat, hash, versiune, data aprobării. Nu expune ciorne; alertele se verifică separat la zi. |
| **CAT-012** Listează documentele publice asociate versiunii | `EXISTENT` | GET /api/v1/trade-items/{id}; GET /api/v1/batches/{id} | GET /api/v2/public/labels/{id}/documents | Metadate și URL de descărcare. Lista există în proiecții; filtrarea rămâne pe versiunea aprobată. |
| **CAT-013** Descarcă documentul public | `EXISTENT` | GET /d/{id} | Păstrează binarul; GET /api/v2/public/documents/{id} pentru metadate | Fișier, hash, mime, titlu, proveniență și licență. Astăzi download octet-stream cu nume generic .bin; nu există metadate publice dedicate. |
| **CAT-014** Listează versiunile de rețetă deschisă | `MISSING` | Lista din HTML produs sau lot | GET /api/v2/public/products/{id}/recipe-versions; GET /api/v2/public/batches/{id}/recipe-versions | Versiuni publice autorizate și licențe. Pentru lot, exclusiv versiunea asociată dacă este publică. |
| **CAT-015** Citește rețeta publică și hashul | `EXISTENT` | GET /api/v1/recipe-versions/{id} | Păstrează; proiecție JSON normalizată | snapshot_json, snapshot_sha256, licență, technical_status. v1 întoarce snapshot_json ca șir; nu schimba tipul fără versiune nouă. |
| **CAT-016** Găsește rețeta după cheia versiunii din pagina publică | `MISSING` | GET /p/{id}/v/{version} | GET /api/v2/public/products/{id}/recipe-versions?version_key= | ID și versiune publică unică. Cheia de versiune trebuie codificată o singură dată. |
| **CAT-017** Adaugă produsul sau lotul în Acasă | `LOCAL` | assets/home/entry.js; GET /api/v2/public/food?code= | SDK addPackage; sincronizare existentă dacă utilizatorul a ales-o | Un ambalaj fizic nou și eveniment add. Nu salva automat în gospodărie la simpla citire a paginii publice. |
| **CAT-018** Listează recenziile și întrebările publicate | `MISSING` | HTML produs: max 30, filtrat după versiunea etichetei | GET /api/v2/public/trade-items/{id}/feedback?label_id=&batch_id=&cursor= | Copii moderate, tip, notă, dată și context. Nu expune original_text sau sesizări quality. |
| **CAT-019** Trimite recenzie | `EXISTENT` | POST /api/v1/feedback | Păstrează | kind=review, text, rating opțional, articol, versiune, lot opțional, consent, submission_key. Aceeași cheie la retry; status_token numai la prima salvare. |
| **CAT-020** Trimite întrebare | `EXISTENT` | POST /api/v1/feedback | Păstrează | kind=question și același context verificat. Întrebarea nu generează automat răspuns aprobat. |
| **CAT-021** Trimite idee de îmbunătățire | `EXISTENT` | POST /api/v1/feedback | Păstrează | kind=idea și context. Nu modifică datele aprobate. |
| **CAT-022** Trimite sesizare privată de calitate | `EXISTENT` | POST /api/v1/feedback | Păstrează | kind=quality, text, lot cunoscut. Unele cuvinte de risc clasifică automat în quality; rămâne restricționat. |
| **CAT-023** Alege sau elimină asocierea feedbackului cu lotul; selectează nota | `LOCAL` | Câmpurile formularului /feedback | Aceeași operație POST /api/v1/feedback | batch_id facultativ; rating nul sau 1–5. Nu este nevoie de un endpoint pentru fiecare câmp sau checkbox. |
| **CAT-024** Confirmă informarea înaintea feedbackului | `EXISTENT` | POST /api/v1/feedback, consent=true | Adaugă versiunea explicită a informării în contract | Dovada informării și acordului pentru această trimitere. Nu este licență automată asupra rețetei sau altor documente. |
| **CAT-025** Urmărește starea propriei trimiteri | `EXISTENT` | GET /api/v1/feedback/{id}/status | Păstrează | X-Status-Token; doar status. Nu există buton dedicat în HTML; API existent trebuie documentat. |
| **CAT-026** Listează propunerile publice și deciziile | `MISSING` | HTML produs: max 20 propuneri și număr voturi | GET /api/v2/public/products/{id}/proposals?cursor= | Titlu, corp public, stare, decizie și voturi. Istoricul deciziilor trebuie separat de rezultatul curent. |
| **CAT-027** Susține propunerea | `MISSING` | POST /proposals/{id}/vote, vote=1 | PUT /api/v2/community/proposals/{id}/vote | Vot unic al utilizatorului verificat. Necesită identitate delegată; contul Acasă nu acordă astăzi drept de vot. |
| **CAT-028** Retrage votul | `MISSING` | POST /proposals/{id}/vote, vote=0 | DELETE /api/v2/community/proposals/{id}/vote | Eliminare idempotentă a votului propriu. Nu permite modificarea votului altei persoane. |
| **CAT-029** Citește dacă utilizatorul poate vota și votul lui | `MISSING` | Auth::user + email_verified în HTML | GET /api/v2/community/me/votes; GET /api/v2/identity/session | Propuneri susținute și capabilitate de participare. Nu exporta lista privată a tuturor votanților. |

<a id="dem"></a>
### DEM — Ghid, demonstrație PBT, QR și fișa de scanare

**Suprafețe:** `/gs1-digital-link#exemplu`; `/demo/paine-buna-tehnic`; `/demo/paine-buna-tehnic/lot/DEMO-PBT-01`; `/demo/paine-buna-tehnic/lot-necunoscut`; `/demo/paine-buna-tehnic/etichete`.

**Acces:** public. **Prioritate orientativă:** P1.

**Surse:** [app/Controllers/DemoBreadController.php](../../app/Controllers/DemoBreadController.php), [app/Domain/DemoBread.php](../../app/Domain/DemoBread.php), [app/Views/digital-link-guide.php](../../app/Views/digital-link-guide.php), [app/Views/demo-bread.php](../../app/Views/demo-bread.php), [app/Views/demo-bread-print.php](../../app/Views/demo-bread-print.php), [public/assets/digital-link-guide.js](../../public/assets/digital-link-guide.js), [public/assets/demo-print.js](../../public/assets/demo-print.js).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **DEM-001** Comută între exemplul QR produs și lot | `LOCAL` | data-dl-kind product/batch | Fără HTTP; două identificări publice distincte | Selecție panou exemplu. Comutarea nu schimbă date pe server. |
| **DEM-002** Scanează sau deschide QR-ul demonstrativ de produs | `EXISTENT` | GET /01/09520000000011 | Păstrează | Destinație demo, GTIN simulat. GTIN13 9520000000011 este rezervat demonstrației, fără vânzare. |
| **DEM-003** Scanează sau deschide QR-ul demonstrativ de lot | `EXISTENT` | GET /01/09520000000011/10/DEMO-PBT-01 | Păstrează | Destinație lot demo. Nu dovedește recunoaștere comercială în orice POS. |
| **DEM-004** Deschide pagina demo de produs sau lot | `STATIC` | GET /demo/paine-buna-tehnic; GET /demo/paine-buna-tehnic/lot/DEMO-PBT-01 | Păstrează URL-urile demo | HTML și avertizarea simulation. R1 experimentală și costurile estimate nu devin rezultate validate. |
| **DEM-005** Deschide exemplul lot necunoscut | `STATIC` | GET /demo/paine-buna-tehnic/lot-necunoscut | Păstrează | Informare privind lipsa lotului. Nicio dată de expirare inventată. |
| **DEM-006** Descarcă datele JSON demo generice | `EXISTENT` | GET /demo/paine-buna-tehnic/date.json | Păstrează; adaugă în indexul sandbox | Snapshot demonstrativ. Nu este CRUD comercial. |
| **DEM-007** Descarcă datele JSON ale lotului demo | `EXISTENT` | GET /demo/paine-buna-tehnic/lot/DEMO-PBT-01/date.json | Păstrează | Snapshot lot demonstrativ. simulation trebuie păstrat la import în aplicații. |
| **DEM-008** Descarcă SVG QR de produs sau lot demo | `STATIC` | DemoBread::asset(false/true) | Păstrează fișierele SVG; descrie URL-urile în sandbox | SVG QR cu quiet zone. Nu este necesar endpoint dinamic pentru aceste două fișiere fixe. |
| **DEM-009** Deschide fișa de testare și tipărește | `LOCAL` | GET /demo/paine-buna-tehnic/etichete; window.print() | HTML print și SDK; PDF opțional ulterior | Fișă cu GTIN, lot, două QR-uri. Imprimarea nu trimite vânzări către POS. |
| **DEM-010** Adaugă demonstrația în Acasă | `LOCAL` | data-home-add; GET /api/v2/public/food?code= | SDK addPackage cu simulation=true | Ambalaj local demonstrativ. Fără creare de produs comercial. |
| **DEM-011** Citește profilul și documentația resolverului | `STATIC` | GET /resolver/profile; GET /api/docs | Reutilizare profil machine-readable de resolver | Funcții și limite declarate. Exemplele tehnice nu se confundă cu toate cheile GS1 suportate. |

<a id="idn"></a>
### IDN — Cont producător, organizație și sesiune

**Suprafețe:** `/login`; `/register`; `/logout`; `/manage`.

**Acces:** utilizator propriu / organizație; separat de Acasă. **Prioritate orientativă:** P0.

**Surse:** [app/Views/auth.php](../../app/Views/auth.php), [app/Controllers/PublicController.php](../../app/Controllers/PublicController.php), [app/Domain/Accounts.php](../../app/Domain/Accounts.php), [app/Core/Auth.php](../../app/Core/Auth.php), [app/Core/Session.php](../../app/Core/Session.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **IDN-001** Înregistrează producătorul și prima organizație | `MISSING` | POST /register | POST /api/v2/identity/registrations | display_name, organization, email, password; cont și membership owner. Respectă registration_enabled, limitarea cererilor și confirmarea contactului. |
| **IDN-002** Autentifică producătorul în browser | `MISSING` | POST /login | POST /api/v2/identity/sessions pentru client web propriu | Sesiune, capabilități, CSRF. Nu introduce password grant OAuth pentru aplicațiile terțe. |
| **IDN-003** Recunoaște contul deja autentificat și redirecționează | `MISSING` | GET /login sau /register redirecționează la /manage | GET /api/v2/identity/session | Identitate și stare sesiune. API nu trebuie să întoarcă redirect HTML către formular. |
| **IDN-004** Deconectează sesiunea producătorului | `MISSING` | POST /logout | DELETE /api/v2/identity/sessions/current | Revocare sesiune curentă. Nu revocă implicit toate integrările ERP. |
| **IDN-005** Listează organizațiile utilizatorului și rolurile sale | `MISSING` | Auth::principal alege tenant din sesiune sau primul membership | GET /api/v2/identity/organizations | Membership-uri active și roluri. Selectarea tenantului nu trebuie bazată pe un tenant_id nevalidat. |
| **IDN-006** Schimbă organizația activă | `PLANNED` | — | POST /api/v2/identity/organization-selection | organization_id membru autorizat. Nu există selector în interfața curentă. |
| **IDN-007** Delegă acces către Android, iOS sau aplicație terță | `PLANNED` | — | GET /oauth/authorize; POST /oauth/token | Authorization Code + PKCE S256, redirect_uri înregistrat, scopes, audiență. Autentificare în browserul sistemului, token scurt; fără secret în APK. |
| **IDN-008** Reînnoiește sau revocă accesul delegat | `PLANNED` | — | POST /oauth/token; POST /oauth/revoke | Refresh token rotit, familie revocabilă. Revocarea și schimbarea rolului trebuie să se aplice imediat. |
| **IDN-009** Descoperă metadatele serverului de autorizare | `PLANNED` | — | GET /.well-known/oauth-authorization-server | issuer, authorization_endpoint, token_endpoint, revocation_endpoint, PKCE. Publicarea numai când funcția este implementată. |
| **IDN-010** Listează și retrage autorizările aplicațiilor | `PLANNED` | — | GET /api/v2/identity/grants; DELETE /api/v2/identity/grants/{id} | Aplicație, scop, organizație, acord și revocare. Fără dezvăluire de refresh tokens. |
| **IDN-011** Înregistrează aplicația dezvoltatorului | `PLANNED` | — | POST /api/v2/developer/apps; GET /api/v2/developer/apps; GET /api/v2/developer/apps/{id} | Nume, URL-uri permise, tip public/confidențial, medii. Nu permite redirect arbitrar și nu cere secret pentru aplicație nativă. |
| **IDN-012** Actualizează sau suspendă aplicația proprie | `PLANNED` | — | PATCH /api/v2/developer/apps/{id}; POST /api/v2/developer/apps/{id}/suspend | Configurație versionată, proprietar, audit. Administrarea aplicației nu acordă acces la utilizatorii ei. |
| **IDN-013** Verifică emailul prin link sigur | `PLANNED` | Astăzi numai contact.verify manual în /admin | POST /api/v2/identity/email-verifications; POST /api/v2/identity/email-verifications/confirm | Cerere cu răspuns neutru; token consumat la confirmare explicită. Previzualizarea GET nu consumă tokenul. |
| **IDN-014** Recuperează sau schimbă parola | `PLANNED` | — | POST /api/v2/identity/password-resets; POST /api/v2/identity/password-resets/confirm; PUT /api/v2/identity/password | Token unic, reautentificare pentru schimbare. Nu există formulare curente de recuperare. |
| **IDN-015** Editează profilul propriu și datele organizației | `PLANNED` | — | GET /api/v2/identity/me; PATCH /api/v2/identity/me; GET /api/v2/manage/organization; PATCH /api/v2/manage/organization | Nume/contact și organizație; câmpuri publice separate de dovezi. Nu schimbă retroactiv operatorul imprimat în etichetele vechi. |
| **IDN-016** Invită, schimbă rolul sau retrage membru din organizație | `PLANNED` | — | GET /api/v2/manage/members; POST /api/v2/manage/invitations; POST /api/v2/identity/invitations/accept; PATCH /api/v2/manage/members/{id}; DELETE /api/v2/manage/members/{id} | owner/editor/quality/viewer, invitație, mandat. Protejează ultimul owner; nu confunda cu membrii familiei Acasă. |

<a id="prd"></a>
### PRD — Spațiul producătorului, produse și articole

**Suprafețe:** `/manage`; `/manage/products/{id}`; `/manage/items/{id}`.

**Acces:** read pentru citire; write + owner/editor/quality pentru creare. **Prioritate orientativă:** P0.

**Surse:** [app/Controllers/ManageController.php](../../app/Controllers/ManageController.php), [app/Views/manage.php](../../app/Views/manage.php), [app/Controllers/ApiController.php](../../app/Controllers/ApiController.php), [app/Domain/Catalog.php](../../app/Domain/Catalog.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **PRD-001** Citește indicatorii din spațiul meu | `MISSING` | GET /manage, counts items/batches/feedback | GET /api/v2/manage/dashboard | Număr articole, loturi și feedback de revizuit. Doar organizația curentă. |
| **PRD-002** Listează produsele organizației | `PARTIAL` | GET /api/v1/manage/products | GET /api/v2/manage/products?cursor=&q=&category= | Produse și paginare completă. v1 limitează la 100, fără cursor. |
| **PRD-003** Creează aliment din orice categorie | `EXISTENT` | POST /api/v1/manage/products | Păstrează | name, category; id. Fără rețetă, licență sau evaluare obligatorie. |
| **PRD-004** Citește produsul privat | `EXISTENT` | GET /api/v1/manage/products/{id} | Păstrează; DTO JSON explicit în v2 | Date produs. Nu returna coloane viitoare sensibile prin SELECT *. |
| **PRD-005** Listează articolele comerciale ale produsului | `MISSING` | GET /manage/products/{id}, lista HTML | GET /api/v2/manage/products/{id}/trade-items?cursor= | Articole, GTIN, starea mandatului, etichetă curentă. Necesar pentru ERP fără exportul întregului tenant. |
| **PRD-006** Creează articol cu GTIN opțional și dovadă | `EXISTENT` | POST /api/v1/manage/products/{id}/trade-items | Păstrează | name, gtin, claim_evidence; id. Validarea cifrei de control nu verifică dreptul organizației. |
| **PRD-007** Citește articolul privat și starea drepturilor | `EXISTENT` | GET /api/v1/manage/trade-items/{id} | Păstrează; proiecție de stare a revendicării | GTIN ca șir, gtin_status, referințe. Dovezile de mandat nu sunt publice. |
| **PRD-008** Validează înainte de salvare GTIN și lot | `MISSING` | Validări în mutații; erori formular/API | POST /api/v2/tools/identifiers/validate | GTIN/AI/lot, diagnostic și valoare canonică. Validare sintactică fără acordarea drepturilor sau divulgarea registrului privat. |
| **PRD-009** Depune sau completează cererea de drepturi GS1 | `PARTIAL` | claim_evidence numai la trade.create; revizie manuală /admin | POST /api/v2/manage/trade-items/{id}/gtin-claims; GET /api/v2/manage/trade-items/{id}/gtin-claims | Dovezi și istoric cerere. Nici tokenul producătorului, nici AI nu se autoverifică. |
| **PRD-010** Pregătește eticheta și loturile articolului | `MISSING` | GET /manage/items/{id}, liste agregate | GET /api/v2/manage/trade-items/{id}/overview sau colecțiile separate | Etichete, loturi, documente eligibile, rețete autorizate. Agregatul opțional reduce round-trip pe mobil; aceeași ACL. |
| **PRD-011** Editează denumirea internă sau arhivează produsul/articolul | `PLANNED` | — | PATCH /api/v2/manage/products/{id}; PATCH /api/v2/manage/trade-items/{id}; POST /api/v2/manage/products/{id}/archive; POST /api/v2/manage/trade-items/{id}/archive | Metadate interne, expected_revision, motiv. Nu reutiliza GTIN și nu șterge paginile loturilor ori istoricul; nu există butoane curente. |
| **PRD-012** Citește jurnalul propriei organizații | `MISSING` | Disponibil indirect în export.audit_events | GET /api/v2/manage/audit-events?object_id=&cursor= | Acțiune, actor autorizat, obiect, dată, motiv. Redactează secretele și datele care nu sunt necesare. |

<a id="lbl"></a>
### LBL — Etichete și date alimentare structurate

**Suprafețe:** `/manage/items/{id}`; `/manage/labels/{id}`.

**Acces:** read; write pentru ciornă; publish + owner/quality pentru aprobare. **Prioritate orientativă:** P0.

**Surse:** [app/Views/manage.php](../../app/Views/manage.php), [app/Views/food-data-form.php](../../app/Views/food-data-form.php), [app/Views/food-data-preview.php](../../app/Views/food-data-preview.php), [app/Domain/FoodData.php](../../app/Domain/FoodData.php), [app/Domain/Catalog.php](../../app/Domain/Catalog.php), [app/Controllers/ApiController.php](../../app/Controllers/ApiController.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **LBL-001** Listează versiunile etichetei | `MISSING` | Listă HTML în /manage/items/{id} | GET /api/v2/manage/trade-items/{id}/labels?cursor= | Versiune, stare, hash și date de aprobare. Nu doar eticheta curentă. |
| **LBL-002** Creează versiune nouă de etichetă | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/labels | Păstrează | version_key și snapshot alimentar. Versiuni imutabile; modificările ulterioare creează versiune nouă. |
| **LBL-003** Completează numele, categoria și descrierea publicabilă | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/labels | Aceeași creare de versiune | name, category, description. Câmpuri ale snapshotului, nu trei endpointuri. |
| **LBL-004** Completează ingredientele și alergenii ca text | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/labels | Aceeași creare de versiune | ingredients, allergens. Textul original rămâne alături de datele structurate. |
| **LBL-005** Completează cantitatea, operatorul, originea și utilizarea | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/labels | Aceeași creare de versiune | net_quantity, operator, origin, usage. Operatorul istoric rămâne în versiune. |
| **LBL-006** Completează păstrarea, după deschidere și nutriția ca text | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/labels | Aceeași creare de versiune | storage, after_opening, nutrition. Nu extrage automat valori validate din text liber. |
| **LBL-007** Confirmă declarația structurată a alergenilor | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/labels | Aceeași creare, food_data.allergens.status | declared sau unknown. Necompletat nu înseamnă fără alergeni. |
| **LBL-008** Selectează fiecare dintre cei 14 alergeni prezenți | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/labels | Aceeași creare, food_data.allergens.contains[] | gluten, crustaceans, eggs, fish, peanuts, soy, milk, nuts, celery, mustard, sesame, sulphites, lupin, molluscs. Aceeași operație pentru fiecare checkbox. |
| **LBL-009** Selectează fiecare alergen posibil din urme | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/labels | Aceeași creare, food_data.allergens.may_contain[] | Aceleași 14 coduri. contains și may_contain rămân distincte. |
| **LBL-010** Alege baza nutriției | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/labels | Aceeași creare, food_data.nutrition | per_100_g, per_100_ml sau per_portion. Nu amesteca grame cu mililitri. |
| **LBL-011** Alege aliment ca atare/preparat și jurisdicția | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/labels | Aceeași creare, food_data.nutrition | preparation și jurisdiction. Datele preparate nu înlocuiesc arbitrar datele ca atare. |
| **LBL-012** Completează energie și fiecare nutrient | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/labels | Aceeași creare, food_data.nutrition.values | energy_kj, energy_kcal, fat_g, saturates_g, carbohydrate_g, sugars_g, polyols_g, fibre_g, protein_g, salt_g. Fiecare câmp este acoperit; nul distinct de zero. |
| **LBL-013** Completează porția și unitatea ei | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/labels | Aceeași creare, food_data.nutrition.portion | amount și unit g/ml. Obligatorii când baza este per porție. |
| **LBL-014** Completează durata și condițiile după deschidere | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/labels | Aceeași creare, food_data.after_opening | hours și conditions. Durată indicată de producător, nu estimare generată de AI. |
| **LBL-015** Asociază documente aprobate etichetei | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/labels | Aceeași creare, document_ids[] | ID-uri de documente publicabile din tenant. Nu face public un document privat prin simpla referire. |
| **LBL-016** Citește snapshotul privat și previzualizarea | `EXISTENT` | GET /api/v1/manage/labels/{id} | Păstrează; GET /api/v2/manage/labels/{id}/public-preview | Snapshot și proiecția exactă de publicat. HTML previzualizează câmpurile; proiecția publică JSON explicită este de adăugat. |
| **LBL-017** Validează ciorna fără creare | `MISSING` | Validări la creare și preview HTML | POST /api/v2/manage/label-validations | Snapshot, erori pe câmp, warnings și proiecție propusă. Nicio scriere și nicio aprobare. |
| **LBL-018** Aprobă și publică eticheta | `EXISTENT` | POST /api/v1/manage/labels/{id}/publish | Păstrează | ID ciornă; versiune publicată. Nu migrează loturile vechi la eticheta nouă. |
| **LBL-019** Copiază o versiune veche în una nouă și compară | `PLANNED` | — | POST /api/v2/manage/labels/{id}/fork; GET /api/v2/manage/labels/{id}/diff?against= | Versiune nouă, diferențe și proveniență. Nu modifica snapshotul publicat in-place. |

<a id="bat"></a>
### BAT — Loturi, eliberare, evenimente și alerte

**Suprafețe:** `/manage/items/{id}`; `/manage/batches/{id}`.

**Acces:** read; write pentru creare; owner/quality + publish/alerts pentru decizii. **Prioritate orientativă:** P0.

**Surse:** [app/Views/manage.php](../../app/Views/manage.php), [app/Controllers/ManageController.php](../../app/Controllers/ManageController.php), [app/Controllers/ApiController.php](../../app/Controllers/ApiController.php), [app/Domain/Catalog.php](../../app/Domain/Catalog.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **BAT-001** Listează loturile articolului | `MISSING` | HTML /manage/items/{id}, max 100 | GET /api/v2/manage/trade-items/{id}/batches?cursor=&state=&lot_code= | Loturi private/publice, stare, versiunea asociată. Paginare fără pierdere după al 100-lea lot. |
| **BAT-002** Caută lot exact după GTIN și cod | `MISSING` | Resolver public doar pentru date publicate | GET /api/v2/manage/batches?gtin=&lot_code= | Identitate exactă în tenant. Lotul este șir opac; nu îl trunchia sau normaliza. |
| **BAT-003** Creează lot fără rețetă | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/batches | Păstrează | lot_code, product_info_version_id și date lot. recipe_version_id nul este valid. |
| **BAT-004** Selectează eticheta istorică aplicabilă | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/batches | Aceeași creare | product_info_version_id publicat al articolului. Nu înlocui implicit cu ultima versiune la replay/import. |
| **BAT-005** Asociază opțional o rețetă autorizată | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/batches | Aceeași creare | recipe_version_id nul, privat sau public în același tenant. Asocierea nu acordă acces public la rețetă. |
| **BAT-006** Completează producția, termenul și notele lotului | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/batches | Aceeași creare | production_date, best_before sau use_by, public_notes. best_before și use_by nu se trimit simultan. |
| **BAT-007** Citește lotul privat | `EXISTENT` | GET /api/v1/manage/batches/{id} | Păstrează | Snapshot, stare și vizibilitate. Necesită DTO pentru API v2; nu presupune că include evenimentele și alertele. |
| **BAT-008** Citește istoricul evenimentelor lotului | `MISSING` | Lista HTML /manage/batches/{id} | GET /api/v2/manage/batches/{id}/events?cursor= | Eveniment, actor, dată și motiv. Snapshotul și evenimentele sunt resurse diferite. |
| **BAT-009** Aprobă și publică lotul | `EXISTENT` | POST /api/v1/manage/batches/{id}/publish | Păstrează | Aprobare publicare. Publicarea este distinctă de eliberarea pentru consum. |
| **BAT-010** Înregistrează eliberarea lotului | `EXISTENT` | POST /api/v1/manage/batches/{id}/release | Păstrează | Decizie de eliberare. Numai rol autorizat; nu este decizie automată AI. |
| **BAT-011** Deschide pagina publică și QR lot | `EXISTENT` | GET /b/{id}; GET /api/v1/manage/qr/batch/{id} | Păstrează | URL public și identificator lot. Vizibil numai după aprobare conform regulilor curente. |
| **BAT-012** Listează toate alertele și rezoluțiile interne | `MISSING` | HTML /manage/batches/{id} | GET /api/v2/manage/batches/{id}/alerts?include_resolved=true | Mesaj, stare, autor, rezoluție. Publicul vede proiecția activă, nu toate datele interne. |
| **BAT-013** Publică alertă și pune lotul în verificare | `EXISTENT` | POST /api/v1/manage/batches/{id}/alerts | Păstrează | message, state=on_hold. Scope alerts și owner/quality; vizibil imediat online. |
| **BAT-014** Publică rechemare de lot | `EXISTENT` | POST /api/v1/manage/batches/{id}/alerts | Păstrează | message, state=recalled. Nu ascunde alerta printr-o schimbare ulterioară a etichetei. |
| **BAT-015** Închide alerta cu motiv | `EXISTENT` | POST /api/v1/manage/alerts/{id}/resolve | Păstrează | resolution. Închiderea alertei nu șterge istoricul și nu înseamnă automat eliberare. |
| **BAT-016** Corectează datele lotului fără rescriere istorică | `PLANNED` | — | POST /api/v2/manage/batches/{id}/corrections | Revizie nouă, motiv, expected_revision și domeniu de aplicare. Nu există editor curent; păstrează identitatea stabilă și probele anterioare. |

<a id="doc"></a>
### DOC — Rețete, documente și propuneri ale producătorului

**Suprafețe:** `/manage/products/{id}`; `/manage/documents/{id}`; `/p/{id}/v/{version}`.

**Acces:** read/write; owner/quality pentru publicare și decizii. **Prioritate orientativă:** P1.

**Surse:** [app/Views/manage.php](../../app/Views/manage.php), [app/Domain/Mutations.php](../../app/Domain/Mutations.php), [app/Domain/Documents.php](../../app/Domain/Documents.php), [app/Domain/Community.php](../../app/Domain/Community.php), [app/Controllers/ApiController.php](../../app/Controllers/ApiController.php), [app/Controllers/PublicController.php](../../app/Controllers/PublicController.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **DOC-001** Listează rețetele private și publice ale produsului | `MISSING` | Lista HTML /manage/products/{id} | GET /api/v2/manage/products/{id}/recipes?cursor= | ID, version_key, vizibilitate, licență și technical_status. Nu trimite conținut privat fără rol read autorizat. |
| **DOC-002** Citește o versiune de rețetă privată | `EXISTENT` | GET /api/v1/manage/recipes/{id} | Păstrează | Snapshot imutabil și hash. API public separat; simpla asociere la lot nu deschide rețeta. |
| **DOC-003** Înregistrează versiune privată de rețetă/proces | `EXISTENT` | POST /api/v1/manage/products/{id}/recipes | Păstrează | version_key, snapshot cu formula/process, visibility=private. Se poate folosi fără publicare și fără siglă. |
| **DOC-004** Publică explicit o versiune nouă de rețetă | `EXISTENT` | POST /api/v1/manage/products/{id}/recipes | Păstrează; comandă separată de publicare pentru versiune existentă de adăugat | visibility=public, license_id, rights_confirmed, license_confirmed, public_projection_confirmed. Toate confirmările și rolul de aprobare sunt obligatorii. |
| **DOC-005** Alege licența și confirmă drepturile asupra rețetei | `EXISTENT` | POST /api/v1/manage/products/{id}/recipes | Aceeași creare; GET /api/v2/public/licenses pentru vocabular | CC-BY-4.0, CC-BY-SA-4.0 sau CC0-1.0 în implementarea curentă. Nu aplica MIT asupra rețetelor prin licența software a platformei. |
| **DOC-006** Publică ulterior o rețetă privată existentă | `MISSING` | Nu există buton sau API separat | POST /api/v2/manage/recipes/{id}/publication-decisions | Copie publicabilă/versionată, licență și confirmări. Snapshotul original privat se păstrează; nu schimba drepturile pe ascuns. |
| **DOC-007** Listează documentele produsului | `MISSING` | HTML /manage/products/{id} | GET /api/v2/manage/products/{id}/documents?cursor= | Titlu, MIME, hash, dimensiune, stare și proveniență. Fără storage_key sau căi ale serverului. |
| **DOC-008** Încarcă document privat | `MISSING` | POST /manage, _action=document.upload, multipart | POST /api/v2/manage/products/{id}/documents | Fișier multipart, title; ID/hash/MIME. Acesta este un gol real pentru ERP; limite de tip/dimensiune și scanare conform politicii. |
| **DOC-009** Citește metadatele documentului privat | `EXISTENT` | GET /api/v1/manage/documents/{id} | Păstrează | Metadate fără storage_key. Evitați expunerea accidentală de coloane printr-un contract explicit v2. |
| **DOC-010** Descarcă documentul privat | `EXISTENT` | GET /manage/documents/{id}; Authorization Bearer read acceptat de Auth::principal | GET /api/v2/manage/documents/{id}/content sau alias stabil documentat | Conținut binar autorizat și hash. Ruta nu are prefix /api, dar poate autentifica token; descrie Content-Disposition/MIME și erorile în contract. |
| **DOC-011** Aprobă publicarea copiei documentului | `EXISTENT` | POST /api/v1/manage/documents/{id}/publish | Păstrează | rights_confirmed, public_projection_confirmed și license_id opțional. Găzduirea publică nu înseamnă obligatoriu licență deschisă. |
| **DOC-012** Înlocuiește sau retrage document publicat | `PLANNED` | — | POST /api/v2/manage/documents/{id}/revisions; POST /api/v2/manage/documents/{id}/withdrawals | Revizie/motiv, efect asupra noilor proiecții. Nu șterge fișierul folosit ca probă istorică; tratați explicit eventualele obligații de eliminare. |
| **DOC-013** Creează propunere de îmbunătățire privată | `EXISTENT` | POST /api/v1/manage/products/{id}/proposals | Păstrează | title, body, public=false. Origine umană, independent de produsul aprobat. |
| **DOC-014** Creează propunere publică | `EXISTENT` | POST /api/v1/manage/products/{id}/proposals | Păstrează | public=true și copie publicabilă. Nicio publicare automată a materialelor private citate. |
| **DOC-015** Listează și citește propunerile interne | `MISSING` | Propuneri în export; nu există listă dedicată manage | GET /api/v2/manage/products/{id}/proposals; GET /api/v2/manage/proposals/{id} | Propuneri, stare, sursă și istoric decizii. Necesare pentru utilizarea API-ului de decizie deja existent. |
| **DOC-016** Decide starea propunerii și păstrează motivul | `EXISTENT` | POST /api/v1/manage/proposals/{id}/decide | Păstrează; GET /api/v2/manage/proposals/{id}/decisions | state=proposed/planned/accepted/rejected/tested, decision. Operație API fără formular dedicat curent; istoricul trebuie accesibil. |

<a id="res"></a>
### RES — Resolver public GS1 Digital Link

**Suprafețe:** `/01/{gtin}[/{AI}/{value}]`; `/eh…`; `/ex…`; `/.well-known/gs1resolver`; `/resolver/profile`; `/resolver/contexts`; `/resolver/linkset-context`.

**Acces:** public. **Prioritate orientativă:** P0.

**Surse:** [app/Controllers/ResolverController.php](../../app/Controllers/ResolverController.php), [app/Domain/Resolver.php](../../app/Domain/Resolver.php), [app/Domain/ResolverUri.php](../../app/Domain/ResolverUri.php), [app/Views/resolver-profile.php](../../app/Views/resolver-profile.php), [app/Views/resolver-linkset.php](../../app/Views/resolver-linkset.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **RES-001** Deschide destinația implicită pentru GTIN | `EXISTENT` | GET /01/{gtin} | Păstrează protocolul resolverului | URI și redirect HTTP conform profilului. Resolverul nu este un endpoint CRUD și nu trebuie înlocuit prin API JSON simplificat. |
| **RES-002** Rezolvă variantă, lot, serie sau TPX | `EXISTENT` | GET /01/{gtin}/22/{variant}/10/{lot}; GET /01/{gtin}/10/{lot}; GET /01/{gtin}/21/{serial}; GET /01/{gtin}/235/{tpx} | Păstrează combinațiile admise în profil | AI 22/10/21/235, valori codificate corect. Cheia primară declarată este 01; nu afirma suport pentru toate cheile GS1. |
| **RES-003** Rezolvă identificatori EPC hex sau base64url | `EXISTENT` | GET /eh…; GET /ex… | Păstrează profilul și vectorii de conformitate | Reprezentare EPC admisă, resurse corespunzătoare. Nu confunda EPC cu orice URL comprimat sau identificator GS1. |
| **RES-004** Cere un tip de legătură | `EXISTENT` | GET /01/{gtin}?linkType=gs1:allergenInfo | Păstrează | Tip GS1/IANA acceptat și resursă publicată. Lipsa tipului cerut poate produce 404; nu inventa resursă. |
| **RES-005** Cere toate legăturile publice | `EXISTENT` | GET /01/{gtin}?linkType=linkset; Accept: application/linkset+json | Păstrează | Linkset, anchor și metadate. Și linkType=all este tratat ca linkset în controller. |
| **RES-006** Negociază limba și formatul | `EXISTENT` | GET /01/{gtin}; Accept și Accept-Language | Păstrează | Resursă potrivită sau alegeri. Documentați prioritatea negociată, Vary și răspunsurile 300/404. |
| **RES-007** Selectează contextul și transmite date suplimentare | `EXISTENT` | GET /01/{gtin}?context=…; parametrii URI validați de ResolverUri | Păstrează regulile existente | Context și query transmis destinației. Codifică o singură dată; păstrează sensul caracterelor din lot. |
| **RES-008** Citește linkset în JSON, JSON-LD sau HTML | `EXISTENT` | GET /01/{gtin}?linkType=linkset; Accept: application/json, application/ld+json sau text/html | Păstrează | Același set public de resurse. HTML oferă și alegeri clickable și JSON-LD încorporat. |
| **RES-009** Deschide o resursă din pagina de alegeri | `LOCAL` | Linkuri din resolver-linkset.php | Fără endpoint nou | URL aprobat al resursei. Aplicația validează schema destinației înaintea navigării. |
| **RES-010** Inspectează fără corp și verifică preflight | `EXISTENT` | HEAD /01/{gtin}; OPTIONS /01/{gtin} | Păstrează | Headere Location/Link/Allow/CORS. HEAD/OPTIONS sunt confirmate la nivelul controllerului, nu presupuse pentru toate API-urile v1/v2. |
| **RES-011** Descoperă capabilitățile resolverului | `EXISTENT` | GET /.well-known/gs1resolver | Păstrează | Chei primare, namespace, contexte și profil. Acesta este manifest GS1, nu manifest general de agent AI. |
| **RES-012** Citește toate contextele publicate | `EXISTENT` | GET /resolver/contexts | Păstrează | contexts[]. Nu include metadatele ciornelor sau ale altui nivel privat. |
| **RES-013** Citește contextul JSON-LD | `EXISTENT` | GET /resolver/linkset-context | Păstrează | application/ld+json. Versiune stabilă și sursă de vocabular. |
| **RES-014** Citește profilul și nivelul verificat | `STATIC` | GET /resolver/profile | GET /.well-known/gs1resolver plus manifest general propus | Profil GTIN și referințe la dovezile de test. Nu eticheta extensiile viitoare ca deja testate. |

<a id="rlk"></a>
### RLK — Administrarea destinațiilor resolverului

**Suprafețe:** `/manage/resolver/{id}`.

**Acces:** read/write/publish; drept GTIN verificat pentru administrare. **Prioritate orientativă:** P0.

**Surse:** [app/Controllers/ResolverManageController.php](../../app/Controllers/ResolverManageController.php), [app/Domain/ResolverRegistry.php](../../app/Domain/ResolverRegistry.php), [app/Views/resolver-manage.php](../../app/Views/resolver-manage.php), [app/Controllers/ApiController.php](../../app/Controllers/ApiController.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **RLK-001** Citește articolul, mandatul și eligibilitatea resolverului | `PARTIAL` | GET /api/v1/manage/trade-items/{id} | GET /api/v2/manage/trade-items/{id}/capabilities | GTIN, starea drepturilor, acțiuni permise și motive. Tokenul cu scope nu înlocuiește verificarea GTIN. |
| **RLK-002** Listează destinațiile și toate reviziile | `EXISTENT` | GET /api/v1/manage/trade-items/{id}/resolver-links | Păstrează; adaugă paginare | link_key, version_no, stare, current, qualifiers, target și decizie. Istoric distinct de setul public curent. |
| **RLK-003** Pregătește destinație nouă | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/resolver-links | Păstrează | title, href, types, qualifiers, is_default, hreflang, type, context. Ciornă până la aprobare explicită. |
| **RLK-004** Pregătește versiune nouă pornind dintr-o legătură | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/resolver-links | Păstrează | link_key și expected_version plus target nou. ?edit din UI preumple local; nu este o actualizare distructivă. |
| **RLK-005** Alege tipurile GS1 sau relațiile IANA | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/resolver-links | Aceeași creare; GET /api/v2/public/vocabularies/link-types | Tipuri și namespace-uri acceptate. Expune vocabularul validabil pentru dezvoltatori și AI. |
| **RLK-006** Stabilește domeniul GTIN, variantă, lot, serie sau TPX | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/resolver-links | Aceeași creare | qualifiers {}, {22}, {10}, {22,10}, {21}, {235}. Nu accepta combinații arbitrare doar fiindcă formularul are toate câmpurile. |
| **RLK-007** Marchează destinația implicită | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/resolver-links | Aceeași creare | is_default și tip descriptiv. Default fără hreflang/media/context opționale conform regulilor curente. |
| **RLK-008** Stabilește limbile, tipul media și contextele | `EXISTENT` | POST /api/v1/manage/trade-items/{id}/resolver-links | Aceeași creare | hreflang[], type, context[]. Formularul CSV este adaptare web; API primește structurile JSON. |
| **RLK-009** Aprobă și publică destinația | `EXISTENT` | POST /api/v1/manage/resolver-links/{id}/publish | Păstrează | public_projection_confirmed=true. owner/quality + publish, audit și revizie anterioară păstrată. |
| **RLK-010** Retrage destinația cu motiv | `EXISTENT` | POST /api/v1/manage/resolver-links/{id}/withdraw | Păstrează | reason. Istoric păstrat; fără reutilizare arbitrară a identității QR. |
| **RLK-011** Simulează alegerea destinației înainte de publicare | `MISSING` | Astăzi inspecție ciornă în UI și teste separate | POST /api/v2/manage/trade-items/{id}/resolution-previews | URI, antete/context și ciorne autorizate; rezultat explicat. Nu publica ciorna în resolverul public și nu executa destinația externă. |

<a id="qrc"></a>
### QRC — Generare și utilizare QR

**Suprafețe:** `/manage/qr/item/{id}`; `/manage/qr/batch/{id}`.

**Acces:** read în organizație. **Prioritate orientativă:** P0.

**Surse:** [app/Controllers/ManageController.php](../../app/Controllers/ManageController.php), [app/Domain/Catalog.php](../../app/Domain/Catalog.php), [public/assets/qr.js](../../public/assets/qr.js).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **QRC-001** Obține adresa QR generic de produs | `EXISTENT` | GET /api/v1/manage/qr/item/{id} | Păstrează | kind, url, native_url, digital_link, reason, resolver_level. Fără lot implicit presupus. |
| **QRC-002** Obține adresa QR pentru lot | `EXISTENT` | GET /api/v1/manage/qr/batch/{id} | Păstrează | Același contract, kind=batch. Lotul exact rămâne în identificare. |
| **QRC-003** Explică de ce QR folosește adresă nativă | `EXISTENT` | GET /api/v1/manage/qr/item/{id}; GET /api/v1/manage/qr/batch/{id} | Păstrează reason și nivelul verificat | Mandat/transport/configurație disponibile. Nu prezenta QR nativ ca resolver GS1 verificat. |
| **QRC-004** Randează și descarcă SVG QR | `LOCAL` | qrcodegen local în assets/qr.js | SDK QR; opțional GET /api/v2/manage/qr/{kind}/{id}.svg | SVG determinist, contrast și quiet zone de 4 module. API existent returnează URL, nu imagine SVG. |
| **QRC-005** Deschide pagina identificată pentru probă | `LOCAL` | Link către qr.url | Reutilizare URL public | Pagina produsului/lotului sau resolver. Nu marchează automat test POS trecut. |
| **QRC-006** Obține QR în volum pentru tipar ERP | `PLANNED` | — | POST /api/v2/manage/qr-jobs; GET /api/v2/manage/qr-jobs/{id}; GET /api/v2/manage/qr-jobs/{id}/content | Listă de ID-uri și format; artefact verificabil. Job limitat cu cote; identificatori aprobați, fără acces transversal. |

<a id="tok"></a>
### TOK — Acces API și tokenuri producători

**Suprafețe:** `/manage/api`.

**Acces:** owner al organizației; tokenurile nu pot crea alte tokenuri. **Prioritate orientativă:** P0.

**Surse:** [app/Views/manage.php](../../app/Views/manage.php), [app/Controllers/ManageController.php](../../app/Controllers/ManageController.php), [app/Domain/Accounts.php](../../app/Domain/Accounts.php), [app/Core/Auth.php](../../app/Core/Auth.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **TOK-001** Listează tokenurile și valabilitatea | `MISSING` | GET /manage/api | GET /api/v2/manage/access-tokens | ID, nume, scopes, expirare, revocare. Fără token_hash sau secret în listă. |
| **TOK-002** Creează token de integrare | `MISSING` | POST /manage, _action=token.create | POST /api/v2/manage/access-tokens | name, days 1–365, scopes[]; secret afișat o singură dată. Numai sesiune owner reautentificată sau grant administrativ dedicat; fără autodelegare prin token ERP. |
| **TOK-003** Alege permisiunile și durata | `LOCAL` | Câmpurile formularului token.create | Aceeași creare | read/write/publish/alerts/export/import. Nu inventa scopes noi ca deja funcționale. |
| **TOK-004** Copiază secretul nou și îl păstrează în ERP | `LOCAL` | Flash în sesiune, afișare unică | Fără endpoint de recitire a secretului | Token brut numai la emitere. Nu apare în documentație, loguri, query sau export. |
| **TOK-005** Revocă tokenul | `MISSING` | POST /manage, _action=token.revoke | DELETE /api/v2/manage/access-tokens/{id} | Revocare și audit. Imediat pentru apelurile ulterioare. |
| **TOK-006** Rotește tokenul fără întreruperea integrării | `PLANNED` | — | POST /api/v2/manage/access-tokens/{id}/rotations | Token nou, perioadă controlată de suprapunere. Niciodată secretul vechi recuperabil. |
| **TOK-007** Citește consumul și limitele integrării | `PLANNED` | — | GET /api/v2/manage/api-usage | Agregate, cote, ferestre și retry_after. Nu expune datele altor producători sau payload-uri private în analitice. |

<a id="imp"></a>
### IMP — Import/export producător și migrare

**Suprafețe:** `/manage/import`; `/manage/export`.

**Acces:** export/import + owner/quality. **Prioritate orientativă:** P0.

**Surse:** [app/Views/manage.php](../../app/Views/manage.php), [app/Controllers/ManageController.php](../../app/Controllers/ManageController.php), [app/Domain/Portability.php](../../app/Domain/Portability.php), [app/Controllers/ApiController.php](../../app/Controllers/ApiController.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **IMP-001** Descarcă exportul JSON al organizației | `EXISTENT` | GET /api/v1/manage/export | Păstrează | openaliment.transfer.v1, date, documente base64, hash. Include date autorizate, nu conturi/tokenuri/contacte private/texte brute feedback. |
| **IMP-002** Descarcă exportul NDJSON în flux | `EXISTENT` | GET /api/v1/manage/export?format=ndjson | Păstrează | openaliment.transfer.stream.v1, rânduri și digest terminal. Pentru volume mari; exportul JSON are limite distincte. |
| **IMP-003** Verifică pachetul de transfer fără import | `EXISTENT` | POST /api/v1/manage/import, dry_run=true | Păstrează | package JSON; raport de validare. Nu verifică drepturile GS1 în numele organizației. |
| **IMP-004** Importă pachetul în spațiul privat | `EXISTENT` | POST /api/v1/manage/import, dry_run=false | Păstrează | Pachet și cheie idempotentă; id_map și raport. Toate publicările, mandate și licențe se reverifică. |
| **IMP-005** Încarcă fișierul JSON de transfer prin formular | `LOCAL` | transfer.inspect / transfer.import în POST /manage | Aceleași API-uri de import; upload mare prin job dedicat dacă necesar | Fișier JSON până la limita implementată. Nu există import NDJSON în streaming doar fiindcă există export NDJSON. |
| **IMP-006** Verifică arhiva pilot OpenBread fără import | `EXISTENT` | POST /api/v1/manage/import-pbt, dry_run=true | Păstrează | Raport manifest și stadiu materiale. Arhivă opțională pe server; răspuns controlat dacă lipsește. |
| **IMP-007** Importă Pâine Bună Tehnic privat | `EXISTENT` | POST /api/v1/manage/import-pbt, dry_run=false | Păstrează | R1, costuri, originale și proveniență. BT01 deteriorat/recuperat și estimările își păstrează avertizările reale. |
| **IMP-008** Citește raportul de import după închiderea paginii | `PARTIAL` | Flash HTML; raport returnat sincron de API | GET /api/v2/manage/imports; GET /api/v2/manage/imports/{id} | Raport, stadiu, mapare ID și erori. Nu este suficientă păstrarea exclusiv în sesiunea browserului. |
| **IMP-009** Importă export NDJSON de volum mare | `PLANNED` | — | POST /api/v2/manage/import-jobs; PUT /api/v2/manage/import-jobs/{id}/chunks/{part}; POST /api/v2/manage/import-jobs/{id}/validate; POST /api/v2/manage/import-jobs/{id}/apply | Manifest/hash, părți, dry-run, aprobare și job. Necesită parser și atomicitate explicită; în prezent neimplementat. |
| **IMP-010** Migrează de la GS1 sau alt furnizor | `PLANNED` | — | GET /api/v2/manage/migration-adapters; POST /api/v2/manage/migration-previews; POST /api/v2/manage/migrations | Format sursă declarat, linkseturi, drepturi, mapări URL și documente. Nu există un format universal de export al tuturor serviciilor GS1; adaptor pe formatul furnizat și permis. |
| **IMP-011** Exportează și reimportă fără pierderea istoricului complet | `PARTIAL` | Portability export/import și payload istoric păstrat | Raport de round-trip cu domeniul păstrat, omis și reverificat | Proveniență, licențe, revizii, URL-uri și amprente. Unele date istorice rămân în payload importat, nu sunt toate reconstruite ca înregistrări active. |

<a id="fbk"></a>
### FBK — Moderare feedback și AI producător

**Suprafețe:** `/manage/feedback`.

**Acces:** read pentru listă; owner/quality + publish pentru moderare. **Prioritate orientativă:** P1.

**Surse:** [app/Views/manage.php](../../app/Views/manage.php), [app/Controllers/ManageController.php](../../app/Controllers/ManageController.php), [app/Domain/Community.php](../../app/Domain/Community.php), [app/Domain/Ai.php](../../app/Domain/Ai.php), [tools/worker.php](../../tools/worker.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **FBK-001** Listează observațiile și întrebările interne | `MISSING` | GET /manage/feedback, max 100 | GET /api/v2/manage/feedback?cursor=&status=&kind=&product_id=&batch_id= | Original privat, stare, context și rezumat AI autorizat. Separă răspunsul intern de copia publică. |
| **FBK-002** Citește o observație și istoricul moderării | `MISSING` | Date în HTML și audit la fiecare decizie | GET /api/v2/manage/feedback/{id}; GET /api/v2/manage/feedback/{id}/decisions | Original, proiecție, motiv și actori. Nu trimite textul quality către pipeline public AI. |
| **FBK-003** Publică o copie revizuită fără date personale | `EXISTENT` | POST /api/v1/manage/feedback/{id}/moderate | Păstrează | status=published, public_text, reason. Sesizările quality nu pot fi publicate prin această comandă. |
| **FBK-004** Păstrează feedback restricționat | `EXISTENT` | POST /api/v1/manage/feedback/{id}/moderate | Păstrează | status=restricted, reason. Nu înseamnă ștergerea originalului sau drept de folosire publică. |
| **FBK-005** Respinge feedback cu motiv | `EXISTENT` | POST /api/v1/manage/feedback/{id}/moderate | Păstrează | status=rejected, reason. Istoric obligatoriu; fără penalizare automată a criticii legitime. |
| **FBK-006** Citește propunerea AI și răspunsul ciornă | `MISSING` | result_json în HTML /manage/feedback | GET /api/v2/manage/feedback/{id}/ai-results | summary, consumer_reply_draft, evidence_refs și stare. Ciornă, nu răspuns aprobat și nu modificare de etichetă. |
| **FBK-007** Verifică disponibilitatea AI și bugetul propriu | `MISSING` | Worker poate întoarce disabled/unconfigured/idle_or_budget | GET /api/v2/manage/ai/capabilities; GET /api/v2/manage/ai/usage | Funcții activate, cote și motive. Nu expune API keys, configurație privată sau cote globale ale altora. |
| **FBK-008** Solicită analiză sau reanaliză autorizată | `PLANNED` | Moderarea publică poate crea automat ai_job; fără comandă HTTP | POST /api/v2/manage/ai-jobs | Tip job, surse/versiuni aprobate, scop, idempotentă; ID. Coada existentă nu înseamnă API generic de chat sau RAG deja disponibil. |
| **FBK-009** Urmărește sau anulează un job AI propriu | `PLANNED` | — | GET /api/v2/manage/ai-jobs; GET /api/v2/manage/ai-jobs/{id}; POST /api/v2/manage/ai-jobs/{id}/cancel | Stare, erori controlate, cost și rezultat permis. Nu expune prompturi/date ale altui tenant. |
| **FBK-010** Transformă rezultatul AI în propunere verificată | `PARTIAL` | POST /api/v1/manage/products/{id}/proposals, creare umană | POST /api/v2/manage/ai-results/{id}/proposals | Rezultat sursă, copie revizuită și motiv. Nu aplică direct schimbări asupra datelor aprobate. |
| **FBK-011** Aprobă și publică răspunsul la întrebare | `PLANNED` | — | POST /api/v2/manage/feedback/{id}/replies; POST /api/v2/manage/replies/{id}/publish | Răspuns versionat și aprobare explicită. Nu există astăzi funcție de răspuns public separat. |

<a id="con"></a>
### CON — Conectoare ERP și magazine

**Suprafețe:** `/manage/connectors`; `/manage/connectors/{id}`.

**Acces:** read/write + rol în organizație. **Prioritate orientativă:** P0.

**Surse:** [app/Controllers/IntegrationManageController.php](../../app/Controllers/IntegrationManageController.php), [app/Controllers/IntegrationController.php](../../app/Controllers/IntegrationController.php), [app/Views/connectors.php](../../app/Views/connectors.php), [app/Domain/Integrations.php](../../app/Domain/Integrations.php), [app/Domain/PartnerMapping.php](../../app/Domain/PartnerMapping.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **CON-001** Listează conexiunile organizației | `EXISTENT` | GET /api/v2/manage/connectors | Păstrează; adaugă cursor | ID, name, kind și created_at. Limită curentă 200 fără paginare. |
| **CON-002** Creează conexiune ERP sau magazin | `EXISTENT` | POST /api/v2/manage/connectors | Păstrează | name, kind=catalog/retail_receipt. Kind nu se presupune după numele partenerului. |
| **CON-003** Citește o conexiune individuală | `PARTIAL` | Se extrage din lista GET /api/v2/manage/connectors | GET /api/v2/manage/connectors/{id} | Stare, configurare nesensibilă și capabilități. Endpoint individual încă absent. |
| **CON-004** Listează și citește versiunile mapării | `EXISTENT` | GET /api/v2/manage/connectors/{id}/mappings | Păstrează; GET /api/v2/manage/mappings/{id} opțional | version_key, sha256, mapping și created_at. Limită curentă 200; versions imutabile. |
| **CON-005** Extinde panoul unei mapări și citește hashul | `LOCAL` | HTML details din connectors.php | Reutilizare GET mappings | Mapare JSON și SHA-256. Nicio scriere și nicio activare implicită. |
| **CON-006** Salvează versiune nouă de mapare | `EXISTENT` | POST /api/v2/manage/connectors/{id}/mappings | Păstrează | version_key, mapping cu JSON Pointer și transformări. Fără eval, cod arbitrar sau URL-uri externe executabile. |
| **CON-007** Verifică payload de produs, lot sau bon fără import | `EXISTENT` | POST /api/v2/manage/connectors/{id}/preview | Păstrează | mapping_id, operation, payload; canonical, writes=false. Idempotency-Key nu este obligatoriu pentru preview fără scriere. |
| **CON-008** Importă produs și etichetă ERP în ciornă | `EXISTENT` | POST /api/v2/manage/connectors/{id}/ingest | Păstrează | operation=product, mapping_id, event_key, external_id, payload. Nu publică automat și nu verifică singur mandatul GTIN. |
| **CON-009** Importă lot ERP cu etichetă aprobată explicită | `EXISTENT` | POST /api/v2/manage/connectors/{id}/ingest | Păstrează | operation=batch, trade_external_id și label_version_key în forma mapată. Lotul existent nu poate fi rescris de un eveniment nou. |
| **CON-010** Creează bon digital de la magazin | `EXISTENT` | POST /api/v2/manage/connectors/{id}/ingest | Păstrează | operation=receipt; URL confidențial și rezultat. Fără date de plată sau identitatea cumpărătorului. |
| **CON-011** Retrimite un eveniment fără dublare | `EXISTENT` | POST /api/v2/manage/connectors/{id}/ingest cu aceleași chei și conținut | Păstrează | Idempotency-Key și event_key; același rezultat. Conținut diferit cu cheie reutilizată produce conflict. |
| **CON-012** Citește raportul și deschide bonul creat | `PARTIAL` | Răspuns ingest/preview și raport HTML | GET /api/v2/manage/connectors/{id}/events/{event_key} | Raport persistat, mapping hash, ID-uri. Nu loga sau lista în clar tokenul bonului. |
| **CON-013** Listează evenimentele, erorile și legăturile externe | `MISSING` | integration_events și integration_bindings există intern | GET /api/v2/manage/connectors/{id}/events; GET /api/v2/manage/connectors/{id}/bindings | Cursor, external_id, rezultat și referințe. Exportul nu înlocuiește o căutare operațională eficientă. |
| **CON-014** Suspendă conexiunea sau modifică numele | `PLANNED` | — | PATCH /api/v2/manage/connectors/{id}; POST /api/v2/manage/connectors/{id}/suspend | Revizie, stare, motiv și audit. Nu șterge mapările și evenimentele vechi. |
| **CON-015** Testează integrarea folosind ERP/magazin simulate | `PARTIAL` | tools/partner-agent.py și teste locale de simulare | Sandbox izolat, fixtures și API pentru resetarea exclusiv a sandboxului | Date sintetice, rapoarte, exemple de retry și mapare. Nu afirma existența unui partener comercial real. |

<a id="hom"></a>
### HOM — Acasă: inventar, detalii, corectare și anulare

**Suprafețe:** `/acasa/`; `dialog details`; `dialog editor`.

**Acces:** local fără cont; gospodărie autorizată pentru sincronizare. **Prioritate orientativă:** P0.

**Surse:** [app/Views/consumer-home.php](../../app/Views/consumer-home.php), [public/assets/home/app.js](../../public/assets/home/app.js), [public/assets/home/domain.js](../../public/assets/home/domain.js), [public/assets/home/store.js](../../public/assets/home/store.js).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **HOM-001** Deschide inventarul fără cont și fără rețea | `LOCAL` | store.read din IndexedDB | SDK storage; opțional GET /api/v2/home/households/{id}/packages | Ambalaje și stare locală. Lista nu trebuie blocată de login, AI sau rețea; endpointul de listă dedicată lipsește. |
| **HOM-002** Vezi Ce am sau Terminate | `LOCAL` | data-filter available/consumed | Același SDK; filtrare state în API de listă propus | Filtru și stare ambalaj. Nu sunt două baze de date sau două conturi. |
| **HOM-003** Ordonează după alerte și ce trebuie folosit întâi | `LOCAL` | domain.status și sortare în app.js | SDK reguli deterministe versionate | Stare, termen, deschidere, alertă și actualitate. Nu declara un produs sigur pe baza lipsei alertelor offline. |
| **HOM-004** Adaugă un ambalaj scanat sau manual | `PARTIAL` | domain.addItem local; POST /api/v2/home/sync op put | SDK addPackage; POST /api/v2/home/households/{id}/package-events opțional | ID ambalaj nou, profil și eveniment add. Același GTIN poate reprezenta mai multe ambalaje fizice. |
| **HOM-005** Adaugă încă un ambalaj identic | `LOCAL` | Buton Încă unul la fel, force=true | SDK addPackage explicit; sincronizarea existentă | ID fizic distinct și aceleași date sursă. Debounce-ul nu trebuie să interzică adăugarea intenționată. |
| **HOM-006** Deschide detaliile ambalajului | `LOCAL` | data-action detail; store.read | GET /api/v2/home/households/{id}/packages/{package_id} opțional | Ambalaj, sursă, date, fotografii, alerte. Cloud astăzi livrează prin changes, nu GET per package. |
| **HOM-007** Citește ingredientele, nutriția, păstrarea și după deschidere | `EXISTENT` | GET /api/v2/public/food?code=; copie locală în profil | Păstrează API public și SDK | Câmpuri text și food_data. Copie locală etichetată cu sursa și actualitatea. |
| **HOM-008** Citește alergenii relevanți pentru preferința locală | `LOCAL` | domain.allergenWarnings | SDK evaluateAllergens | Declarație completă/necunoscută și coduri locale selectate. Nu trimite selecția personală la producător. |
| **HOM-009** Calculează carbohidrații porției | `LOCAL` | domain.carbs, input cantitate | SDK calculateCarbohydrates; serviciu stateless doar opțional | Profil, cantitate, unitate; grame carbohidrați sau necunoscut. Nu reprezintă dozare de insulină sau recomandare de tratament. |
| **HOM-010** Marchează Am deschis | `PARTIAL` | domain.changeItem open; POST /api/v2/home/sync | SDK openPackage + eveniment idempotent; comandă semantică opțională | opened_at și istoric before/after. Butonul deja deschis este dezactivat, nu dublează momentul. |
| **HOM-011** Marchează Am terminat | `PARTIAL` | domain.changeItem consume; POST /api/v2/home/sync | SDK consumePackage + eveniment | consumed_at și state=consumed. Trebuie să oprească viitoarele memento-uri când vor exista. |
| **HOM-012** Anulează ultima adăugare/deschidere/consum/corecție | `PARTIAL` | domain.undo; POST /api/v2/home/sync pentru efect și istoric | SDK undoEvent cu expected_revision | Eveniment undo, undo es și snapshot anterior. Nu anula peste o modificare concurentă ulterioară. |
| **HOM-013** Deschide sau închide editorul/detaliile | `LOCAL` | dialog.showModal/close; data-close | Fără endpoint | Stare UI și focus accesibil. Închiderea fără salvare nu scrie date. |
| **HOM-014** Corectează numele personal | `PARTIAL` | edit-form; domain.changeItem edit; POST /api/v2/home/sync | SDK editPackage / comandă semantică opțională | name și expected_revision. Nu schimbă numele public al producătorului. |
| **HOM-015** Alege tipul datei: necunoscut, limită, preferință sau memento | `PARTIAL` | edit-form date_kind; POST /api/v2/home/sync | SDK checkedDate și editPackage | unknown/use_by/best_before/reminder. Memento personal distinct de termenul declarat. |
| **HOM-016** Alege precizia zi/lună/an și data | `PARTIAL` | edit-form date_precision/date_value; POST /api/v2/home/sync | SDK checkedDate | Valoare ISO și precizie. Nu transforma luna/anul în dată exactă fără indicarea preciziei. |
| **HOM-017** Schimbă locația acasă/frigider/cămară/congelator | `PARTIAL` | edit-form location; POST /api/v2/home/sync | SDK editPackage / eveniment move viitor | location. În prezent schimbă locația; nu calculează automat durabilitatea după congelare/decongelare. |
| **HOM-018** Corectează cantitatea și unitatea | `PARTIAL` | edit-form quantity/unit; POST /api/v2/home/sync | SDK editPackage | quantity, unit=pack/g/ml. Conversia masă-volum nu este implicită. |
| **HOM-019** Salvează corecțiile și istoricul | `PARTIAL` | edit-form submit; POST /api/v2/home/sync | SDK atomic edit + eveniment | Snapshot, before/after și revizie. Nu suprascrie pe server fără control de concurență. |
| **HOM-020** Vizualizează fotografia alimentului | `PARTIAL` | store.photo; GET /api/v2/home/photos/{id} dacă sincronizat | Păstrează; SDK pentru local blob | JPEG privat și ID. Fotografiile personale nu sunt imagini publice de produs. |
| **HOM-021** Citește avertizările, sursa, lotul și ultima verificare | `EXISTENT` | GET /api/v2/public/food?code= | Păstrează | alerts, notices, source.version, alerts_checked_at, lot_code. Necunoscut și neverificat nu sunt echivalente cu fără probleme. |
| **HOM-022** Deschide pagina sursă a produsului | `LOCAL` | safeLink(profile.public_url) | Fără endpoint nou | URL public validat. Refuză scheme periculoase și credențiale în URL. |
| **HOM-023** Citește cu voce numele și avertizările | `LOCAL` | SpeechSynthesis local, lang ro-RO | SDK/native TextToSpeech | Text deja accesibil utilizatorului. Nu necesită serviciu cloud de voce. |
| **HOM-024** Reverifică automat alertele la revenire online sau periodic | `EXISTENT` | GET /api/v2/public/food?code= per ambalaj | POST /api/v2/public/food-lookups pentru batch opțional | Profil actual doar pentru overlay alerte/notices. Lipsește batch API; nu rescrie datele personale și snapshotul istoric. |
| **HOM-025** Vezi lipsa conexiunii sau eroarea fără pierderea inventarului | `LOCAL` | online/offline, status și toast | SDK cu erori și starea actualității | Inventar local intact și retry ulterior. Fără endpoint separat pentru fiecare mesaj UI. |

<a id="scn"></a>
### SCN — Acasă: scanare, introducere cod, fotografie și OCR

**Suprafețe:** `/acasa/`; `dialog scanner`; `dialog code-dialog`; `dialog editor`.

**Acces:** local fără cont; API public pentru date. **Prioritate orientativă:** P0.

**Surse:** [app/Views/consumer-home.php](../../app/Views/consumer-home.php), [public/assets/home/app.js](../../public/assets/home/app.js), [public/assets/home/scanner.js](../../public/assets/home/scanner.js), [public/assets/home/ocr.js](../../public/assets/home/ocr.js), [public/assets/home/domain.js](../../public/assets/home/domain.js), [app/Controllers/ConsumerController.php](../../app/Controllers/ConsumerController.php), [public/assets/home/sw.js](../../public/assets/home/sw.js).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **SCN-001** Deschide camera prin Adaugă și permite accesul | `LOCAL` | camera.start/getUserMedia | SDK scanner și permisiune nativă | Cadre locale și cod decodat. Permisiune contextuală; niciun upload al fluxului video. |
| **SCN-002** Scanează EAN/UPC/QR și formate recunoscute de detector | `PARTIAL` | BarcodeDetector sau ZXing, apoi parseCode | SDK decode + parseCode cu matrice de formate testate | Cod brut, simbologie și AI-uri interpretate. Detectorul poate decoda mai multe simbologii decât parserul înțelege; nu afirma scanare GS1 universală deja completă. |
| **SCN-003** Validează și interpretează GTIN, Digital Link și datele | `LOCAL` | domain.parseCode și ConsumerFood::code | SDK comun cu vectori; POST /api/v2/tools/identifiers/parse opțional | GTIN ca șir, calificatori, date, warnings. Nu loga URI-uri personale sau capability tokens. |
| **SCN-004** Caută produsul după scanare | `EXISTENT` | GET /api/v2/public/food?code= | Păstrează | Profil normalizat și proveniență. Astăzi caută în OpenAliment; nu interoghează automat resolver extern sau Open Food Facts. |
| **SCN-005** Salvează necunoscutul când lipsesc date/rețea | `LOCAL` | unknownProfile și cache local | SDK fallback explicit | Ambalaj cu câmpuri necunoscute și termen cunoscut doar din sursă. Nu inventa ingredientele sau termenul. |
| **SCN-006** Previne scanări repetate accidentale | `LOCAL` | lastScan, GTIN/lot și interval 8 secunde | SDK debounce per sesiune de scanare | Rezultat salvat o singură dată. Nu este idempotency key de server și nici dovadă de cumpărare. |
| **SCN-007** Oferă feedback vizual, haptic și continuă scanarea | `LOCAL` | toast, navigator.vibrate, scan-again | SDK/native UI | Stare adăugat sau eroare. Fără endpoint HTTP. |
| **SCN-008** Închide camera la închidere, fundal sau părăsire | `LOCAL` | camera.stop, dialog cancel, visibilitychange/pagehide | SDK lifecycle | Oprire tracks și controale. Nicio resursă cloud de închis. |
| **SCN-009** Introdu manual cod de bare sau URL | `LOCAL` | code-form | Reutilizare GET /api/v2/public/food?code= și SDK add | code până la limita contractului. Nu cere numele produsului dacă identificarea este suficientă. |
| **SCN-010** Fotografiază sau alege imagine din galerie | `LOCAL` | photo-file și photoFile | SDK imagine, resize și eliminarea metadatelor | JPEG local și limite. Originalul nu este trimis pentru lookup. |
| **SCN-011** Citește codul din fotografie | `LOCAL` | camera.fromPhoto ZXing | SDK decoder din fotografie | Cod sau rezultat absent. Dacă nu există cod, poate crea aliment fotografiat. |
| **SCN-012** Adaugă aliment fotografiat fără cod | `PARTIAL` | unknownProfile + addItem cu photo_id; sync opțional | SDK addPhotoPackage | ID ambalaj, JPEG privat, nume editabil. Fotografia nu dovedește ingredientele sau identitatea. |
| **SCN-013** Fotografiază data de pe ambalaj | `LOCAL` | date-file, photoFile 1800 și OCR local | SDK OCR cu aceleași reguli | Imagine locală. OCR nu cere token AI și nu trimite date de sănătate. |
| **SCN-014** Citește propunerile de dată și progresul OCR | `LOCAL` | ocr.recognize/candidates | SDK OCR; modele și contract de rezultat publicate | Candidates, confidence, kind, precision. Nu tratează încrederea OCR ca aprobare a datei. |
| **SCN-015** Alege una dintre mai multe date și confirmă în editor | `LOCAL` | Butoane de candidates; edit-form save | SDK date-candidate + editPackage | Data aleasă cu sursa user. Doar salvarea modifică ambalajul. |
| **SCN-016** Introdu data manual când OCR eșuează | `LOCAL` | edit-form date fields | Același SDK și sync existent | Valoare și tip introduse de persoană. Lipsa OCR nu blochează adăugarea. |
| **SCN-017** Cere păstrarea stocării locale și înregistrează PWA | `LOCAL` | navigator.storage.persist; serviceWorker.register | SDK storage și PWA/native instalare | Permisiuni și stare. Nu transforma lipsa instalării în cerință de cont. |
| **SCN-018** Citește capabilitățile publice disponibile | `EXISTENT` | GET /api/v2/public/capabilities | Păstrează; versiune extinsă de capabilități | date_ocr, cloud_sync, digital_receipts, external_sources, sync_scope, shopping_sync. external_sources=false și shopping_sync=false sunt limite reale curente. |

<a id="loc"></a>
### LOC — Acasă: cumpărături și preferințe private

**Suprafețe:** `/acasa/`; `tab De cumpărat`; `dialog settings`.

**Acces:** local, fără cont; preferințele de sănătate nu se sincronizează implicit. **Prioritate orientativă:** P1.

**Surse:** [app/Views/consumer-home.php](../../app/Views/consumer-home.php), [public/assets/home/app.js](../../public/assets/home/app.js), [public/assets/home/domain.js](../../public/assets/home/domain.js), [public/assets/home/store.js](../../public/assets/home/store.js).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **LOC-001** Deschide lista De cumpărat | `LOCAL` | data-filter shopping | SDK shopping.list | Listă locală. Astăzi fără sincronizare cloud. |
| **LOC-002** Adaugă aliment în lista de cumpărături | `LOCAL` | shopping form și store.update | SDK shopping.add; POST /api/v2/home/households/{id}/shopping-items numai cu sync opt-in viitor | Nume, ID, done=false. API pentru lista comună încă absent. |
| **LOC-003** Bifează sau debifează aliment cumpărat | `LOCAL` | shopping checkbox change | SDK shopping.toggle; PATCH /api/v2/home/households/{id}/shopping-items/{item_id} viitor | done și revizie. Bifarea nu adaugă automat ambalaj în inventar. |
| **LOC-004** Șterge rândul din lista de cumpărături | `LOCAL` | shopping remove button | SDK shopping.remove; DELETE /api/v2/home/households/{id}/shopping-items/{item_id} viitor | ID rând. Nu șterge istoricul inventarului. |
| **LOC-005** Deschide preferințele Pentru mine | `LOCAL` | data-action settings | SDK preferences.read | Alergeni locali și setări UI. Nu cere login pentru preferințe locale. |
| **LOC-006** Selectează sau deselectează fiecare alergen personal | `LOCAL` | preferences checkbox pentru cele 14 coduri | SDK preferences.setAllergens | Coduri alese local. Nu adăuga endpoint de trimitere către producător. |
| **LOC-007** Păstrează alegerea și reevaluează avertizările | `LOCAL` | preferences submit și render | SDK local evaluateAllergens | Preferințe locale, avertizări recalculate. Un eventual backup cloud al preferințelor cere funcție/consimțământ separat. |
| **LOC-008** Închide dialoguri, schimbă taburi și anulează fără salvare | `LOCAL` | data-close/data-filter, dialog close | Fără endpoint | Stare UI. Toate butoanele de închidere și selectoarele de afișare sunt acoperite aici. |

<a id="bkp"></a>
### BKP — Acasă: copie personală și restaurare

**Suprafețe:** `/acasa/`; `dialog settings`.

**Acces:** local; export cloud numai pentru cont/gospodărie autorizată. **Prioritate orientativă:** P1.

**Surse:** [public/assets/home/transfer.js](../../public/assets/home/transfer.js), [public/assets/home/domain.js](../../public/assets/home/domain.js), [public/assets/home/store.js](../../public/assets/home/store.js), [app/Views/consumer-home.php](../../app/Views/consumer-home.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **BKP-001** Exportă copia de pe dispozitiv | `LOCAL` | exportCopy și download JSON | SDK exportCopy | openaliment.home.transfer.v1, inventar, poze, evenimente, preferințe, shopping și SHA-256. Fără cookie, token de conectare sau secrete. |
| **BKP-002** Selectează fișierul de import | `LOCAL` | import-file | SDK pickImportFile | JSON local până la 64 MB. Selectarea nu trimite fișierul pe server. |
| **BKP-003** Verifică schema, hashurile și fotografiile înainte de import | `LOCAL` | importCopy și importState | SDK validateTransfer | Erori complete și rezultat fără scrieri parțiale. Fotografii lipsă/diferite sau hash invalid opresc importul. |
| **BKP-004** Îmbină copia fără dublarea ambalajelor și evenimentelor | `LOCAL` | importCopy, IDs păstrate | SDK mergeTransfer | Număr adăugat, preferințe și shopping îmbinate. Nu consideră profilul importat drept sursă reverificată. |
| **BKP-005** Reverifică alertele după restaurare | `EXISTENT` | GET /api/v2/public/food?code= | Păstrează | Alerte actuale și moment verificare. alerts_checked_at resetat pentru copiile importate. |
| **BKP-006** Exportează integral datele de pe server ale contului | `PLANNED` | — | POST /api/v2/home/account/exports; GET /api/v2/home/account/exports/{id}; GET /api/v2/home/account/exports/{id}/content | Cont, spații autorizate, fotografii, istoric, manifest și cote. Backupul local existent nu garantează că a descărcat toate datele cloud. |
| **BKP-007** Importă în cloud cu previzualizare și raport | `PLANNED` | — | POST /api/v2/home/account/import-previews; POST /api/v2/home/account/imports | Pachet validat, destinație explicită și alegerea îmbinării. Nu transfera preferințe private tuturor membrilor familiei. |

<a id="hid"></a>
### HID — Acasă: conectare opțională și sesiune persistentă

**Suprafețe:** `/acasa/`; `dialog settings`; `#conectare=…`; `dialog cloud-login-dialog`.

**Acces:** cookie personal oa_home; fără Bearer pentru clienți nativi în implementarea actuală. **Prioritate orientativă:** P0.

**Surse:** [public/assets/home/cloud.js](../../public/assets/home/cloud.js), [app/Controllers/HomeController.php](../../app/Controllers/HomeController.php), [app/Domain/HomeIdentity.php](../../app/Domain/HomeIdentity.php), [app/Views/consumer-home.php](../../app/Views/consumer-home.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **HID-001** Citește disponibilitatea, sesiunea și dispozitivele | `PARTIAL` | GET /api/v2/home/session | Păstrează; suport token delegat pentru client nativ | available, magic_link_ready, authenticated, session, devices. Răspunsul este JSON dar autentificarea este cookie de browser. |
| **HID-002** Solicită legătura de conectare prin email | `EXISTENT` | POST /api/v2/home/login/request | Păstrează; documentează fluxul aplicațiilor native | email; răspuns neutru. Limitare cereri; nu divulgă existența contului și nu expune linkul în API public. |
| **HID-003** Deschide linkul fără consumare automată | `LOCAL` | #conectare=token; eliminare fragment din istoric | Universal/App Link plus preview existent | Token unic în fragment, afișaj de confirmare. Nu consuma la GET sau la scanarea emailului de către un robot. |
| **HID-004** Previzualizează identitatea din legătură | `EXISTENT` | POST /api/v2/home/login/preview | Păstrează | token; email destinatar. Citire, nu login automat. |
| **HID-005** Alege dacă păstrează și alimentele/fotografiile locale în cont | `LOCAL` | cloud-login-keep și connect(keepLocal) | SDK selecție explicită + sync | Inventar local selectat, fără preferințe de alergeni. Nu trimite date înainte de confirmare. |
| **HID-006** Confirmă conectarea dispozitivului | `PARTIAL` | POST /api/v2/home/login/confirm | Flux cookie păstrat; OAuth/PKCE pentru terți | token, device_name; cookie persistent și sesiune. Token consumat o singură dată; nu transforma răspunsul curent în token Bearer fără proiectare. |
| **HID-007** Activează Păstrează și alimentele de aici | `PARTIAL` | store.changeHousehold(keepLocal=true); POST /api/v2/home/sync | SDK opt-in sync | Migrare controlată inventar local în familia aleasă. Nu unește automat gospodării diferite. |
| **HID-008** Revine în cont fără login de rutină | `PARTIAL` | Cookie 90 zile, GET /api/v2/home/session | Reînnoire sigură a grantului pentru client nativ | Stare identitate/dispozitiv. Sesiunile producătorului și consumatorului rămân separate. |
| **HID-009** Deconectează dispozitivul curent | `PARTIAL` | POST /api/v2/home/logout | Păstrează; revocare token delegat echivalentă | Revocare dispozitiv, oprire sync local. Nu șterge inventarul local sau toate datele cloud. |
| **HID-010** Revine la alimentele numai de pe dispozitiv | `LOCAL` | cloud-local; changeHousehold(null, keepLocal=false) | SDK selectLocalWorkspace | Spațiu local distinct și sync oprit. Nu este cerere de ștergere a familiei cloud. |
| **HID-011** Primește mesaj de reconectare după expirare/revocare | `LOCAL` | HTTP 401 și UI cloud-guest | SDK auth state | Date locale intacte și posibilitate de reconectare. Nu pierde coada offline la expirare. |

<a id="fam"></a>
### FAM — Acasă: familie, invitații și dispozitive

**Suprafețe:** `/acasa/`; `settings Familia mea`; `#familie=…`; `dialog cloud-join-dialog`.

**Acces:** cookie personal și rol owner/editor în gospodăria selectată. **Prioritate orientativă:** P0.

**Surse:** [public/assets/home/cloud.js](../../public/assets/home/cloud.js), [app/Controllers/HomeController.php](../../app/Controllers/HomeController.php), [app/Domain/HomeIdentity.php](../../app/Domain/HomeIdentity.php), [app/Views/consumer-home.php](../../app/Views/consumer-home.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **FAM-001** Listează spațiile/familiile proprii | `PARTIAL` | GET /api/v2/home/households | Păstrează; extinde autorizarea nativă | items id/name/role. Nu include gospodării fără membership. |
| **FAM-002** Listează persoanele cu acces | `PARTIAL` | GET /api/v2/home/members | Păstrează | Membri ai familiei active. Emailurile sunt date private ale acestui spațiu. |
| **FAM-003** Redenumește familia | `PARTIAL` | POST /api/v2/home/rename | Păstrează; API cu household_id explicit recomandat | name; owner. Nu schimbă datele producătorului. |
| **FAM-004** Schimbă familia activă | `PARTIAL` | POST /api/v2/home/switch | Păstrează pentru web; resurse cu household_id în aplicații | household_id autorizat. Cursor și stocare separate pe familie; fără amestecarea inventarelor. |
| **FAM-005** Creează invitație cu acces | `PARTIAL` | POST /api/v2/home/invites | Păstrează | Rol permis, URL unic, termen 7 zile. owner; nu adaugă automat alt membru prin simpla deschidere. |
| **FAM-006** Copiază legătura invitației | `LOCAL` | navigator.clipboard sau input read-only | SDK clipboard/share sheet | URL confidențial. Nu există endpoint pentru copiere sau obligație de trimitere mesaj. |
| **FAM-007** Previzualizează invitația | `EXISTENT` | POST /api/v2/home/invites/preview | Păstrează | token; nume familie. Nu consumă invitația. |
| **FAM-008** Păstrează invitația până după conectare | `LOCAL` | pending_invite local și magic link | SDK pending-invite | Token local pe durata fluxului. Nu loga tokenul și nu îl pune în analitice. |
| **FAM-009** Alege dacă include alimentele locale la alăturare | `LOCAL` | cloud-join-keep | SDK merge choice | keepLocal true/false. Implicit neales în UI de alăturare; fără expunere accidentală. |
| **FAM-010** Acceptă invitația și intră în familie | `PARTIAL` | POST /api/v2/home/join | Păstrează; suport token delegat | token; membership și spațiu activ. Consumare unică; sync local numai după opțiune explicită. |
| **FAM-011** Retrage accesul unui membru | `PARTIAL` | POST /api/v2/home/members/{id}/remove | Păstrează | account_id; owner. Nu poate șterge copiile deja exportate pe dispozitivul altcuiva. |
| **FAM-012** Listează dispozitivele conectate | `PARTIAL` | GET /api/v2/home/session, devices[] | GET /api/v2/home/devices ca resursă opțională | ID, nume, stare/date permise. Nu expune secretul de dispozitiv. |
| **FAM-013** Revocă alt dispozitiv | `PARTIAL` | POST /api/v2/home/devices/{id}/revoke | Păstrează; token nativ asociat dispozitivului | ID propriu, revocare imediată. Nu poate revoca dispozitivul altui cont. |
| **FAM-014** Creează familie suplimentară, părăsește sau transferă proprietatea | `PLANNED` | — | POST /api/v2/home/households; POST /api/v2/home/households/{id}/leave; POST /api/v2/home/households/{id}/ownership-transfers | Spațiu nou sau decizie explicită, acceptarea noului owner. Funcții lipsă astăzi; protejează ultimul owner și datele offline. |
| **FAM-015** Listează/revocă invitații și schimbă roluri | `PLANNED` | — | GET /api/v2/home/households/{id}/invites; DELETE /api/v2/home/households/{id}/invites/{invite_id}; PATCH /api/v2/home/households/{id}/members/{member_id} | Rol și revizie autorizate. Nu confundă retragerea invitației cu revocarea membership-ului deja acceptat. |

<a id="syn"></a>
### SYN — Acasă: sincronizare, fotografii și conflicte

**Suprafețe:** `/acasa/`; `dialog cloud-conflict-dialog`.

**Acces:** cookie personal + X-Home-CSRF pentru scrieri; ACL gospodărie. **Prioritate orientativă:** P0.

**Surse:** [public/assets/home/cloud.js](../../public/assets/home/cloud.js), [public/assets/home/cloud-domain.js](../../public/assets/home/cloud-domain.js), [public/assets/home/store.js](../../public/assets/home/store.js), [app/Controllers/HomeController.php](../../app/Controllers/HomeController.php), [app/Domain/HomeSync.php](../../app/Domain/HomeSync.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **SYN-001** Trimite mutațiile și istoricul în mod atomic | `PARTIAL` | POST /api/v2/home/sync | Păstrează; autorizare nativă și documentație de evenimente | operations put/delete cu event_id, expected_revision și history. Nu există endpoint separat pentru fiecare buton open/consume/edit; se trimit snapshot + istoric. |
| **SYN-002** Citește modificările de la cursor | `PARTIAL` | GET /api/v2/home/changes?after=&limit= | Păstrează; cursor legat de gospodărie | changes, cursor, has_more și household_id. HEAD acceptat de HomeController; schimbările includ tombstones/istoric. |
| **SYN-003** Trimite o fotografie privată | `PARTIAL` | POST /api/v2/home/photos/{id}, JPEG brut | Păstrează | photo_id și JPEG până la 1 MB. Autorizare pe familie și cote, nu un upload public de produs. |
| **SYN-004** Descarcă fotografia privată la cerere | `PARTIAL` | GET /api/v2/home/photos/{id} | Păstrează | JPEG autorizat. Nu cache public și nu expune URL fără ACL. |
| **SYN-005** Reia o trimitere după timeout fără dublare | `LOCAL` | Outbox persistent și aceleași event_id/payload | SDK outbox + POST sync existent | Confirmări și chei stabile. Nu genera event_id nou la fiecare retry. |
| **SYN-006** Recepționează conflictul de revizie | `PARTIAL` | POST /api/v2/home/sync, HTTP 409 și conflicts[] | Păstrează | Snapshot curent și revizie server. SDK nu trebuie să trateze 409 ca succes fără rezolvarea conflictului. |
| **SYN-007** Păstrează varianta familiei | `LOCAL` | cloud-domain.resolve choice=remote | SDK resolveConflict, actualizare ack și cursor | ID, revizie și snapshot ales. Nu necesită endpoint separat dacă actualizarea și următorul sync sunt corecte. |
| **SYN-008** Păstrează varianta locală și retrimite conștient | `LOCAL` | cloud-domain.resolve choice=local | SDK resolveConflict + sync nou cu expected_revision curent | Decizie explicită și eveniment nou. Nu face last-write-wins ascuns pentru date personale concurente. |
| **SYN-009** Reia sincronizarea la revenire online sau periodic | `LOCAL` | online și timer cloud.js | SDK scheduler cu backoff/jitter și cote | Status și retry controlat. Fără API pentru fiecare timer sau eveniment de lifecycle. |
| **SYN-010** Coordonează filele browserului fără cozi concurente | `LOCAL` | Lease local, BroadcastChannel și home-changed | SDK mecanism de exclusivitate locală | Lock/lease și notificare locală. Nu este acces distribuit între utilizatori. |
| **SYN-011** Separă overlay-ul alertelor de snapshotul personal | `LOCAL` | cloud-domain overlay și refreshAlerts | SDK reguli de reconciliere | alerts/checked_at/notices actualizabile independent. Nu sincroniza copia veche ca alertă autoritativă. |
| **SYN-012** Șterge sau arhivează un ambalaj individual | `PARTIAL` | Protocol sync kind=delete există; fără buton dedicat | SDK deletePackage și comandă semantică opțională | Tombstone, revizie și eveniment. Consumarea și ștergerea sunt acțiuni diferite; retenția istoricului trebuie explicită. |

<a id="rcp"></a>
### RCP — Acasă: bon digital și adăugare grupată

**Suprafețe:** `/acasa/#bon=…`; `dialog receipt-dialog`.

**Acces:** posesor al linkului confidențial; inventar local sau gospodărie autorizată. **Prioritate orientativă:** P0.

**Surse:** [app/Controllers/ReceiptController.php](../../app/Controllers/ReceiptController.php), [app/Domain/DigitalReceipt.php](../../app/Domain/DigitalReceipt.php), [public/assets/home/receipt.js](../../public/assets/home/receipt.js), [public/assets/home/app.js](../../public/assets/home/app.js), [app/Views/consumer-home.php](../../app/Views/consumer-home.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **RCP-001** Deschide/scanează legătura bonului | `LOCAL` | Fragment #bon=token și eliminare din istoric | SDK deep link parser | Token hex 64 în fragment. Nu pune tokenul în query/loguri sau într-un catalog public de bonuri. |
| **RCP-002** Previzualizează bonul fără autentificare | `EXISTENT` | POST /api/v2/home/receipts/preview | Păstrează; mecanism explicit permis aplicațiilor externe | token; merchant, id, lines și simulation. Capability token, termen 30 zile; preview nu adaugă produse. |
| **RCP-003** Citește sumarul și liniile înainte de confirmare | `LOCAL` | receipt-dialog și cleanReceipt | SDK cleanReceipt | Nume, cantitate, unitate, GTIN/lot/termen când există. Fără date de plată sau identitatea cumpărătorului. |
| **RCP-004** Confirmă Adaugă acasă pentru întregul bon | `PARTIAL` | importReceipt + local transaction; sync opțional | SDK importReceipt; POST /api/v2/home/households/{id}/receipt-imports opțional | Bon, profile și rezultat cu număr adăugat. O singură confirmare, nu un formular pentru fiecare aliment. |
| **RCP-005** Completează profilurile cunoscute ale liniilor | `EXISTENT` | GET /api/v2/public/food?code= pentru fiecare cod unic | Batch lookup public propus | Date aprobate sau necunoscute explicite. Nu presupune lot sau termen din simplul SKU/nume. |
| **RCP-006** Separă ambalajele identice și deduplică același bon | `LOCAL` | receipt_id/line_id/unit_index și istoricul add | SDK mergeReceipt cu aceleași identități | Ambalaje fizice și referințe stabile. Nu deduplica doar după GTIN și nu dubla la retry. |
| **RCP-007** Tratează termenul conflictual dintre bon și produs | `LOCAL` | mergeReceipt compară kind/value/precision | SDK reconciliere cu confirmare | Dată necunoscută până la verificare când există conflict. Nu alege arbitrar data cea mai convenabilă. |
| **RCP-008** Închide bonul fără import | `LOCAL` | data-close receipt-dialog | Fără endpoint | Doar UI. Preview nu consumă bonul și nu modifică inventarul. |

<a id="adm"></a>
### ADM — Administrare platformă: identitate și drepturi

**Suprafețe:** `/admin`.

**Acces:** admin platformă; separat de tokenurile producătorului. **Prioritate orientativă:** P1.

**Surse:** [app/Controllers/AdminController.php](../../app/Controllers/AdminController.php), [app/Views/admin.php](../../app/Views/admin.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **ADM-001** Listează organizațiile și starea identității | `OPS` | GET /admin, max 100 organizații | GET /api/v2/admin/organizations?cursor=&status= | Organizații și verificări autorizate. Endpoint administrativ propus, nepublic; lipsește astăzi JSON. |
| **ADM-002** Înregistrează verificarea identității și reprezentării | `OPS` | POST /admin, action=organization.verify | POST /api/v2/admin/organizations/{id}/identity-reviews | evidence, evaluator, decizie și dată. Nu este acțiune permisă producătorului asupra propriei organizații. |
| **ADM-003** Listează revendicările GTIN și dovezile | `OPS` | GET /admin, claims max 100 | GET /api/v2/admin/gtin-claims?cursor=&state= | GTIN, organizație, articol și dovezi. Nu expune documentele de identitate în catalog public. |
| **ADM-004** Verifică dreptul de administrare GTIN | `OPS` | POST /admin, action=gtin.review, state=verified | POST /api/v2/admin/gtin-claims/{id}/decisions | evidence, state=verified și motiv. Necesită organizație verificată și controlul conflictului registrului; nu atribuie GTIN nou. |
| **ADM-005** Marchează dreptul GTIN în dispută | `OPS` | POST /admin, action=gtin.review, state=disputed | Aceeași resursă de decizie | Dovezi și stare disputed. Păstrează istoria și comportamentul public de avertizare. |
| **ADM-006** Retrage dreptul de administrare GTIN | `OPS` | POST /admin, action=gtin.review, state=withdrawn | Aceeași resursă de decizie | Dovezi, state=withdrawn și impact. Nu muta implicit identificatorul către alt producător. |
| **ADM-007** Listează contactele active și starea verificării | `OPS` | GET /admin, contacts max 100 | GET /api/v2/admin/users?cursor=&verification= | Nume, email, stare verificare. Date personale numai pentru administrare autorizată. |
| **ADM-008** Înregistrează verificarea efectivă a contactului | `OPS` | POST /admin, action=contact.verify | POST /api/v2/admin/users/{id}/contact-reviews | Dovezi, responsabil și dată. Nu falsifica verificarea doar pentru activarea votului. |
| **ADM-009** Citește istoricul deciziilor administrative | `OPS` | Catalog::audit la verificări | GET /api/v2/admin/reviews; GET /api/v2/admin/audit-events | Decizii, actori, motiv și referințe. Jurnal fără parole, chei sau payload-uri sensibile inutile. |

<a id="ops"></a>
### OPS — Actualizări, instalare și mentenanță privată

**Suprafețe:** `/admin/updates`; `/setup`; `/hosting-check.php`; `bridge HTTPS privat`; `tools/worker.php`.

**Acces:** operator/admin; niciodată scope write/read al producătorului. **Prioritate orientativă:** P2.

**Surse:** [app/Controllers/UpdatesController.php](../../app/Controllers/UpdatesController.php), [app/Views/updates.php](../../app/Views/updates.php), [app/Controllers/SetupController.php](../../app/Controllers/SetupController.php), [public/hosting-check.php](../../public/hosting-check.php), [tools/deploy/bridge.template.php](../../tools/deploy/bridge.template.php), [tools/worker.php](../../tools/worker.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **OPS-001** Citește starea migrărilor, transportului, OCR și SMTP | `OPS` | GET /admin/updates | GET /api/v2/ops/readiness, acces operator | Versiuni și stări fără secrete. Nu face publice DSN, căi private sau credențiale. |
| **OPS-002** Instalează migrarea resolverului | `OPS` | POST /admin/updates, install_resolver | POST /api/v2/ops/migration-runs cu versiune permisă | Migrare fixă, jurnal și rezultat. Nu acceptă SQL arbitrar sau nume de fișier din cerere. |
| **OPS-003** Instalează migrarea conectoarelor/bonurilor | `OPS` | POST /admin/updates, install_integrations | Aceeași resursă de migration-runs | Versiune 003 autorizată. Nu necesită endpoint public pentru dezvoltatorii de aplicații. |
| **OPS-004** Instalează migrarea Acasă | `OPS` | POST /admin/updates, install_home | Aceeași resursă de migration-runs | Versiune 004 autorizată. Preservă datele producătorilor. |
| **OPS-005** Instalează resursele OCR verificate | `OPS` | POST /admin/updates, install_consumer_assets | POST /api/v2/ops/asset-installations | Manifest fix, hashuri și rezultat. Nu permite download arbitrar sau scriere de fișiere din instrucțiuni AI. |
| **OPS-006** Înregistrează probele HTTPS Digital Link | `OPS` | POST /admin/updates, verify_transport | POST /api/v2/ops/resolver-transport-reviews | Raport JSON, checks trecute și confirmed. Simpla depunere a unui JSON nu execută testele; dovezile trebuie legate de run/domeniu/versiune verificabile. |
| **OPS-007** Pregătește fișierul privat SMTP | `OPS` | POST /admin/updates, prepare_smtp | Menține instrument operator; fără API de secrete pentru dezvoltatori | Șablon privat fără parolă. Parola se gestionează prin canalul privat de operare, nu prin contract public. |
| **OPS-008** Probează TLS și autentificarea SMTP fără email | `OPS` | POST /admin/updates, probe_smtp | POST /api/v2/ops/mail-probes | Stare conectare/autentificare. Nu dovedește livrarea în inbox și nu trimite un mesaj. |
| **OPS-009** Verifică mediul înainte de instalare | `OPS` | POST /hosting-check.php cu token și CSRF | Păstrează temporar numai înainte de installed.lock | PHP, extensii, storage și DB, fără DSN/secrete. Fișierul refuză după instalare; nu transforma în endpoint diagnostic public permanent. |
| **OPS-010** Instalează schema și primul administrator | `OPS` | POST /setup cu setup_token și CSRF | Păstrează procedură unică de bootstrap | Date primul cont și organizație. Blocat după instalare; nu îl expune agenților terți. |
| **OPS-011** Inspectează starea conexiunii de mentenanță | `OPS` | Bridge POST action=status | Protocol privat openaliment.deploy.v1 existent | Capabilități, expirare și limite. Șablonul se află în tools/deploy; fișierul activ nu este sursă publică de aplicație. |
| **OPS-012** Inspectează hashuri și citește surse permise | `OPS` | Bridge POST action=inspect/read | Protocol privat existent | Listă allowlist și amprente/conținut permis. Fără config privat, shell, SQL liber sau citire arbitrară. |
| **OPS-013** Pregătește fișiere de release | `OPS` | Bridge POST action=stage | Protocol privat existent | Conținut, hashuri și baseline așteptat. Drept separat de API produs sau cont dezvoltator. |
| **OPS-014** Aplică release și citește jurnalul | `OPS` | Bridge POST action=commit/deployment | Protocol privat existent | ID deployment, rezultat și jurnal. Update autorizat și verificat, fără operațiuni arbitrare. |
| **OPS-015** Revine la release anterior | `OPS` | Bridge POST action=rollback | Protocol privat existent | Snapshot și validări baseline. Rollback cod nu echivalează automat cu rollback de bază de date. |
| **OPS-016** Dezactivează conexiunea de mentenanță | `OPS` | Bridge POST action=disable | Protocol privat existent | Blocare persistentă a conexiunii. Nu include credentialul în inventarul API sau pe GitHub. |
| **OPS-017** Execută workerul AI limitat | `OPS` | tools/worker.php, exclusiv CLI | Worker/cron sau job operator autentificat cu funcție fixă | Job lease, rezultat și buget. Nu expune execuție shell sau worker arbitrar printr-un API AI public. |

<a id="dev"></a>
### DEV — Contract comun pentru dezvoltatori, aplicații native și AI

**Suprafețe:** `/api/docs`; `/api/*openapi.json`; `viitor portal dezvoltatori`.

**Acces:** public pentru descriere; autorizare și scopes pentru execuție. **Prioritate orientativă:** P0.

**Surse:** [contracts/openapi.json](../../contracts/openapi.json), [contracts/consumer-openapi.json](../../contracts/consumer-openapi.json), [contracts/integration-openapi.json](../../contracts/integration-openapi.json), [contracts/home-openapi.json](../../contracts/home-openapi.json), [app/Core/Auth.php](../../app/Core/Auth.php), [app/Core/Http.php](../../app/Core/Http.php), [app/Core/Seo.php](../../app/Core/Seo.php).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **DEV-001** Descoperă toate API-urile dintr-un singur loc | `MISSING` | Patru fișiere OpenAPI și pagină HTML separate | GET /api/manifest.json | Versiuni, URL OpenAPI/scheme, resolver, auth, capabilități, starea deprecărilor. Adresă propusă proprie OpenAliment; nu pretinde standard universal de discovery. |
| **DEV-002** Descarcă toate schemele necesare operațiilor documentate | `PARTIAL` | Opt scheme expuse; product-label, batch și ai-review există numai ca fișiere în repository | GET /api/schemas/product-label.schema.json; GET /api/schemas/batch.schema.json; GET /api/schemas/ai-review.schema.json; index de scheme | URL-uri versionate stabile, enum, hash și compatibilitate. Nu expune data_model.json ca autoritate asupra ACL sau ca API pentru baza de date; consumer-assets.json descrie resurse, nu datele utilizatorilor. |
| **DEV-003** Identifică fără ambiguitate operația apelabilă | `MISSING` | Niciun operationId în cele patru OpenAPI | operationId stabil și unic pentru fiecare operație | Nume de operație, taguri, descriere și exemple. Nu necesită endpoint HTTP nou; necesită completarea contractelor. |
| **DEV-004** Citește tipurile și validările fiecărui request/response | `PARTIAL` | Scheme și OpenAPI existente, acoperire incompletă | OpenAPI complet pentru toate rutele, binare și headerele importante | Request/response, enum, nullable, unități, exemple și securitate. Inventarul nu este încă specificație OpenAPI executabilă. |
| **DEV-005** Descoperă acțiunile permise pentru identitatea curentă | `MISSING` | Roles/scopes verificate de server | GET /api/v2/identity/capabilities; capabilități per resursă | Acțiune, motiv refuz, prerechizite și limite. O descriere de instrument AI nu acordă autoritate. |
| **DEV-006** Selectează scopes minime și resursele delegate | `PARTIAL` | read/write/publish/alerts/export/import pe organizație | Granturi delegate cu scopuri documentate și restricții resursă | Permisiuni minime, audiență, titular și expirare. Noi scopes pentru home/admin/AI sunt propuneri; nu funcționează astăzi. |
| **DEV-007** Integrează o aplicație mobilă fără cookie PHP copiat | `MISSING` | Bearer producător; Acasă numai cookie | OAuth/PKCE + adaptare HomeIdentity la grant autorizat | Tokenuri limitate, storage sigur și revocare dispozitiv. CORS nu este autentificare și aplicația nativă nu are nevoie de CORS pentru a apela HTTP. |
| **DEV-008** Integrează un frontend web de pe alt domeniu | `PARTIAL` | CORS public pentru consumer/resolver/trei spec-uri; API privat same-origin | Politică CORS explicită pe aplicații înregistrate și OAuth | Origins permise, preflight, antete și credențiale. Nu activa wildcard cu cookie sau acces privat; /api/openapi.json nu are blocul CORS al celorlalte trei spec-uri. |
| **DEV-009** Primește erori previzibile și corelează suportul | `PARTIAL` | error_code/message/request_id în front controller | Catalog complet de erori și răspunsuri OpenAPI | 422 pe câmp, 409 cu revizii, 429 Retry-After, 503 indisponibil. Nu divulga stack trace sau SQL. |
| **DEV-010** Reia în siguranță scrierile | `EXISTENT` | Idempotency-Key producător; event_id Acasă; event_key integrare | Păstrează și documentează separat cele trei mecanisme | Aceeași cheie și același payload, conflicte clare. Nu afirma că toate POST-urile sunt automat idempotente. |
| **DEV-011** Parcurge orice colecție fără truncare | `PARTIAL` | Mai multe liste LIMIT 60/100/200 fără cursor | Cursor/limit/next_cursor pe toate colecțiile noi | Ordine stabilă și filtre documentate. Sumele din dashboard nu înlocuiesc lista paginată. |
| **DEV-012** Controlează concurența și versiunile | `PARTIAL` | expected_version resolver; expected_revision home | Revizii/ETag și precondiții documentate la toate actualizările | 409/412, snapshot curent și opțiuni de reconciliere. Nu schimba în loc etichete/rețete deja publicate. |
| **DEV-013** Obține operații grupate sau joburi de volum | `MISSING` | Fluxuri individuale și export NDJSON | POST /api/v2/manage/bulk-jobs; GET /api/v2/manage/jobs/{id}; POST /api/v2/manage/jobs/{id}/cancel | Batch limitat, rezultat pe obiect și atomicitate declarată. Publicarea nu se aplică automat întregului batch fără drept și intenție explicită. |
| **DEV-014** Verifică starea serviciului fără date private | `MISSING` | GET /api/v2/public/capabilities, orientat Acasă | GET /api/v2/public/status | Disponibilități componente și timestamp. Fără versiuni sensibile ale serverului sau conexiuni DB. |
| **DEV-015** Primește exemple complete în română și engleză | `PARTIAL` | docs API română, simulatoare și câteva exemple | Ghid developer și exemple curl/JS/Kotlin/Swift/Python versionate | Scanare, publish, alerte, household, ERP, retry, export. SDK-urile nu dublează regulile divergente; vectori comuni de contract. |
| **DEV-016** Lucrează într-un sandbox izolat | `MISSING` | Simulatoare/teste locale și demo read-only | Mediu sandbox cu aceleași contracte; POST /api/v2/developer/sandbox-resets | Date sintetice, cote și fixture IDs. Reset exclusiv tenant sandbox, niciodată producție. |
| **DEV-017** Citește changelog și politica de compatibilitate | `PARTIAL` | CHANGELOG.md și rute v1/v2 păstrate | GET /api/v2/public/api-versions; changelog versionat | Versiuni, deprecări și perioadă de suport. Nu muta toate API-urile sub v2 prin schimbare incompatibilă. |
| **DEV-018** Importă descrierea platformei într-un agent AI | `MISSING` | OpenAPI manual și unele scheme publicate | Manifest + pachet OpenAPI/scheme/exemple descărcabil explicit | Operații deterministe, auth, limite și surse. AI poate primi URL-ul/pachetul fără crawling HTML; respectă politica robots pentru crawling. |
| **DEV-019** Folosește instrumente AI bazate pe aceleași API-uri | `PLANNED` | — | Adaptor MCP opțional peste operațiile HTTP existente | Tools/resources și scheme, aceeași identitate delegată. MCP nu înlocuiește OpenAPI și nu primește acces la DB/shell/fișiere arbitrare. |
| **DEV-020** Previzualizează acțiuni cu efect înainte de confirmare | `PARTIAL` | Dry-run import și preview conector existente | Preview uniform pentru publish/import/alerte/schimbări de destinație | Impact, obiect, revizie și acțiunea exactă. Confirmarea se cere pentru efectul concret conform drepturilor; nu pentru fiecare citire sau gest local. |
| **DEV-021** Separă textul documentelor de instrucțiunile agentului | `PARTIAL` | Ai::context/validate limitează sursele pipeline-ului existent | Contract de dovezi și ACL la fiecare tool call | Text ca date, source IDs, hashuri, citări și autoritate. Protecție contra prompt injection, fără instrucțiuni executabile din rețete/recenzii. |
| **DEV-022** Observă aplicația și consumul fără date personale inutile | `MISSING` | request_id și audit parțial | GET /api/v2/developer/apps/{id}/usage; acces operator separat | Rate, erori agregate, latențe și cote. Nu oferă inventarul utilizatorilor producătorilor sau dezvoltatorului implicit. |

<a id="fut"></a>
### FUT — Anexă: funcții din plan fără pagini complete astăzi

**Suprafețe:** `viitoare pagini consumator/producător`; `nu sunt rute curente`.

**Acces:** public / cont / tenant / evaluator după resursă, cu grant separat. **Prioritate orientativă:** P2.

**Surse:** [docs/OPENALIMENT_IMPLEMENTATION_PLAN_2026-10-24.md](../OPENALIMENT_IMPLEMENTATION_PLAN_2026-10-24.md), [docs/plans/openaliment-backlog-2026-10-24.json](../plans/openaliment-backlog-2026-10-24.json).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **FUT-001** Conectează o sursă externă de produse sau resolver | `PLANNED` | — | GET /api/v2/public/data-sources; POST /api/v2/public/external-lookups | Cod și sursă permisă; profil, licență, proveniență pe câmp. WP09; acces extern controlat, limite SSRF, timeout și cache; external_sources=false astăzi. |
| **FUT-002** Inspectează conflictele de date din surse externe | `PLANNED` | — | GET /api/v2/public/product-profiles/{id}/provenance; POST /api/v2/home/source-decisions | Valori concurente, surse și alegere personală. Nu înlocui declarația aprobată a producătorului prin crowd data fără indicare. |
| **FUT-003** Interpretează coduri GS1 2D și coduri locale de magazin suplimentare | `PLANNED` | — | SDK scan.parse extins; GET /api/v2/public/scan-profiles | Simbologie, AI-uri suportate, reguli locale și vectori. WP08; matricea de formate este condiție de acceptare, nu suport implicit de orice scanner. |
| **FUT-004** Introdu aliment prin voce | `PLANNED` | — | SDK speech input local; fără API cloud obligatoriu | Text confirmat și ambalaj personal. WP08; citirea cu voce existentă nu înseamnă dictare implementată. |
| **FUT-005** Congelează, decongelează sau consumă parțial | `PLANNED` | — | POST /api/v2/home/households/{id}/package-events | Tip, cantitate, dată, expected_revision, condiții. WP05/WP10; o simplă schimbare location nu extinde automat termenul alimentar. |
| **FUT-006** Corectează momentul deschiderii | `PLANNED` | — | POST /api/v2/home/households/{id}/package-events | opened_at observat, motiv și revizie. WP10; păstrează istoricul corecției. |
| **FUT-007** Configurează memento-uri locale de expirare | `PLANNED` | — | SDK localNotifications; GET /api/v2/public/food-rule-versions | Program local, permisiune contextuală și anulare. WP11; nu necesită cont sau upload de profil sănătate. |
| **FUT-008** Activează notificări Web Push/FCM/APNs | `PLANNED` | — | POST /api/v2/home/push-subscriptions; GET /api/v2/home/push-subscriptions; DELETE /api/v2/home/push-subscriptions/{id} | Token dispozitiv, platformă, permisiune și cont autorizat. WP11; tokenurile sunt private, nu chei publice pentru producători. |
| **FUT-009** Configurează canalele și intervalele notificărilor | `PLANNED` | — | GET /api/v2/home/notification-preferences; PUT /api/v2/home/notification-preferences | Rezumat, ore, canale și acord. WP11; minim de setări, valori inițiale clare, dezabonare simplă. |
| **FUT-010** Citește și marchează o notificare | `PLANNED` | — | GET /api/v2/home/notifications; GET /api/v2/home/notifications/{id}; POST /api/v2/home/notifications/{id}/acknowledgements | Motiv, produs/lot, actualitate și acțiune. WP11; acceptarea de furnizor nu dovedește citirea. |
| **FUT-011** Suspendă memento-uri după consum sau revocare | `PLANNED` | — | Eveniment package consumat și DELETE push-subscription; worker intern | Anulare deterministă a programării. WP11; aceeași regulă pe toate dispozitivele. |
| **FUT-012** Importă rechemări din surse autorizate | `PLANNED` | — | GET /api/v2/public/recalls; GET /api/v2/public/recalls/{id}; POST /api/v2/admin/recall-imports | Sursă, GTIN, lot/interval, stare și actualizări. WP11; import operator autorizat, nu retragere inventată de AI. |
| **FUT-013** Fotografiază sau partajează bon pe hârtie | `PLANNED` | — | SDK receipt OCR; POST /api/v2/home/receipt-recognition-jobs numai dacă se alege cloud | Imagine minimizată și rezultat ciornă. WP12; bonurile digitale implementate nu sunt OCR de bon implementat. |
| **FUT-014** Potrivește și confirmă numai liniile neclare ale bonului | `PLANNED` | — | POST /api/v2/home/receipt-match-previews; POST /api/v2/home/receipt-imports | Linii, scor/proveniență, alegeri și eveniment de grup. WP12; una sau puține confirmări, fără fabricarea lotului/termenului. |
| **FUT-015** Anulează importul întregului bon | `PLANNED` | — | SDK undoReceiptImport; POST /api/v2/home/households/{id}/package-events cu group_id | Grup și expected_revision pentru fiecare ambalaj. WP12; nu șterge ambalaje modificate ulterior fără conflict explicit. |
| **FUT-016** Primește sugestii de folosire a alimentelor | `PLANNED` | — | POST /api/v2/home/meal-suggestions | Inventar accesibil, preferințe local aplicate sau acord explicit, surse. WP14; nu recomandă alimente retrase și nu afirmă siguranță dacă datele lipsesc. |
| **FUT-017** Compară produse și alternative | `PLANNED` | — | GET /api/v2/public/product-comparisons?ids=; SDK filtre personale | Aceleași baze/unități, lipsuri și surse. WP14; comparabilitate factuală, nu verdict medical universal. |
| **FUT-018** Readaugă la cumpărături un aliment terminat | `PLANNED` | — | SDK shopping.reorder; POST shopping-items dacă sync opt-in | Referință produs și nume confirmat. WP14; recumpărarea este voluntară, fără comandă comercială automată. |
| **FUT-019** Autentifică prin passkey | `PLANNED` | — | POST /api/v2/identity/passkeys/registration-options; POST /api/v2/identity/passkeys/registrations; POST /api/v2/identity/passkeys/authentication-options; POST /api/v2/identity/passkeys/authentications | Challenge, origine, RP ID și credential public. WP04; folosire facultativă, recuperare și revocare. |
| **FUT-020** Conectează Apple/Google și unește identități | `PLANNED` | — | GET /api/v2/identity/federation/{provider}/authorize; GET /api/v2/identity/federation/{provider}/callback; POST /api/v2/identity/account-merges | State, nonce, identități verificate și acord explicit. WP04; nu uni conturi doar după un email neconfirmat. |
| **FUT-021** Recuperează contul și gestionează dispozitivele pierdute | `PLANNED` | — | POST /api/v2/identity/recoveries; POST /api/v2/identity/recoveries/confirm; GET /api/v2/identity/sessions; DELETE /api/v2/identity/sessions/{id} | Dovezi și revocarea granturilor. WP04/WP23; păstrează separarea identității Acasă/producător. |
| **FUT-022** Cere ștergerea contului și urmărește cererea | `PLANNED` | — | POST /api/v2/home/account/deletion-requests; GET /api/v2/home/account/deletion-requests/{id}; POST /api/v2/home/account/deletion-requests/{id}/cancel | Confirmare titular, spații partajate, retenție și rezultat. WP23/WP25; logout nu este ștergere și copiile altor persoane nu dispar automat. |
| **FUT-023** Gestionează consimțămintele și cererile de confidențialitate | `PLANNED` | — | GET /api/v2/identity/consents; POST /api/v2/identity/consent-decisions; POST /api/v2/identity/privacy-requests | Versiune politică, scop, temei și decizie. WP23; nu cere acord generic care deschide toate datele. |
| **FUT-024** Șterge numai datele locale și cache-ul sensibil | `PLANNED` | — | SDK eraseLocalWorkspace cu alegere explicită și backup | Eliminare locală, alegere privind coada nesincronizată. WP23; nu deduce ștergere cloud din golirea browserului. |

<a id="dos"></a>
### DOS — Anexă: documentație alimentară deschisă completă

**Suprafețe:** `viitor editor dosar`; `viitoare pagini publice de documentație`.

**Acces:** tenant read/write/publish; public numai proiecții licențiate și aprobate. **Prioritate orientativă:** P2.

**Surse:** [docs/OPENALIMENT_IMPLEMENTATION_PLAN_2026-10-24.md](../OPENALIMENT_IMPLEMENTATION_PLAN_2026-10-24.md), [OpenBread_Codex_v1_2026-10-23/OPENBREAD_MASTER.md](../../OpenBread_Codex_v1_2026-10-23/OPENBREAD_MASTER.md).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **DOS-001** Citește standarde, profiluri de categorie și șabloane | `PLANNED` | — | GET /api/v2/public/category-profiles; GET /api/v2/public/category-profiles/{id}/versions/{version}; GET /api/v2/public/dossier-templates | Secțiuni, criterii, unități și versiuni. WP16; profilul panificație nu impune hidratare/preferment celorlalte alimente. |
| **DOS-002** Creează și citește dosarul și toate secțiunile | `PLANNED` | — | POST /api/v2/manage/products/{id}/dossiers; GET /api/v2/manage/products/{id}/dossiers; GET /api/v2/manage/dossiers/{id}; GET /api/v2/manage/dossiers/{id}/sections | Dosar, categorie, secțiuni versionate și stadiu. WP16; rețeta JSON existentă nu este editor de dosar complet. |
| **DOS-003** Actualizează o secțiune și păstrează reviziile | `PLANNED` | — | POST /api/v2/manage/dossiers/{id}/sections/{section_key}/revisions; GET /api/v2/manage/dossiers/{id}/sections/{section_key}/revisions | Text/structură, dovezi, expected_revision și hash. WP16; toate secțiunile specificate în șablon, nu doar rețeta. |
| **DOS-004** Editează formula, procesul și profilul de panificație | `PLANNED` | — | POST /api/v2/manage/dossiers/{id}/formula-revisions; POST /api/v2/manage/dossiers/{id}/process-revisions; POST /api/v2/manage/dossiers/{id}/profile-revisions | Ingrediente, unități, etape, parametri și profil. WP16; istoricul R1 păstrat exact. |
| **DOS-005** Scalează rețeta și calculează indicatorii tehnici | `PLANNED` | — | SDK deterministic calculations; POST /api/v2/manage/calculations/formula-scale opțional | Mase, pierderi și randamente; hidratare/preferment numai bread. WP16; rezultatul calculat nu validează proba de producție. |
| **DOS-006** Administrează furnizori și materii prime | `PLANNED` | — | GET/POST /api/v2/manage/suppliers; GET /api/v2/manage/suppliers/{id}; POST /api/v2/manage/suppliers/{id}/revisions; GET/POST /api/v2/manage/materials; POST /api/v2/manage/materials/{id}/revisions | Date, oferte și proveniență. WP16; contacte și condiții comerciale rămân private dacă nu sunt aprobate. |
| **DOS-007** Creează scenarii de cost și compară versiunile | `PLANNED` | — | GET/POST /api/v2/manage/products/{id}/cost-scenarios; GET /api/v2/manage/cost-scenarios/{id}; GET /api/v2/manage/cost-scenarios/{id}/comparison?against= | Prețuri cu dată, unități, taxe, ipoteze și total determinist. WP16; COST1–COST3 sunt scenarii istorice/estimări, nu prețuri curente validate. |
| **DOS-008** Documentează aprovizionare, ambalare și decizii | `PLANNED` | — | Secțiuni versionate în /api/v2/manage/dossiers/{id}/sections/{section_key}/revisions | Dovezi, opțiuni, motive și stadiu. WP16; nu cere endpoint distinct pentru fiecare paragraf. |
| **DOS-009** Planifică încercare și atașează lot experimental | `PLANNED` | — | POST /api/v2/manage/products/{id}/experiments; GET /api/v2/manage/products/{id}/experiments; GET /api/v2/manage/experiments/{id}; POST /api/v2/manage/experiments/{id}/revisions | Ipoteză, variabilă, criterii, lot și metodă. WP16; experimentul nu eliberează automat lotul. |
| **DOS-010** Înregistrează rezultate și probe de conservare | `PLANNED` | — | POST /api/v2/manage/experiments/{id}/results; POST /api/v2/manage/products/{id}/shelf-life-studies; POST /api/v2/manage/shelf-life-studies/{id}/results | Măsurători, protocol, condiții, probe, semnatar și concluzie. WP16; nu transforma observația sau estimarea în termen alimentar validat. |
| **DOS-011** Compară versiuni și trasează sursele istorice | `PLANNED` | — | GET /api/v2/manage/dossiers/{id}/diff?against=; GET /api/v2/manage/dossiers/{id}/provenance | Revizii, locatori și hashuri. WP16; fișiere deteriorate/recuperate rămân marcate, fără rescriere original. |
| **DOS-012** Publică proiecția licențiată a dosarului | `PLANNED` | — | POST /api/v2/manage/dossiers/{id}/publication-decisions; GET /api/v2/public/dossiers/{id} | Snapshot, licențe pe materiale, drepturi și aprobare. WP16; separare A/B/C și nicio licență implicită prin creare pagină QR. |
| **DOS-013** Exportă dosarul cu dovezi și manifest | `PLANNED` | — | POST /api/v2/manage/dossiers/{id}/exports; GET /api/v2/manage/dossier-exports/{id}; GET /api/v2/manage/dossier-exports/{id}/content | JSON/fișiere, proveniență și hashuri. WP16/WP18; drepturile de reutilizare rămân pe fiecare material. |

<a id="eva"></a>
### EVA — Anexă: evaluarea documentației și registrul siglei

**Suprafețe:** `viitor spațiu evaluator`; `viitor registru public`.

**Acces:** producător, evaluator eligibil și emitent în roluri separate. **Prioritate orientativă:** P2.

**Surse:** [docs/OPENALIMENT_IMPLEMENTATION_PLAN_2026-10-24.md](../OPENALIMENT_IMPLEMENTATION_PLAN_2026-10-24.md), [OpenBread_Codex_v1_2026-10-23/05_standard](../../OpenBread_Codex_v1_2026-10-23/05_standard).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **EVA-001** Citește standardul, profilul și criteriile versionate | `PLANNED` | — | GET /api/v2/public/standards; GET /api/v2/public/standards/{id}/versions/{version} | Criterii OB-01–OB-24 și profil. WP17; nu este certificare alimentară generală. |
| **EVA-002** Solicită separat evaluarea unui dosar înghețat | `PLANNED` | — | POST /api/v2/manage/dossiers/{id}/submissions; GET /api/v2/manage/submissions; GET /api/v2/manage/submissions/{id} | Snapshot exact, standard/profil și probe. WP17; pagina/GTIN/rețeta publicată nu acordă sigla. |
| **EVA-003** Retrage cererea înainte de decizie | `PLANNED` | — | POST /api/v2/manage/submissions/{id}/withdrawals | Motiv și istoric. WP17; nu șterge probele deja depuse. |
| **EVA-004** Declară eligibilitatea evaluatorului și conflictul de interese | `PLANNED` | — | GET /api/v2/review/eligibility; POST /api/v2/review/conflict-declarations | Identitate, competențe, conflict și recuzare. WP17; evaluatorul nu se autoatribuie pe un dosar neautorizat. |
| **EVA-005** Atribuie evaluatori și citește sarcinile | `PLANNED` | — | POST /api/v2/admin/submissions/{id}/assignments; GET /api/v2/review/assignments; GET /api/v2/review/submissions/{id} | Mandat și acces la probele necesare. WP17; acces temporar, limitat la dosarul alocat. |
| **EVA-006** Evaluează fiecare criteriu și cere completări | `PLANNED` | — | PUT /api/v2/review/submissions/{id}/criteria/{criterion_id}; POST /api/v2/review/submissions/{id}/clarification-requests | Rezultat, motiv, evidence_refs și revizie. WP17; toate OB-01–OB-23 înainte de decizia OB-24. |
| **EVA-007** Răspunde cu remedieri fără înlocuirea snapshotului inițial | `PLANNED` | — | POST /api/v2/manage/submissions/{id}/clarification-responses | Probe noi, răspuns și versiune. WP17; schimbările sunt urmărite și reevaluate. |
| **EVA-008** Înregistrează decizia și emite credentialul | `PLANNED` | — | POST /api/v2/review/submissions/{id}/decisions; POST /api/v2/admin/submissions/{id}/issuances | Criterii îndeplinite, semnatar, perioadă și domeniu. WP17; tranzacțional și autorizat, fără acordare automată de AI. |
| **EVA-009** Citește registrul, credentialul și activele de siglă | `PLANNED` | — | GET /api/v2/public/credentials; GET /api/v2/public/credentials/{id}; GET /api/v2/public/credentials/{id}/badge.svg; GET /api/v2/public/credentials/{id}/certificate.pdf | Stare curentă, standard, profil, dosar și valabilitate. WP17; SVG/PDF nu trebuie să ascundă revocarea sau domeniul verificării. |
| **EVA-010** Suspendă, revocă, expiră sau reînnoiește sigla | `PLANNED` | — | POST /api/v2/admin/credentials/{id}/status-decisions; POST /api/v2/manage/credentials/{id}/renewal-submissions | Motiv, dată, stare și istoric public relevant. WP17; expirarea se execută intern, nu prin client arbitrar. |
| **EVA-011** Depune și soluționează contestația | `PLANNED` | — | POST /api/v2/manage/submissions/{id}/appeals; GET /api/v2/manage/appeals/{id}; POST /api/v2/review/appeals/{id}/decisions | Probe, motiv și evaluator independent. WP17; registru și decizii versionate. |

<a id="aix"></a>
### AIX — Anexă: utilizarea AI din plan, cu surse autorizate

**Suprafețe:** `viitoare pagini asistent public`; `viitor spațiu producător AI`.

**Acces:** public numai surse publice; tenant numai surse autorizate; grant AI separat. **Prioritate orientativă:** P2.

**Surse:** [OpenBread_Codex_v1_2026-10-23/OPENBREAD_MASTER.md](../../OpenBread_Codex_v1_2026-10-23/OPENBREAD_MASTER.md), [app/Domain/Ai.php](../../app/Domain/Ai.php), [docs/OPENALIMENT_IMPLEMENTATION_PLAN_2026-10-24.md](../OPENALIMENT_IMPLEMENTATION_PLAN_2026-10-24.md).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **AIX-001** Mapează documentele în dosar | `PLANNED` | — | POST /api/v2/manage/ai-jobs, kind=document_map | Extrase autorizate, catalog și cerințe; clasificare propusă. Job din master O5; fără publicare. |
| **AIX-002** Identifică lipsurile dosarului | `PLANNED` | — | POST /api/v2/manage/ai-jobs, kind=dossier_gap_check | Snapshot exact, checklist și citări. Nu bifează în numele evaluatorului. |
| **AIX-003** Analizează feedbackul minimizat | `PARTIAL` | Ai::work și ai-review.schema.json, coadă internă | POST /api/v2/manage/ai-jobs, kind=feedback_triage | Feedback public moderat și eticheta publică aferentă. Pipeline limitat existent, nu un API generic pentru orice text privat. |
| **AIX-004** Agregă feedbackul pe versiune și perioadă | `PLANNED` | — | POST /api/v2/manage/ai-jobs, kind=feedback_digest | Date permise și agregate calculate de aplicație. Numerele nu sunt inventate de model. |
| **AIX-005** Propune experiment controlat | `PLANNED` | — | POST /api/v2/manage/ai-jobs, kind=experiment_proposal | Problemă agregată, versiune și rezultate disponibile. Ipoteză și protocol ciornă, fără modificarea rețetei aprobate. |
| **AIX-006** Răspunde public cu citări verificabile | `PLANNED` | — | POST /api/v2/public/questions; GET /api/v2/public/answers/{id} | kind=public_qa, întrebare, versiune și surse exclusiv publice. Nu recuperează rețete private pentru a le rezuma. |
| **AIX-007** Explică inventarul personal cu alegerea utilizatorului | `PLANNED` | — | POST /api/v2/home/assistant-sessions; POST /api/v2/home/assistant-sessions/{id}/messages | Scop limitat, surse accesibile, stare și citări. Inventarul și preferințele nu ajung implicit la producători sau la AI. |
| **AIX-008** Caută în documente și urmărește citările | `PLANNED` | — | POST /api/v2/manage/search; GET /api/v2/manage/source-revisions/{id}?locator= | Tenant, ACL, versiune, vizibilitate, stadiu, hash și locator. Filtrare înaintea retrievalului/modelului; descărcarea verifică din nou ACL. |
| **AIX-009** Revizuiește, respinge sau transformă rezultatul AI în propunere | `PLANNED` | — | POST /api/v2/manage/ai-results/{id}/review-decisions | Copie revizuită, motiv, reviewer și eventual proposal_id. Fără autoaprobare, autoemitere siglă sau executare de instrucțiuni din documente. |

<a id="evt"></a>
### EVT — Anexă: webhooks, domenii, continuitate și operare

**Suprafețe:** `viitoare setări integrări și domenii`; `viitor panou operațional`.

**Acces:** tenant owner pentru configurare; operator pentru infrastructură. **Prioritate orientativă:** P2.

**Surse:** [docs/OPENALIMENT_IMPLEMENTATION_PLAN_2026-10-24.md](../OPENALIMENT_IMPLEMENTATION_PLAN_2026-10-24.md), [docs/INTEGRARI_PARTENERI.md](../INTEGRARI_PARTENERI.md), [docs/HTTPS_MAINTENANCE.md](../HTTPS_MAINTENANCE.md).

| ID / acțiune | Stare | Astăzi | API/contract necesar | Date și reguli |
| --- | --- | --- | --- | --- |
| **EVT-001** Listează tipurile de evenimente pentru integrări | `PLANNED` | — | GET /api/v2/public/event-types | product/label/batch/alert/resolver/import/credential și versiuni schemă. Nu trimite evenimente despre gospodării producătorilor. |
| **EVT-002** Configurează webhook al organizației | `PLANNED` | — | GET/POST /api/v2/manage/webhooks; GET/PATCH/DELETE /api/v2/manage/webhooks/{id} | URL permis, evenimente, secret emis o dată, stare și probe. Semnături, replay protection, SSRF și retry cu limite. |
| **EVT-003** Testează webhook și urmărește livrările | `PLANNED` | — | POST /api/v2/manage/webhooks/{id}/tests; GET /api/v2/manage/webhooks/{id}/deliveries; GET /api/v2/manage/webhook-deliveries/{id}; POST /api/v2/manage/webhook-deliveries/{id}/retry | Event_id, attempt, stare, următor retry. Un răspuns 2xx nu dovedește aplicarea comercială în ERP. |
| **EVT-004** Rotește cheia de semnare webhook | `PLANNED` | — | POST /api/v2/manage/webhooks/{id}/secret-rotations | key_id și suprapunere controlată. Nu publica secretele în GET sau export. |
| **EVT-005** Adaugă domeniu controlat de producător | `PLANNED` | — | POST /api/v2/manage/domains; GET /api/v2/manage/domains; GET /api/v2/manage/domains/{id} | Host validat, challenge DNS/HTTP și tenant. Nu activează un Host arbitrar dintr-o cerere. |
| **EVT-006** Verifică proprietatea și activează domeniul | `PLANNED` | — | POST /api/v2/manage/domains/{id}/verification-runs; POST /api/v2/manage/domains/{id}/activation-requests | Dovadă control, TLS și stare infrastructură. Actualizarea DNS/TLS poate necesita proces operator/cPanel, nu este garantată de endpoint. |
| **EVT-007** Dezactivează domeniul și urmărește continuitatea | `PLANNED` | — | POST /api/v2/manage/domains/{id}/deactivation-requests; GET /api/v2/manage/domains/{id}/health | Impact URL, acord și stare. Adresele de pe ambalaje nu se pierd prin ștergerea unei setări. |
| **EVT-008** Administrează mapările URL pentru migrare | `PLANNED` | — | GET /api/v2/manage/url-mappings; POST /api/v2/manage/url-mapping-previews; POST /api/v2/manage/url-mapping-decisions | Sursă, destinație, identitate, drepturi și istoric. Nu crea open redirects și nu schimba retroactiv produsul identificat. |
| **EVT-009** Pregătește transferul la alt furnizor și urmărește verificarea | `PLANNED` | — | POST /api/v2/manage/migration-plans; GET /api/v2/manage/migration-plans/{id}; POST /api/v2/manage/migration-plans/{id}/checks | Export, adaptor, mapări și probe înainte/după. GS1 Digital Link nu transferă singur proprietatea domeniului sau mandatul GTIN. |
| **EVT-010** Citește starea joburilor și a notificărilor interne | `PLANNED` | — | GET /api/v2/ops/jobs; GET /api/v2/ops/jobs/{id}; POST /api/v2/ops/jobs/{id}/retry | Lease, încercări, erori controlate și cote. Fără SQL/shell liber; nu expune payload privat în monitorizare generală. |
| **EVT-011** Exersează backup/restaurare și monitorizează incidente | `PLANNED` | — | POST /api/v2/ops/backups; GET /api/v2/ops/backups/{id}; POST /api/v2/ops/restore-drills; GET /api/v2/ops/health | Artefacte criptate, manifest și raport în mediu izolat. Operații strict operator; nu sunt instrumente publice pentru agenți externi. |
| **EVT-012** Publică asocierea aplicațiilor cu domeniul și configurația mobilă | `PLANNED` | — | GET /.well-known/assetlinks.json; GET /.well-known/apple-app-site-association; GET /api/v2/public/mobile-config | ID-uri de aplicație/certificate publice, versiune minimă și capabilități. WP07/WP25; fără chei private de semnare în endpoint sau repository. |

## 5. Acces pentru AI fără dependență de crawling

AI-ul trebuie să primească explicit indexul de integrare și contractele permise, prin URL sau pachet descărcat, apoi să apeleze API-ul cu identitatea și autoritatea utilizatorului. Această cale nu presupune indexarea paginilor HTML și nu justifică ocolirea unei interdicții de crawling. `robots.txt` este un protocol pentru crawleri, nu un mecanism de control al accesului; datele private rămân protejate de autentificare și ACL. [RFC 9309](https://www.rfc-editor.org/rfc/rfc9309).

Pachetul propus este `GET /api/manifest.json`, indexul tuturor OpenAPI-urilor, schemele, dicționarele, exemplele, autentificarea, erorile, paginarea, idempotenta și politica de versiuni. Fiecare operație are `operationId` stabil, parametri tipizați, prerechizite și exemple de rezultat. OpenAPI permite oamenilor și programelor să înțeleagă serviciul fără inspecția surselor sau a traficului. Proiectul folosește acum OpenAPI 3.1.0; nu este necesară schimbarea versiunii pentru a completa descrierile. [OpenAPI Specification](https://spec.openapis.org/oas/v3.2.1.html).

Pentru aplicații native și agenți delegați propunem Authorization Code cu PKCE, tokenuri de durată scurtă, audiență/scopes clare, refresh sigur și revocare. Aplicația nativă folosește browserul sistemului pentru autorizare și nu conține un secret de client în APK. Autentificarea simplă prin magic link poate rămâne experiența persoanei în acel flux. [RFC 8252](https://www.rfc-editor.org/rfc/rfc8252), [OAuth Security BCP — RFC 9700](https://www.rfc-editor.org/rfc/rfc9700).

Un adaptor MCP este o posibilă interfață suplimentară peste aceste API-uri, nu o condiție pentru prima versiune și nici un privilegiu suplimentar. Agenții primesc numai proiecții autorizate; sursele sunt date neîncrezătoare, nu instrucțiuni de execuție. Publicarea, retragerea, modificările de acces și emiterea unei sigle păstrează aceleași decizii, roluri și istoric ca interfața umană. Indisponibilitatea AI nu blochează resolverul, QR-ul, datele publice sau inventarul local.

## 6. Ordinea recomandată pentru închiderea acoperirii

1. Completează contractele pentru operațiile existente, adaugă `operationId`, indexul de discovery, toate erorile și exemplele. Validează documentația față de routere fără schimbarea rutelor.
2. Închide lipsurile din paginile actuale: liste paginate, dashboard, document upload/download/metadata, feedback/propuneri/voturi, tokenuri și rapoarte de import/conector. Contractele noi folosesc proiecții și reguli de domeniu comune cu UI.
3. Adaugă identitatea delegată pentru mobile/terți și adaptați API-ul Acasă cu aceeași separare a gospodăriilor și cu revocare. Publică SDK/contracte pentru operațiile locale și sincronizarea offline.
4. Închide funcțiile din planul extins pe contractele inventariate: notificări, surse externe, bon OCR, dosare, evaluare, webhooks și domenii. Acestea sunt listate complet aici, fără a fi marcate existente.
5. Verifică paritatea UI/API/SDK cu scenarii pozitive și negative: două organizații, două familii, scope insuficient, revocare, retry, conflict, lot necunoscut, caractere codificate, etichetă istorică, alertă curentă, lipsa AI și round-trip export/import. Pentru fiecare nou endpoint: probă de contract și probă de izolare, nu doar răspuns 200.

Inventarul se închide ca livrabil când toate paginile/controllerele sunt mapate, toate operațiile API existente sunt clasificate față de OpenAPI, acțiunile locale sunt explicite și funcțiile propuse sunt separate de cele disponibile. Implementarea se consideră închisă numai după realizarea și testarea contractelor alese, nu prin existența acestui document.

## 7. Anexă — operațiile HTTP API existente și acoperirea OpenAPI

| Contract | Versiune OpenAPI | Operații enumerate | Fără operationId |
| --- | --- | ---: | ---: |
| `contracts/consumer-openapi.json` | 3.1.0 | 2 | 2 |
| `contracts/home-openapi.json` | 3.1.0 | 18 | 18 |
| `contracts/integration-openapi.json` | 3.1.0 | 7 | 7 |
| `contracts/openapi.json` | 3.1.0 | 21 | 21 |

Rutele de mai jos sunt extrase și confruntate cu controllerele. Prefixul serverului `/api/v1` din contractul ERP este aplicat în tabel; numele diferite ale placeholderelor sunt normalizate numai pentru comparație. HEAD/OPTIONS sunt suportate unde controllerul le declară: public consumer și resolver au CORS; HomeController acceptă HEAD, fără private CORS; ApiController v1 și IntegrationController nu oferă universal HEAD/OPTIONS.

| Metodă | Rută implementată | Autorizare | Enumerată în OpenAPI |
| --- | --- | --- | --- |
| GET | `/api/v1/products` | public | `openapi.json` |
| GET | `/api/v1/products/{id}` | public | `openapi.json` |
| GET | `/api/v1/trade-items/{id}` | public | `openapi.json` |
| GET | `/api/v1/batches/{id}` | public | `openapi.json` |
| GET | `/api/v1/recipe-versions/{id}` | public | **Lipsește** |
| GET | `/api/v1/feedback/{id}/status` | X-Status-Token | **Lipsește** |
| POST | `/api/v1/feedback` | public + consent + submission_key | **Lipsește** |
| GET | `/api/v1/manage/export` | export + owner/quality | `openapi.json` |
| GET | `/api/v1/manage/products` | read | `openapi.json` |
| GET | `/api/v1/manage/trade-items/{id}/resolver-links` | read | `openapi.json` |
| GET | `/api/v1/manage/qr/item/{id}` | read | `openapi.json` |
| GET | `/api/v1/manage/qr/batch/{id}` | read | `openapi.json` |
| GET | `/api/v1/manage/products/{id}` | read | **Lipsește** |
| GET | `/api/v1/manage/trade-items/{id}` | read | **Lipsește** |
| GET | `/api/v1/manage/labels/{id}` | read | **Lipsește** |
| GET | `/api/v1/manage/batches/{id}` | read | **Lipsește** |
| GET | `/api/v1/manage/recipes/{id}` | read | **Lipsește** |
| GET | `/api/v1/manage/documents/{id}` | read | **Lipsește** |
| POST | `/api/v1/manage/products` | write | `openapi.json` |
| POST | `/api/v1/manage/products/{id}/trade-items` | write | `openapi.json` |
| POST | `/api/v1/manage/trade-items/{id}/labels` | write | `openapi.json` |
| POST | `/api/v1/manage/labels/{id}/publish` | publish | `openapi.json` |
| POST | `/api/v1/manage/trade-items/{id}/batches` | write | `openapi.json` |
| POST | `/api/v1/manage/batches/{id}/publish` | publish | `openapi.json` |
| POST | `/api/v1/manage/batches/{id}/release` | publish | `openapi.json` |
| POST | `/api/v1/manage/batches/{id}/alerts` | alerts | `openapi.json` |
| POST | `/api/v1/manage/alerts/{id}/resolve` | alerts | `openapi.json` |
| POST | `/api/v1/manage/products/{id}/recipes` | write | **Lipsește** |
| POST | `/api/v1/manage/products/{id}/proposals` | write | **Lipsește** |
| POST | `/api/v1/manage/proposals/{id}/decide` | publish | **Lipsește** |
| POST | `/api/v1/manage/documents/{id}/publish` | publish | **Lipsește** |
| POST | `/api/v1/manage/feedback/{id}/moderate` | publish | **Lipsește** |
| POST | `/api/v1/manage/trade-items/{id}/resolver-links` | write | `openapi.json` |
| POST | `/api/v1/manage/resolver-links/{id}/publish` | publish | `openapi.json` |
| POST | `/api/v1/manage/resolver-links/{id}/withdraw` | publish | `openapi.json` |
| POST | `/api/v1/manage/import` | import | **Lipsește** |
| POST | `/api/v1/manage/import-pbt` | import | **Lipsește** |
| GET | `/api/v2/manage/connectors` | read | `integration-openapi.json` |
| POST | `/api/v2/manage/connectors` | write | `integration-openapi.json` |
| GET | `/api/v2/manage/connectors/{id}/mappings` | read | `integration-openapi.json` |
| POST | `/api/v2/manage/connectors/{id}/mappings` | write | `integration-openapi.json` |
| POST | `/api/v2/manage/connectors/{id}/preview` | write | `integration-openapi.json` |
| POST | `/api/v2/manage/connectors/{id}/ingest` | write | `integration-openapi.json` |
| GET | `/api/v2/public/food` | public; CORS GET/HEAD/OPTIONS | `consumer-openapi.json` |
| GET | `/api/v2/public/capabilities` | public; CORS GET/HEAD/OPTIONS | `consumer-openapi.json` |
| GET | `/api/v2/home/session` | cookie oa_home; session facultativ pentru /session | `home-openapi.json` |
| GET | `/api/v2/home/changes` | cookie oa_home; session facultativ pentru /session | `home-openapi.json` |
| GET | `/api/v2/home/members` | cookie oa_home; session facultativ pentru /session | `home-openapi.json` |
| GET | `/api/v2/home/households` | cookie oa_home; session facultativ pentru /session | `home-openapi.json` |
| GET | `/api/v2/home/photos/{id}` | cookie oa_home; session facultativ pentru /session | `home-openapi.json` |
| POST | `/api/v2/home/login/request` | sameOrigin; cookie + X-Home-CSRF exceptând login/* și invites/preview | `home-openapi.json` |
| POST | `/api/v2/home/login/preview` | sameOrigin; cookie + X-Home-CSRF exceptând login/* și invites/preview | `home-openapi.json` |
| POST | `/api/v2/home/login/confirm` | sameOrigin; cookie + X-Home-CSRF exceptând login/* și invites/preview | `home-openapi.json` |
| POST | `/api/v2/home/invites/preview` | sameOrigin; cookie + X-Home-CSRF exceptând login/* și invites/preview | `home-openapi.json` |
| POST | `/api/v2/home/photos/{id}` | sameOrigin; cookie + X-Home-CSRF exceptând login/* și invites/preview | `home-openapi.json` |
| POST | `/api/v2/home/sync` | sameOrigin; cookie + X-Home-CSRF exceptând login/* și invites/preview | `home-openapi.json` |
| POST | `/api/v2/home/invites` | sameOrigin; cookie + X-Home-CSRF exceptând login/* și invites/preview | `home-openapi.json` |
| POST | `/api/v2/home/join` | sameOrigin; cookie + X-Home-CSRF exceptând login/* și invites/preview | `home-openapi.json` |
| POST | `/api/v2/home/logout` | sameOrigin; cookie + X-Home-CSRF exceptând login/* și invites/preview | `home-openapi.json` |
| POST | `/api/v2/home/switch` | sameOrigin; cookie + X-Home-CSRF exceptând login/* și invites/preview | `home-openapi.json` |
| POST | `/api/v2/home/rename` | sameOrigin; cookie + X-Home-CSRF exceptând login/* și invites/preview | `home-openapi.json` |
| POST | `/api/v2/home/devices/{id}/revoke` | sameOrigin; cookie + X-Home-CSRF exceptând login/* și invites/preview | `home-openapi.json` |
| POST | `/api/v2/home/members/{id}/remove` | sameOrigin; cookie + X-Home-CSRF exceptând login/* și invites/preview | `home-openapi.json` |
| POST | `/api/v2/home/receipts/preview` | sameOrigin + capability token; fără cont | `integration-openapi.json` |

Resolverul, manifestele/schemele publice, demo JSON și descărcarea binară a documentelor sunt inventariate separat în PUB/DEM/RES/DOC. Existența unei resurse HTTP în afara prefixului `/api` nu o face inutilizabilă programatic, dar contractul ei trebuie descris explicit.

## 8. Dovezi și limite ale auditului

Catalogul JSON conține amprente pentru 97 fișiere sursă/contract și 419 localizări statice de formulare, câmpuri, details, butoane, linkuri, comenzi generate, listeners sau fetch-uri. Acestea sunt apariții în sursă, nu numărul de butoane randate pentru un anumit cont. Formularele PHP și listele dinamice multiplică aceleași operații în funcție de date.

Au fost verificate și operațiile fără buton dedicat: `proposal.decide`, status feedback, citiri private, export NDJSON și mutația sync `delete`. Butoanele/câmpurile repetate care au aceeași semnificație folosesc același rând sau contract. Extensiile din plan sunt etichetate separat și nu sunt dovezi de implementare.

Nu s-au citit sau inclus parole, chei, tokenuri active, configurații private ori datele personale ale utilizatorilor. Nu s-au trimis emailuri, rulat migrări, modificat aplicația sau apelat mutații de producție pentru acest inventar. Dovezile anterioare de funcționare rămân în `docs/verification`; nu sunt prezentate ca o retestare efectuată acum.
