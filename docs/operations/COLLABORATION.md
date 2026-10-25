# Colaborare GitHub și medii OpenAliment

Repository public: `https://github.com/web3metatrade/openaliment`. Administrator inițial: `web3metatrade`. Codul public și licențele sunt separate de configurațiile, datele și materialele istorice private.

## Cod, review și pachete

Există două ramuri permanente: `dev`, implicită pentru contribuții și integrare, și `main`, pentru codul acceptat pentru producție. Un branch nu este o instalare: aprobarea unui PR, merge-ul și publicarea pe hosting sunt acțiuni separate.

1. Dezvoltatorul pornește din `dev`, într-un branch scurt `feature/...` sau `fix/...`, inclusiv în fork, și trimite PR către `dev`.
2. După review și CI, branchul scurt se poate integra prin squash. Proprietarul instalează explicit pachetul verificat al commitului `dev` pe staging și consemnează rezultatul testării.
3. Pentru o promovare se deschide PR `dev` → `main`, se verifică diferențele și dovezile stagingului și se rulează CI. Se folosește **merge commit**, fără squash sau rebase între ramurile permanente.
4. Proprietarul decide separat publicarea în producție. Pachetul de producție este construit din commitul rezultat în `main`; conținutul său runtime trebuie să corespundă celui testat pe staging. Dacă au intrat alte modificări în `dev` între timp, promovarea și verificarea se repetă pentru diferențele noi.
5. După promovare se deschide și integrează PR `main` → `dev`, tot prin merge commit, pentru păstrarea istoricului comun. Nu se resetează și nu se forțează ramurile permanente.

O corecție urgentă poate porni din `main` într-un branch `hotfix/...` al repository-ului și ajunge prin PR în `main`, cu review și CI. Se sincronizează apoi `main` → `dev` și se publică explicit; eticheta „hotfix” nu ocolește verificările. Contribuitorii externi propun inițial corecția către `dev`, iar maintainerul pregătește separat un hotfix când este necesar.

PR-ul descrie problema, comportamentul rezultat, verificările și compatibilitatea. Modificările nucleului GS1 indică ediția standardului, secțiunea și probele de regresie. Nu se schimbă fixture-urile doar pentru a accepta o implementare incorectă.

Workflow-ul CI folosește PHP 8.4, Node 24, Python 3.12 și baze MariaDB 10.6/11.4 dispensabile. Nu primește configurația sau credentialele hostingului. După verificările reușite pe `dev` sau `main`, construiește profilul `app` și păstrează ZIP-ul plus SHA-256 ca artifact `cpanel-app-<branch>-<commit>`. PR-urile nu produc pachete publicabile. Nu există deploy automat. Probele browser se pot selecta la pornirea manuală a workflow-ului.

Ambele ramuri cer un PR, cel puțin o aprobare, invalidarea aprobărilor vechi, rezolvarea discuțiilor și verificările `branch-flow`, `local (10.6)`, `local (11.4)` și `advisories`, cu branchul actualizat. Force push și ștergerea sunt interzise. Istoricul liniar nu este impus, deoarece promovările și sincronizările păstrează merge commits. `branch-flow` admite către `main` doar `dev` sau `hotfix/...` din repository-ul propriu; un branch cu același nume dintr-un fork nu primește acest drept. Acest control al destinației nu înlocuiește review-ul codului și al workflow-urilor.

Proprietarul păstrează posibilitatea existentă de bypass a protecției branchului pentru configurarea inițială și urgențe. Aceasta nu este fluxul obișnuit de contribuție și nu acordă acces la hosting. Singurul maintainer nu își poate aproba propriul PR; înainte de review independent pentru PR-urile lui, se desemnează un al doilea reviewer de încredere.

Mediul GitHub `production` permite numai branchul `main`, cu aprobare `web3metatrade` și fără bypass al regulilor mediului. Nu se folosește „Protected branches only”, deoarece și `dev` este protejat. Mediul `staging` permite numai `dev`, tot cu aprobarea proprietarului. Aceste gate-uri sunt pregătite pentru workflow-uri viitoare: CI-ul actual nu le invocă, nu conține secrete de hosting și nu publică fișiere.

`CODEOWNERS` desemnează inițial administratorul. Autorul unui PR nu își poate aproba propriul PR: înainte să cerem obligatoriu aprobarea code owner-ului pentru toate modificările, adăugăm un al doilea maintainer de încredere sau atribuim zonele corespunzător. Aprobarea codului nu acordă drept de publicare în producție.

## Medii și acces

