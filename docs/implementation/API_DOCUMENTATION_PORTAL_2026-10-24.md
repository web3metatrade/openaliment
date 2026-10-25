# Portalul documentației API — 0.5.1

Stare: implementat, publicat și verificat pe HTTPS la 24 octombrie 2026. Pagina principală: [openaliment.ro/api/docs](https://openaliment.ro/api/docs).

## Organizare

Portalul are 13 ghiduri și pagini proprii pentru toate cele 206 operații din OpenAPI. Ghidurile integrează și sursele tehnice existente pentru resolverul GS1, conectoare, Acasă, transfer și API v1; nu sunt numai rezumate. Navigarea separă API public, produse, etichete, loturi, documente, resolver, feedback, conectoare, import/export, Acasă, chei, OAuth, identitate, AI, administrare, descoperire și compatibilitate v1. Căutarea include titlurile, metodele, adresele, identificatorii operațiilor și textul ghidurilor.

Fiecare pagină oferă „Copiază pagina”, „Deschide în ChatGPT”, „Deschide în Claude” și „Vezi ca Markdown”. Copierea citește reprezentarea Markdown a paginii curente dintr-un template HTML inert, cu caracterele HTML escapate, astfel încât Clipboard API să fie apelat în contextul gestului utilizatorului, fără un fetch care poate pierde activarea în unele browsere. Există fallback cu text selectabil când browserul refuză accesul la clipboard. Codul are copiere separată și file de limbaj cu navigare prin tastatură.

Linkurile AI conțin numai adresa publică a documentației Markdown și o cerere de explicații. Nu transferă sesiunea, chei, cookie-uri sau date private. Nu sunt create conversații automat. Exemplul interactiv citește numai PBT demonstrativ, fără autentificare sau mutații.

Sunt disponibile `/api/docs.md`, pagini individuale `.md`, negociere `Accept: text/markdown`, `/api/docs/index.json`, `/llms.txt` și `/llms-full.txt`. Corpusul complet consumă 24 de unități din bugetul de documentație, adică maximum cinci cereri/minut la limita implicită de 120. Ancorele istorice principale și fișierul `/api/guide.md` rămân utilizabile.

## Implementare și compatibilitate

Generator nou: `tools/build/api_documentation.py`, inclus în constructorul contractelor. Sursele sunt ghidul menținut, introducerea proprie și OpenAPI. Fișierul compilat este `contracts/api-documentation.json`. Runtime-ul folosește controllerul dedicat, `ApiDocumentation`, cele două views și CSS/JavaScript proprii, fără biblioteci ori servicii noi pe hosting. Iconurile sunt SVG.

Nu se schimbă rutele/payload-urile funcționale, permisiunile sau datele istorice. Contractul agregat precizează acum `X-CSRF-Token` ca obligatoriu pentru mutațiile care cer exclusiv sesiune; execuția impunea deja această regulă. Descrierea comună a autorizării este în română. Nu se aplică o migrare DB pentru acest release. Migrarea 005 a mecanismului API rămâne un pas separat, fără a fi aplicată prin această reorganizare.

## Verificări

Activarea ulterioară a migrării API 005 pe producție este consemnată separat în [raportul de activare](../verification/API_ACTIVATION_2026-10-24.md).

Suita completă a încheiat 29 de suite cu 2.433 verificări și zero eșecuri, inclusiv produs/lot, resolver GS1, Acasă, conectoare, autorizare, chei și portabilitate. Toate cele 219 pagini au fost verificate ca HTML și Markdown, inclusiv egalitatea conținutului Copy Page și contextul AI per pagină. Exemplele Python sunt parsate fără executarea cererilor.

După precizarea CSRF și ajustările finale de căutare, au fost repetate probele afectate: 435 verificări de documentație/domeniu și 886 HTTP, fără eșec. Cele 21 de contracte generate nu au drift. Pe producție au trecut 21 de probe generale API/QR și cinci probe specifice portalului/Markdown/indexurilor, fără autentificare sau scrieri de date.

În browser au fost verificate meniul, raportarea succesului Clipboard API, căutarea, pagina de rotație, filele de cod, apelul PBT și navigarea mobilă. La viewport 390 × 844, navigarea pornește restrânsă și pagina nu are overflow orizontal; override-ul a fost resetat. Conținutul copiat este verificat prin paritatea template–Markdown în testele HTTP; citirea clipboard-ului prin unealta de browser a returnat gol, astfel încât nu este pretins un test de lipire în altă aplicație. Linkurile AI sunt validate, fără trimiterea automată a unui prompt către servicii terțe.

## Publicare

Primul lot HTTPS a verificat 13 fișiere: `b2b9e286881940c8bbe2be3b2b043ccc`. Ajustarea contractului, indexului și căutării a verificat trei fișiere: `56a48dcd6f524dcf9ef25fe7ded9255a`. Corpusul cu sursele detaliate a fost publicat în lotul `bf09f8a99b184436a0df38c13ac44eb4`, după repetarea acelorași probe de documentație. Hashurile au fost comparate cu baseline-ul anterior și verificate după scriere; copiile și jurnalele de revenire sunt păstrate privat. Configurațiile DB/SMTP și documentele PBT nu au fost încărcate sau suprascrise.

Rapoarte: `tests/runtime/reports/qa-summary.json`, `api-documentation-domain.json`, `api-documentation-http.json`, `api-documentation-production.json`, `api-production-smoke.json`. Capturi: `tests/runtime/screenshots/api-portal-desktop.jpg` și `api-portal-mobile.jpg`. Datele de test/configurațiile private sunt excluse din distribuție.

Surse și întreținere: [docs/api/README.md](../api/README.md). Organizarea este inspirată de [referința Snipe-IT](https://snipe-it.readme.io/reference/api-overview), cu texte și implementare proprii OpenAliment.
