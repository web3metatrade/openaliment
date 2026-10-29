# Cursuri, examene și calificări

Cursul este al persoanei, independent de firma la care lucrează. Lecțiile publicate pot fi citite fără cont. Contul personal salvează progresul; nu creează o organizație fictivă.

## Ediții și înscriere

Catalogul public este `GET /api/v2/public/program/courses`. Fiecare curs indică `release_id`, ediția imuabilă a conținutului. O înscriere fixează ediția și specializarea: `erp-integration`, `printing-integration`, `platform-operations` sau `product-data`.

```json
{"release_id":"11111111111111111111111111111111","specialization":"erp-integration","policy_accepted":true}
```

Înlocuiește ID-ul ilustrativ cu `release_id` din catalog. Trimite corpul la `POST /api/v2/program/me/enrollments`, cu permisiunea `learning:write` și o cheie de idempotentă. O ediție retrasă nu acceptă înscrieri noi; înscrierile existente își păstrează istoricul.

La citirea înscrierii primești lecțiile aplicabile și progresul propriu. Exercițiul formativ este public, inclusiv explicația; nu este cheia examenului privat. `/lessons/{lesson_id}/practice` primește indicele variantei și `expected_revision`. Prima salvare folosește revizia 0. Finalizarea lecției cere rezolvarea exercițiului.

## Examenul teoretic

Începutul, răspunsurile și predarea cer sesiunea personală în browser. OAuth poate citi progresul și rezultatele aprobate pentru aplicație, dar nu permite unui ERP sau unui agent să susțină examenul în numele persoanei.

Examenul are 30 de întrebări, cu extragere aleatoare dintr-o ediție privată și ordine aleatoare a variantelor. Distribuția este: identitate 6, URI 6, resolver 6, date 4, API 4 și operare 4. Ponderile sunt 20%, 20%, 20%, 15%, 15% și 10%. Există o întrebare critică în fiecare domeniu.

Promovarea cere toate cele trei condiții: minimum 85% ponderat, minimum 70% în fiecare domeniu și toate întrebările critice corecte. Calculul folosește valori exacte înainte de rotunjirea afișării. Pentru răspunsuri multiple sau asocieri este necesar setul complet, fără selecții în plus și fără punctaj parțial.

Termenul standard este de 45 de minute pe server. `due_at` și `server_time` sunt UTC în format SQL. Reîncărcarea paginii sau schimbarea ceasului dispozitivului nu prelungește termenul. O adaptare de durată se stabilește înaintea primei tentative, fără colectarea diagnosticului în justificare.

Reîncercarea este permisă după 24 de ore, cu maximum trei tentative în șapte zile. La întrerupere, `GET /api/v2/program/me/attempts/{id}` recuperează doar întrebările servite și răspunsurile deja salvate. Nu include identificatorii băncii, indicatorii critici, cheia de corectare sau explicațiile private.

```json
{"expected_revision":0,"answer":"a"}
```

Acesta este formatul unui răspuns cu o singură variantă, trimis prin `PUT /api/v2/program/me/attempts/{id}/answers/{item_id}`. Pentru variante multiple folosește un array de chei; pentru asociere sau componente URI, un obiect cu chei și valori exacte. Folosește cheile servite, nu poziția vizuală a opțiunii. `item_id` aparține tentativei proprii.

La `/submit`, răspunsurile devin imuabile. După expirare se evaluează numai ce a fost salvat înainte de termen. Workerul închide în tranșe tentativele expirate; niciun răspuns tardiv nu este acceptat în așteptarea cronului.

## Probe practice

Fiecare înscriere cere P01 și P02, plus proba specializării: P03 ERP, P04 tipărire, P05 operare sau P06 date de produs. Instrucțiunile și șabloanele sunt afișate în pagina probei.

Laboratorul este virtual: nu creează produse reale, nu trimite SQL sau comenzi către echipamente și nu interoghează URL-uri introduse de candidat. Datele și explicația se salvează ca document JSON limitat la 100 KB. Verificările automate oferă feedback, apoi proba este predată unui evaluator independent.

Rubrica are patru criterii de câte 25 de puncte: identitate, istoric, securitate și explicație. Sunt necesare minimum 85 de puncte și toate verificările obligatorii. P04 cere și dovada fizică revizuită; un ACK, o imagine generată sau scanarea pe ecran nu reprezintă validare POS.

O singură probă validată sau teoria promovată nu emite calificare. Calificarea apare după îndeplinirea tuturor condițiilor specializării. Proba teoretică valabilă pentru aceeași ediție poate susține mai multe specializări; probele specifice rămân separate.

## Rezultate, contestații și verificare publică

Rezultatele sunt private. Firma nu primește răspunsurile sau punctajele persoanei prin simpla afiliere. Exportul personal include rezultatele proprii, fără banca privată. Importul unui export nu creează calificări, promovări sau mandate. `POST /api/v2/program/me/import-preview` verifică pachetul; `POST /api/v2/program/me/imports` poate relua doar pozițiile de lectură pentru înscrieri existente compatibile. Identitatea conținutului se verifică prin release_id local sau course_ref cu slug, versiune și SHA-256, fără importarea finalizărilor.

O contestație păstrează motivul în istoric. Reevaluarea creează o versiune nouă, fără rescrierea rezultatului anterior sau a ediției băncii. O întrebare anulată se consideră corectă pentru tentativa reevaluată, cu motiv explicit. Calificările susținute de un rezultat înlocuit se reevaluează.

Verificarea publică folosește ID-ul public al calificării și arată specializarea, emitentul, starea și valabilitatea. Numele persoanei este ascuns implicit și se publică doar prin alegere explicită. Calificarea are valabilitate de 12 luni; reînnoirea cere o evaluare valabilă, nu simpla importare a certificatului.

Adresa certificatului este o adresă web a programului. Nu inventăm un AI GS1 pentru a reprezenta calificarea profesională.

## Administrarea conținutului

Editorii creează ciorne și pot importa/exporta documente JSON validate. Publicarea creează o ediție nouă cu hash. Cursanții deja înscriși păstrează ediția lor. Materialele proprii folosesc o licență explicită; standardele GS1 sunt indicate prin referințe și își păstrează drepturile.

Răspunsurile vechi se minimizează după 12 luni dacă nu există contestație pendinte; dovezile unei calificări se protejează până la expirare plus 24 de luni. Deciziile, punctajele, edițiile și amprentele rămân. O tentativă minimizată răspunde cu `409 assessment_archived` la cererea de reevaluare automată.

Banca reală are minimum 100 de întrebări revizuite și rămâne privată în baza de date și în copiile protejate. Nu o comite în GitHub și nu o include în manifestul public, documentație sau pachetul cPanel. Fixture-urile sintetice din repository sunt respinse în afara mediului `test`. Exemplele de bancă din documentație folosesc synthetic=true și chei SYNTHETIC-*; ele sunt destinate testării locale și nu pot fi publicate ca examen real pe hosting. Ediția privată se exportă prin GET /api/v2/admin/program/assessment-releases/{id}, exclusiv de editor/evaluator.

Rolurile sunt distincte: `course_editor`, `course_publisher`, `exam_reviewer`, `partner_reviewer` și `community_editor`. Delegarea lor nu acordă administrarea globală a platformei. API-urile administrative necesită sesiune web și CSRF; tokenurile personale sau ERP nu le pot apela.
