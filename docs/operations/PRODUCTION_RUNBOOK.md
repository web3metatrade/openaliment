# Operarea OpenAliment pe cPanel

Starea proiectului: ONG în curs de înființare, fără operare comercială până la organizarea lui. Instrumentele de mai jos pregătesc operarea tehnică; nu stabilesc o entitate juridică și nu confirmă automat lansarea comercială.

## Activarea actualizării

1. Păstrează configurațiile private, originalele și storage. Publică pachetul verificat cu rădăcina domeniului `/home/apelero/openaliment.ro/public/`.
2. Din `/admin/updates`, activează protecția conturilor și documentelor. Migrarea `007_readiness` este aditivă și poate fi reluată; nu schimbă ID-urile produselor, loturilor sau adresele QR. Nu rescrie snapshoturile istorice.
3. Producătorii se reconectează o dată, deoarece sesiunile PHP sunt mutate în storage privat. Conturile Acasă și datele locale rămân independente.
4. Înrolează și verifică o cheie WebAuthn pentru fiecare administrator. Păstrează codurile de recuperare offline și adaugă o cheie de rezervă. Abia apoi activează `mfa_required_for_admin=true` în configurația privată. Fără înrolare prealabilă, această setare blochează administrarea până la înrolare din pagina de securitate.
5. Confirmă fluxul produs din altă categorie, fără rețetă/siglă, etichetă publicată și lot istoric; scanează atât QR-ul generic cât și cel de lot, inclusiv cu AI dezactivat.

## Cron Jobs

PHP web este 8.4.27 conform probei furnizate de utilizator. Executabilul CLI `/usr/local/bin/ea-php84` este un candidat cPanel și trebuie confirmat separat. Nu copia executabilele 8.2/8.3 din comenzile celorlalte domenii.

Rulează o dată din Cron Jobs:

```text
/usr/local/bin/ea-php84 /home/apelero/openaliment.ro/tools/worker.php --diagnostics
```

Proba reușită înregistrează `cron.probe`, versiunea PHP, `PHP_BINARY` și SAPI `cli` în panoul privat. Erorile se raportează prin stderr; o versiune sub 8.4 este refuzată. Nu sunt necesare root, shell interactiv sau executarea comenzilor din HTTP.

După probă, configurează fiecare minut:

```text
* * * * * /usr/local/bin/ea-php84 /home/apelero/openaliment.ro/tools/worker.php --quiet
```

Workerul păstrează blocarea comună pe toată execuția, inclusiv AI. Procesele suprapuse ies cu `busy`. O execuție curăță un lot limitat de date temporare, trimite cel mult un mesaj de securitate, scanează cel mult un fișier, procesează ștergerile private și registrul de ștergere, apoi cel mult un job AI. Fiecare transport are timeout propriu; un job lent poate depăși minutul și următorul va fi sărit. `--cleanup-only` exclude AI. Heartbeatul peste 180 secunde apare `stale`; urmărește eroarea reală înainte de a modifica frecvența.

Păstrează Cron Email pentru erori. `--quiet` suprimă numai rezultatul reușit; nu folosi `2>&1` către `/dev/null`. Comenzile altor domenii ale contului nu se modifică.

## Documente și scanare

Fișierele identice sunt deduplicate numai în interiorul organizației. Un fișier poate avea mai multe proveniențe. Originalele și stările de carantină PBT sunt păstrate, inclusiv BT01 deteriorat; deduplicarea nu înseamnă verificare antivirus.

Configurează un daemon ClamAV privat disponibil pe hosting în `document_scanner`, de exemplu transport `clamd` și socket Unix oferit de administratorul hostingului. Adaptorul acceptă numai un socket local Unix sau loopback TCP. Nu lansează programe și nu trimite fișierul unui serviciu public. Instalează și actualizează motorul/semnăturile prin furnizorul hostingului; aplicația nu are root.

Fără scaner, rezultatul rămâne `pending`, niciodată `clean`. Documentele noi nu se publică până la verificare. Din pagina produsului se poate cere o verificare; workerul le procesează și în fundal. Publicarea cere separat drepturi și aprobarea copiei publicabile. Materialele istorice deja publicate rămân accesibile; la un rezultat `rejected`, toate referințele aceluiași hash din organizație devin private. Testul `test_clean` este permis numai pentru mediul `test` și baza izolată `openaliment_tests`; nu constituie probă antivirus reală.

## Backup criptat

Butonul „Pregătește backupul criptat” din `/admin/updates` creează configurația privată și o cheie aleatoare într-un fișier separat. Nu o afișează în browser. Pentru instalarea curentă, căile implicite sunt:

- configurație: `/home/apelero/openaliment.ro/config/backup.local.php`;
- cheie: `/home/apelero/openaliment-backup.key`;
- arhive: `/home/apelero/openaliment-backups/`.

