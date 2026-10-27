# Integrarea Open Food Facts în OpenAliment

Plan de implementare pentru echipa de dezvoltare, 27 octombrie 2026. Bază inspectată: OpenAliment 0.7.3, migrări existente 001–008. Stare: propunere tehnică pentru implementare; funcțiile și migrările de mai jos nu sunt activate prin acest document.

Open Food Facts extinde recunoașterea alimentelor în Acasă și poate primi, separat, informații distribuite voluntar de producători. Integrarea păstrează autoritatea datelor, proveniența, licențele și istoricul. Produsul extern, articolul administrat de un producător și ambalajul dintr-o gospodărie rămân obiecte diferite.

## Obiectivul și limitele schimbării

Prima livrare completă cuprinde citirea OFF, catalogul local, imagini, recunoaștere în Acasă, proveniență, precompletarea ciornelor, compararea diferențelor, API, export, monitorizare și recuperare. Nu depinde de acordarea unui acces de scriere de către OFF.

Planul include și distribuirea în sens invers: conexiuni autorizate, acorduri de distribuire, transmitere, confirmare, conflicte și oprire. Activarea scrierii reale este o etapă distinctă, condiționată de mandatul producătorului, licențe și stabilirea transportului cu OFF. Nu este prezentată ca disponibilă în prima livrare.

Serviciul A produs–lot–QR continuă fără rețetă și fără distribuire OFF. Serviciul B păstrează licențierea explicită a documentației tehnice. Serviciul C nu acordă sigla pe baza prezenței în OFF, a scorurilor sau a completitudinii unei fișe.

Proiectul rămâne PHP MVC, PDO, MySQL/MariaDB, JavaScript simplu și dependențe minime. Runtime-ul nu cere Python, Node, MongoDB, Redis, Composer sau procese permanente pe hosting. Testele și pregătirea importurilor mari se pot executa în dezvoltare sau CI.

## Situația existentă și punctele de integrare

| Componentă inspectată | Comportament existent | Consecință pentru implementare |
| --- | --- | --- |
| `app/Domain/ConsumerFood.php` | Interpretează codul și proiectează informațiile publice; lipsa produsului produce un profil necunoscut | Se reutilizează interpretarea; rezoluția externă se compune separat |
| `app/Domain/FoodData.php` și `contracts/food-data.schema.json` | `declared` descrie declarații ale producătorului | Datele OFF nu se introduc automat cu acest statut |
| `app/Domain/HomeInventory.php` | Acceptă profil v1 și `source.kind=external`, dar validează strict câmpurile | Proveniența simplă există deja; nu justifică introducerea arbitrară a unor câmpuri noi |
| `public/assets/home/domain.js`, `cloud-domain.js` | Curăță datele, calculează amprente și sincronizează profilurile existente | Nu se schimbă implicit forma profilului ori amprentele inventarului |
| `home_items`, `home_changes`, `home_history` | Snapshoturi și istoric personal, cu revizii și conflicte | Datele externe nu rescriu inventarul sau evenimentele deja salvate |
| `Integrations.php` și `003_integrations.sql` | Mapări versionate, import în ciornă, evenimente idempotente | Se reutilizează regulile de ciornă, nu se tratează OFF ca ERP al unei organizații |
| `Catalog.php`, `gtin_registry`, `product_info_versions`, `batches` | Mandat separat, etichete și loturi istorice | Importul extern nu rezervă GTIN și nu aprobă etichete |
| `Resolver*`, `DigitalLink`, `EpcDecoder`, `Printing` | Nucleu GS1 și tipărire serializată | Fără apel OFF pe traseul resolverului sau al imprimării |
| `Operations::worker()` | Worker comun pentru mentenanță, email, scanare și AI | I/O extern nu se introduce sub blocarea acestui worker |
| `ConsumerController`, `DiscoveryController` | Sursele externe sunt declarate inactive | Disponibilitatea se schimbă numai după migrare și verificarea modulului |
| `ApiRoutes` și `tools/build/` | Registru și contracte generate | Noile operații și documentația se generează din sursele existente |

Referințe de proiect: [master](../OPENALIMENT_MASTER.md), [harta codului](../architecture/README.md), [GS1](../GS1_IMPLEMENTATION.md), [sincronizare Acasă](../ACASA_SINCRONIZARE.md), [licențe](../../LICENSING.md).

## Reguli de autoritate și selecție

1. Identitatea GS1, mandatul organizației, lotul, seria și publicarea sunt determinate de mecanismele OpenAliment existente. OFF nu constituie dovadă a mandatului.
2. Profilul aprobat al unui lot este versiunea aplicabilă acelui lot. Datele curente ale aceluiași GTIN din OFF nu descriu automat formula istorică.
3. Datele externe au proveniență pe câmp: sursă, revizie, limbă, bază, tip de afirmație, moment al preluării și transformări. `fetched_at` nu este o verificare a exactității.
4. Diferențele de alergeni sau ingrediente nu se rezolvă prin combinarea automată a valorilor. Consumatorul vede incertitudinea, iar producătorul primește o comparație.
5. Alertele OpenAliment curente rămân independente de cache-ul OFF. Erorile OFF și lipsa alertelor OFF nu înseamnă că un aliment este sigur.
6. Înregistrările externe nu creează organizații, revendicări, loturi, rețete, eliberări sau sigle. Demonstrațiile și GTIN-ul demonstrativ PBT sunt excluse din orice contribuție reală.
7. Un câmp lipsă rămâne necunoscut. Nu se deduce absența unui alergen, data expirării, densitatea, porția sau jurisdicția etichetei din lipsa datelor.
8. Datele și imaginile din OFF nu primesc licența MIT. Copierea într-o ciornă nu șterge proveniența și obligațiile de reutilizare.

## Fluxurile funcționale

### Scanare în Acasă

Se interpretează codul existent, păstrând identificatorii și datele observate. Se caută întâi datele aprobate OpenAliment și alertele curente. Catalogul extern poate furniza un supliment despre produs, fără să modifice profilul lotului.

La un produs extern, denumirea și cantitatea disponibile completează profilul minimal. Fotografia și informațiile alimentare externe sunt afișate din suplimentul licențiat. Pentru data ambalajului se folosesc lotul, codul sau confirmarea utilizatorului. Nu se importă `expiration_date` din fișa generică OFF drept termen al ambalajului.

Citirea locală și adăugarea alimentului nu așteaptă obligatoriu un serviciu extern. La lipsă în cache, clientul poate cere o singură căutare explicită prin API. Aceasta încearcă o citire OFF numai dacă primește imediat buget și lease, cu termen strict; altfel răspunde cu starea programată. Utilizatorul poate continua cu „Aliment” și adăuga data. Îmbogățirea ulterioară nu dublează ambalajul și nu modifică numele personalizat, cantitatea, locul, data sau starea acestuia.

Confirmarea adăugării apare o singură dată. Nu cerem cont OFF, alegerea sursei sau învățarea termenilor GTIN/cache. Sursa, licența și vechimea informației sunt accesibile în detalii. Problemele privind alergenii sunt vizibile lângă informația afectată.

### Precompletare pentru producător

Din articolul comercial sau din import, producătorul caută un GTIN, vede fișa externă și selectează câmpurile pentru ciornă. Previzualizarea arată diferențele și câmpurile incompatibile. Crearea ciornei este idempotentă și legată de revizia externă exactă; publicarea rămâne în fluxul curent owner/quality.

Nu se completează automat operatorul legal cu marca. Dacă articolul nu există, utilizatorul îl creează prin fluxul normal; verificarea mandatului GS1 nu este sărită. Fotografiile OFF rămân resurse externe licențiate și nu sunt transformate în documente proprii verificate.

### Comparare și actualizare

O nouă revizie externă produce diferențe relevante pentru articolele asociate voluntar de organizații. Fișa externă nu este o sarcină obligatorie pentru fiecare produs din catalogul public. Duplicatele aceleiași diferențe se reunesc prin amprentă.

