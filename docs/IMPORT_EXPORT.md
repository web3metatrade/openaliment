# Import, export și continuitate

## Formate

Format activ: `openaliment.transfer.v1`, UTF-8 JSON. Conține data exportului, domeniul sursă, identitatea declarată a organizației, colecțiile versionate și `data_sha256`. Hashul este SHA-256 al reprezentării JSON fără whitespace emise de aplicație, cu Unicode și slashuri neescapate, în ordinea păstrată de exportator. Nu reordona/re-serializa colecțiile înainte de verificarea hashului; pentru interoperabilitate păstrează reprezentarea și verifică folosind aceeași regulă PHP. Hashurile fișierelor sunt pe octeții originali. Nu reprezintă semnătură a unei autorități.

Exportul privat al organizației include produse, articole, toate versiunile de etichetă și rețetă, loturi, evenimente, alerte, documente și proveniență, propuneri, mapări URL, recenziile publicate și auditul tehnic. Nu include utilizatori, hashuri de parole, tokenuri API, tokenuri de feedback, contacte private ori originalele brute ale reclamațiilor. Fișierele de maximum 15 MB sunt incluse base64; cele mai mari necesită transfer separat prin descărcarea autorizată. Fișierele originale sunt întotdeauna necesare pentru un transfer complet al documentelor.

Exportul acesta este privat și poate include rețete private. Nu îl încărca pe o pagină publică. API-urile publice de produs/lot sunt o proiecție mult mai restrânsă și nu returnează rețetele private.

## Import sigur

Verificarea `dry_run=true` controlează forma, hashul și numărul colecțiilor și raportează datele ce urmează să fie tratate. Importul efectiv mai verifică identități, referințe, hashuri de snapshot și fișiere, relații și constrângerile SQL. Dry-run nu înlocuiește aceste verificări tranzacționale. Limita HTTP inițială este 24 MB; importurile mai mari se pregătesc ca operație de migrare dedicată, cu backup și cote verificate.

Importul creează identificatori locali noi și întoarce `id_map`. Versiunile de etichetă intră draft, loturile și rețetele private, fișierele cu drepturi neconfirmate și GTIN-urile ca solicitări neverificate. O rețetă validată la sursă nu primește automat validare locală. Loturile păstrează eticheta și rețeta mapate; aprobarea locală se face înainte de publicare. Alertele și evenimentele loturilor se păstrează; o alertă nu este suprimată de schimbarea versiunii.

Payloadul sursă complet este păstrat privat în registrul importului, inclusiv deciziile și istoria care nu conferă autoritate locală. Reluarea aceleiași surse și aceluiași hash întoarce aceeași mapare. O sursă schimbată nu suprascrie automat obiectele vechi.

## OpenBread v0.1

Adaptorul pilot recunoaște `openbread.product_seed.v0.1`, `openbread.recipe.general.v0.1` și reprezentarea R1 a pachetului; păstrează planul/mappingul, catalogul și draftul dosarului originale. Nu aplică direct modelul logic ca SQL.

Înainte de import este verificat întregul `HASHES.sha256`. Aceeași `(project_key=pbt, version_key=R1)` produce o singură versiune. Produsul intră în organizația autorizată care execută importul; potrivirea după numele MOPAN nu acordă membership. Importul trebuie executat în spațiul organizației care are mandatul asupra dosarului. Proveniența istorică MOPAN nu se schimbă.

R1: 3.790 g nominal, fără dublarea inoculului. COST3: scenarii estimate; COST1 istoric, COST2 corecție istorică. Niciun GTIN, lot executat, certificat, recenzie, rezultat fizic sau termen de valabilitate nu se inventează. Originalele, extrasele și cele șapte recuperări BT01 se copiază privat. ZIP-ul deteriorat rămâne carantinat și nu este extras.

Șabloanele `template_only=true`, exemplul AI și scenariile de test nu se importă ca rezultate reale. Pentru un dump vechi diferit de pachetul pilot este necesar un adaptor al formatului efectiv: ID-urile și snapshoturile se păstrează în proveniență; eticheta se construiește separat din date aprobate, iar un lot cu informații insuficiente intră privat. Nu se pretinde că există o bază OpenBread instalată sau un import universal al tuturor dumpurilor SQL.