Descarcă separat cheia prin File Manager și păstreaz-o offline, într-o destinație controlată. Arhiva nu include această cheie. Configurația aplicației, configurația SMTP dacă există, cheia internă a aplicației, schema, datele și fișierele referite sunt incluse în arhiva criptată; ea este confidențială.

Exemplu după verificarea executabilului CLI, o dată pe oră:

```text
5 * * * * /usr/local/bin/ea-php84 /home/apelero/openaliment.ro/tools/ops/backup.php --quiet
```

Verificarea ultimei arhive:

```text
/usr/local/bin/ea-php84 /home/apelero/openaliment.ro/tools/ops/backup.php --verify-latest
```

Backupul folosește snapshot InnoDB consistent și ZIP AES-256. Octeții privați sunt verificați față de hashurile din bază; fișierele absente sau diferite opresc copia. Arhiva este finalizată atomic; un fișier `.partial` nu este o copie validă. O verificare citește toate intrările și amprentele. Backupul blochează migrările și ștergerea fizică a fotografiilor în timpul copierii, nu paginile QR sau actualizările obișnuite.

Măsoară durata și spațiul folosit pe contul cPanel înainte de a fixa retenția. Nu există ștergere automată a arhivelor prin simpla configurare. Spațiul filesystemului nu este cota contului cPanel.

Copia locală nu este offsite. Copiază arhivele și registrul de ștergere actual într-o destinație separată de contul de hosting, păstrând cheia separat; verifică un download și o restaurare din acea destinație. Până la această probă, `backup_external_verified` rămâne false. Destinația externă nu a fost încă selectată de utilizator.

## Restaurare și registrul de ștergere

Folosește `config/restore.local.example.php` ca bază pentru un fișier privat `restore.local.php`. Creează o bază goală și un director privat gol, fără trafic public. Unealta refuză o bază sau un director cu date; nu suprascrie aplicația live și nu copiază configurațiile restaurate peste configurația curentă.

În configurație indică arhiva, cheia ei și o copie **actuală, păstrată separat** a `storage/private/erasure/ledger.ndjson`. Copia dintr-o arhivă veche nu este suficientă pentru ștergerile ulterioare. Un registru fără evenimente poate fi un fișier gol. Pentru copiile anterioare migrării 007, actualizează structura în mediul izolat înaintea reconcilierii.

```text
/usr/local/bin/ea-php84 /home/apelero/openaliment.ro/tools/ops/restore.php
```

Unealta verifică decriptarea, numele intrărilor, dimensiunile, hashurile și numărul rândurilor. Revocă sesiunile, cheile API și granturile, invalidează legăturile și invitațiile vechi și coada de mesaje sensibile. Aplicațiile trebuie reautorizate; credențialele vechi nu sunt reactivate.

Reconcilierea verifică HMAC-ul identității cu cheia aplicației, elimină din nou identitățile și spațiile personale șterse după backup și păstrează datele altor membri. Reia numai transferuri de proprietate documentate în registru, către membri existenți. Un proprietar documentat indisponibil sau dovezi insuficiente produc conflict; mediul restaurat rămâne închis. Raportul privat `RESTORE_REPORT.json` indică rezultatul.

După rezolvarea unui conflict în mediul izolat, reconcilierea poate fi reluată fără restaurarea din nou a bazei:

```text
/usr/local/bin/ea-php84 /home/apelero/openaliment.ro/tools/ops/reconcile-erasure.php
```

Nu deschide traficul înainte de reconciliere, verificarea alertelor și revocărilor ulterioare backupului, probele aplicației și aprobarea schimbării destinației. Țintele RPO/RTO din plan sunt obiective; ele se confirmă prin copii externe și probe cronometrate, nu prin existența scripturilor.

## Conexiune separată pentru migrări

`config/migrations.local.example.php` se poate copia în `migrations.local.php`. Contul de migrare trebuie să folosească exact aceeași bază și același server. Este încărcat numai la instalare/actualizare. Contul runtime poate avea `SELECT`, `INSERT`, `UPDATE`, `DELETE` pe baza aplicației, iar DDL rămâne în contul de migrare. Testează întâi combinația în mediul izolat; aplicația nu modifică singură privilegiile cPanel.

## Monitorizare independentă

Rulează dintr-un sistem separat de hosting:

```text
python tools/ops/monitor.py --base-url https://openaliment.ro --output PRIVATE_PATH/availability.json
```

Sunt verificate HTTPS, starea bazei prin endpointul public, descriptorul resolverului, Acasă, prima pagină și expirarea certificatului TLS. Codul de ieșire 1 indică problemă; raportul nu conține credentiale și unealta nu trimite emailuri. Configurează monitorul extern să alerteze persoana de contact la eșec repetat și recuperare. Rularea pe același hosting nu detectează propria indisponibilitate completă. Acesta este un monitor de disponibilitate, nu test de încărcare sau probă fizică POS.
