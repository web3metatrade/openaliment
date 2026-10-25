# OpenAliment

Platformă PHP MVC simplă pentru `openaliment.ro`. Prima etapă implementează fluxul prioritar produs–articol–etichetă–lot–QR, independent de rețetă, licență deschisă, siglă și disponibilitatea AI.

## Contribuții și dezvoltare

Codul propriu este publicat sub [MIT](LICENSE). [Licențele materialelor și bibliotecilor](LICENSING.md) rămân separate de licența software și de documentele alimentare publicate voluntar.

Pentru colaboratori: [CONTRIBUTING.md](CONTRIBUTING.md), [indexul documentației](docs/README.md), [harta codului](docs/architecture/README.md) și [raportarea vulnerabilităților](SECURITY.md).

Ramura implicită `dev` primește contribuțiile prin pull request și furnizează pachetele pentru staging. `main` păstrează codul acceptat pentru producție. Promovarea `dev` → `main` cere review și CI; niciun merge nu actualizează automat hostingul. Publicarea pe `dev.openaliment.ro` sau `openaliment.ro` rămâne explicită și controlată de proprietar.

[Organizarea GitHub și a mediilor](docs/operations/COLLABORATION.md) descrie pull request-urile, stagingul, publicarea controlată și limitele contului cPanel comun. Repository-ul distribuie codul și date sintetice de test; arhiva istorică OpenBread, configurațiile private și capturile administrative rămân separate.

**English:** See the [development guide](docs/development/README.md) for reproducible setup, isolated tests, generated contracts and cPanel packaging. The UI remains Romanian; hosting requires no Node, Python, Composer or shell.

După instalarea uneltelor de dezvoltare, verificarea rapidă este `python tools/qa/run.py --suite quick`. Suita locală completă cere explicit `--suite local --reset-test-db` și folosește numai `openaliment_tests`. Rapoartele noi sunt în directorul ignorat `tests/runtime/reports/`.

## Puncte de intrare

- [Schimbarea de direcție](OPENALIMENT_CHANGE_REQUEST_01.md) — primul livrabil creat.
- [Specificația activă](docs/OPENALIMENT_MASTER.md) și contractele din `contracts/`.
- [Planul complet de implementare](docs/OPENALIMENT_IMPLEMENTATION_PLAN_2026-10-24.md) — platformă, aplicație pentru consumatori, integrări, publicare și operare; [matricea de acceptare](docs/plans/OPENALIMENT_ACCEPTANCE_MATRIX_2026-10-24.md) și [backlogul structurat](docs/plans/openaliment-backlog-2026-10-24.json).
- [Instalare cPanel fără shell](docs/DEPLOY_CPANEL.md).
- [Ghid API, chei și OAuth](docs/api/DEVELOPER_GUIDE.md), [contract agregat](contracts/platform-openapi.json), [compatibilitate ERP v1](docs/API.md) și pagina aplicației `/api/docs`.
- [Edițiile oficiale GS1 și copiile private](docs/standards/GS1.md).
- [Nivelul GS1 implementat](docs/GS1_IMPLEMENTATION.md).
- [Import/export și compatibilitate OpenBread](docs/IMPORT_EXPORT.md).
- [Verificări și limite](docs/VERIFICATION.md).

Document root confirmat: `/home/apelero/openaliment.ro/public/`. Numai conținutul `public/` este accesibil direct prin web. Configurația reală se creează din `config/config.example.php` în `config/local.php`, în afara document root. Nu există parole sau conturi implicite în release.

## Implementat în etapa 1

Pagini în română, responsive, Bootstrap local, iconuri și QR SVG; conturi, organizații, roluri și autorizare pe tenant; articole și candidați GTIN cu verificarea separată a drepturilor; versiuni de etichetă aprobate; loturi fără rețetă, istoric, eliberare și alerte distincte; QR produs/lot; API cu scopes și idempotentă; transfer JSON și export complet NDJSON; import privat PBT; încărcare/documente selectate; rețete opționale cu licență explicită; recenzii, întrebări, moderare, propuneri, voturi pentru contacte verificate și worker AI care salvează drafturi.

Nu sunt necesare Composer, framework PHP, Node, root sau shell pe hosting. Bootstrap 5.3.8 și biblioteca QR Nayuki sunt livrate local cu notificările de licență. Python/Node/PHP portabil/MariaDB local sunt doar unelte de verificare și nu intră în release.

## Actualizare 02 — resolver și ofertă comercială

Resolver GS1 1.2.1 pentru profilul GTIN (01), calificatori, destinații versionate și aprobare, negociere, linkset și decompresie EPC. UI și API ERP pentru registru; migrare aditivă 002 din panoul administrativ. Landing comercial, SEO tehnic și contact pentru integrare. Matricea și dovezile sunt în `docs/GS1_IMPLEMENTATION.md`.

## Planul integral de finalizare — 24 octombrie 2026

Versiunea 0.5.0 adaugă API public limitat, resurse private pentru funcțiile existente, chei cu expirare/rotație/revocare, OAuth PKCE pentru aplicații terțe și acces Acasă delegat. Manifestul `/api/manifest.json` și OpenAPI descriu 206 operații cu `operationId` stabil. Documentația include 58 de exemple de cerere validate, clienți Python/JavaScript testați și un exemplu Android/Kotlin. [Raportul implementării API](docs/implementation/API_PLATFORM_2026-10-24.md) precizează probele, activarea migrării și limitele livrării.

Planul cuprinde 28 de pachete, 56 de cerințe și 132 de scenarii de acceptare: finalizarea documentației deschise și a evaluării pentru siglă, căutare/RAG și joburi AI, domenii ale producătorilor, portabilitate, plus aplicația consumatorului pentru inventar, scanare, alergeni, nutriție, bonuri și notificări. Include Android/iPhone/web, integrări reale ERP/magazin, probe POS, capacitate, recuperare și stabilizare. Fiecare pachet are responsabil, dependențe și criterii de închidere.

Execuția este în curs. Sunt publicate inventarul web/PWA Acasă, OCR local, proiecțiile alimentare publice, conectoarele cu mapări versionate, importul ERP în ciornă și bonurile digitale. Au fost adăugate identitatea personală prin email, familiile, fotografiile private și sincronizarea ambalajelor/istoricului cu idempotență și conflicte explicite. Preferințele de alergeni și cumpărăturile rămân locale. [Starea și dovezile execuției](docs/implementation/EXECUTION_2026-10-24.md), [sincronizarea Acasă](docs/ACASA_SINCRONIZARE.md), [conectoarele](docs/INTEGRARI_PARTENERI.md) și [SMTP cPanel](docs/SMTP_CPANEL.md) descriu limitele reale.

Aceste incrementări nu încheie întregul plan. Distribuția nativă, notificările de fundal, sursele externe, finalizarea B/C, AI/RAG, domeniile, capacitatea și stabilizarea au în continuare criterii deschise. Scenariul calendaristic de 40 de săptămâni este condiționat de echipă și accesurile externe. Simulările nu sunt probe de integrare cu un retailer real, de tipar sau POS.

Pachetul OpenBread rămâne neschimbat în `OpenBread_Codex_v1_2026-10-23/`. Originalele, R1, scenariile COST3 și istoricul nu au fost redenumite sau publicate automat. Datele de test sunt izolate și excluse din release.
