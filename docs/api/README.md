# Documentația API

Portalul este disponibil la [openaliment.ro/api/docs](https://openaliment.ro/api/docs). Fiecare operație din contractul agregat și fiecare ghid au propria pagină. Contractul versiunii 0.8.0 auditate include 294 de operații, dintre care 36 pentru surse externe; aceste numere descriu codul și nu confirmă instalarea în producție. Navigarea grupează referința pe resurse și separă compatibilitatea v1. Căutarea folosește titlurile, metodele, adresele și identificatorii operațiilor. Disponibilitatea unei funcții se verifică în manifestul instanței.

## Pagini și formate

- Prezentare: `/api/docs`; Markdown: `/api/docs.md`.
- Ghid: `/api/docs/guides/{slug}`; aceeași adresă cu `.md` pentru Markdown.
- Operație: `/api/docs/reference/{operationId}`; aceeași adresă cu `.md` pentru Markdown.
- Negociere: `Accept: text/markdown` pe adresa HTML. `Vary: Accept` și antetul `Link` indică reprezentările disponibile.
- Index JSON: `/api/docs/index.json`.
- Index pentru AI: `/llms.txt`; corpus complet: `/llms-full.txt`, cu buget mai mic de descărcare.

Fiecare pagină are „Copiază pagina”, „Deschide în ChatGPT”, „Deschide în Claude” și „Vezi ca Markdown”. Copierea folosește Markdown-ul canonic al paginii curente, deja inclus în document, fără a depinde de o cerere suplimentară sau de cookie-uri. Copierea codului funcționează separat. Dacă browserul nu permite clipboard-ul, se afișează textul selectabil într-un dialog accesibil.

Legăturile către asistenți conțin numai adresa publică a paginii Markdown și o cerere de explicații. Nu includ contul, cheia, cookie-urile sau datele unui produs privat. Asistentul ales este un serviciu extern și nu primește automat acces la OpenAliment. Exemplul executabil citește numai produsul demonstrativ PBT, fără autentificare sau mutații.

## Întreținere

Sursa ghidurilor este [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md), iar introducerea este [pages/overview.md](pages/overview.md). Sursa operațiilor este [platform-openapi.json](../../contracts/platform-openapi.json), generat din registru și contractele active. Explicațiile și proiecțiile de răspuns sunt întreținute în `tools/build/api_reference.py`, cu metadatele declarative din `docs/api/reference/metadata.json`. Cele patru ghiduri aprofundate au surse în `docs/api/pages/`. Nu edita manual contractele generate.

Detaliile publice despre resolver, sincronizare, conectoare, transfer și compatibilitate sunt întreținute separat în `docs/api/sections/`, cu maparea explicită `DETAILS`. Rapoartele interne `GS1_IMPLEMENTATION.md`, `INTEGRARI_PARTENERI.md`, `ACASA_SINCRONIZARE.md`, `IMPORT_EXPORT.md` și `API.md` nu sunt incluse în portal. Planurile, rapoartele QA și instrucțiunile de dezvoltare aparțin documentației repository-ului; ghidurile publice descriu contractul și comportamentul disponibil. Păstrează avertizările reale despre demonstrații, date necunoscute și funcții indisponibile.

Rulează `python tools/build/artifacts.py` după modificarea surselor. Generatorul `tools/build/api_documentation.py` verifică maparea explicită a secțiunilor, creează paginile și exemplele și permite detectarea drift-ului prin `--check`. Când adaugi o secțiune în ghid, actualizează lista `GUIDES`; pentru o resursă nouă verifică gruparea în navigare. Schemele și permisiunile provin din contract, fără operații fictive.

Controllerul dedicat servește documentația fără date private sau SQL de domeniu. Rutele și payload-urile API existente nu se schimbă. Adresele vechi `/api/guide.md`, OpenAPI și SDK-urile se păstrează; ancorele vechi `#chei`, `#oauth`, `#acasa`, `#conectoare` trimit la ghidul corespunzător când JavaScript este activ.

Pentru validare există `tests/api-documentation.php` și `tests/test_api_documentation.py`, incluse în runner. Sunt verificate toate paginile HTML/Markdown, paritatea Copy Page, contextul legăturilor AI, exemplele Python, CORS, HEAD, metodele refuzate și indexurile. Interacțiunile clipboard, meniul, căutarea și afișarea pe telefon se verifică și în browser.

Pentru modulul OFF, [matricea acțiunilor](external-action-coverage.json) asociază formularele, listele și operațiile pentru clienți cu `operationId`. `tests/test_external_api_coverage.py` verifică matricea față de formulare, registrul runtime, permisiuni, paginare și paginile generate; testul rulează și în suita rapidă CI. Instalarea și configurarea hostingului sunt clasificate explicit ca operații de operator. Inventarul din 24 octombrie este un document istoric de proiectare, nu descrierea capabilităților release-ului curent.

Organizarea este inspirată de [referința API Snipe-IT](https://snipe-it.readme.io/reference/api-overview), cu interfață proprie OpenAliment și fără dependență ReadMe pe hosting.

## Referință aprofundată — 0.5.2

Fiecare operație are răspunsuri concrete și exemple sintetice validate, parametri explicați și reguli de recuperare din erori. Tabelele includ câmpuri imbricate, null, enum, limite și valori implicite. API v1 păstrează proiecțiile istorice cu șiruri JSON. Nu sunt folosite Record/Collection/MutationResult ca răspunsuri de succes generale.

`contracts/api-documentation.json` este un index mic. Conținutul este generat pe grupuri în `contracts/api-docs/`; runtime-ul citește numai grupul necesar. OpenAPI agregat este compactat sub limita de transport de 2 MiB. Colecția Postman este asamblată din grupuri la `/api/docs/postman.json`, iar manifestul local `contracts/api-postman-collection.json` indică fragmentele sale. Pentru import în Postman folosește descărcarea completă, nu manifestul de asamblare.

Exploratorul diferențiază exemplele statice de cererile reale. Pentru rutele publice GET și rutele Bearer eligibile, fetch folosește aceeași origine, fără cookie-uri sau redirect-uri, cu timeout și limită de răspuns. O scriere cere confirmare explicită. Tokenul se păstrează numai în DOM-ul paginii curente și se golește la ieșire; Copy Page și legăturile AI folosesc exclusiv Markdown-ul canonic. Sesiunile, consimțământul, fișierele și trimiterile publice nu sunt executate automat.

Exemplul `tools/examples/erp_workflow.py`, disponibil și la `/api/docs/examples/erp-workflow.py`, persistă operațiile fără secrete și verifică produs → lot fără rețetă → QR prin citire publică. Publicarea necesită opțiunea explicită `--publish`. Suportul de testare verifică fiecare operație curentă pe HTTP local și reluarea exemplului fără duplicate; numărul efectiv este calculat din contract în `tests/test_api_coverage.py`.

Testele suplimentare sunt `test_api_reference_quality.py` (exemple și contract), `test_api_reference_http.py` (execuție și răspunsuri reale locale), `api_contract_probe.py` (validare reutilizabilă) și `api-explorer.mjs` (confirmare, credențiale, origine, codificare, JSON și afișare). Ele sunt integrate în runner. Succesul local nu reprezintă certificare GS1/POS, lansare mobilă sau test de sarcină pentru hosting.
