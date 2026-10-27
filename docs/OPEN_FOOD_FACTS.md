# Open Food Facts — operare și limite ale versiunii 0.8.0

Integrarea adaugă un catalog comunitar separat, recunoaștere în Acasă, precompletare în ciornă și pachete pentru contribuțiile producătorilor. Nucleul GS1 Digital Link, drepturile asupra identificatorilor, etichetele aprobate și loturile rămân autorități distincte.

## Instalare pe dev

Codul se integrează prin PR în `dev`, se verifică în CI și se instalează din pachetul acelui commit. Producția nu se actualizează prin acest flux. În `/admin/updates` se instalează resursele fixe ale release-ului și migrările aditive 009/010. În `/admin/sources` se confirmă atribuirea și distribuția licențiată și se activează citirea. Sursa este oprită implicit la instalare; nu se copiază baza producției în dev.

Migrarea 009 adaugă zece tabele: furnizori, produse externe, evenimente administrative globale, observații, imagini, joburi, limite, pachete de date, asocieri și diferențe. 010 adaugă conexiuni, acorduri versionate și contribuții. Tabelul suplimentar `external_provider_events` evită atribuirea falsă a unei operații globale unui producător: auditul existent cere obligatoriu `tenant_id`.

Configurația opțională este `config/external.local.php`, în afara `public/`, pornind de la exemplul distribuit. Nu conține credentiale OFF; nu există transport de scriere remote activ. Setările reale nu se comit în GitHub. Activarea nu necesită AI, shell, Python sau un cont OFF al consumatorului.

## Mediul oficial OFF

Pe `dev.openaliment.ro` adaptorul folosește implicit `world.openfoodfacts.net`, mediul de test indicat de documentația OFF, cu poarta Basic publică documentată de ei. Pe `openaliment.ro` folosește implicit `world.openfoodfacts.org`. Datele păstrează `source_environment`; pe ecran sunt marcate observațiile din mediul de test. Catalogul fixează mediul la instalare și refuză amestecarea după schimbarea configurației.

Pentru utilizarea publică a API-ului OFF se completează formularul de utilizare cerut în [documentația oficială](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/), folosind identitatea proiectului și contactul autorizat. Acesta nu se trimite automat de către aplicație. Scrierea remote și conturile OFF rămân separate de poarta publică a mediului lor de test.

## Procesare pe cPanel

Worker CLI, o dată pe minut, după verificarea binarului PHP 8.4 al domeniului:

```text
* * * * * /usr/local/bin/ea-php84 /home/apelero/dev.openaliment.ro/tools/external-worker.php >> /home/apelero/dev.openaliment.ro/storage/logs/external-worker.log 2>&1
```

Comanda este pentru mediul dev. cPanel poate avea altă cale pentru binarul PHP; se verifică înainte de activare. Workerul are porții limitate, lease-uri, checkpoint și bugete persistente. Nu ține tranzacții DB în timpul apelurilor de rețea. `/admin/sources` permite și procesare manuală limitată, utilă pentru prima verificare fără cron. Automatizarea continuă necesită cronul configurat de operator.

Buget implicit: 2 citiri produs/minut pe dev, maximum 8/minut în producție. Împart același IP cPanel; alte aplicații trebuie incluse în calcul. API OFF 3.6 documentează 15 citiri/minut/IP. Căutarea liberă prin text și scrierea remote nu sunt expuse. Maximum 20 GTIN-uri distincte/IP/oră, 500 joburi active, 10.000 produse, aproximativ 200 MiB de payload DB și 500 MiB fișiere externe. Metadatele și indexurile DB sunt suplimentare față de contorul payloadurilor. Cotele sunt operaționale, nu o promisiune că întregul catalog mondial încape pe shared hosting.

Apelurile folosesc HTTPS, hosturi oficiale fixe, verificare TLS, fără redirect sau proxy și un resolver DNS asincron cu termen limitat. Fără capabilitatea libcurl `ASYNCHDNS`, rețeaua adaptorului este refuzată. Termenul cURL include DNS: 1,5 s pentru produs și 3,5 s pentru imagine. Răspunsurile și imaginile sunt limitate ca octeți; sunt verificate schema, MIME și pixelii. QR-ul nu face apel OFF în timpul rezoluției.

GTIN-ul GS1 rămâne pe 14 cifre în OpenAliment și în cererea către OFF. OFF poate elimina zeroul de completare și răspunde `success_with_warnings`, cu avertizarea `different_normalized_product_code`. Adaptorul acceptă acest caz numai pentru `product_found`, fără erori, cu impact `none` și cod returnat echivalent aceluiași GTIN. Avertizările necunoscute ori care afectează datele sunt refuzate. Codul original al sursei se păstrează separat, conform [normalizării OFF](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/ref-barcode-normalization/).

`429` deschide cooldown-ul conform `Retry-After`. Trei erori tehnice consecutive deschid circuitul pentru cinci minute. Erorile nu șterg observația bună. Un `not_found` are cache negativ de șase ore; trei confirmări ulterioare pentru o fișă cunoscută retrag disponibilitatea curentă, păstrând istoricul. Aceasta nu înseamnă rechemare alimentară.

## Import de subset și export

Exporturile oficiale de producție se încarcă într-un catalog configurat pentru sursa `production`, separat de catalogul OFF staging. Pentru cataloage mai mari se folosesc exporturile oficiale, procesate în afara hostingului. Instrumentul local citește JSONL sau JSONL gzip în streaming și produce pachete de maximum 8 MiB:

