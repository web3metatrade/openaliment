# Auditul structurii OpenAliment pentru contribuții pe GitHub

Data: 24 octombrie 2026. Scop: pregătirea codului pentru colaborare publică, cu păstrarea funcționării pe hostingul cPanel existent.

OpenAliment are o bază potrivită pentru un MVC simplu. Separarea `public/`, `app/`, `config/`, `database/`, `contracts/` și `storage/` trebuie păstrată. Prioritatea este un proiect ușor de instalat, verificat și modificat de un dezvoltator nou: configurații de test reproductibile, reguli de contribuție, licențe clare, cod lizibil și publicări controlate. Mutarea tuturor claselor sau adoptarea unui framework nu sunt necesare pentru această pregătire.

Acest livrabil conține analiza și ordinea schimbărilor propuse. Nu aplică restructurări, migrări SQL, publicări pe hosting sau pe GitHub. Verificarea este statică; rapoartele testelor existente sunt dovezi istorice ale versiunilor descrise în ele, nu probe executate din nou pentru acest audit.

## Structura existentă

Inventarul include fișierele locale, inclusiv directoarele ignorate. Dimensiunile sunt aproximative, în MiB, la momentul analizei.

| Director | Fișiere | MiB | Rol și observație |
| --- | ---: | ---: | --- |
| `app/` | 80 | 0,65 | MVC, domeniu, infrastructură și PHPMailer inclus local. |
| `public/` | 42 | 7,23 | Front controller, verificare inițială a hostingului și resurse servite prin web. |
| `config/` | 4 | sub 0,01 | Două exemple și două configurații locale private. Valorile private nu au fost necesare auditului. |
| `contracts/` | 24 | 0,29 | OpenAPI, scheme JSON, profil pâine și resurse GS1 cu proveniență. |
| `database/` | 4 | 0,02 | Migrările SQL numerotate 001–004. |
| `docs/` | 86 | 1,83 | Specificații active, istoric, planuri, rapoarte și capturi. |
| `tools/` | 17 | 0,09 | Generatoare, împachetare, worker, agent partener și mentenanță HTTPS; include și cache Python local. |
| `tests/` | 605 | 583,03 | Teste și un volum mare de fișiere generate în `runtime/`. |
| `storage/` | 1 | sub 0,01 | În această copie, protecția directorului; datele reale ale hostingului sunt separate. |
| `OpenBread_Codex_v1_2026-10-23/` | 80 | 49,66 | Arhiva istorică și sursele pilotului PBT. |
| `output/` | 2 | 4,28 | Imaginile sursă pentru ilustrarea ambalajului. |
| `release/` | 14 | 139,55 | Pachete generate, excluse deja din Git. |
| `.tools/` | 1.445 | 716,74 | Runtime-uri locale, baze de test, copii și configurații private; exclus deja din Git. |

Directorul analizat nu este încă un repository Git, nici parte a unuia părinte, conform `git rev-parse --show-toplevel`. Nu se presupune existența unui istoric Git curat sau a unui repository GitHub configurat.

### Elemente care merită păstrate

