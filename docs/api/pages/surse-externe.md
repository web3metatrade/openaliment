# Open Food Facts și proveniența datelor

OpenAliment folosește Open Food Facts ca sursă comunitară complementară pentru alimente identificate prin GTIN. Informațiile aprobate de producător, observațiile externe și datele unui lot au proveniențe separate. Importul nu acordă drepturi GS1, sigla documentației deschise sau licență asupra unei rețete private.

Pe dev se consultă implicit mediul oficial de test OFF (`world.openfoodfacts.net`). Câmpul `source_environment` identifică mediul; datele lui nu sunt amestecate cu sursa OFF de producție.

## Rezolvă scanarea

Trimite codul complet numai către OpenAliment. Acest endpoint citește date locale și nu contactează OFF. Profilul `openaliment.consumer-product.v1` și protocolul inventarului rămân compatibile; noul înveliș `openaliment.consumer-resolution.v1` adaugă `supplement`, `external_state` și politica de selecție.

```bash
curl 'https://dev.openaliment.ro/api/v2/public/food/resolve' \
  -H 'Content-Type: application/json' \
  --data '{"code":"3274080005003"}'
```

`profile.source.kind=unavailable` înseamnă că profilul aprobat lipsește. `supplement=null` nu înseamnă că alimentul este sigur sau că nu conține alergeni. Clientul poate adăuga alimentul și solicita data de pe ambalaj, fără a aștepta sursa externă.

## Solicită căutarea

```bash
curl -i 'https://dev.openaliment.ro/api/v2/public/food/lookups' \
  -H 'Content-Type: application/json' \
  --data '{"gtin":"3274080005003"}'
```

Exemplul folosește un GTIN din catalogul public OFF. Răspunsul real depinde de conținutul sursei. În cache cald primești `200`, `state=completed` și `supplement`. O operație în așteptare întoarce `202`, ID și `Retry-After`. Citește `GET /api/v2/public/food/lookups/{id}` respectând intervalul. Acest GET nu contactează OFF. `result_code=not_found` este distinct de o eroare temporară; erorile nu înlocuiesc ultima observație bună.

La OFF se trimite numai GTIN-ul, cu User-Agent identificabil. Lotul, seria, expirarea, cookies, preferințele de sănătate și fotografiile personale nu sunt transmise. Sunt excluse codurile demonstrative proprii și codurile de circulație restrânsă. Nu există căutare publică arbitrară prin text, proxy URL sau import nelimitat.

## Limite și indisponibilitate

OpenAliment limitează cererile publice și admite maximum 20 GTIN-uri distincte/IP/oră. Coada are implicit maximum 500 joburi active. OFF API 3.6 are propriile limite per IP: bugetul local este maximum 2 citiri de produs/minut pe dev și 8/minut în producție, deoarece cele două medii pot partaja IP-ul hostingului. Această repartizare lasă rezervă sub limita de 15/minut documentată de furnizor; alte aplicații de pe același IP trebuie luate în calcul de operator.

Bugetele, lease-urile și cooldown-ul sunt persistente în DB. Un `429` respectă `Retry-After`; un timeout produce reîncercare limitată. QR-ul, profilul nativ, inventarul și alertele proprii funcționează independent. `GET /api/v2/public/capabilities` indică activarea sursei. Nu încerca să ocolești limitele folosind chei sau IP-uri suplimentare.

## Interpretarea informațiilor alimentare

Fișa externă include denumirea, limba, ingredientele, cantitatea, clasele de alergeni, piețele, seturile nutritive, imaginile și proveniența. Marca nu este confundată cu operatorul legal. Categoria externă nu rescrie automat profilul de categorie OpenAliment.

Seturile nutritive păstrează baza `per_100_g`, `per_100_ml` sau `per_portion`, starea `as_sold`/`as_prepared` și originea `reported`/`estimated`. Valorile agregate de OFF nu sunt tratate ca declarații de etichetă. Unitatea mg este convertită explicit în g. Nu se presupune densitatea alimentului, mărimea unei porții necunoscute sau că formula curentă este identică pentru un lot istoric.

