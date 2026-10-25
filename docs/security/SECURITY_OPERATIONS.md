# Operarea protecțiilor de securitate

## Actualizarea 0.5.3

Publică fișierele compatibile ale release-ului fără să înlocuiești `config/local.php`, `config/mail.local.php` sau `storage/`. Sesiunile vechi de producător necesită o singură reconectare. Conturile Acasă nu sunt deconectate de această actualizare.

Din contul administratorului, deschide `/admin/updates` și activează actualizarea de securitate. Migrarea aditivă `006_security` creează un singur tabel pentru rezultatul mentenanței și adaugă indici de expirare/creare, prin verificări compatibile MySQL/MariaDB în migrator. Nu șterge și nu rescrie produse, loturi, rețete sau documente. Este repetabilă. Protecția scopes/HTML și a parolelor nu depinde de activarea migrării.

HSTS este publicat cu `max-age=86400`, fără `includeSubDomains` sau preload. Creșterea duratei și extinderea la subdomenii sunt decizii operaționale separate, după verificarea tuturor gazdelor.

Release-ul 0.5.3 și migrarea 006 au fost confirmate pe producție la 24.10.2026. Probele repetabile de după publicare sunt `python tools/qa/api_smoke.py`, `python tools/qa/api_docs_smoke.py` și `python tools/qa/security_smoke.py`. Acestea citesc numai domeniul de producție și nu cer credentiale, nu creează date și nu trimit emailuri; rapoartele rămân în `tests/runtime/reports/`. Ultimul script verifică și HSTS pe static/redirect/erori, cookie-ul sesiunii, CSP și închiderea setup/hosting-check. Aceste probe nu înlocuiesc testele locale de autorizare și nu reprezintă teste de încărcare.

## Curățare privată, independentă de AI

La cPanel Cron Jobs, dacă furnizorul oferă PHP CLI, configurează la 5–15 minute calea PHP CLI reală a furnizorului urmată de:

```text
/home/apelero/openaliment.ro/tools/worker.php --cleanup-only
```

Exemplul nu presupune o anumită cale către binarul PHP. Nu expune workerul printr-un URL. Rularea normală fără argument efectuează curățarea și apoi verifică joburile AI; argumentul `--cleanup-only` nu pornește AI. Pagina QR nu execută și nu așteaptă acest job. Fără cron, administratorul poate executa un lot din `/admin/updates`; aceasta nu este o programare automată.

Curățarea este serializată prin lock DB și elimină cel mult 200 de rânduri din fiecare colecție la rularea normală; API-ul intern limitează explicit lotul la maximum 500. Ratele expirate sunt eliminate; linkurile Acasă, invitațiile și cererile/codurile OAuth expirate au o marjă de șapte zile. Tokenurile OAuth vechi se elimină numai după expirarea întregului grant și marja de șapte zile. Verificatorii refresh deja folosiți rămân păstrați pe toată durata grantului, pentru detectarea replay.

Idempotency-Key are retenție de 90 de zile. După expirare, o cerere foarte veche cu aceeași cheie poate produce o operație nouă. Deduplicarea evenimentelor ERP, istoricul loturilor, alertele, importurile și auditul comercial nu sunt curățate de acest mecanism. Tabelele de conturi și metadatele granturilor/dispozitivelor nu sunt șterse automat; retenția lor și ștergerea integrală de cont necesită un flux separat.

Ultima rulare și numărul de înregistrări eliminate se păstrează în `security_maintenance`, fără emailuri, tokenuri sau payloaduri private. Un rest mare de date expirate se curăță prin rulări repetate, cu monitorizarea hostingului.

## Sesiuni și parole

Sesiunile producătorului au inactivitate maximă de două ore și durată absolută de opt ore. Verificatorul sesiunii este legat de hashul parolei din DB; modificarea/reînsărarea acelui hash invalidează celelalte sesiuni. Schimbarea parolei sau retragerea accesurilor din `/manage/security` revocă și cheile/granturile producător emise de cont, în toate organizațiile sale. Acest efect este afișat înaintea confirmării parolei.