- Document root este `public/`; configurațiile, originalele și fișierele private nu trebuie mutate sub acesta. `.htaccess` de la rădăcina proiectului refuză accesul web.
- `app/bootstrap.php` mapează namespace-ul `App\` către `app/`. Organizarea claselor poate continua în acest director.
- Configurațiile reale `config/local.php` și `config/*.local.php`, `.tools/`, `tests/runtime/`, `storage/` și `release/` sunt deja excluse prin `.gitignore`, cu păstrarea protecției `storage/.htaccess`.
- Migrațiile sunt separate de aplicație, iar contractele publice sunt fișiere identificabile.
- JavaScript Acasă este deja împărțit în module pentru domeniu, stocare, scanare, OCR, transfer, bonuri și sincronizare.
- Dependențele au versiuni și hashuri în `docs/DEPENDENCIES.json`; hostingul nu necesită Composer, Node, Python, root sau shell.
- Testele bazei de date verifică explicit baza izolată `openaliment_tests`. Această protecție trebuie păstrată în orice runner nou.

## Probleme și priorități

P0 înseamnă necesar înaintea primei publicări publice; P1 înseamnă necesar pentru contribuții reproductibile; P2 înseamnă reorganizare graduală după stabilirea verificărilor.

| Prioritate | Constatare în proiect | Acțiune |
| --- | --- | --- |
| P0 | Lipsesc o licență pentru codul propriu și documentele principale pentru contribuții. | Stabilește licența software și adaugă `LICENSE`, `CONTRIBUTING.md`, `SECURITY.md` și regulile de colaborare. |
| P0 | `.gitignore` nu acoperă explicit `__pycache__/`, `*.pyc`, `output/` sau datele editorilor; capturile din `docs/previews/` sunt candidate la publicare. | Definește politica de fișiere publice și verifică lista efectivă înainte de commit. Revizuiește capturile și rapoartele pentru date private. |
| P0 | Arhiva OpenBread conține documente, extrase și materiale recuperate; licența aplicației nu clarifică drepturile asupra lor. | Separă inventarul drepturilor pentru cod, documentație alimentară, imagini, standarde și biblioteci. Păstrează originalele și avertizările de proveniență. |
| P1 | Mai multe teste cer `.tools/test-config.php`; unele acceptă `OA_TEST_CONFIG`, altele au calea fixă. | Adaugă bootstrap comun pentru teste și un exemplu public de configurare, fără valori de producție. |
| P1 | Testele folosesc pachete Python din `.tools/python` sau `.tools/test-python`, `OA_NODE_MODULES`, browserul Edge și portul local 8088. | Declară uneltele de dezvoltare cu versiuni fixate și parametrizează mediul local. |
| P1 | Testele HTTP depind de fișiere generate de alte teste; fotografii sintetice necesare se află în `tests/runtime/`. | Separă fixtures publice, generatoare și rezultate temporare; publică ordinea de execuție într-un runner. |
| P1 | Nu există `.github/`, CI sau un punct de intrare unic pentru verificări. | Adaugă verificări automate pentru pull requests, cu DB izolată și fără acces la producție. |
| P1 | Codul propriu este frecvent comprimat pe linii foarte lungi. | Formatează pe grupuri mici; separă formatarea de schimbările de comportament. |
| P1 | Modelul logic generat este rămas în urmă față de migrări. | Regenerare verificată și control CI pentru sincronizarea surselor cu artefactele. |
| P1 | Împachetarea are numele versiunii și lista unor unelte scrise direct în script. | Manifest de release explicit, variante de pachet și verificări pentru excluderea secretelor. |
| P2 | Rutarea, antetele și tratarea erorilor sunt concentrate în `public/index.php`; controllerele mai fac și dispatch intern. | Extrage gradual componente interne, cu aceeași ordine și aceleași rute externe. |
| P2 | `app/Domain/` combină validări, SQL, email, fișiere și operațiuni de business. | Definește responsabilități și extrage doar cazurile care simplifică modificări reale; evită un strat nou pentru fiecare clasă. |
| P2 | Documentația activă și rapoartele succesive sunt accesibile prin multe fișiere, fără index comun. | Adaugă un index și distinge specificațiile active de dovezile și deciziile istorice. |

### Licențe și publicare sigură

O licență explicită este necesară pentru ca dezvoltatorii să înțeleagă drepturile de reutilizare și contribuție. Alegerea licenței codului aparține proprietarului; acest audit nu o atribuie automat. Explicația GitHub despre publicarea software-ului și rolul licenței este în [Licensing a repository](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/licensing-a-repository).

Înainte de publicare trebuie să existe un registru cu: componentă, autor/sursă, licență, drept de redistribuire, notificări obligatorii și locația fișierelor. Acesta trebuie să acopere PHPMailer, Bootstrap, QR, ZXing, Tesseract și datele OCR, resursele GS1/IANA, imaginile și materialele PBT. Se păstrează notificările existente, inclusiv `contracts/gs1/NOTICE.json`.

Licența codului platformei, licența unei rețete, drepturile asupra documentelor încărcate și acordarea unei sigle sunt lucruri separate. Publicarea repository-ului nu trebuie să publice automat documentele private ale unui producător sau să acorde o licență deschisă asupra lor.

`.gitignore` este o protecție pentru selecția viitoare a fișierelor, nu o verificare a conținutului și nici o curățare a istoricului. Se verifică separat fișierele pregătite pentru commit, arhivele, capturile și rapoartele. Dacă un secret a fost deja publicat, acesta se revocă sau rotește înaintea operațiunilor de curățare, potrivit [ghidului GitHub pentru date sensibile](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository). Auditul de față nu a constatat publicarea unui secret.

## Structura recomandată

Ținta este un singur repository pentru aplicația actuală și un pachet cPanel construit separat. Directoarele existente rămân în loc în prima etapă. Directoarele noi din arbore se adaugă când primesc o responsabilitate concretă; nu sunt necesare directoare goale pentru funcții încă neimplementate.

```text
openaliment/
  .github/                 # CI și șabloane pentru issues și pull requests
  .editorconfig
  .gitattributes
  .gitignore
  LICENSE
  README.md
  CONTRIBUTING.md
  SECURITY.md
  CODE_OF_CONDUCT.md
  CHANGELOG.md
  composer.json            # metadate, autoload și unelte de dezvoltare
  composer.lock            # după introducerea efectivă a Composer
  package.json             # numai uneltele JS necesare dezvoltării
  package-lock.json
  requirements-dev.txt     # uneltele Python ale testelor și generatoarelor

  app/
    bootstrap.php
    Controllers/
    Core/
    Domain/
    Http/                  # extragere ulterioară a dispatch-ului și antetelor
    Support/               # helpers comuni extrași din bootstrap
    Views/
      components/          # fragmente partajate, introduse gradual
    Vendor/                # PHPMailer existent, păstrat inițial

  config/
    config.example.php
    mail.local.example.php
    local.php              # privat, ignorat
    mail.local.php         # privat, ignorat

  public/
    index.php
    .htaccess
    hosting-check.php      # păstrează protecțiile și contractul instalării
    assets/                # URL-urile existente rămân valabile

  contracts/               # căile existente ale schemelor se păstrează
  database/migrations/     # 001–004 rămân nemodificate la restructurare
  docs/
    README.md              # indexul documentației
    architecture/          # explicații și decizii tehnice noi
    development/           # pornire locală, teste, convenții
    operations/            # ghiduri noi de operare
    history/               # materiale explicit istorice
    verification/          # dovezi selectate pentru versiuni

  tests/
    bootstrap.php
    config.example.php
    fixtures/              # date sintetice redistribuibile
    generators/
    unit/                  # mutări graduale ale testelor existente
    integration/
    http/
    browser/
    runtime/               # rezultate temporare, ignorate

  tools/
    dev/
    qa/
    build/
    deploy/                # protocolul actual rămâne compatibil
    worker.php             # calea cron existentă se păstrează

  storage/                 # privat și inscriptibil; numai protecții în Git
  OpenBread_Codex_v1_2026-10-23/  # locație istorică păstrată inițial
  release/                 # pachete generate, ignorate
  .tools/                  # instrumente și stare locală, ignorate
```

Nu există o cerință PSR de a redenumi `app/` în `src/`. [PSR-4](https://www.php-fig.org/psr/psr-4/) definește maparea namespace-urilor la directoare și respectarea literelor mari/mici. Maparea actuală `App\` → `app/` poate fi păstrată și declarată explicit. Compatibilitatea se verifică și pe Linux, unde diferențele de majuscule din căi pot produce erori ascunse pe Windows.

### Organizarea codului PHP

Se păstrează MVC simplu și se documentează responsabilitățile:

- Controllerele traduc cereri și răspunsuri și aplică autorizarea necesară.
- Clasele de domeniu păstrează regulile pentru identificatori, versiuni, loturi, mapări și inventar.
- `Core/` conține facilități partajate, precum DB, configurare, HTTP și sesiuni.
- Operațiunile complexe de import, publicare sau sincronizare pot primi clase de serviciu dedicate atunci când extragerea reduce amestecul responsabilităților. Nu este necesară o interfață sau un repository pentru fiecare tabel.
- View-urile formatează date deja autorizate; fragmentele comune pot fi extrase în `Views/components/`, cu păstrarea numelor câmpurilor și a acțiunilor formularelor.

Cele 27 de clase din `app/Domain/` pot rămâne inițial în locațiile actuale. Organizarea lor logică se explică într-o hartă: catalog, resolver, documentație, comunitate, integrări și Acasă. Mutarea în subnamespace-uri se face doar pe module, dacă dezvoltarea o justifică. Clasele vechi pot delega temporar spre implementarea nouă, fără schimbarea contractelor externe.

### Lizibilitate și convenții

Exemple concrete sunt `app/Domain/Portability.php`, cu linii de peste 1.000 de caractere, `app/Views/consumer-home.php`, cu linii de aproape 3.000 de caractere, și `public/assets/home/home.css`, cu trei linii. Această comprimare îngreunează review-ul și rezolvarea conflictelor.

Pentru PHP, se adoptă o convenție verificabilă, de exemplu [PSR-12](https://www.php-fig.org/psr/psr-12/): indentare consecventă, instrucțiuni lizibile, LF și reguli comune pentru clase și metode. Pentru JavaScript, CSS și șabloane se fixează o convenție în instrumentele de dezvoltare. Fișierele proprii trebuie să rămână lizibile; comprimarea pentru transfer se poate face în pachetul public și prin compresia HTTP.

Formatarea nu se combină cu redenumiri de clase, schimbări SQL sau modificări funcționale. Introducerea `strict_types` în fișiere care nu îl au se tratează separat de formatare, deoarece poate modifica comportamentul. Codul terț, artefactele generate și originalele istorice se exclud din formatarea automată.

`.editorconfig` și `.gitattributes` trebuie să normalizeze numai sursele proprii. Arhiva OpenBread, copiile terțe cu hashuri și fișierele binare trebuie păstrate byte cu byte. O regulă globală de conversie a liniilor poate invalida `HASHES.sha256` sau hashurile dependențelor. Se stabilesc excepții explicite pentru arhivă, `app/Vendor/`, `public/assets/vendor/`, `public/assets/ocr/` și copiile de standarde care au hashuri de proveniență.

## Dezvoltare reproductibilă

### Configurare și unelte

Un dezvoltator trebuie să poată porni dintr-un clone curat fără să primească `.tools/` sau fișiere de pe calculatorul actual. Ghidul public trebuie să descrie PHP 8.4+, extensiile necesare, MariaDB/MySQL, document root, permisiunile de stocare, inițializarea și comenzile de test.

Se păstrează fișierele private PHP pentru cPanel; nu este necesară impunerea unui `.env` sau a unui pachet nou pentru a citi configurația. Exemplele trebuie să explice clar destinațiile `config/local.php` și `config/mail.local.php` și să nu includă contacte sau credențiale reale. Configurația locală trebuie să folosească URL-ul local, email simulat și AI dezactivat.

Un `composer.json` poate declara `App\` → `app/`, cerințele PHP și uneltele de dezvoltare. Composer rulează pe calculatorul dezvoltatorului sau în CI; pachetul cPanel rămâne pregătit pentru încărcare fără shell. Dacă se introduce instalarea dependențelor prin Composer, se fixează versiunile în `composer.lock` și se folosește `install` pentru reproducerea lor, conform [documentației Composer](https://getcomposer.org/doc/01-basic-usage.md). PHPMailer nu trebuie gestionat simultan în două copii active; migrarea sa din `app/Vendor/` poate fi un pas separat, cu adaptarea `Mailer.php` și a împachetării.

La fel, uneltele Node și Python se declară cu versiuni fixate. Exemple existente: `sharp`, Playwright, `jsonschema`, Pillow și decodorul QR independent. Acestea sunt unelte ale dezvoltării, nu dependențe cerute hostingului. Containerizarea poate fi oferită opțional; pornirea locală simplă trebuie să rămână documentată.

### Teste și fixtures

`tests/bootstrap.php` trebuie să ofere un singur mod de încărcare a configurației de test. `OA_TEST_CONFIG` se păstrează ca override, iar un exemplu public înlocuiește dependența implicită de un fișier privat nedistribuit. Verificarea DSN-ului și verificarea `SELECT DATABASE()` rămân obligatorii înainte de resetarea DB.

Fotografiile sintetice pentru OCR și alte exemple redistribuibile se pun în `tests/fixtures/` sau se generează printr-o unealtă publică. Cookie-urile, tokenurile, emailurile simulate, exporturile și rezultatele rămân în `tests/runtime/`, ignorat. Fixtures nu se obțin prin copierea datelor de producție.

Runnerul trebuie să cunoască dependențele dintre suite: inițializare DB, fixtures de bază, resolver, consumatori, integrări, Acasă, apoi HTTP și browser. Testele care resetează aceeași bază nu rulează în paralel. Mutarea testelor în subdirectoare necesită actualizarea căilor `__DIR__`, `Path(__file__)`, importurilor JS și căilor fixtures.

Se propun comenzi comune pentru instalare locală, verificare rapidă, suite DB, HTTP, browser și construire release. Aceste comenzi se documentează după implementarea runnerului; ele nu există încă sub o interfață unică.

### CI și colaborare pe GitHub

CI pentru pull requests trebuie să ruleze: sintaxă PHP, stil pentru codul schimbat, teste de domeniu, integrare într-o DB izolată, HTTP, validare JSON/OpenAPI și controlul artefactelor generate. Se adaugă o execuție Linux pentru căi și permisiuni și se testează compatibilitatea cu versiunea MariaDB de pe hosting. O etichetă generală de suport MySQL nu înlocuiește o probă pe acel motor.

Workflow-urile pull requests nu primesc cheia HTTPS, configurații reale, acces la SMTP sau date de producție. Tokenul GitHub are permisiuni minime, acțiunile terțe se fixează la commituri verificate, iar codul din fork-uri nu se execută cu secrete printr-un workflow privilegiat. Aceste măsuri urmează [ghidul de securitate GitHub Actions](https://docs.github.com/en/actions/reference/security/secure-use).

`CONTRIBUTING.md` trebuie să explice pornirea, stilul, testele, schimbările DB/API și formatul unui PR. `SECURITY.md` oferă o cale privată de raportare. Se adaugă șabloane de bug și PR, reguli de conduită și, când sunt desemnați responsabili reali, `CODEOWNERS`. GitHub descrie aceste fișiere și locațiile lor în [documentația pentru contribuții](https://docs.github.com/en/communities/setting-up-your-project-for-healthy-contributions/creating-a-default-community-health-file). README oferă o orientare scurtă în română și un punct de intrare în engleză pentru colaboratori internaționali; interfața aplicației rămâne în română.

## Contracte și documentație

`contracts/data_model.json` enumeră 27 de tabele și migrările 001–002. Fișierele SQL 001–004 definesc 43 de tabele; cele 16 tabele suplimentare sunt ale integrărilor, bonurilor și Acasă. `tools/build-contracts.py` parcurge deja fișierele de migrare, dar artefactul salvat nu reflectă ultimele două migrări. Aceasta este o nealiniere a documentației generate; nu demonstrează lipsa tabelelor din producție.

Este necesar un inventar al generatoarelor și al fișierelor pe care le produc. Generarea trebuie să aibă un mod de verificare în director temporar sau prin comparare, ca CI să semnaleze deriva. Generatorul modelului trebuie să indice setul complet de migrări și să nu prezinte o stare de instalare pe producție drept proprietate universală a modelului. Câmpul actual `database_applied_to_production` și datele de execuție aparțin rapoartelor de publicare.

Se păstrează căile publice `/api/openapi.json`, `/api/consumer-openapi.json`, `/api/integration-openapi.json`, `/api/home-openapi.json` și `/api/schemas/...`. La fel, se păstrează identificatorii schemelor și importurile OpenBread deja recunoscute. Reorganizarea fișierelor de documentație nu justifică modificarea versiunii unui contract dacă structura sa nu se schimbă.

`docs/README.md` trebuie să indice specificația activă, arhitectura, dezvoltarea, operarea, API-urile și starea planului. Documentele istorice păstrează data și contextul. Mutările se fac cu actualizarea legăturilor și a trimiterilor din README, scripturi și rapoarte. Rapoartele selectate pentru un release trebuie să aibă versiunea codului, mediul și data; rezultatele curente din CI se păstrează ca artefacte, fără a rescrie la fiecare rulare dovezile unui release anterior.

## Arhiva OpenBread și pachetele de distribuție

Arhiva nu trebuie redenumită sau mutată în prima etapă. `config/config.example.php` folosește cheia `handover_path`, iar `app/Domain/Portability.php` citește `HASHES.sha256`, `04_import/` și documentele din `01_originale/`, `02_extrase_text/`, `03_bt01_recuperat/`. O mutare fizică fără această adaptare ar strica importul pilotului.

Pentru viitor sunt utile două distribuții:

| Distribuție | Conținut |
| --- | --- |
| Aplicație cPanel | Runtime, resursele necesare, contracte, migrări, configurații exemplu, unelte operaționale necesare și notificări de licență. |
| Date pilot PBT | Arhiva originală verificată, proveniență, hashuri și importuri, cu drepturi de redistribuire clarificate. |

Importul PBT trebuie să poată indica locația pachetului opțional și să ofere o eroare controlată dacă acesta lipsește. Originalele, R1, scenariile de cost și materialele deteriorate/recuperate își păstrează identitatea și stadiul. Până la verificarea variantei opționale, distribuția completă existentă rămâne disponibilă.

`tools/package-release.py` trebuie să primească o versiune și un manifest explicit, să diferențieze fișierele necesare runtime-ului de documentație și unelte și să verifice conținutul arhivei. Cerințele sunt: fără configurații locale, DB, outbox, cookie-uri, chei sau cache-uri; toate fișierele runtime prezente; hashuri verificate după împachetare; instalare probată din ZIP într-un mediu gol. `.gitignore` singur nu controlează ce intră într-un ZIP.

Activele binare publice și bibliotecile necesare pot rămâne în Git pentru început, având volum redus față de runtime-urile locale. Git LFS nu este obligatoriu pentru această structură; s-ar evalua dacă istoricul imaginilor și al arhivei crește semnificativ. Fișierele sursă din `output/` se inventariază separat: materialele necesare se publică într-un loc documentat numai cu drepturi clare, iar cele de lucru se ignoră. Nu se șterg imagini publice doar pentru că par variante vechi.

## Compatibilitatea care trebuie păstrată

| Componentă | Dependență actuală | Protecție la restructurare |
| --- | --- | --- |
| Autoload | `App\` → `app/` și includerea `app/bootstrap.php`. | Păstrează maparea; orice mutare cere actualizarea namespace-urilor și o probă Linux. |
| Hosting | `/home/apelero/openaliment.ro/public/`, reguli Apache și handler cPanel `alt-php84`. | Păstrează document root și handlerul; configurarea unui alt mediu se face separat. |
| Domenii | `.htaccess` și instrumentele de mentenanță au reguli pentru `openaliment.ro`. | Pentru fork-uri/staging se documentează configurarea explicită a domeniului. Redirecționarea HTTP actuală către domeniul de producție nu trebuie copiată neadaptată. |
| Mentenanță HTTPS | Allowlist pentru `app/*.php` recursiv, resurse, contracte și migrări; limite 32 fișiere, 2 MiB/fișier, 8 MiB/lot. | Noile fișiere runtime rămân în căile permise. Configurațiile private și alte directoare nu se fac accesibile generic pentru a facilita o mutare. |
| Worker | `tools/worker.php` poate fi referit în cron. | Păstrează un wrapper compatibil dacă implementarea sa se mută. |
| Resolver și API | Rute, negocieri, linkseturi, sintaxă GS1 și URI-uri persistente. | Teste de contract și regresie; fără schimbarea URL-urilor ca efect al mutării codului. |
| Acasă și PWA | Importuri `?v=5`, listă `ASSETS`, cache-uri și scope `/acasa/`. | Publicare coordonată și compatibilitate cu clienții vechi. Nu se șterge IndexedDB și nu se schimbă scope-ul pentru reorganizare. |
| Configurații | `config/local.php`, `config/mail.local.php`, `storage`, `handover_path`. | Nicio suprascriere cu exemple; nicio mutare a datelor private în zona publică. |
| Schema DB | Migrări aplicate și istoricul versiunilor. | Nu se rescriu 001–004 pentru o reorganizare; schimbările reale primesc migrări noi. |
| Arhivă și biblioteci | Manifestele SHA-256 verifică octeții originali. | Excludere de la normalizarea liniilor, formatare și regenerare arbitrară. |

O extragere a rutării trebuie să păstreze și ordinea potrivirilor. De exemplu, `/api/v2/home/receipts/preview` este verificat înaintea prefixului general `/api/v2/home/`, iar resolverul înaintea rutelor aplicației. Se păstrează și răspunsurile HEAD/OPTIONS, CORS public separat de API-ul privat, cookie-urile și antetele de cache și securitate.

## Ordinea de implementare

| Etapă | Fișiere și acțiuni | Condiție de încheiere |
| --- | --- | --- |
| 1 Pregătirea publicării | Inventar de fișiere publicabile; extinderea `.gitignore`; revizuirea rapoartelor/capturilor; clarificarea licențelor; documentele pentru contribuții. | Lista primului commit este verificată, fără stare privată; drepturile fișierelor publicate sunt explicite. |
| 2 Reproducerea mediului | `tests/bootstrap.php`, configurații exemplu de test, manifeste pentru unelte, fixtures și ghid de pornire. | Un dezvoltator pornește și testează din clone curat fără `.tools/` primit și fără acces la producție. |
| 3 Verificări automate | Runner comun și `.github/workflows/`; mediu DB izolat; teste de contract și artefacte. | PR-urile rulează verificările relevante fără secrete; ordinea suitelor este deterministă. |
| 4 Stil și lizibilitate | `.editorconfig`, `.gitattributes`, reguli de stil; formatare graduală a codului propriu. | Diff-uri numai de stil, teste trecute și hashurile arhivei/bibliotecilor păstrate. |
| 5 Documentație și contracte | `docs/README.md`, index de generatoare, modelul logic actualizat, separarea rapoartelor istorice. | Legături valide și generatoare fără diferențe neașteptate; cele 43 de tabele sunt reprezentate corect. |
| 6 Extrageri interne mici | Helpers, componente de view și dispatch intern, apoi servicii unde sunt utile. | Aceleași rute, payloaduri, permisiuni, SQL și acțiuni ale formularelor. Nicio schimbare de date. |
| 7 Distribuție | Manifest și versiune de release, pachet cPanel și pachet pilot opțional verificat. | ZIP-ul se instalează din mediu gol; actualizarea păstrează configurațiile și datele; revenirea este probată. |
| 8 Deschiderea contribuțiilor | Repository inițial privat pentru verificare, apoi publicare și reguli de review. | README, licență, CI și instrucțiunile de contribuție sunt utilizabile; nicio publicare automată în producție dintr-un PR extern. |

Etapele 1–5 pregătesc colaborarea fără schimbarea arhitecturii funcționale. Etapele 6–7 se livrează prin PR-uri mici, fiecare cu propriul impact și propriile probe. Schimbările de structură nu se amestecă cu funcții noi ale planului de produs.

## Probe înainte de fiecare publicare a unei restructurări

1. Pornire din clone curat, verificare sintactică și teste pe Linux pentru căi, autoload și permisiuni.
2. Fluxul non-pâine fără rețetă și fără siglă, cu etichetă aprobată, lot și QR corect.
3. Resolver, API, caractere de lot, negocieri, CORS și HEAD/OPTIONS; URL-urile persistente rămân identice.
4. Izolarea organizațiilor, conturilor personale și familiilor; fotografiile și rețetele private nu apar public.
5. Istoricul etichetelor și loturilor, alerte curente și funcționare cu AI indisponibil.
6. Inventar Acasă existent, deschidere offline, actualizare PWA, export/import și conflicte de sincronizare; nicio resetare de date locale.
7. Importuri vechi OpenBread și PBT cu hashuri, R1 și stări experimentale păstrate.
8. Conținutul ZIP-ului, configurațiile excluse, diferențele față de versiunea activă și procedura de revenire.

Pentru refactorizările relevante se rerulează suitele afectate și regresiile comune, inclusiv setul de 15 suite înscris în `docs/verification/home-increment-regression-report.json`. Cele 574 de verificări consemnate acolo sunt punctul de plecare istoric; numărul singur nu înlocuiește verificarea comportamentului pe versiunea nouă. Probele fizice QR/POS și livrarea reală a emailului rămân distincte de organizarea fișierelor.

La activare se păstrează întâi compatibilitatea dintre versiuni: fișiere noi înaintea apelurilor noi, migrări aditive când sunt necesare și vechile puncte de intrare disponibile pe perioada tranziției. Protocolul HTTPS înlocuiește atomic fișiere individuale, nu întregul release simultan. Revenirea codului nu trebuie să elimine migrări aplicate sau date create între timp.

## Criteriul de finalizare a reorganizării

Reorganizarea este încheiată când un colaborator poate instala, înțelege, testa și propune modificări folosind numai repository-ul și exemplele publice, iar același release poate fi construit și încărcat pe cPanel fără shell. Rutele, schema publică, datele private, istoricul, importurile și inventarele existente trebuie să rămână compatibile. Acest rezultat se obține prin reproducere și verificări, fără o rescriere generală a aplicației.
