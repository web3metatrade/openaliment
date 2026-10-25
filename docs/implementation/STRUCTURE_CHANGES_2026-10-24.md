# Organizarea proiectului pentru contribuții

Data: 24 octombrie 2026. Bază: [auditul structurii](../STRUCTURE_AUDIT_2026-10-24.md). Scop: îmbunătățirile utile pentru colaborare, cu păstrarea MVC-ului și a contractelor existente.

## Schimbări aplicate

| Zonă | Rezultat |
| --- | --- |
| Colaborare | MIT pentru codul propriu, `CONTRIBUTING.md`, `SECURITY.md`, reguli de conduită, șabloane issue/PR și changelog. |
| Drepturi separate | `LICENSING.md` și inventarul componentelor terțe; arhiva, imaginile și materialele producătorilor nu sunt relicențiate automat. |
| Fișiere publicabile | Ignorarea configurațiilor de test locale, runtime-urilor, cache-urilor, capturilor, imaginilor de lucru, dependențelor instalate și fișierelor uzuale de autentificare. |
| Convenții | `.editorconfig` și `.gitattributes`; excepții pentru originale, dependențe, standarde și migrări cu hashuri de proveniență. |
| Pornire PHP | `app/autoload.php`, `app/bootstrap.php` și `app/Support/view_helpers.php` separă încărcarea claselor, configurarea și afișarea. Configurația explicită de test nu este înlocuită cu cea privată. |
| Teste | Bootstrap comun, exemplu public de configurare, transport email simulat și verificarea DSN-ului plus a bazei efectiv conectate. |
| Unelte | Manifeste cu versiuni fixate pentru Node/Python și metadate Composer fără biblioteci runtime suplimentare. |
| Runner | `tools/qa/run.py`: verificări rapide, suite locale în ordine, server loopback propriu și oprirea lui la final. Resetarea DB cere `--reset-test-db`. |
| Date de test | Bon sintetic public, generator de imagini sintetice și rapoarte noi numai în `tests/runtime/`. |
| Generatoare | Implementări în `tools/build/`, comenzi vechi păstrate ca adaptoare, verificare în director temporar prin `--check`. |
| Model logic | 43 de tabele și migrările 001–004, cu hash pentru setul complet de surse; fără presupunere despre DB instalată pe un anumit hosting. |
| Distribuție | Versiune în `release.json`, selecție explicită de fișiere, profiluri `app`/`full`/`pilot`, ZIP determinist pentru aceiași octeți de intrare și verificarea hashurilor. |
| CI | Workflow cu acțiuni fixate la commituri, permisiuni read-only, MariaDB 10.6/11.4 și opțiune manuală pentru browser. Nu conține secrete sau publicare pe hosting. |
| Documentație | Index, hartă a codului și ghid de dezvoltare în engleză, cu punct de intrare din README. |

## Compatibilitate

Rutele, namespace-urile claselor existente, tabelele, migrările aplicate și fișierele private își păstrează locațiile. Directorul `app/` nu este redenumit în `src/`. Importul PBT folosește aceeași cheie `handover_path`; fără arhivă, importul răspunde controlat, iar celelalte funcții rămân disponibile.

`public/index.php` folosește `require_once` pentru bootstrap, ceea ce permite serverului de test să injecteze mai întâi configurația izolată. În cererea normală configurația se încarcă din aceleași fișiere private ca înainte. Funcțiile de afișare păstrează numele și rezultatele existente, iar fusul orar rămâne Europe/Bucharest.

Comenzile vechi ale generatoarelor și `tools/package-release.py` rămân valabile. Varianta implicită de pachet rămâne `full`; noile nume ale arhivelor folosesc versiunea explicită și profilul. Schema modelului logic este metadată de dezvoltare, actualizată la v1.2; schemele API și de transfer nu sunt modificate prin această reorganizare.

## Verificare și stare

Verificările și eventualele publicări ale acestui increment sunt consemnate în [raportul de reorganizare](../verification/structure-reorganization-report.json). Rapoartele de lucru și logurile rămân private în `tests/runtime/`.

Au trecut 914 verificări în fluxul local din copia curată, în 23 de pași. Verificarea statică a fost repetată după corectarea filtrării căilor relative pentru o copie aflată sub un director runtime. Sursa finală, care verifică și că o configurație goală explicită nu încarcă fișiere private, a trecut 915 verificări în cei 23 de pași. Cele cinci probe de selecție/determinism a pachetului au fost repetate pe constructorul final. O probă de mentenanță pentru legături simbolice a fost omisă din cauza limitelor Windows; nu este prezentată ca trecută.

Pe producție au fost publicate șapte fișiere prin trei loturi HTTPS, cu hashuri verificate. Trei pagini anonime au rămas identice byte cu byte și alte 11 verificări read-only pentru Acasă au trecut. Nu au fost aplicate migrări DB, modificate configurații private sau trimise emailuri.

Instalarea din package-lock.json cu scripturile dezactivate a fost verificată, iar Playwright și sharp s-au încărcat la versiunile fixate. Suita browser nu a fost reluată în acest increment, care păstrează resursele și markup-ul paginilor.

Workflow-ul GitHub este pregătit pentru un repository, dar nu este prezentat drept CI executat pe GitHub sau drept probă Linux în lipsa acelei execuții. Licențele și drepturile materialelor istorice și ale imaginilor se verifică înaintea publicării lor în repository. Nu a fost creat sau publicat un repository GitHub în acest increment.

Nu au fost introduse framework-uri, biblioteci runtime suplimentare, mutări de date private sau restructurări generale ale domeniului/rutării. Formatările ample, schimbările de namespace și un strat nou de servicii rămân schimbări separate, justificate de modificări concrete.