Editorul poate pregăti o propunere, iar owner/quality o poate decide. Acceptarea generează o ciornă nouă. Respingerea sau marcarea „altă piață/altă formulă” păstrează explicația; aceeași revizie nu reapare zilnic ca problemă nouă. Alertele oficiale de lot se gestionează separat.

### Distribuire către OFF

Owner activează o conexiune pentru organizație și un acord explicit pentru articol și câmpurile eligibile. Acordul precizează licențele, atribuirea contribuției și caracterul public al distribuirii. Revocarea oprește transmiterile viitoare; nu promite retragerea copiilor deja distribuite legal.

O versiune publicată eligibilă produce intenția de distribuire. Se verifică din nou mandatul, acordul și starea conexiunii înaintea expediției. Se compară baza ultimei sincronizări, versiunea locală și versiunea externă. Actualizările concurente incompatibile sunt trimise spre decizie, nu suprascrise.

Rețetele private, fișierele interne, costurile, loturile/seriile, datele gospodăriilor și fotografiile personale nu intră în payload. Un câmp gol nu devine comandă de ștergere; ștergerea se cere explicit și se mapează după transportul OFF ales.

## Modelul de date propus

Se adaugă 12 tabele: nouă pentru citire și operare, trei pentru distribuirea autorizată. Se evită un tabel pentru fiecare nutrient sau valoare: snapshoturile normalizate și proveniența pe câmp sunt JSON validate. Identitățile, cozile și relațiile sunt coloane indexate.

Toate ID-urile interne noi sunt `CHAR(32)`. GTIN este `CHAR(14)` ASCII binar, fără conversie numerică. Codurile sursei sunt șiruri ASCII limitate. Momentele operaționale sunt UTC; datele alimentare rămân calendaristice. Tabelele sunt InnoDB/utf8mb4. Enumerările sunt validate în domeniu și contracte, fără presupunerea acelorași facilități JSON/CHECK pe toate versiunile MySQL.

### Migrarea 009 pentru catalogul extern

Numele propus este `009_external_catalog.sql`; se reverifică disponibilitatea numărului înainte de implementare. Nu se modifică migrările 001–008.

| Tabel | Coloane principale propuse | Chei și reguli |
| --- | --- | --- |
| `external_providers` | `id VARCHAR(40)`, `name`, `api_version`, `mapping_version`, `dataset_license_json`, `read_state`, `outbound_state`, `policy_version`, `policy_reviewed_at`, `updated_at` | PK `id`; prima intrare `openfoodfacts`; hostname-urile și transporturile sunt fixate în adaptor, nu introduse liber în UI |
| `external_products` | `id`, `provider_id`, `gtin14`, `source_code`, `availability`, `current_revision_id NULL`, `last_fetched_at`, `last_attempt_at`, `next_refresh_at`, `failure_count`, `negative_until` | UNIQUE `(provider_id,gtin14)`; index `(provider_id,availability,next_refresh_at)`; fără `tenant_id`, fără mandat GS1; `not_found` nu primește revizie fictivă |
| `external_product_revisions` | `id`, `external_product_id`, `remote_revision`, `remote_schema_version`, `source_modified_at`, `source_updated_at`, `fetched_at`, `mapping_version`, `content_sha256`, `normalized_json`, `provenance_json`, `visibility`, `raw_json NULL`, `raw_archive_key NULL`, `normalized_archive_key NULL` | UNIQUE `(external_product_id,content_sha256)`; UNIQUE `(external_product_id,id)` pentru FK compuse; index pe produs și moment; conținutul normalizat este imuabil, vizibilitatea poate fi suspendată cu audit |
| `external_assets` | `id`, `external_product_id`, `source_asset_key`, `source_url`, `remote_revision`, `license_json`, `credit_json`, `state`, `sha256`, `mime`, `width`, `height`, `size_bytes`, `storage_key`, `fetched_at`, `last_error_code` | UNIQUE `(external_product_id,source_asset_key)`; index `(state,fetched_at)`; versiunea imaginii face parte din cheia sursă; se servesc numai copii verificate |
| `external_jobs` | `id`, `provider_id`, `kind`, `gtin14 NULL`, `external_product_id NULL`, `active_key NULL`, `state`, `priority`, `available_at`, `lease_token`, `lease_until`, `attempts`, `deadline_at`, `result_code`, `created_at`, `finished_at` | UNIQUE `(provider_id,active_key)`; index `(state,available_at,priority)` și pe lease; terminalul eliberează `active_key`; coada nu conține gospodărie, URL scanat sau preferințe |
| `external_provider_limits` | `provider_id`, `bucket`, `next_slot_at`, `cooldown_until`, `consecutive_failures`, `breaker_state`, `updated_at` | PK `(provider_id,bucket)`; rezervare tranzacțională scurtă; bugete separate `product_read`, `search`, `image`, `write` |
| `external_dataset_runs` | `id`, `provider_id`, `kind`, `state`, `manifest_json`, `manifest_sha256`, `artifact_key`, `byte_offset`, `record_count`, `accepted_count`, `rejected_count`, `started_at`, `finished_at`, `result_json` | Index `(provider_id,kind,state)`; import, export licențiat și arhivare; checkpoint reproductibil; fără cale arbitrară dată de client |
| `external_product_bindings` | `id`, `tenant_id`, `trade_item_id`, `external_product_id`, `last_seen_revision_id NULL`, `last_import_revision_id NULL`, `last_import_label_id NULL`, `created_by`, `state`, `created_at` | UNIQUE `(tenant_id,trade_item_id,external_product_id)`; FK compuse către articol și etichetă; legare exactă la GTIN-ul articolului, nu după nume |
| `external_conflicts` | `id`, `tenant_id`, `binding_id`, `external_product_id`, `trade_item_id`, `external_revision_id`, `local_label_id`, `direction`, `severity`, `fingerprint`, `diff_json`, `state`, `decision`, `decided_by`, `decided_at`, `created_at` | UNIQUE `(tenant_id,binding_id,fingerprint)`; index `(tenant_id,state,severity,created_at)`; FK compuse pentru legăturile private |

`current_revision_id` trebuie să aparțină aceluiași produs: FK `(id,current_revision_id)` către `external_product_revisions(external_product_id,id)`, instalat pe tabelul nou după crearea reviziilor și înainte de activare. Migratorul verifică existența constrângerii la reluarea unui DDL parțial. Referințele de revizie din bindings au FK `(external_product_id,revision_id)` pentru fiecare coloană relevantă. Bindingul are o cheie unică suplimentară `(tenant_id,id,external_product_id,trade_item_id)`, referită de conflicte; conflictul referă și `(external_product_id,external_revision_id)`. Etichetele importate sau comparate sunt verificate ca aparținând exact articolului legat, nu doar aceleiași organizații. Stările și deciziile verifică tranzacțional aceste relații, cu teste de substituire a ID-urilor.

`content_sha256` acoperă conținutul sursă selectat, versiunea schemei, maparea și proveniența stabilă; exclude momentele locale de fetch. O nouă citire identică nu creează revizie. Schimbările datelor calculate pot crea o revizie chiar dacă `remote_revision` nu s-a schimbat.

Răspunsurile cu identificator incompatibil, produs nealimentar sau schemă necunoscută nu schimbă capul valid. Se păstrează o probă limitată în carantină privată și un cod de eroare. Nu se creează pentru ele identități GS1 aproximative.

Un 404 ulterior pentru o fișă cunoscută nu șterge istoricul și nu declară rechemarea alimentului. După confirmări repetate sau notificare autentică a sursei se poate marca `withdrawn`; publicarea curentă și imaginile pot fi suspendate, păstrând intern proveniența necesară. Datele retrase nu mai completează scanări noi. Un cache sau export public deja descărcat nu poate fi retras retroactiv prin modificarea bazei noastre.

### Migrarea 010 pentru distribuire