Parolele noi utilizează Argon2id când este disponibil, 19 MiB și două treceri; fallback bcrypt cost 12 cu limită de 72 de octeți. Algoritmul este oferit de PHP, fără o bibliotecă proprie de criptografie. Configurarea urmează [OWASP Password Storage](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html). Conturile bcrypt existente rămân acceptate; o parolă legacy mai lungă de 72 de octeți nu este migrată automat, deoarece sufixul nu poate fi autentificat din hash. Utilizatorul este îndrumat să aleagă explicit o parolă nouă. Testează costul pe hosting înaintea modificării parametrilor.

## Jurnale și bugete

Jurnalul de securitate se află în `storage/logs/security/`, în afara rădăcinii publice, cu permisiuni restrictive, maximum 2 MiB pe fișier zilnic și retenție automată de șapte zile la rularea mentenanței. După atingerea plafonului zilnic, evenimente suplimentare sunt omise; monitorizarea de hosting trebuie să detecteze și această condiție. Nu se păstrează antete, cookie-uri, parole, corpuri de cereri, emailuri sau coduri de lot în acest jurnal. Corelarea folosește request ID și un pseudonim zilnic al adresei de rețea; acesta nu este anonimizare garantată.

Bugetele globale inițiale, configurabile în fișierul privat prin `security_limits`, sunt 300 solicitări de email/oră, 100 înscrieri/oră și 1000 cereri OAuth/client/oră. Se adaugă limitelor existente per IP/destinație. Nu cresc prin payloadul unui client. Există maximum 100 cereri OAuth pending/client, 200 granturi active/cont producător și 100 dispozitive active/cont Acasă. Ajustează bugetele după capacitate și traficul legitim; limita per IP poate fi comună multor utilizatori din aceeași rețea.

## Fișiere, GitHub și dependențe

Originalele istorice și transferurile rămân private la import. Tipurile nesuportate nu pot deveni publice; se încarcă o copie publicabilă separată, fără ștergerea originalului. JPEG-urile personale noi sunt păstrate fără APP1 EXIF/XMP, APP13 IPTC și comentarii, fără recodificarea pixelilor. Fotografiile deja stocate nu sunt rescrise retroactiv. Acest filtru nu reprezintă antivirus.

Înainte de publicare rulează:

```text
python tools/qa/security_sources.py --check
python tools/qa/advisories.py
```

Primul instrument verifică tipare țintite și secrete cu încredere ridicată, fără a tipări valoarea potrivirilor; nu este un SAST complet sau o verificare exhaustivă a istoricului Git. Al doilea trimite către OSV numai nume și versiuni publice de pachete și refuză să declare succes când serviciul nu poate fi verificat. Snapshoturile fără versiune cer verificare manuală. CI și Dependabot sunt configurate, dar execuția pe GitHub trebuie verificată după publicarea repository-ului.

Pillow pentru testele locale a fost actualizat la 13.0.0 după verificarea intervalelor OSV și a [notelor oficiale de securitate](https://pillow.readthedocs.io/en/stable/releasenotes/13.0.0.html). Python/Pillow nu sunt dependențe ale hostingului.

## Controale care necesită operare sau o etapă distinctă

MFA/passkeys și recuperarea completă a contului nu sunt implementate de 0.5.3. Nu este integrat un serviciu antivirus/CDR, un WAF/CDN sau un sistem extern de alertare. Nu s-au verificat în cPanel backupurile independente, restaurarea, izolarea contului, permisiunile DB și programarea cronului. Scanarea de cod este țintită, nu completă. Nu se declară siguranță absolută ori certificare pe baza testelor automate.

Generatorul noilor conexiuni de mentenanță are termen implicit șapte zile, maximum 30. Endpointul deja instalat își păstrează expirarea inițială până la înlocuirea coordonată prin cPanel. Nu a fost dezactivat automat și nu a fost actualizat printr-o ocolire a propriilor restricții. Backupul bridge-ului nu înlocuiește backupul independent al platformei.


## Actualizare 0.6.0

Cheile de acces și recuperarea contului, sesiunile revocabile, confirmările Acasă, adaptorul privat ClamAV, backupul AES/restaurarea izolată și monitorul HTTPS sunt implementate și testate local în 0.6.0. Configurarea și probele reale pe hosting rămân distincte. Urmează [runbook-ul curent](../operations/PRODUCTION_RUNBOOK.md) și [raportul implementării](../implementation/PRODUCTION_READINESS_IMPLEMENTATION_2026-10-25.md); constatarea despre 0.5.3 de mai sus este istorică.
