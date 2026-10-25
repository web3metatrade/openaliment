# Contribuții la OpenAliment

Mulțumim pentru contribuții. Start with [the development guide](docs/development/README.md); it includes English instructions and an isolated local test workflow. The application UI stays Romanian. Code identifiers and contributor tooling use English.

## Pornire și verificări

1. Instalează PHP 8.4+ cu PDO MySQL, mbstring, fileinfo și OpenSSL, Node 24+ și Python 3.12+.
2. Rulează `npm ci --ignore-scripts` și instalează `requirements-dev.txt` într-un mediu Python virtual.
3. Rulează `python tools/qa/run.py --suite quick`. Nu cere DB și nu accesează producția.
4. Pentru suita completă locală, creează numai baza dispensabilă `openaliment_tests`, configurează variabilele din ghid și rulează `python tools/qa/run.py --suite local --reset-test-db`.

## Un pull request ușor de verificat

- Pornește din `dev` și trimite PR-ul către `dev`, inclusiv din fork. `main` primește promovări din `dev` sau corecții urgente dintr-un branch `hotfix/...` al repository-ului. După promovare, sincronizăm `main` în `dev`. Vezi [fluxul complet](docs/operations/COLLABORATION.md).
- Folosește squash pentru branchurile scurte, dar merge commit pentru `dev` → `main` și `main` → `dev`, ca să păstrăm istoricul comun. Aprobarea, merge-ul și publicarea pe hosting sunt acțiuni distincte.
- Descrie problema, comportamentul rezultat și verificările executate.
- Separă formatarea de schimbările funcționale. Folosește convențiile PSR-12 pentru PHP nou și module JS simple; nu adăuga dependențe runtime fără o nevoie concretă.
- Păstrează compatibilitatea URL-urilor persistente, a importurilor și a contractelor publice. O modificare incompatibilă cere migrare și explicație în PR.
- Nu rescrie migrări aplicate. Adaugă o migrare numerotată nouă pentru o schimbare reală de schemă.
- Dacă schimbi un generator, rulează `python tools/build/artifacts.py`, apoi `python tools/build/artifacts.py --check` și include artefactele actualizate.
- Nu modifica biblioteci incluse, standarde copiate sau originale OpenBread prin formatare automată.
- Nu include configurații locale, fotografii personale, cookie-uri, tokenuri, outbox, dumpuri DB sau capturi administrative.
- Nu folosi conturi sau servicii de producție în teste. SMTP și AI sunt simulate în mediul de test.

CI verifică sintaxa, namespace-urile, hashurile dependențelor/originalelor, contractele generate și suitele locale. Review-ul de stil rămâne necesar pentru fișierele istorice compacte; proiectul nu pretinde că toate acestea sunt deja formatate PSR-12.

## Modificări ale nucleului GS1

GS1 Digital Link nu este un format propriu al platformei. Pentru orice modificare a parserului, generatorului, resolverului, scanerului sau exportului, indică ediția și secțiunea normativă aplicabilă din [catalogul fixat](contracts/gs1/standards-catalog.json). Nu inventa AI-uri numerice, nu folosi namespace-ul GS1 pentru concepte OpenAliment și nu modifica valorile loturilor/seriilor pentru a ocoli infrastructura.

Păstrează diferența dintre un URI de cerere și asocierea unei legături în registru, dintre GTIN și dreptul de administrare și dintre identitate și atribute. Un serviciu anexă nu acordă autoritate asupra nucleului. Actualizarea unei ediții nu se face automat peste referințele vechi și cere revizuirea matricei și a probelor.

`tests/fixtures/gs1-compatibility.json` este folosit atât de `tests/gs1-compatibility.php`, cât și de `tests/gs1-compatibility.mjs`. Suita include separat HTTP, schemele oficiale, EPC, istoric, transport și browser. O contribuție care schimbă interpretarea datelor trebuie să actualizeze vectorii cu sursa normativă și să treacă suita completă. O scanare software reușită nu reprezintă verificare de tipar/POS.

## Licență și raportări

Contribuțiile la codul propriu se publică sub MIT, conform [LICENSE](LICENSE). Pentru materiale ale altor autori, păstrează sursa, licența și drepturile de redistribuire. [LICENSING.md](LICENSING.md) separă codul de documentația alimentară și de imaginile terțe.

Nu raporta vulnerabilități printr-un issue public cu date exploatabile. Urmează [SECURITY.md](SECURITY.md). Modificările din pull requests nu sunt publicate automat pe hostingul de producție.