Calculatorul Acasă folosește doar carbohidrați raportați fără calificatori, pentru produsul ca atare și o bază neechivocă. Nu calculează doze de insulină și nu aplică nutriția generică unui lot identificat. Lipsa alergenilor din sursă nu confirmă absența lor. Datele prea vechi nu sunt folosite pentru funcțiile sensibile.

## Precompletarea etichetei producătorului

1. Citește `/api/v2/manage/items/{id}/sources` cu `read`.
2. Cere o comparație prin `POST .../sources/openfoodfacts/preview`, indicând `revision_id` și câmpurile selectate.
3. Creează o ciornă prin `POST .../sources/openfoodfacts/drafts` cu `write`, `Idempotency-Key`, `version_key` nou și `expected_label_id` citit anterior; folosește `null` pentru prima etichetă.
4. Verifică și publică eticheta prin fluxul obișnuit de aprobare. Importul nu publică singur.

Pentru prima etichetă, `completion` furnizează câmpurile obligatorii lipsă, inclusiv operatorul legal. Importul ingredientelor, alergenilor sau nutriției resetează declarațiile structurate afectate la `unknown`, până la confirmare. Asocierea este privată și cere același GTIN; organizațiile nu pot vedea asocierile sau conflictele altora.

O observație nouă poate crea o diferență în `/api/v2/manage/source-conflicts`. `accepted` creează o ciornă; `rejected` și `different_market` păstrează motivul. Nu se modifică etichete publicate, loturi vechi, recenzii sau alerte.

## Contribuția autorizată a producătorului

Proprietarul configurează o conexiune `producer_package` și un acord explicit pe articol. Scope-ul `sharing:manage` este distinct de `write` și de dreptul de a pregăti contribuții `sharing:write`. Articolul trebuie să aibă mandat GTIN verificat. Acordul fixează câmpurile, imaginile eligibile, licențele și versiunea; schimbarea cere o versiune nouă. Autentificarea web este reconfirmată pentru operațiile sensibile după 15 minute.

`POST .../publications/preview` arată contribuția fără scrieri. `POST .../publications` fixează pachetul în starea `package_ready`. Descărcarea autorizată include ZIP cu `products.csv`, `contribution.json` și instrucțiuni. Numai `code`, `product_name_ro`, `ingredients_text_ro` și `quantity` au mapare CSV automată în acest transport; celelalte câmpuri rămân în JSON, cu indicație de mapare manuală. Pachetul nu conține loturi, expirări sau rețete.

Pregătirea sau descărcarea nu confirmă importul în OFF. Scrierea remote automată este dezactivată până la validarea unui transport cu OFF, a mandatului organizației și a mecanismelor de concurență. Un câmp importat din OFF nu este retrimis ca aport original fără confirmarea verificării independente. Revocarea acordului, conexiunii ori mandatului blochează pachetele neexpediate; copiile deja distribuite sub licență deschisă pot rămâne disponibile.

## Proveniență, licențe și migrare

Datele OFF păstrează [ODbL 1.0](https://opendatacommons.org/licenses/odbl/1-0/) pentru bază și [DbCL 1.0](https://opendatacommons.org/licenses/dbcl/1-0/) pentru conținut. Fotografiile păstrează [CC BY-SA 3.0](https://creativecommons.org/licenses/by-sa/3.0/) și atribuirea contributorilor. Licențele sursei nu acordă acces la documente sau rețete private ale producătorilor.

`GET /api/v2/public/external-revisions/{id}` descarcă observația normalizată completă, cu proveniență și licențe. Imaginile sunt servite prin endpointul same-origin numai după validarea originii, MIME, dimensiunii și integrității. Nu insera direct URL-uri arbitrare în aplicație.

Exportul public NDJSON este pregătit în fundal și include numai subsetul extern eligibil. Nu include bindings, conflicte, organizații sau inventare personale. Suspendarea unei revizii invalidează exporturile pregătite anterior. Acasă exportă suplimentele separat în `home.transfer.v2` când acestea sunt disponibile; importul `home.transfer.v1` rămâne acceptat. O copie importată nu este prezentată ca reverificată.

Referințe oficiale: [API OFF](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/), [licențe](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/tutorials/license-be-on-the-legal-side/), [cache local](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/tutorials/creating-a-local-cache-of-open-food-facts-data/).
