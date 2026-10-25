# Instalare cPanel fără root sau shell

Pachetul livrat nu conține configurația locală, parole, tokenuri, baze sau conturi de test. Domeniul vizat este `openaliment.ro`; document root confirmat de proprietar: `/home/apelero/openaliment.ro/public/`.

## Variante de distribuție

Un release poate conține aplicația (`app`), aplicația împreună cu arhiva pilot (`full`, varianta implicită) sau numai pachetul istoric (`pilot`). Constructorul este `tools/package-release.py`, cu selecție explicită în `tools/build/release-manifest.json` și versiune în `release.json`. Acesta rulează pe calculatorul dezvoltatorului, nu pe hosting.

Varianta `app` nu are nevoie de arhiva PBT pentru paginile produselor, QR sau Acasă. Pentru un import nou PBT adaugă separat pachetul pilot în afara `public/` și configurează privat `handover_path`, dacă locația diferă de cea implicită. Absența pachetului produce un mesaj controlat numai la solicitarea importului.

## Dispunere

În File Manager încarcă arhiva release în directorul `openaliment.ro/`, apoi extrage. Rezultatul trebuie să fie:

```text
openaliment.ro/
  app/
  config/config.example.php
  config/local.php                  ← creat privat de tine
  database/migrations/001_initial.sql
  contracts/
  storage/private/
  storage/logs/
  tools/
  public/                          ← singurul document root
    index.php
    .htaccess
    hosting-check.php
    assets/
```

Nu încărca `app`, `config`, `storage` sau arhiva OpenBread în `public/`. Arhiva istorică este inclusă în release ca director privat soră `OpenBread_Codex_v1_2026-10-23/`; configurația `handover_path` folosește această cale relativă în mod implicit. Originalele sunt incluse în afara zonei publice, cu octeții și manifestul lor păstrate.

## Configurare securizată

1. Fă backup al fișierelor și DB existente; lucrează întâi pe un subdomeniu de staging și o bază separată. Codul nu șterge un site sau o bază existentă.
2. În cPanel selectează PHP 8.4+ cu `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`; `curl` pentru AI și `zip` dacă va fi necesar ulterior. Nu este necesar Composer sau Node pe hosting.
3. Configurează utilizatorul și baza dedicate din cPanel. Schema inițială necesită CREATE/INDEX/REFERENCES/ALTER plus drepturile aplicației; după instalare poți limita utilizatorul aplicației la SELECT/INSERT/UPDATE/DELETE. Nu folosi contul de administrare globală a serverului.
4. Copiază `config/config.example.php` în `config/local.php`, în același director privat. Completează DSN-ul cu hostul/portul/numele DB și utilizatorul/parola furnizate de cPanel. Fișierul PHP întoarce un array; nu afișează datele. Setează permisiuni restrictive, de exemplu 0600 dacă modelul de utilizator al gazdei permite. Nu trimite acest fișier în chat.
5. Deschide local `tools/setup-key.html`. Generează tokenul și copiază numai hashul în `setup_token_hash`. Păstrează tokenul privat pentru instalare. Nu îl pune în URL.
6. Păstrează `url=https://openaliment.ro`, `environment=production`, `session_secure=true`, `ai.enabled=false`, `registration_enabled=false`. Storage trebuie să fie scriibil de PHP, în afara document root; nu îl face 0777.
7. Deschide `https://openaliment.ro/hosting-check.php`, introdu tokenul în formular și verifică raportul. Acesta nu afișează DSN, parole, chei sau phpinfo. După instalare, verificatorul răspunde 404.
8. Deschide `https://openaliment.ro/setup`, introdu tokenul și creează administratorul real și organizația sa. Nu există parolă implicită. Instalarea aplică schema cu tabele noi, creează contul și scrie lockul privat. Dacă baza conține deja conturi, instalarea inițială se blochează.
9. Șterge valoarea `setup_token_hash` din configurația privată. Nu elimina lockul. Verifică faptul că `/setup` și `/hosting-check.php` răspund 404.

Nu importa SQL-ul peste tabelele unui alt proiect. Numele inițiale nu sunt prefixate: folosește o bază dedicată. Dacă instalezi manual schema din phpMyAdmin, contul inițial se creează tot prin `/setup`, înainte de existența oricărui cont în DB.

## Primul produs real

Pentru actualizări efectuate de agent prin HTTPS, fără SSH sau FTP, conexiunea opțională și fișierul unic de încărcat sunt descrise în [HTTPS_MAINTENANCE.md](HTTPS_MAINTENANCE.md). Cheia sa este separată de tokenul inițial de instalare și de parola contului din platformă.

Intră în `/login`: creează produsul și categoria, articolul comercial, versiunea informațiilor, revizuiește și publică. Creează lotul cu versiunea exactă; rețeta poate rămâne absentă. Publică informațiile lotului; eliberarea este o decizie separată. Generează QR-ul din pagina articolului sau lotului. Niciunul dintre acești pași nu emite o siglă open.