Numele propus este `010_external_sharing.sql`. Se aplică după 009; nu este necesară pentru utilizarea catalogului în Acasă.

| Tabel | Coloane principale propuse | Chei și reguli |
| --- | --- | --- |
| `external_connections` | `id`, `tenant_id`, `provider_id`, `remote_organization_ref`, `transport`, `credential_ref`, `state`, `verified_at`, `verified_by`, `last_error_code`, `created_at` | UNIQUE `(tenant_id,provider_id)`; FK tenant/provider; referință la secret privat, fără parola sau cookie-ul OFF în rânduri exportabile |
| `external_sharing_grants` | `id`, `tenant_id`, `connection_id`, `trade_item_id`, `grant_key`, `version`, `previous_id NULL`, `action`, `fields_json`, `asset_ids_json`, `licenses_json`, `mode`, `actor_id`, `created_at` | UNIQUE `(tenant_id,grant_key,version)`; versiuni imuabile pentru acord/modificare/revocare; ultimul eveniment este starea efectivă; FK compuse la organizație/articol/conexiune |
| `external_publications` | `id`, `tenant_id`, `connection_id`, `trade_item_id`, `label_id`, `grant_id`, `payload_sha256`, `payload_json`, `base_remote_revision`, `base_remote_json`, `state`, `attempts`, `available_at`, `lease_until`, `lease_token`, `remote_result_json`, `confirmed_at`, `created_at` | UNIQUE `(tenant_id,connection_id,label_id,payload_sha256)`; index `(state,available_at)`; payload fixat; aprobarea se verifică din nou la expediere |

Se reutilizează `audit_events` pentru actor, decizii și încercările distribuirii; nu se dublează infrastructura de audit. Publicațiile au stări `pending`, `sending`, `confirmed`, `retry`, `uncertain`, `conflict`, `cancelled`, `dead`. Numai confirmarea validată produce `confirmed`.

Secretul este în configurație privată separată, cu referință fixată de server; nu se acceptă o cale de fișier din API. Forma autentificării și eventuala asociere a contului tehnic la organizațiile OFF se fixează după validarea transportului cu echipa lor.

### Tabelele existente

Nu se schimbă semantica sau cerințele `gtin_registry`, `trade_items`, `product_info_versions`, `batches`, `recipe_versions`, `resolver_links`, `print_units` și `print_jobs`. Ciornele importate folosesc tabelele existente și bindingul extern ca proveniență.

`home_items` și istoricul rămân în formatul actual. Profilul extern minimal folosește câmpurile existente `source.kind=external`, `source.name`, `source.url`, `source.license`, `source.version_id`, `source.version`, `source.sha256`. Acestea identifică revizia locală imuabilă; nu afirmă aprobarea producătorului. Validatoarele PHP/JS se verifică împreună, fără modificarea implicită a formatului `home.v1`.

## Contractele și maparea datelor

### Pachetul de rezoluție

Se introduce `contracts/consumer-resolution.schema.json`, cu `schema_version=openaliment.consumer-resolution.v1`. Acesta este un pachet nou, nu o reinterpretare a `openaliment.consumer-product.v1`.

```json
{
  "schema_version": "openaliment.consumer-resolution.v1",
  "profile": {"schema_version": "openaliment.consumer-product.v1"},
  "supplement": null,
  "external_state": "not_requested",
  "policy_version": "openaliment.source-selection.v1"
}
```

Exemplul ilustrează structura; `profile` complet trebuie să respecte contractul existent. `supplement`, dacă există, respectă `external-food-record.schema.json` și conține identificatorul/revizia, faptele externe normalizate, imaginile, proveniența pe câmp, licențele și vechimea. Stările externe sunt `available`, `stale`, `queued`, `not_found`, `temporarily_unavailable`, `not_requested`, `not_applicable`.

În profilul minimal extern se pot copia denumirea, categoria mapată și cantitatea cu sursa externă explicită. `producer` rămâne necunoscut dacă este disponibilă numai marca. `food_data` rămâne necunoscut; alergenii și nutriția OFF sunt în supliment, cu propriul tip de afirmație. Un client vechi poate afișa alimentul fără a lua datele externe drept declarație verificată.

Pentru un profil aprobat, sursa producer_approved nu este înlocuită cu external. Suplimentul este vizibil separat. Pentru un lot istoric, o nepotrivire de variantă nu permite folosirea automată a nutriției curente externe în calcule. Interfața explică faptul că fișa externă este despre produs, fără confirmarea formulei lotului.

### Maparea

| Familie OFF | Destinație și validare |
| --- | --- |
| `code`, GTIN cerut | Păstrare ca șir, verificare cifră de control și echivalență GTIN-14; fără eliminarea cifrei de ambalare a unui GTIN-14 distinct; UPC-E se extinde numai când simbologia este cunoscută |
| Nume, limbă, marcă, cantitate | Limite de lungime, limba originală și alternative explicite; fără traducere AI implicită sau operator legal inventat |
| Categorii și `tags_sources` | Mapare versionată; păstrarea taxonului sursă și a părinților; etichetele comerciale nu devin certificări OpenAliment |
| Ingrediente și analize | Text de etichetă separat de ingrediente deduse, procente estimate, statut vegan/vegetarian calculat; nu se construiește rețeta tehnologică |
| Alergeni și urme | Mapare documentată către cele 14 clase existente; păstrarea dovezii și tipului afirmației; taxon necunoscut raportat, nu aruncat în tăcere; absența nu devine declarație negativă |
| Nutriție | Energie kJ/kcal, grame, porție, bază 100 g/100 ml/porție, vândut/preparat și metodă de obținere; lipsa bazei împiedică un calcul numeric prezentat ca exact |
| Ambalaje și origine | Piață de comercializare, origine și loc de fabricație separate; fără deducerea originii din prefixul GTIN |
| Scoruri și calitate | Tip, algoritm/versiune când sunt disponibile, stare și sursă; completitudinea sau un scor bun nu echivalează cu absența riscului |
| Imagini | Tip, limbă, revizie, URL permis, credit și licență; copii locale verificate, fără conversia fotografiei de etichetă în dovadă a lotului curent |
| Revizii și surse | `rev`, `schema_version`, `last_modified_t`, `last_updated_t`, `sources`, `tags_sources` unde sunt documentate; adaptorul nu depinde de câmpuri interne nedocumentate |

Se fixează API OFF v3.6 și maparea inițială în configurație/catalog. Documentația lor semnalează schimbări incompatibile de nutriție și taxonomii; fixture-urile trebuie să includă aceste forme, nu doar exemplele vechi din referința GET. Câmpurile suplimentare se ignoră controlat; o schimbare incompatibilă în câmpurile utilizate oprește ingestia acelei fișe.

Pentru PWA se adaugă `external.js` pentru comunicare și `external-domain.js` pentru validare/selecție. `domain.js` păstrează regulile producer food_data. Funcțiile de avertizare/calcul extern sunt explicite și primesc suplimentul, nu îl maschează ca food_data declarat.

Suplimentele se păstrează în IndexedDB ca înregistrări separate în store-ul existent `state`, cu chei `external:<revision_id>`. Nu se modifică structura obiectului inventarului și nu se declanșează sincronizări personale la fiecare refresh de cache. Pentru profilurile externe, `source.version_id` permite altui dispozitiv să recupereze revizia exactă. Pentru produsele proprii, suplimentul curent rămâne informație externă separată; nu este parte a etichetei istorice.

## Pagini și view-uri