## GS1 și alți furnizori

GS1 Digital Link standardizează reprezentarea identificatorilor și rezolvarea linkurilor; nu definește un export universal de ERP, rețete sau bază de produs a oricărui furnizor. Se solicită exportul efectiv disponibil (JSON/CSV/linkset și documente), autoritatea organizației și dicționarul câmpurilor. Mapperul se scrie pentru formatul identificat și păstrează câmpurile nesuportate în proveniență. Nicio accesare automată a serviciilor private GS1 nu este presupusă.

Linksetul unui resolver poate ajuta la inventarul destinațiilor publice, dar nu dovedește titularul și nu conține obligatoriu documentația completă. O migrare nu transferă licența GS1 ori dreptul asupra unui domeniu. Mărcile, licențele documentelor și mandatele se verifică separat.

## URL-uri tipărite și domenii

Exportul păstrează domeniul sursă și identitățile/mapările. Importul înregistrează mapările sursă→obiect local ca pending; nu le activează ca redirect arbitrar. Pentru continuitate este necesar acordul/controlul domeniului vechi. Mutarea datelor nu poate modifica URL-ul deja imprimat pe ambalaj.

Modelul rezervă `producer_domains` pentru domenii controlate de producători. Challenge-ul DNS/HTTP, verificarea mandatului, certificatul TLS, host routing și testele de transport sunt condiții pentru activare ulterioară. Domeniile nu sunt activate printr-un parametru Host neîncredere.

## Export complet pentru volume mari

`GET /api/v1/manage/export?format=ndjson` (scope export) și linkul din pagina Import descarcă `openaliment.transfer.stream.v1`. Citirea SQL este nebufferizată și folosește un snapshot tranzacțional; nu se construiește în memorie întregul producător. Prima linie conține schema și sursa; fiecare linie următoare conține `table` și `row`. Ultima linie conține `end=true`, `records` și SHA-256 al tuturor octeților liniilor anterioare, inclusiv LF. Lipsa ultimei linii sau hashul incorect arată un export incomplet.

Fluxul include și registrul importurilor, astfel încât scenariile COST3, seed-urile, catalogul și proveniența PBT să poată fi recuperate complet, alături de documente. Nu include conturi sau tokenuri active. Datele rămân private și trebuie transferate securizat.

Exportul JSON obișnuit este limitat preventiv la 10.000 rânduri per colecție și aproximativ 8 MB de documente/istoric pentru a evita epuizarea memoriei pe shared hosting. NDJSON este format de portabilitate pentru prelucrare în flux și migrare asistată. API v2 oferă `POST /api/v2/manage/import-stream?dry_run=true` pentru NDJSON de maximum 24 MB și 10.000 rânduri per colecție, cu verificarea digestului înainte de scriere. `dry_run=false` cere Idempotency-Key și aplică atomic. Importatorul web acceptă JSON sau NDJSON în aceeași limită. Pentru volume mari se pregătește un adaptor în flux pe exportul real și se verifică timeouturile/cotele furnizorului.

## Revizii resolver · actualizare 02

Migrarea 002 adaugă colecția opțională `resolver_links` în transfer.v1 și în fluxul NDJSON. Exportul include toate reviziile și deciziile organizației. Exporturile anterioare fără colecție rămân valide. Importul generează ID-uri și link_key locale, păstrează numărul versiunii și metadatele, dar toate reviziile intră draft, is_current=0, fără aprobări. Sursa integrală rămâne în proveniența privată. Reaprobarea și verificarea drepturilor sunt obligatorii înainte de activare.


Documentele din export includ opțional `sources`, cu proveniențe independente și amprente ale obiectelor JSON. Importurile vechi fără acest câmp rămân valide. Documentele identice sunt deduplicate numai în organizația țintă; licența din sursă se păstrează ca metadată privată, fără publicare sau acordare automată de drepturi. Octeții sunt păstrați în documente; istoricul importului nu dublează base64 în payload. `GET /api/v2/public/recipe-versions/{id}/archive` descarcă numai rețeta publică licențiată și documentele publice cu licențe explicite, într-un ZIP autonom.
