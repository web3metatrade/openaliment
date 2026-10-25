# Colaborare GitHub și medii OpenAliment

Repository: `https://github.com/web3metatrade/openaliment`. Administrator inițial: `web3metatrade`. Repository-ul este privat în etapa de pregătire; publicarea presupune verificarea conținutului și a licențelor materialelor distribuite.

## Cod, review și pachete

Fiecare modificare se dezvoltă într-un branch scurt `feature/...` sau `fix/...` și ajunge în `main` prin pull request. PR-ul descrie problema, comportamentul rezultat, verificările și compatibilitatea. Modificările nucleului GS1 indică ediția standardului, secțiunea și probele de regresie. Nu se schimbă fixture-urile doar pentru a accepta o implementare incorectă.

Workflow-ul CI folosește PHP 8.4, Node 24, Python 3.12 și baze MariaDB 10.6/11.4 dispensabile. Nu primește configurația sau credentialele hostingului. După verificările reușite pe `main`, construiește profilul `app` și păstrează ZIP-ul plus SHA-256 ca artifact identificat prin commit. Nu publică pe hosting. Probele browser se pot selecta la pornirea manuală a workflow-ului.

La configurarea regulilor repository-ului, `main` trebuie să ceară verificările CI și review, să interzică force push și ștergerea și să invalideze aprobarea când se schimbă codul. Protejarea unui repository privat și regulile de mediu depind de planul GitHub; nu se consideră active până când interfața confirmă aplicarea lor. Pentru un repository public, protecția branchurilor este disponibilă și pe GitHub Free.

`CODEOWNERS` desemnează inițial administratorul. Autorul unui PR nu își poate aproba propriul PR: înainte să cerem obligatoriu aprobarea code owner-ului pentru toate modificările, adăugăm un al doilea maintainer de încredere sau atribuim zonele corespunzător. Aprobarea codului nu acordă drept de publicare în producție.

## Medii și acces

| Mediu | Origine | Document root | Date |
| --- | --- | --- | --- |
| Dezvoltare locală / CI | loopback | `public/` | `openaliment_tests`, exclusiv dispensabilă |
| Staging | `https://dev.openaliment.ro` | `/home/apelero/dev.openaliment.ro/public/` | bază și utilizator SQL proprii, date sintetice |
| Producție | `https://openaliment.ro` | `/home/apelero/openaliment.ro/public/` | configurația și datele existente |

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
