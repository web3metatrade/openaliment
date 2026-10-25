# Redirecționare pentru conturile deja autentificate

## Problema și corecția

În release-ul inițial, un utilizator cu sesiune activă vedea formularul la `/login`, deși meniul îl recunoștea și afișa „Spațiul meu”. Același lucru se întâmpla la `/register`.

La cererile GET și HEAD către aceste două adrese, aplicația verifică acum sesiunea folosind aceeași funcție `Auth::user()` și redirecționează utilizatorul autentificat către `/manage` cu HTTP 303. Vizitatorii neautentificați primesc în continuare formularul. Cererile POST, protecția CSRF, drepturile și durata sesiunii își păstrează comportamentul existent.

## Instalare prin cPanel

1. Păstrează o copie a fișierului actual `app/Controllers/PublicController.php` într-un director privat de backup, în afara `public/`.
2. Înlocuiește `/home/apelero/openaliment.ro/app/Controllers/PublicController.php` cu fișierul din patch. Dacă extragi ZIP-ul, alege `/home/apelero/openaliment.ro/`, astfel încât structura `app/Controllers/` să rămână corectă.
3. Din browserul în care ești deja autentificat, deschide `/login` și `/register`: ambele trebuie să ducă la `/manage`.
4. Într-o fereastră privată, `/login` trebuie să afișeze formularul de autentificare.

Nu există migrare SQL. Nu sunt necesare schimbări în `config/local.php`, reinstalarea aplicației sau eliminarea `storage/installed.lock`. Dacă hostingul păstrează o versiune veche în OPcache, folosește funcția de resetare PHP/OPcache disponibilă în cPanel sau solicită furnizorului reîncărcarea fișierului.

Pentru revenire, restaurează copia privată a aceluiași fișier.

## Verificare

PHP 8.4.27, MariaDB 11.4.10, bază locală izolată `openaliment_tests`: sintaxa PHP validă și 58/58 verificări HTTP trecute. Sunt incluse accesul anonim la formular și redirecționarea autentificată pentru GET/HEAD pe ambele adrese. Raport: `docs/verification/auth-redirect-http-report.json`.

Codul inițial a fost observat în sesiunea autentificată pe domeniul real. Corecția a fost testată local; instalarea patch-ului pe hosting și verificarea după instalare rămân de efectuat.