| Mediu | Origine | Document root | Date |
| --- | --- | --- | --- |
| Dezvoltare locală / CI | loopback | `public/` | `openaliment_tests`, exclusiv dispensabilă |
| Staging | `https://dev.openaliment.ro` | `/home/apelero/dev.openaliment.ro/public/` | bază și utilizator SQL proprii, date sintetice |
| Producție | `https://openaliment.ro` | `/home/apelero/openaliment.ro/public/` | configurația și datele existente |

Sursa pentru staging este un commit verificat din `dev`; sursa pentru producție este un commit acceptat în `main`. Crearea ramurilor nu actualizează fișierele instalate. Paginile publice ale stagingului sunt în prezent accesibile cu `noindex`; controlul general de acces la nivel de hosting nu este încă configurat.

Stagingul și producția sunt în același cont cPanel `apelero`, conform deciziei proprietarului din 25.10.2026. Separarea directoarelor și bazelor nu izolează drepturile utilizatorului PHP. Nu se oferă colaboratorilor acces cPanel, FTP, chei de actualizare sau execuție de cod nerevizuit pe staging. PR-urile externe rulează numai în CI izolat. Pentru publicare delegată echipei sau preview-uri de PR pe hosting, stagingul se mută mai întâi într-un cont de sistem separat.

Stagingul trebuie restricționat echipei prin control de acces la nivel de hosting. `X-Robots-Tag: noindex, nofollow` este doar o măsură de neindexare. Configurațiile, sesiunile, stocarea, cheile, conturile și callback-urile OAuth rămân distincte. Nu se copiază baza de producție pentru teste. SMTP/AI rămân dezactivate inițial; conectoarele și imprimantele reale nu sunt conectate la staging.

Configurația stagingului păstrează validările de transport ale modului `production`, cu URL-ul `https://dev.openaliment.ro`; nu activează `environment=test`. Transportul email `test_file` este destinat exclusiv bazei dispensabile locale și nu se activează pe cPanel.

## Instalare și actualizări

Primul pachet de staging se extrage în rădăcina proiectului, **deasupra** `public/`. Baza și utilizatorul SQL confirmate de proprietar sunt `apelero_dev_openaliment`. Utilizatorul primește acces numai la această bază. Parola se completează numai în `config/local.php` pe hosting. Instalarea `/setup` se face cu tokenul privat de instalare, apoi tokenul se elimină din configurație. Nu se folosește contul SQL al producției.

Conexiunea HTTPS pentru staging se generează separat:

```sh
python tools/deploy/prepare.py --environment staging
python tools/deploy/client.py --config .tools/deploy/staging.json status
```

Fișierul generat `release/staging/openaliment-connect.php` se încarcă exclusiv în `dev.openaliment.ro/public/`. Clientul verifică asocierea dintre mediu și hostname, certificatul TLS și refuză redirecționările cu cheia de acces. Configurația implicită de producție și cheia ei nu sunt înlocuite. Endpointul cere o aplicație deja extrasă, cu `config/local.php` și `storage/`; nu este un installer SQL sau un shell.

Publicarea unui release este decisă de Alex Patrut. Se identifică exact commitul, versiunea și hashul pachetului, se verifică stagingul și se promovează același pachet. Configurațiile private nu sunt înlocuite de actualizările obișnuite. Nu se face `git pull` direct peste document root și nu se editează codul prin File Manager în paralel cu release-ul.

Migrările sunt noi și numerotate; cele aplicate nu se rescriu. Backupul, compatibilitatea temporară a codului și planul de revenire se verifică înainte de activare. Conexiunea actuală înlocuiește atomic fișiere individuale, nu întregul release; rollbackul codului nu inversează automat migrările sau datele.

## Conținut public și materiale private

Codul propriu și documentația software au licența din `LICENSE`. Licențele terților se păstrează. Arhiva OpenBread, datele producătorilor, configurațiile private, capturile administrative, dumpurile și logurile nu se adaugă în Git. Originalele rămân la locația lor existentă; importul lor continuă să folosească `handover_path` și manifestul de integritate.

Un clone obișnuit generează contractele fără arhiva istorică și testează importul schemei vechi cu un pachet sintetic. Dacă originalele sunt disponibile separat, se verifică și octeții lor; absența lor este raportată explicit. Integritatea originalelor nu se deduce din testele sintetice. Profilurile `full` și `pilot` necesită arhiva separată; profilul `app` este autonom.

Înainte de primul push și înaintea publicării se verifică lista efectiv urmărită de Git, nu doar `.gitignore`. Dacă un secret ajunge într-un commit, se revocă/rotește și se curăță istoricul înainte de distribuție; ștergerea într-un commit ulterior nu îl elimină din istoric.

Conectorii comunității pot avea repository-uri și release-uri proprii. Executabilele sunt distribuite ca artifacts/release-uri ale furnizorului, cu licențe, versiuni și compatibilitate API declarate, fără includerea lor în istoricul surselor platformei.