```bash
python tools/data/prepare_off_subset.py /private/openfoodfacts-products.jsonl.gz \
  --country en:romania --output /private/off-romania
```

Alternativ, `--gtins /private/gtins.txt` selectează identificatori exacți. Instrumentul nu descarcă date și nu folosește credentiale. Datele reale pregătite nu se adaugă în repository. Verifică selecția și cotele înainte de încărcare. Formularul administrativ cere manifestul și fișierul NDJSON, validează hashul, GTIN-urile, schema și numărul rândurilor înainte de admitere. Workerul aplică porții idempotente de zece rânduri. API-ul permite reluarea unui run eșuat de la checkpoint.

Exportul public conține un manifest, observațiile normalizate eligibile și o linie finală cu SHA-256 al octeților precedenți, inclusiv LF. Se servește numai un artifact complet, verificat. Endpointul de enumerare a reviziilor permite recuperarea întregului subset prin citiri paginate și descărcări individuale. Cursorul ID nu este cronologic și nu este un feed incremental: se reia parcurgerea pentru actualizări. Suspendarea unei revizii invalidează exporturile vechi; nu poate retrage copiile deja descărcate.

Observațiile normalizate istorice mai vechi de 30 zile sunt arhivate în stocarea privată cu hash, apoi recitite și verificate înainte de eliminarea payloadului DB. Datele brute expiră după 30 zile. Metadatele, proveniența și referințele istorice nu se șterg. La atingerea cotei se opresc admisiile; operatorul exportă sau mărește justificat cota, fără ștergerea etichetelor ori loturilor.

Backupul criptat include noile tabele, imaginile și arhivele referite. După restore, sursa este pusă pe pauză, conexiunile sunt deconectate și pachetele neexpediate sunt anulate. Operatorul verifică mediul înainte de reactivare. Exportul producătorului păstrează separat observațiile licențiate folosite în ciorne și proveniența; importul le păstrează ca dovezi, fără reactivarea conexiunilor, acordurilor sau mandatului GS1.

## Acasă și prioritatea informațiilor

Profilul aprobat rămâne `consumer-product.v1`. Suplimentul extern este separat, inclusiv în stocarea locală. Scanarea adaugă un ambalaj fără să aștepte căutarea externă; îmbogățirea ulterioară păstrează cantitatea, numele corectat de utilizator, lotul, seria, expirarea și istoricul. Profilurile externe deja fixate nu sunt înlocuite retrospectiv cu o formulă nouă. Cloud-ul păstrează protocolul v1 al inventarului; suplimentul poate fi recuperat după referință pe un alt dispozitiv.

Cache-ul offline păstrează maximum 128 observații. Copia personală v2 include observațiile disponibile și licențele; v1 rămâne importabil și se exportă când nu există suplimente. Importul unei copii nu actualizează catalogul global și nu afirmă că datele sunt reverificate.

Datele raportate, derivate și estimate rămân distincte. Valorile agregate OFF nu devin etichete declarate. Calculatorul nutritiv refuză estimări, calificatori, baze ambigue, conversii masă–volum și aplicarea fișei generice unui lot identificat. Nu calculează doze de insulină. Lipsa unei informații nu este zero, absență de alergeni sau confirmare a siguranței.

## Producători și contribuții

În spațiul producătorului există Surse externe, comparația pe articol și distribuirea autorizată. Importul este selectiv și creează numai o ciornă. Schimbarea ingredientelor, alergenilor sau nutriției cere reconfirmarea declarațiilor structurate. Diferențele OFF sunt private organizației și nu modifică date aprobate sau loturi istorice.

Transportul livrat pentru contribuții este `producer_package`: acord versionat, selecție de câmpuri/imagine, mandate GTIN verificate, revocare, comparație în trei versiuni și ZIP cu CSV conservator plus JSON complet. `package_ready` nu înseamnă publicat în OFF. Operatorul nu are credentiale OFF în acest release. Transportul automat rămâne oprit până la acordul tehnic și organizațional cu OFF și verificarea concurenței, confirmării și capabilităților fiecărui câmp. Nu se completează automat formulare și nu se trimite corespondență către OFF.

## Licențe și verificare

Date: [ODbL 1.0](https://opendatacommons.org/licenses/odbl/1-0/) și [DbCL 1.0](https://opendatacommons.org/licenses/dbcl/1-0/). Imagini: [CC BY-SA 3.0](https://creativecommons.org/licenses/by-sa/3.0/). Atribuire: Open Food Facts și contributorii săi. Separarea logică nu este o scutire declarată de obligațiile licenței. Catalogul derivat și transformările sale sunt distribuite cu proveniență și export reutilizabil; rețetele private nu intră în acel export.

Testele `external.php`, `external-domain.mjs`, `test_external_http.py`, `external-browser.cjs` și `test_external_seed.py` folosesc exclusiv date sintetice și rețea simulată. Proba de citire a unui produs public OFF pe dev este separată de aceste teste. Rezultatele efective se consemnează în raportul implementării, fără a confunda simularea ERP/OFF cu o integrare remote validată.

Vezi [ghidul API](api/pages/surse-externe.md), [planul](plans/OPEN_FOOD_FACTS_IMPLEMENTATION_PLAN_2026-10-27.md) și [evaluarea strategiei](audits/OPEN_FOOD_FACTS_STRATEGY_REVIEW_2026-10-27.md).