| Pagină | View și resurse | Modificare propusă |
| --- | --- | --- |
| `/acasa/` scanare/listă | `consumer-home.php`, `home/app.js`, `scanner.js`, noile module externe | Recunoaștere automată, confirmare unică, imagine opțională, continuare fără OFF; fără formular sau cont nou |
| `/acasa/` detalii | `consumer-home.php`, `home/app.js`, `home.css` | Informații externe cu sursă, revizie și vechime; alergeni/nutriție separați; link către fișa originală |
| `/acasa/` preferințe și transfer | `consumer-home.php`, `store.js`, `transfer.js`, `privacy.js` | Cache licențiat separat; export complet cu suplimente; ștergerea locală elimină și cache-ul; alegerile de sănătate rămân locale |
| `/manage/items/{id}` | `manage.php` | Legătură contextuală „Date din Open Food Facts”, starea comparației și acces la precompletare; nu se încarcă întreaga fișă în dashboard |
| `/manage/items/{id}/sources` nou | `external-sources-manage.php` nou | Asociere, comparație câmp cu câmp, alegere de câmpuri și creare ciornă; acțiuni numai potrivit rolului |
| `/manage/labels/{id}` | `manage.php`, component nou `external-provenance.php` | Rezumat al provenienței câmpurilor preluate și avertizarea diferențelor; aprobarea curentă se păstrează |
| `/manage/import` | `manage.php` | Intrare separată pentru precompletare din OFF; importurile JSON/NDJSON/PBT existente rămân distincte |
| `/manage/sources` nou | `external-sources-manage.php` | Diferențe și stări numai pentru articolele organizației, cu paginare și filtre utile |
| `/manage/connectors` | `connectors.php` | Acces contextual la distribuire OFF; tipurile ERP și bon nu sunt redenumite sau reinterpretate |
| `/manage/sources/openfoodfacts/sharing` nou | `external-sharing.php` nou | Conexiune, acorduri, câmpuri distribuite, mod manual/automat și istoric; apare ca funcție activabilă numai după gate-ul de scriere |
| `/admin/sources` nou | `external-sources-admin.php` nou | Activare citire, bugete, pauză, carantină, import/export, starea licențelor și resurse; fără secrete în pagină |
| `/admin/updates` | `updates.php` | Migrări 009/010 cu explicația impactului; aplicarea migrării nu activează singură accesul extern |
| Panou operațional | `operations-panel.php` | Hit rate cache, erori, vechime, coadă, circuit, spațiu și heartbeat separat |
| `/surse/open-food-facts` nou | `external-source.php` nou | Proveniență, atribuiri, licențe, limite și exportul public licențiat; fără catalog duplicat pentru SEO |
| `/confidentialitate` | `privacy.php`, `PlatformInformation.php` | Căutarea externă transmite numai GTIN/date tehnice; fără inventar, cont, fotografii sau preferințe |
| `/api/docs` și pagini generate | view-urile API existente și generatorul | Ghidul surselor externe, operații, exemple de stări/erori, Markdown și acces pentru AI |
| `/catalog`, `/p`, `/t`, `/b`, pagini QR | view-urile existente | Nicio includere automată a catalogului OFF; eventual legătură aprobată și atribuire, fără înlocuirea etichetei |
| Landing, demo PBT și marketplace | view-urile existente | Se actualizează oferta numai după activare verificată; nu se afirmă parteneriat oficial, certificare sau două sensuri înainte de probe |

Se păstrează layout-ul comun, alinierea `workspace-heading`, gridurile și footerul 0.7.3. Sursa se afișează prin aceeași componentă în toate locurile relevante. Pe 320/390 px nu apar tabele orizontale obligatorii: diferențele sunt carduri cu etichete; pe desktop pot fi coloane. Focus vizibil, tastatură, cititoare de ecran, iconuri SVG și contrastul nu depind exclusiv de culoare.

## API și documentație

Rutele de mai jos sunt propuse. Operațiile se înregistrează în `ApiRoutes.php`; autorizarea este aplicată în domeniu și gateway. `/api/v2/public/food` și API v1 rămân cu semantica actuală. Nu se copiază numerotarea OFF în API-ul nostru.

| Metodă și rută | Acces | Contract și comportament |
| --- | --- | --- |
| `POST /api/v2/public/food/resolve` | Public, limitat | `code` în corp, maximum 4096 caractere; pachet de rezoluție din citire locală și alerte curente; fără fetch OFF sau enqueue; răspuns no-store |
| `POST /api/v2/public/food/lookups` | Public, buget strict | Numai `gtin`; cache existent sau căutare deduplicată; `200` terminal, `202` programat, `429` limită cu Retry-After; fără URL extern liber |
| `GET /api/v2/public/food/lookups/{id}` | Public, ID aleator | Stare și rezultat public, fără actor/IP/cerere originală; max. două reîncercări automate în UI, apoi continuare normală |
| `GET /api/v2/public/sources` | Public | Surse activate, licențe, versiuni și disponibilitate; fără configurare internă sau credențiale |
| `GET /api/v2/public/external-revisions/{id}` | Public | Snapshot normalizat licențiat și imuabil; fără payload brut, erori interne sau relații ale organizațiilor |
| `GET /api/v2/public/external-assets/{id}` | Public | Imagine locală verificată sau fallback; nicio descărcare externă declanșată de GET |
| `GET /api/v2/public/sources/openfoodfacts/export` | Public | Ultimul export licențiat complet, manifest și linkuri; streaming/cache; fără creare de dump la cerere |
| `GET /api/v2/manage/items/{id}/sources` | Producer `read` | Asocieri, revizii și diferențe ale organizației |
| `POST /api/v2/manage/items/{id}/sources` | Producer `write` | Asociere voluntară cu un produs extern având exact același GTIN; fără mandat sau publicare implicită |
| `POST /api/v2/manage/items/{id}/sources/{binding}/disconnect` | Producer `write` | Oprește urmărirea diferențelor; proveniența ciornelor/versiunilor deja create se păstrează |
| `POST /api/v2/manage/items/{id}/sources/openfoodfacts/preview` | Producer `read` | Previzualizare fără import, cu versiuni și diferențe |
| `POST /api/v2/manage/items/{id}/sources/openfoodfacts/drafts` | Producer `write` | Ciornă din revizia și câmpurile alese; idempotency key; niciun publish implicit |
| `GET /api/v2/manage/source-conflicts` | Producer `read` | Listă cu cursor, severitate și stare |
| `POST /api/v2/manage/source-conflicts/{id}/decisions` | Owner/quality și scope `publish` | Decizie cu reviziile așteptate și motiv; acceptarea creează ciornă |
| `GET /api/v2/manage/sources/openfoodfacts/connection` | Owner | Metadatele conexiunii, fără secret |
| `POST /api/v2/manage/sources/openfoodfacts/connection` | Owner, `sharing:manage` | Solicită/actualizează asocierea organizației și transportul permis; secretul se provisionază separat, în privat; nu acceptă credential_ref sau cale de fișier arbitrară |
| `POST /api/v2/manage/sources/openfoodfacts/connection/disconnect` | Owner, `sharing:manage` | Oprește accesul și intențiile viitoare; confirmare recentă; operațiile deja în zbor se reconciliază |
| `GET /api/v2/manage/sources/openfoodfacts/sharing-grants` | Owner, `read` | Lista și istoricul propriilor acorduri, cu stare efectivă |
| `POST /api/v2/manage/sources/openfoodfacts/sharing-grants` | Owner, scope nou `sharing:manage` | Acord versionat explicit; nu este acordat automat tokenurilor existente |
| `POST /api/v2/manage/sources/openfoodfacts/sharing-grants/{id}/revoke` | Owner, `sharing:manage` | Eveniment de revocare și oprire a publicațiilor neexpediate |
| `POST /api/v2/manage/items/{id}/sources/openfoodfacts/publications/preview` | Owner/quality, `read` | Payload eligibil, licențe, bază și diferențe; fără trimitere |
| `POST /api/v2/manage/items/{id}/sources/openfoodfacts/publications` | Owner/quality, scope nou `sharing:write` | Intenție explicită pentru eticheta și acordul fixate; idempotentă |
| `GET /api/v2/manage/sources/openfoodfacts/publications` | Producer `read` | Istoric propriu și stări; fără acreditări |
| `POST /api/v2/admin/sources/{provider}/state` | Admin cu sesiune/CSRF | Pauză/activare după gates; citire și scriere distincte |
| `GET /api/v2/admin/sources/{provider}/status` | Admin | Sănătate, bugete, cote și cozi |
| `POST /api/v2/admin/sources/{provider}/connections/{id}/verify` | Admin cu sesiune/CSRF | Verifică transportul și referința privată provisionată; fără afișarea secretului |
| `POST /api/v2/admin/sources/{provider}/revisions/{id}/visibility` | Admin cu sesiune/CSRF | Suspendă/reia expunerea cu motiv; nu poate transforma o schemă sau un identificator invalid în date valide |
| `POST /api/v2/admin/sources/{provider}/jobs/{id}/retry` | Admin cu sesiune/CSRF | Reprogramare controlată, fără ignorarea bugetelor sau reluarea oarbă a unei scrieri incerte |
| `POST /api/v2/admin/sources/{provider}/datasets/preview` | Admin cu sesiune/CSRF | Validarea unui artifact pregătit și a manifestului, fără ingestie |
| `POST /api/v2/admin/sources/{provider}/datasets` | Admin cu sesiune/CSRF | Job reluabil de ingestie, cu limite de volum |
| `POST /api/v2/admin/sources/{provider}/exports` | Admin cu sesiune/CSRF | Pregătește exportul licențiat în fundal |