GTIN este opțional pentru QR nativ. Pentru Digital Link: completează GTIN și dovada mandatului, verifică identitatea organizației și dreptul de administrare din `/admin`, apoi probează profilul URL pe hosting. Cu actualizarea 002, înregistrează raportul probelor în `/admin/updates`; verificarea este păstrată în baza de date. Opțiunea `gs1_transport_verified` rămâne numai pentru compatibilitatea profilului conservator inițial. Nu activa un profil de caractere mai larg decât cel verificat efectiv.

## Probe obligatorii pe domeniu

- TLS valid, redirect HTTPS, document root corect și refuzul accesului la `config`, `storage`, originale și fișiere ascunse.
- Ruta `/b/{id}` și Digital Link pentru GTIN și lot exact; coduri cu majuscule și zerouri; caractere speciale prin întregul traseu Apache/CDN/WAF. `%2F` poate necesita intervenția furnizorului la nivel de virtual host. În lipsa suportului, folosește QR nativ.
- Două organizații, cont fără rol de aprobare, token cu scope limitat, CSRF și expirare/revocare token.
- L1 → lot → L2, scanarea vechiului lot, alertă nouă vizibilă imediat, AI dezactivat și absența rețetei private din pagină/export public.
- SVG descărcat și scanat pe ambalajul real; dimensiuni și contrast aprobate de responsabilul ambalare. Testul pe ecran nu înlocuiește proba de tipar.
- Backup și restaurare într-o bază separată, cu verificarea hashurilor și mapărilor. Cronul este opțional pentru paginile QR.

## AI și feedback

Feedbackul rămâne dezactivat până la completarea identității/contactului operatorului și a textului de confidențialitate adoptat în configurare. Activează-l după ce există un responsabil real de moderare/incidente. Nu publica șabloanele juridice cu substituenți.

AI se configurează separat, cu cheia în `config/local.php`, modelul disponibil și plafonul de joburi. Dacă cPanel oferă Cron Jobs cu un PHP CLI al furnizorului, configurează rularea privată `tools/worker.php` la intervalul disponibil. Nu este necesar shell interactiv. Dacă furnizorul nu oferă cron/CLI, analiza rămâne dezactivată; QR și feedbackul nu depind de ea. Nu expune workerul printr-o adresă web.

## Release și revenire

### Actualizare API 0.5.0

Publică fișierele release-ului, păstrând `config/local.php`, `config/mail.local.php` și `storage/`. Din sesiunea administratorului deschide `/admin/updates` și apasă „Activează accesul pentru aplicații”. Operația aplică migrarea aditivă `005_api` după verificarea migrărilor necesare, sub un lock SQL, și construiește indexul căutării din etichetele aprobate. Este repetabilă și nu creează chei, aplicații sau granturi pentru utilizatori. Păstrează backupul DB înaintea actualizării.

Verifică `/api/manifest.json`: OAuth devine disponibil după instalarea schemei; accesul personal depinde suplimentar de `home_sync_enabled`. Documentația se află la `/api/docs`; cheile la `/manage/api`, aplicațiile la `/manage/developers`. Configurația privată poate ajusta `api_limits`, `producer_limits` și originile ERP permise. Nu copia exemple de parole peste configurația existentă. O restrângere intenționată: rețetele și propunerile publice cer scope `publish` pe lângă `write`.

Migrarea nu șterge tabele vechi. Revenirea la fișierele release-ului precedent lasă noile tabele inactive; nu le șterge și nu modifica migrările 001–004. După utilizarea OAuth, documentează separat revenirea funcțională: aplicațiile delegate nu mai pot folosi rute care lipsesc în versiunea veche.

Încarcă fișierele în staging și verifică înainte de înlocuirea release-ului activ. Păstrează release-ul precedent în afara document root. Migrarea 001 este pentru instalare nouă și folosește CREATE TABLE IF NOT EXISTS; nu este o migrare a unei baze OpenBread existente. Handoverul inspectat nu avea DB instalată.

Pentru rollbackul instalării inițiale revino la backup sau la baza dedicată anterioară; nu există buton care să șteargă datele. Nu aplica DROP pe baza activă. Migrațiile viitoare trebuie să fie aditive, versionate, precedate de backup și compatibile cu release-ul precedent pe durata revenirii.

Înainte de trafic mare măsoară limitele reale de PHP workers, conexiuni DB, I/O și trafic. Testele locale nu probează milioane de scanări pe shared hosting.


Pentru migrarea 007, chei de acces, cron verificat, scanare, backup AES, restaurare izolată și reconcilierea ștergerilor, urmează [procedura de operare](operations/PRODUCTION_RUNBOOK.md). Instrumentele nu confirmă singure instalarea cronului sau existența unei copii externe.
