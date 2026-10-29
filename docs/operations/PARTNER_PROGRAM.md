# Operarea programului de contribuitori, formare și parteneri

Versiune software 0.10.0. Activarea se face separat pe fiecare instalare. Pentru această livrare ținta este exclusiv `dev.openaliment.ro`.

## Instalare și migrare

1. Instalează pachetul cPanel generat de GitHub din commitul verificat al ramurii `dev`, cu configurațiile și stocarea private păstrate.
2. Conectează-te ca administrator în `/admin/updates` și activează programul. Acțiunea fixă aplică migrările aditive 012–015, sub lock, și publică ediția inițială a cursului. Nu modifică produse, GTIN-uri, loturi, rețete sau organizațiile existente.
3. Verifică GD în diagnosticul operațional și SMTP privat. Uploadul de imagini refuză procesarea dacă GD lipsește; nu publică originalul nesanitizat. Citirea QR, lecțiile și examenele nu depind de AI.
4. În `/admin/program/banks`, importă fișierul **privat** al băncii, revizuiește întrebările, răspunsurile, explicațiile și referințele, apoi publică ediția asociată cursului. Nu încărca banca reală în repository, în `public/`, în screenshoturi sau în artefactul GitHub. Fixture-urile `SYNTHETIC-*` și `synthetic=true` sunt respinse pe hosting.
5. Atribuie roluri individuale din `/admin/program/staff`. Un editor nu primește implicit dreptul de publicare sau autorizare. Persoana și firmele la care este afiliată nu își pot evalua propriile probe.

Migrările se pot relua. Nu există migrare de ștergere sau rescriere a istoricului; revenirea codului înainte de activare cere păstrarea backupului și oprirea rutelor noi. După activare, o versiune mai veche nu cunoaște noul program; nu șterge tabelele pentru a face rollback.

## Mentenanță cPanel

Workerul general existent include programul. Dacă nu este deja programat pentru staging, adaugă:

```text
* * * * * /usr/local/bin/ea-php84 /home/apelero/dev.openaliment.ro/tools/worker.php >> /home/apelero/dev.openaliment.ro/storage/logs/worker.log 2>&1
```

Confirmă binarul PHP 8.4 al hostingului și rotește logul prin mentenanța existentă. Acesta este un job diferit de `tools/external-worker.php`, destinat surselor externe. Nu programa o copie pentru producție în această etapă.

Un apel procesează maximum cinci tentative expirate, o reverificare fiscală scadentă, o notificare, cinci imagini retrase și trei tentative pentru minimizarea dovezilor. Lockul împiedică suprapunerea workerilor programului. SMTP se execută în afara tranzacțiilor. Reverificarea ANAF începe după 27 de zile; la eșec se aplică backoff, fără a bloca verificarea celorlalte firme. La 30 de zile fără observație fiscală validă, profilul nu mai apare ca autorizat. Aceasta este o regulă strictă de prospețime, fără perioadă de grație suplimentară.

Pagina publică verifică valabilitatea la fiecare citire: nu așteaptă cronul pentru retragerea consimțământului, revocare, expirare, contact înlocuit sau pierderea afilierii/calificării. ANAF nu este apelat în timpul scanării QR sau al citirii registrului.

## Verificări și incidente

- ANAF confirmă datele fiscale, nu mandatul persoanei. Reviewerul înregistrează separat cum a verificat reprezentarea. ANAF v9 nu furnizează email; telefonul fiscal nu devine automat contact verificat.
- O firmă existentă poate primi o nouă cerere de reprezentare. Reviewerul confirmă explicit asocierea cu profilul juridic existent; nu se transferă tenantul, produsele sau drepturile asupra GTIN-urilor.
- Schimbarea CUI-ului creează o firmă și un dosar distinct. Calificarea aparține persoanei. Afilierea veche se încheie numai prin acțiune explicită, iar datele istorice rămân la firma lor.
- Suspendă o calificare sau o autorizare cu motiv din panoul programului. Revocarea este definitivă pentru înregistrarea respectivă; o calificare revocată nu este recreată prin simpla reconciliere.
- Pentru un profil curatoriat fără cont, persoana își confirmă acordul prin email. Retragerea se solicită contactului platformei și se aplică prin suspendarea profilului de către editor; nu se cere crearea unui cont pentru retragere.
- P04 cere dovadă fizică revizuită. Testele automate cu fixture-uri validează fluxul software și nu certifică imprimante sau compatibilitatea POS reală.

## Stocare, retenție și restaurare

Imaginile sunt reencodate PNG, fără metadate, cu maximum 2 MiB și 4 megapixeli la intrare. Bugetul este 30 MiB/cont pentru toate scopurile și 2 GiB global; imaginile de profil au maximum 640 px, probele 1600 px. Această limită comună simplifică rezervarea atomică și este mai restrictivă decât încărcări independente nelimitate. Probe: maximum 20 noi/zi și 60/an/cont, 20 de salvări și predări pe oră, JSON de maximum 100 KB; fără video sau executabile. Identitatea fiecărui fișier este verificată prin SHA-256 înainte de servire. Scrierile personale cu idempotency au și buget ponderat după cerere + răspuns: 2 MiB/cont/zi, 20 MiB global/zi; la depășire tranzacția este anulată. Rolurile editoriale verificate sunt exceptate de acest buget de conținut. Configurația privată poate ajusta limitele `security_limits.program_write_user_kib_day`, `program_write_kib_day` și `program_labs_day`.

Confirmările expirate se elimină după 30 de zile; dovada acordului curatoriat confirmat rămâne asociată profilului. Recipisele de retry și notificările încheiate se elimină după 90 de zile. Fișierele imaginilor retrase se elimină după 30 de zile. Răspunsurile tentativelor vechi se minimizează după 12 luni dacă nu există contestație pendinte; dovezile unei calificări se protejează până la expirare plus 24 de luni. Deciziile, punctajele, edițiile și amprentele rămân în istoric. O tentativă minimizată nu poate fi recalculată automat cu răspunsuri absente; verificarea administrativă se solicită separat.

Backupul include noile tabele și stocarea privată, cu criptarea existentă. La restaurare se invalidează confirmările și rolurile delegate, se retrag acordurile publice, se suspendă calificările și firmele active și se invalidează contactele și publicarea imaginilor. Reverifică stările și registrul de ștergeri înainte de redeschiderea unei copii restaurate. O copie veche nu dovedește că nu a existat o revocare ulterioară.

## API și export

Registrul complet al celor 109 operații este în [matricea API](../api/PROGRAM_ACTION_MATRIX.md), iar schemele, exemplele și erorile sunt generate în portal. Scopurile OAuth ale programului sunt separate de producători și de Acasă. Examenele și administrarea cer sesiune web; API-urile de progres, dosar, imagini și contribuții permit OAuth personal explicit.

Exportul persoanei nu include cheia examenului; exportul firmei nu include răspunsurile angajaților. Importul poate relua doar poziții de lectură pentru înscrieri existente cu aceeași ediție: ID local sau slug + versiune + hash. Nu importă promovări, mandate sau autorizații active. Banca privată se exportă numai de editorul/evaluatorul autorizat.