Noile scopes nu sunt aliasuri pentru `write` sau `publish`. Se extind selecția cheilor, OAuth, manifestul și exemplele; acțiunile cu sesiune cer CSRF și confirmare recentă pentru acorduri. Un editor cu cheie ERP poate crea ciorne, fără să activeze distribuirea.

Rezoluția care include lot/serie se cere prin POST pentru a evita introducerea lor în query-urile noii rute și în logurile HTTP obișnuite. Ruta legacy GET este păstrată pentru compatibilitate. Metadatele externe publice se pot cache-ui separat, cu ETag și durată limitată potrivit politicii de retragere; pachetele compuse și alertele rămân no-store. Publicațiile, conflictele și conexiunile private nu sunt cache public. HEAD nu declanșează I/O extern.

Scheme noi: `external-food-record.schema.json`, `consumer-resolution.schema.json`, `external-lookup.schema.json`, `external-import.schema.json`, `external-conflict-decision.schema.json`, `external-sharing.schema.json`, `external-dataset-manifest.schema.json`, `external-transfer.schema.json`. Ghidul API explică limitele, lipsa lotului, citare/licențe, cache și versiuni. Exemple executabile: scanare GTIN, QR cu lot/serie, cache lipsă, OFF indisponibil, ciornă, conflict, acord/revocare, timeout după expediere și export/import.

## Sincronizare, capacitate și cPanel

Se folosește un worker extern separat, `tools/external-worker.php`, cu lease DB pe job și limite persistente per provider. Nu se introduce rețea OFF în `Operations::worker()`, sub lock-ul comun cu SMTP/scaner/AI. Statusul se publică în mecanismul operațional existent, cu heartbeat separat. Există și execuție HTTPS administrativă limitată, printr-un serviciu de domeniu cu același buget; nu se publică un runner anonim ori un token în URL.

Pentru căutarea inițiată de scanare, handlerul POST poate executa cel mult o citire de produs în afara tranzacției, folosind același mecanism de claim/buget ca workerul. Dacă nu poate obține imediat permisiunea, nu așteaptă un slot: răspunde 202. Căutările în masă, imaginile și reîncercările sunt numai joburi. Blocarea sesiunii PHP este închisă înainte de rețea acolo unde există sesiune.

Parametrii următori sunt valori inițiale propuse, nu capacități măsurate sau condiții impuse de OFF:

| Parametru | Valoare inițială și regulă |
| --- | --- |
| Citire OFF produs | Buget administrat de maximum 10/minut pentru IP-ul comun: inițial 8/minut producție și 2/minut staging, cu sloturi la minimum 7,5 s, respectiv 30 s, fără rafală; staging folosește implicit simulatoare |
| Căutare OFF | Dezactivată în autocomplete; maximum 5/minut pentru IP-ul comun, împărțite 4 producție și 1 staging pentru probele reale necesare; citirea exactă GTIN este preferată |
| Timeout POST scanare | Conectare 0,5 s, total rețea 1,5 s; răspunsul continuă fără OFF la depășire |
| Worker | Maximum 20 s/executare și memorie țintă sub 64 MB pentru modul; fără sleep pentru rate limit; cron la minut după verificarea binarului PHP 8.4 |
| Răspuns produs | Maximum 256 KiB după decomprimare, JSON cu adâncime limitată și câmpuri solicitate explicit |
| Prospețime | 24 h până la refresh recomandat pentru produse urmărite; refreshurile de fundal nu pot ocupa mai mult de jumătate din buget când există cereri noi |
| Fișă veche | După 7 zile se semnalează vechimea; după 30 zile datele externe sensibile nu alimentează automat calcule/declarații, dar istoricul rămâne consultabil |
| Produs negăsit | Cache negativ 6 h; 429/timeout/503 nu sunt „produs negăsit” |
| Reîncercări citire | Maximum 5 cu backoff și jitter; Retry-After are prioritate; circuit deschis la 429 sau erori repetate; fără buclă activă |
| Imagine | Descărcare serială și separată; maxim 2 MiB/fișier și limită de pixeli; se preferă miniatura documentată potrivită |
| Cote pilot | Maximum 10.000 produse, 200 MiB date externe DB și 500 MiB pentru imagini/arhive/artifacte, reduse dacă planul hosting nu permite; oprire înainte de umplerea discului |

Coada publică are inițial maximum 500 joburi active, cu plafon de 20 GTIN-uri noi/oră/IP și un plafon global configurabil. Numerele sunt parametri de pilot, nu o promisiune de volum. Joburile interactive expiră după 2 h; joburile nereușite ies în stare terminală, fără a fi regenerate automat la fiecare polling. În mod normal se folosește maximum jumătate din buget pentru refresh și restul pentru produse noi; când o clasă nu are lucru, capacitatea poate fi utilizată de cealaltă. Datele sursei nu se corelează cu identitatea gospodăriei pentru prioritizare.

Limitele documentate OFF sunt în prezent 15 citiri de produs/minut/IP și 10 căutări/minut/IP. Pentru mai mult de câteva sute de produse, ei recomandă exporturi; traficul foarte mare poate necesita un backend propriu alimentat din exporturi. Nu se distribuie traficul pe IP-uri pentru ocolirea limitelor. Pe același cont/IP pot exista și alte aplicații, deci 10/minut nu garantează evitarea oricărui 429. [API oficial](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/).

Producția și stagingul sunt în același cont cPanel; nu primesc fiecare întregul buget IP. Alocările din configurațiile separate trebuie să însumeze cel mult bugetul aprobat și includ citirile pentru reconcilierea scrierilor. Nu se partajează credențiale DB de producție cu stagingul pentru coordonare. Modificarea alocării cere verificarea celorlalți consumatori de pe IP; un backend dedicat poate centraliza ulterior limitarea fără schimbarea contractului public.

Un catalog cu 10.000 produse ar consuma aproximativ 20 ore și 50 minute pentru o parcurgere la alocarea de producție de 8 cereri/minut, înaintea prioritizării și erorilor. Refreshul zilnic exhaustiv pe API nu este strategia de creștere. Se folosesc recența pe produs și subseturi din exporturi; vechimea de 24 h este obiectiv de selecție, nu promisiune universală.

Seedul se construiește inițial pentru produse comercializate în România și alte produse întâlnite efectiv, fără excluderea alimentelor importate. Exportul mare se filtrează în afara cPanel și produce NDJSON și manifest cu sursă, versiune, licențe, hashuri și număr de rânduri. Hostingul citește streaming, verifică manifestul, procesează loturi mici și salvează offsetul; nu descarcă baza mondială în memoria unei cereri PHP.

