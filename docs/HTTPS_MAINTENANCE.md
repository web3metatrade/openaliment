# Administrarea fișierelor prin HTTPS pe cPanel

## Prima conectare

Conexiunea a fost solicitată explicit de proprietarul OpenAliment pentru actualizarea codului de producție fără SSH, FTP sau încărcarea manuală a fiecărui patch.

Fișierul generat pentru încărcare este `release/conexiune/openaliment-connect.php`. Încarcă numai acest fișier în:

```text
/home/apelero/openaliment.ro/public/openaliment-connect.php
```

Nu modifica configurația aplicației și nu copia cheia în chat. Generatorul păstrează cheia de 256 de biți numai în `.tools/deploy/production.json`, pe calculatorul local. Fișierul PHP conține hashul SHA-256 al cheii și termenul de expirare. `.tools/` și `release/` sunt excluse din Git, iar configurația privată nu se include în pachetul aplicației.

La deschiderea adresei în browser, răspunsul 404 este intenționat. Conexiunea acceptă doar cereri POST JSON autentificate, prin HTTPS, către `openaliment.ro`. Cheia este transmisă într-un antet, niciodată în URL. Nu se utilizează parola administratorului din interfața platformei.

După încărcare, agentul verifică `status`, apoi poate citi codul permis, compara versiuni, încărca și activa actualizările. Certificatele TLS sunt verificate de client; redirecționările nu sunt urmate cu cheia de acces.

## Acces și limite

Pentru staging, `python tools/deploy/prepare.py --environment staging` generează separat `release/staging/openaliment-connect.php` și `.tools/deploy/staging.json`, fixate la `dev.openaliment.ro`. Fișierul PHP se încarcă în `/home/apelero/dev.openaliment.ro/public/`. Comenzile clientului folosesc explicit `--config .tools/deploy/staging.json`. Generarea nu rotește și nu înlocuiește cheia producției. Vezi [organizarea mediilor](operations/COLLABORATION.md).

Endpointul poate administra numai codul PHP din `app/`, `public/index.php`, `public/.htaccess`, resursele statice din `public/assets/`, contractele JSON/YAML, fișierele SQL din `database/migrations/` și `tools/worker.php`. Fișierele SQL pot fi transferate, dar nu sunt executate de acest protocol.

Accesul direct la `config/`, `storage/`, arhiva OpenBread, fișierele ascunse și endpointul de conectare este refuzat. Nu există execuție de comenzi de shell, evaluare de expresii PHP sau execuție SQL ca operațiuni ale protocolului. Totuși, o cheie care poate actualiza cod PHP are autoritatea unui administrator de release: codul activat rulează cu drepturile contului PHP al hostingului. Cheia trebuie păstrată ca acces administrativ.

Căile sunt relative și verificate, inclusiv fiecare componentă existentă prin `lstat`; legăturile simbolice sunt refuzate. Nu se extrag arhive trimise de client. Limite: 2 MiB per fișier, 8 MiB și 32 de fișiere per actualizare. Clientul încarcă numai fișierele enumerate explicit.

## Publicarea unei actualizări

1. Citește/inspectează versiunea existentă și compar-o cu modificarea locală. Nu înlocui modificări independente fără reconciliere.
2. Rulează testele locale potrivite modificării.
3. `inspect` obține hashurile curente. `stage` verifică aceste precondiții, hashurile conținutului, limitele și sintaxa PHP folosind parserul PHP al hostingului, fără a executa fișierul încărcat.
4. `commit` verifică din nou precondițiile, păstrează toate versiunile anterioare în spațiul privat și activează fișierele prin înlocuire atomică per fișier. Copiile, jurnalul și auditul se află în `storage/maintenance/`, în afara document root.
5. Clientul recitește hashurile, iar agentul probează separat comportamentul aplicației prin HTTPS. Hashul confirmă transferul; testul funcțional confirmă comportamentul.

Blocarea exclusivă previne două operațiuni simultane prin acest endpoint. O actualizare cu mai multe fișiere nu este o schimbare atomică a întregului release; modificările trebuie să fie compatibile între versiuni pe durata activării. Pentru schimbări incompatibile este necesară o strategie separată de migrare și fereastră de mentenanță.

Comenzi locale, executate de agent folosind Python local; pe hosting nu sunt necesare Python sau shell:

```text
python tools/deploy/client.py status
python tools/deploy/client.py inspect app/Controllers/PublicController.php
python tools/deploy/client.py read app/Controllers/PublicController.php --output .tools/deploy/remote/PublicController.php
python tools/deploy/client.py deploy --files app/Controllers/PublicController.php --label "Redirectionare cont autentificat" --dry-run
python tools/deploy/client.py deploy --files app/Controllers/PublicController.php --label "Redirectionare cont autentificat"
```

## Revenire și întreruperi

Fiecare actualizare are un ID și un jurnal. Clientul salvează ID-ul înainte de activare în `.tools/deploy/receipts/`. Dacă răspunsul se pierde, verifică mai întâi starea acelui ID; nu porni automat un nou transfer peste o operațiune incertă.

```text
python tools/deploy/client.py deployment ID_ACTUALIZARE
python tools/deploy/client.py rollback ID_ACTUALIZARE
```

La o eroare gestionabilă în timpul activării, endpointul încearcă restaurarea copiilor. O întrerupere a procesului poate necesita `rollback` explicit pentru stările `applying`, `restoring` sau `recovery_required`. Restaurarea verifică integritatea tuturor copiilor și refuză suprascrierea fișierelor modificate între timp din altă sursă. Fișierele nou adăugate sunt eliminate la revenire, cu versiunea lor păstrată în staging-ul privat. Acest mecanism nu înlocuiește backupul complet al fișierelor și bazei de date.

## Expirare și oprire

Începând cu 0.5.3, cheia nou generată este valabilă implicit 7 zile, configurabil între 1 și 30 de zile. Această modificare nu scurtează retroactiv valabilitatea unui endpoint deja instalat. Accesul existent se poate opri imediat prin ștergerea fișierului public din cPanel sau prin comanda autentificată:

```text
python tools/deploy/client.py disable
```

Oprirea prin comandă păstrează un lock privat și copiile. Pentru reînnoire/rotație, agentul generează o pereche nouă cu `prepare.py --rotate`, iar proprietarul înlocuiește fișierul public. Endpointul nu își poate modifica singur autentificarea, expirarea sau codul.

## Verificarea efectuată

Suita `tests/test_deploy_bridge.py` testează endpointul PHP real într-un director local izolat, cu o cheie distinctă de producție și fără bază de date. TLS și hostname sunt simulate numai de routerul privat de test; acest router nu este inclus în fișierul livrat. Raportul curent este `docs/verification/deploy-bridge-report.json`, inclusiv orice probă omisă din cauza limitelor Windows.

Prima conectare pe hosting și prima actualizare au fost verificate la 23.10.2026: răspuns autentificat, staging, activare, backup și hash final. Redirecționările pentru contul autentificat au fost observate în browserul de producție, iar formularele anonime au fost probate prin HTTPS fără cookie-uri. Evidența este în [updates/PRODUCTION_AUTH_2026-10-23.md](updates/PRODUCTION_AUTH_2026-10-23.md). Restaurarea a fost testată local; nu s-a provocat o revenire inutilă a fișierului funcțional pe producție.