Nu se presupune existența unui webhook public de actualizare OFF. Până la confirmarea unui mecanism oficial, sincronizarea folosește refresh programat și exporturi. [Documentația lor despre cache](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/tutorials/creating-a-local-cache-of-open-food-facts-data/).

## Păstrare, export și migrare

Payloadurile brute se păstrează cel mult 30 zile dacă nu există incident justificat. Snapshoturile normalizate și proveniența necesare referințelor istorice rămân recuperabile: în DB sau într-o arhivă privată cu cheie, hash și index. Arhivarea este probată prin citire și restore înaintea eliminării copiei DB. Nu se șterg reviziile prin presupunerea că nu mai există referințe în JSON-urile gospodăriilor.

La atingerea cotei se opresc admisiile noi, imaginile și joburile neesențiale. Nu se șterg etichete, loturi, audit sau copii personale și nu se blochează QR. Panoul arată cauza și permite creșterea cotei, export sau mutarea arhivei.

Exportul public al subsetului derivat OFF include datele eligibile, proveniența, modificările de mapare și licențele, în format reutilizabil. Nu include bindings/conflicts, organizații, istoric de scanare, secrete sau date personale. Se pregătește în fundal; publicul descarcă un artifact verificat. Dacă publicarea unei versiuni derivate necesită actualizarea exportului licențiat, versiunea și exportul se pregătesc împreună înaintea activării acelei versiuni.

Publicarea unui set de date și artifactul său sunt legate de același manifest și checkpoint. Pentru ingestia la cerere, snapshotul normalizat complet și atribuirea sunt descărcabile imediat prin endpointul public al reviziei; mecanismul acceptat pentru întregul subset poate folosi snapshot de bază plus pachete incrementale enumerate într-un manifest. Astfel, nu se reconstruiește întregul dump pentru fiecare scanare. Dacă obligațiile aplicabile nu pot fi satisfăcute de această formă, catalogul nou rămâne privat până la publicarea exportului necesar; QR-ul și datele proprii funcționează în continuare.

Exportul organizației include referințele/snapshoturile licențiate folosite în ciorne și proveniența, fără acorduri active sau acreditări transferabile. Un nou furnizor reverifică mandatul și acordul; importul nu reactivează distribuirea externă. Exporturile vechi OpenBread/OpenAliment rămân recunoscute.

Acasă primește un format nou de transfer `home.transfer.v2` care include inventarul v1, fotografiile personale și o colecție separată de suplimente licențiate, cu hashuri. Importul v1 continuă să funcționeze; exportul de compatibilitate v1 rămâne disponibil, indicând lipsa suplimentelor offline. Preferințele rămân tratate conform politicii existente. O copie importată nu obține statut de informație reverificată și nu poate modifica catalogul global.

Pentru cloud se păstrează protocolul actual al itemurilor. Un dispozitiv nou poate recupera suplimentul după referința publică a profilului extern. Nu se adaugă un tabel care corelează catalogul global cu gospodăriile pentru simpla numărare a utilizării. Profilurile mixte păstrează sursa producătorului, iar suplimentul curent este recuperat separat.

## Licențe, confidențialitate și securitate

OFF indică ODbL pentru bază, DbCL pentru conținuturile individuale și CC BY-SA pentru imagini. Modelul de atribuire pe ecran, export/API, modificări și imagini trebuie fixat înaintea expunerii publice. Separarea logică a datelor nu este declarată drept scutire automată de share-alike. [Licențele OFF](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/tutorials/license-be-on-the-legal-side/) și [ODbL 4.3–4.7](https://opendatacommons.org/licenses/odbl/1-0/).

Catalogul importat și transformările sale se pot distribui sub condițiile aplicabile; datele proprii care nu au acord de distribuire nu sunt amestecate într-un export derivat public. Înainte de activare se fixează cu responsabilul proiectului și, pentru cazurile neclare, cu OFF/juristul, tratamentul catalogului combinat și al precompletării. Nu se cere fiecărui producător să publice rețeta pentru a respecta licența datelor externe.

API-ul extern primește numai GTIN și parametri tehnici minimali, cu User-Agent al aplicației. Nu se trimit codul QR complet, lotul, seria, cookies, emailul consumatorului, preferințele sau fotografia. Noua rută POST evită query-uri cu lot/serie; logurile aplicației nu includ corpul scanării. Logurile legacy și cele ale hostingului se verifică separat, fără promisiunea că pot fi redactate integral din PHP. Tokenurile de bon nu sunt acceptate în lookup. Nu se contactează OFF și nu se completează formularul lor în cadrul redactării acestui plan.

Imaginile se preiau numai din hostname-urile oficiale aprobate de adaptor, prin HTTPS cu verificare TLS, fără redirect către hosturi nepermise. Sunt blocate IP-urile private/link-local/localhost, URL-urile cu userinfo și căile furnizate de utilizator. Se validează MIME real, pixeli, dimensiune, extensie și conținut; HTML și SVG activ nu intră în imaginile de produs. CSP rămâne same-origin; nu se adaugă acces larg la domenii externe.

Importul NDJSON protejează împotriva path traversal, decompression bombs, JSON prea adânc și cote depășite. Browserul nu trimite mapări executabile, SQL, URL arbitrar sau script de conector. Toate câmpurile de text sunt escapate, inclusiv nume, categorii, comentarii și licențe.

Citirea publică are rate limit local, plafon de GTIN-uri distincte și coadă finită; CORS și un GTIN valid nu sunt tratate drept protecție suficientă contra abuzului. Cache-ul și răspunsurile negative se consultă înaintea admisiei unui job. GET/HEAD nu produc fetch extern. Niciun token OFF nu ajunge în JavaScript, documentație, export sau release.

## Distribuirea autorizată și tratarea conflictelor

Transportul preferat este o integrare pentru producători agreată cu OFF, păstrând identitatea organizației și atribuirea câmpurilor. Ghidul lor descrie importul prin fișiere și conectoare pentru cataloage. API-ul public de scriere există, dar disponibilitatea lui nu stabilește singură mandatul conexiunii noastre. [Ghid producători](https://blog.openfoodfacts.org/en/EN-Pro-Platform-User-Guide.pdf).

Adaptorul de ieșire primește numai proiecția publicabilă și lista permisă, nu obiectele brute din catalog sau tenant. Transmiterea folosește snapshotul etichetei eligibile. Un grant cu mod automat poate distribui viitoarele versiuni publicate numai în lista și licențele deja acceptate; o fotografie nouă sau un câmp nou cere eligibilitate explicită. Revocarea dreptului GS1, conexiunii ori acordului invalidează joburile încă neexpediate.

Un câmp provenit din OFF nu este retrimis doar pentru că a fost copiat într-o etichetă aprobată. Comparația amprentelor elimină ecourile; pentru date completate/confirmate independent de producător se păstrează proveniența și drepturile distribuirii. Matricea de capabilități a transportului este pe câmp: referința PATCH v3 nu trebuie interpretată ca suport demonstrat pentru toate câmpurile nutritive și toate tipurile de imagini. Un câmp fără transport validat intră în pachetul de import pentru producător ori rămâne netransmis, cu motiv vizibil. [Referința scrierii](https://openfoodfacts.github.io/documentation/docs/Product-Opener/v3/products/patch-api-v3-product-code/).

Revocarea și claim-ul unei publicații se serializează în tranzacții scurte. O operație deja expediată poate fi acceptată remote chiar dacă revocarea intervine între timp; acest caz se reconciliază și se arată în istoric. Nu se menține o tranzacție DB deschisă pe durata rețelei pentru a pretinde anularea unei cereri deja plecate.

Comparația în trei versiuni este pe câmp: baza ultimei confirmări, local și remote. Se transmit câmpurile locale schimbate pentru care remote nu s-a schimbat incompatibil. Câmpurile completate de comunitate și nespecificate în acord nu se șterg. Se verifică `owner_fields`, sursele și regulile producătorilor dacă transportul ales le expune; nu se inventează un control de proprietate.

Fără un mecanism OFF de scriere condițională confirmat, citirea urmată de PATCH nu garantează absența unei curse. În această situație modul automat de înlocuire rămâne dezactivat; sunt permise pachete pentru importul producătorului sau operații manual confirmate, cu reverificare. Nu se prezintă comparația în trei versiuni ca tranzacție distribuită.

După timeout de scriere, starea devine `uncertain`; se citește remote și se reconciliază câmpurile înaintea unei alte tentative. Nu se retrimit orbește imagini sau comenzi de ștergere. Se salvează confirmarea, atribuirea și eventualele câmpuri respinse de OFF. Un răspuns HTTP 200 nu este suficient dacă rezultatul semantic conține erori.

## Fișiere și responsabilități

| Zonă | Fișiere propuse sau afectate |
| --- | --- |
| Domeniu nou | `ExternalCatalog.php`, `OpenFoodFacts.php`, `ExternalFood.php`, `ExternalJobs.php`, `ExternalMedia.php`, `ExternalDatasets.php`, `ExternalSharing.php` în `app/Domain/`; clase separate numai pentru responsabilitățile concrete descrise |
| Compunere și proveniență | `ConsumerFood.php` păstrat ca proiecție aprobată; noul serviciu compune pachetul; `Catalog.php`, `Portability.php`, `TransferStream.php` pentru proveniență/import |
| HTTP și UI | Noi controllere pentru citire/căutare externă și administrarea surselor; view-urile din matrice; `public/index.php` păstrează ordinea rutelor resolver/bon/cloud |
| API | `ApiRoutes.php`, `ApiInput.php`, `PlatformApiController.php`, `ApiKeys.php`, `OAuth.php`, `DiscoveryController.php`, `ConsumerController.php` și registrul documentației |
| Generatoare | `tools/build/external_contracts.py` nou; `consumer_contracts.py`, `home_contracts.py`, `api_contracts.py`, `api_reference.py`, `api_documentation.py`, `api_registry.php`, manifestele de build |
| PWA | `home/external.js`, `external-domain.js` noi; `app.js`, `domain.js`, `store.js`, `transfer.js`, `privacy.js`, `sw.js`, `entry.js`, `home.css` și view-ul Acasă |
| Operare | `tools/external-worker.php` nou; `Operations.php` numai pentru status, `Maintenance.php`, `Backups.php`, `OperationsAssets.php`, `Migrations.php`, `UpdatesController.php`, `config/external.local.example.php` fără secrete; workerul și exemplul trebuie adăugate explicit în `tools/build/release-manifest.json`, configurația reală rămâne exclusă |
| Date mari | `tools/data/prepare_off_subset.py` nou, streaming în dezvoltare/CI; fără runtime Python pe hosting |
| Documentație | Ghid API nou `docs/api/pages/surse-externe.md`, ghid de operare, mapare și atribuire; actualizări punctuale master, licențe, import/export, Acasă, manifest API și index |

Fișierele generate se reconstruiesc din sursele din `tools/build/`; nu se editează doar JSON-urile rezultate. Arhiva OpenBread și edițiile GS1 rămân neschimbate. Nu se publică dumpuri reale, cookies sau imagini fără licență în fixture-urile GitHub.

## Pachete de lucru și criterii de închidere

| Pachet | Livrabil concret | Dependențe și închidere |
| --- | --- | --- |
| OFF01 | Contracte, mapare, selecția surselor și politica licențelor | Fixture-uri pentru schema actuală, unități, taxonomii și atribuiri; responsabil tehnic plus responsabil de drepturi |
| OFF02 | Migrarea 009 și repository de catalog | FK/izolare, execuție repetabilă și reluare după DDL parțial; baza veche funcționează cu modulul oprit |
| OFF03 | Adaptor citire și coadă/bugete | Timeout, TLS, deduplicare, 404/429/503, circuit, fără tranzacție ținută în rețea |
| OFF04 | Media, seed și export licențiat | CSP same-origin, import streaming reluabil, cote, hashuri și export recuperabil |
| OFF05 | API public de rezoluție și Acasă | Flux complet cache cald/rece/offline, fără login, fără ambalaje duplicate, fără modificarea expiry sau sincronizării v1 |
| OFF06 | Precompletare și diferențe producător | Ciornă/selectare/proveniență/decizie, mandat separat, nicio publicare automată |
| OFF07 | Admin, operare, backup și portabilitate | Oprire instantanee, restore, transfer v1/v2, status și gates; catalogul derivat are calea de reutilizare necesară |
| OFF08 | Documentație și validare staging | Toate operațiile generate, exemple executabile, matricea de acceptare și regresiile existente trecute |
| OFF09 | Activare inbound controlată | Politica de licențe acceptată, utilizarea API înregistrată conform cerințelor OFF, seed măsurat și cotă verificată; fără autorizare de scriere OFF |
| OFF10 | Migrarea 010, acorduri și transport de ieșire | Transport și atribuire validate cu OFF, mandate și drepturi explicite; probe exclusiv în mediu de test înaintea pilotului real |
| OFF11 | Reconcilieri și pilot bidirecțional | Conflict concurent, revocare, timeout incert, actualizare și arhivare probate; modul automat limitat la garanțiile reale ale transportului |
| OFF12 | Publicare și predare operațională | Pachet GitHub verificat identic pe staging/producție, monitorizare, rollback probat și ghid pentru administrator |

Ordinea este OFF01–OFF09 pentru prima activare completă de citire. OFF10–OFF12 închid distribuirea în ambele sensuri. Nu se solicită un acord comercial cu OFF pentru a începe dezvoltarea și testele locale; nu se activează un transport real presupus. Accesul, drepturile și probele externe sunt criterii de activare, nu funcții fictiv bifate.

Estimare inițială pentru dimensionare: 25–40 zile de dezvoltare pentru inbound complet, 12–20 zile pentru outbound după clarificarea transportului, plus 8–12 zile QA/operare parțial suprapuse. Nu reprezintă termen calendaristic promis; se recalibrează după contracte și pilotul de volum. Proprietarul poate livra inbound fără să aștepte outbound.

## Matrice de acceptare

Scenariile sunt cerințe pentru implementare, nu probe deja executate. Testele automate folosesc date sintetice și server OFF simulat. Un set separat de probe read-only poate valida schema reală cu trafic minim; scrierile merg numai în mediul de test convenit.

| ID | Scenariu și rezultat cerut |
| --- | --- |
| OFT01 | Un GTIN cunoscut numai în OFF este recunoscut fără cont; sursa externă este vizibilă |
| OFT02 | Noul profil minimal rămâne valid în validatoarele PHP/JS și în cloud v1 |
| OFT03 | Datele OFF nu apar ca `food_data.status=declared` al producătorului |
| OFT04 | Un GTIN-8/12/13 echivalent se asociază corect; codul original și zerourile se păstrează |
| OFT05 | Un GTIN-14 cu nivel de ambalare diferit nu este redus la articolul individual |
| OFT06 | UPC-E, cod intern/restricționat și cifră de control invalidă nu produc identități comerciale inventate |
| OFT07 | QR cu CPV/lot/serie și procente/slash/plus păstrează identitatea după lookup și transfer |
| OFT08 | AI 15/17 și precizia lunii nu sunt înlocuite cu data generică OFF |
| OFT09 | Lotul vechi păstrează eticheta, iar suplimentul nu se prezintă drept formula lui confirmată |
| OFT10 | Alertele și disputele de mandat rămân vizibile cu OFF oprit sau cu date vechi |
| OFT11 | GTIN extern nu creează registru de mandat, organizație, siglă sau eliberare |
| OFT12 | Demonstrația PBT și identificatorii de test nu pot fi trimiși în OFF real |
| OFT13 | 100 cereri simultane pentru același GTIN produc un singur job activ și un fetch admis |
| OFT14 | GET/HEAD, refreshul alertelor și prefetchul browserului nu declanșează apeluri OFF |
| OFT15 | Bugetul persistă între procese, requesturi și worker; 429 activează cooldown |
| OFT16 | 404 semantic, timeout, 429 și 503 produc stări distincte; rezultatul bun anterior nu este distrus |
| OFT17 | Schema incompatibilă ori GTIN returnat diferit rămân în carantină; capul valid nu se schimbă |
| OFT18 | Câmpuri lipsă, zero numeric și declarație negativă rămân distincte |
| OFT19 | Alergen derivat, declarat și urme nu se amestecă; taxonii neacoperiți sunt semnalați |
| OFT20 | 100 g/100 ml/porție și vândut/preparat sunt diferențiate; fără densitate sau porție inventată |
| OFT21 | Ingrediente/scoruri calculate nu devin rețetă sau recomandare medicală |
| OFT22 | Cache rece adaugă o singură dată; rezultatul întârziat nu schimbă numele editat, cantitatea ori data |
| OFT23 | Offline și OFF indisponibil permit inventar, OCR și scanarea codurilor deja suportate |
| OFT24 | Dispozitiv vechi și nou, cloud și istoric, nu pierd referința externă sau produc conflicte la refreshul cache-ului |
| OFT25 | Cache extern local eliminat sau cotă IndexedDB atinsă nu șterg inventarul/fotografiile |
| OFT26 | Actualizarea service worker și revenirea la versiunea anterioară păstrează inventarul |
| OFT27 | Transferul personal v1 este importabil; v2 păstrează suplimente/atribuiri și detectează modificarea hashurilor |
| OFT28 | Export/import organizație păstrează proveniența, fără reactivarea acordurilor sau mandatului |
| OFT29 | Imagine/redirect către IP privat, SVG activ sau răspuns peste limită sunt respinse |
| OFT30 | Ingestia externă și exportul public nu conțin tenant, gospodărie, token de bon, email sau preferințe |
| OFT31 | Substituirea unui binding/conflict/label al altui tenant este respinsă și prin relațiile DB |
| OFT32 | Precompletarea produce ciornă idempotentă; editorul nu publică și nu acordă distribuire |
| OFT33 | Importul NDJSON întrerupt se reia, iar repetarea nu dublează revizii; manifestul invalid oprește ingestia |
| OFT34 | Cotele opresc munca externă fără blocarea QR, SMTP, backupului și scanerului |
| OFT35 | Restore-ul recuperează DB, imagini și revizii arhivate, păstrând referințele |
| OFT36 | Publicul poate obține subsetul derivat și licențele fără autorizare de producător |
| OFT37 | Concordanță UI/API/Markdown/manifest, aceleași stări, limite, exemple și atribuiri |
| OFT38 | 320/390/768/1440 px, tastatură și cititor de ecran, fără aliniere inconsistentă sau scroll orizontal obligatoriu |
| OFT39 | Rutele, descriptorul, linksetul, negocierea și query-ul GS1 trec regresiile neschimbate |
| OFT40 | Serviciul A fără rețetă/siglă/OFF și importurile istorice OpenBread rămân funcționale |
| OFT41 | Fără grant explicit și scope nou nu se poate trimite nimic către OFF |
| OFT42 | Revocarea oprește o publicație deja în coadă și nu promite ștergerea copiilor publice |
| OFT43 | Comparația în trei versiuni detectează modificări concurente și nu șterge contribuții nespecificate |
| OFT44 | Timeout după scriere produce `uncertain`; reconcilierea evită retransmiterea oarbă și imagini duplicate |
| OFT45 | Răspuns 200 cu erori de câmp nu este confirmare integrală; confirmarea salvează rezultatul semantic |
| OFT46 | Schimbarea licenței, imaginii, mandatului sau conexiunii suspendă operațiile devenite neeligibile |
| OFT47 | Cursa remote fără scriere condițională demonstrată blochează modul automat de înlocuire |
| OFT48 | Oprirea inbound/outbound și rollbackul aplicației păstrează datele și operațiile incerte pentru reconciliere |

OFT15 include explicit suma bugetelor staging + producție pe același IP. OFT36 include snapshotul de bază și incrementalele, precum și corelarea manifestului cu versiunea făcută publică. OFT42 include separat revocarea înainte de claim și revocarea unei operații deja în zbor.

## Activare, măsurare și revenire

1. Feature branch din `dev`, PR, contracte și teste locale; fără modificarea producției sau a standardelor fixate.
2. Publicare pe staging cu migrarea 009, modul implicit oprit și simulatoare; configurație/bază/secrete separate de producție.
3. Validarea licențelor, atribuiri și export, verificarea cotei cPanel, cron PHP 8.4 și utilizare API identificată. Codul brut al produselor nu se introduce în loguri de uz general.
4. Seed limitat și probe ale adaptorului real; apoi activare Acasă și precompletare pentru pilot, cu monitorizare zilnică a cotelor și erorilor. Erorile externe sunt stări gestionate, nu lipsa funcționării aplicației.
5. Promovare `dev` → `main` prin regulile GitHub existente; instalarea pe hosting rămâne explicită, cu backup și același artifact verificat pe staging.
6. Citirea și afișarea suplimentelor se opresc separat; QR și alertele continuă. Outbound este oprit din start și se activează numai după OFF10/OFF11.
7. Rollbackul codului nu șterge tabelele noi, reviziile sau publicațiile incerte. Migrarea inversă prin DROP nu este procedură de revenire. Se opresc workerul și distribuirea înaintea restaurării codului.

Ținte de validat, nu rezultate măsurate: p95 pentru rezoluție cu cache cald sub 300 ms pe staging și sub 500 ms pe hostingul pilot; maximum 10% degradare a p95 QR față de aceeași probă fără joburi externe; scenariul de miss extern nu întârzie confirmarea peste 2 s pe infrastructura sănătoasă. Se raportează separat latența de rețea a clientului, procesarea PHP și timpul OFF.

Se măsoară cache hit/miss, cereri unice noi/minut, distribuția vechimii, eșecuri de mapare, 429/5xx, dimensiuni, lungimea/vârsta cozii, duratele import/export și timpul de reconciliere. Dacă cererea de produse noi depășește persistent bugetul OFF sau cota shared, se extinde seedul/backendul dedicat înaintea promisiunilor de volum. Nu se majorează automat bugetele sursei.

Criteriul de finalizare pentru inbound este închiderea OFF01–OFF09 și OFT01–OFT40/OFT48, cu runbook, licențe și rollback. Pentru bidirecțional se adaugă OFF10–OFF12 și OFT41–OFT47, inclusiv probele externe reale autorizate. Starea reală a celor două livrări se afișează separat în discovery și documentație.

## Referințe externe

- [API și limite OFF](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/).
- [Schimbările schemelor și versiunilor](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/ref-api-and-product-schema-change-log/).
- [Normalizarea codurilor](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/ref-barcode-normalization/).
- [Citirea produsului](https://openfoodfacts.github.io/documentation/docs/Product-Opener/v3/products/get-api-v3-product-code/) și [scrierea produsului](https://openfoodfacts.github.io/documentation/docs/Product-Opener/v3/products/patch-api-v3-product-code/).
- [Cache local](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/tutorials/creating-a-local-cache-of-open-food-facts-data/), [imagini](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/how-to-download-images/) și [licențe](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/tutorials/license-be-on-the-legal-side/).
- [Ghidul platformei producătorilor](https://blog.openfoodfacts.org/en/EN-Pro-Platform-User-Guide.pdf), [ODbL 1.0](https://opendatacommons.org/licenses/odbl/1-0/), [GS1 URI Syntax 1.7.0](https://ref.gs1.org/standards/digital-link/uri-syntax/1.7.0/) și [GS1 Resolver 1.2.1](https://ref.gs1.org/standards/resolver/1.2.1/).

Analiza strategiei după redactare este păstrată separat în [evaluarea planului](../audits/OPEN_FOOD_FACTS_STRATEGY_REVIEW_2026-10-27.md).
