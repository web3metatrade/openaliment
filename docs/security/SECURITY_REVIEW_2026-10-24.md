# Analiza securității OpenAliment — 24 octombrie 2026

Stare: analiză și recomandări; remedierile de mai jos nu sunt implementate prin acest document. Baza analizată este codul local 0.5.2 și un eșantion de răspunsuri publice HTTPS de pe openaliment.ro. Raportul trebuie tratat intern până la remedierea constatării SEC-01; nu îl publica automat într-un issue public.

Starea ulterioară a implementării, inclusiv diferența dintre verificarea locală și publicarea pe hosting, este în [raportul release-ului 0.5.3](../implementation/SECURITY_HARDENING_0.5.3.md). Constatările de mai jos rămân evidența auditului inițial.

## Concluzie și priorități

Prioritatea este corectarea autorizării pe rutele HTML de administrare. Am confirmat local că un token cu numai `read` poate crea un produs, publica o etichetă și descărca exportul folosind aceste rute. Același token este refuzat corect de API v2 pentru creare. Protecția CSRF nu înlocuiește permisiunea operației.

Nu am confirmat acces între organizații, acces anonim la date private, SQL injection sau execuție de cod prin upload. Aceasta este limita constatărilor, nu o certificare a absenței vulnerabilităților.

| ID | Prioritate / impact | Constatare | Nivelul dovezii |
|---|---|---|---|
| SEC-01 | P0 / ridicat | Ocolirea scopes prin paginile HTML de administrare | Reprodus prin HTTP local, cu date sintetice |
| SEC-02 | P1 / mediu | Parole de până la 200 de octeți acceptate peste limita efectivă bcrypt de 72 | Reprodus pe PHP local 8.4.27; mecanism prezent în cod |
| SEC-03 | P1 / mediu | HSTS absent din răspunsurile publice verificate | Observat prin șapte cereri HTTPS publice |
| SEC-04 | P1 / mediu | Înregistrările temporare SQL nu au un mecanism de curățare și cote complete | Confirmat prin analiza codului; volumul real nu a fost inspectat |
| SEC-05 | P1 / întărire | Sesiuni administrative fără expirare absolută, MFA și invalidare centrală | Confirmat în implementarea analizată |
| SEC-06 | P1 / întărire cu impact mare | Accesul de mentenanță are autoritate de actualizare a codului și valabilitate implicită 90 zile | Confirmat în șablon și generator; nu este o compromitere demonstrată |
| SEC-07 | P2 / mediu la scară mare | Protecția disponibilității diferă între API, HTML și operații costisitoare | Confirmat în cod; fără teste de încărcare pe hosting |
| SEC-08 | P2 / întărire | Jurnalizare insuficientă pentru detectarea abuzului și incidente | Confirmat la nivel de aplicație; logurile furnizorului neverificate |
| SEC-09 | P2 / întărire | Validarea fișierelor și a importurilor trebuie unificată; lipsesc scanarea și eliminarea metadatelor foto | Confirmat în cod; fără execuție malițioasă demonstrată |
| SEC-10 | P2 / întărire | Contractul HTTP, erorile de bootstrap și antetele nu sunt uniforme | Confirmat în cod și la endpointul de diagnostic public |
| SEC-11 | P2 / întărire | Publicarea GitHub necesită verificări automate pentru secrete și vulnerabilități de dependențe | CI inspectat; nu este confirmată o scurgere de secrete |
| SEC-12 | P2 / operațional | Restaurarea independentă, retenția datelor personale și izolarea hostingului trebuie verificate explicit | Verificare necesară; fără acces la administrarea cPanel |

P0 înseamnă remediere înaintea extinderii accesului terților. P1 înseamnă următoarea intervenție de securitate. P2 înseamnă consolidare înaintea creșterii volumului. Impactul și prioritatea sunt evaluate pentru această platformă; nu sunt scoruri CVSS calculate.

## Metodă și limite

Am urmărit autentificarea, autorizarea pe rol/scopes/organizație, sesiunile, OAuth, Acasă, fișierele, import/export, resolverul, AI, mentenanța HTTPS, configurarea web și CI. Am citit implementarea și am executat probe punctuale în baza separată `openaliment_tests`, fără resetarea acesteia.

Pe producție am făcut numai GET anonim, fără urmărirea redirecționărilor, la `/`, `/api/docs`, `/login`, `/setup`, `/hosting-check.php`, `/assets/api-docs.css` și `/01/09520000000011`. Nu am creat conturi, chei, produse sau emailuri pe producție și nu am testat acolo ocolirea permisiunilor. Nu am schimbat codul aplicației, configurarea, DNS-ul sau baza de producție.

Probe și antete fără secrete: [security-review-2026-10-24.json](../verification/security-review-2026-10-24.json). Scripturile de reproducere sunt locale, în `.tools/security-review/`, excluse din Git și din pachete. Organizația, utilizatorul, cheia și produsele sintetice au fost eliminate după verificare. Raportul JSON nu păstrează valoarea cookie-ului primit la GET `/login`.

Nu au fost auditate configurația efectivă a PHP/LiteSpeed, permisiunile contului cPanel și ale utilizatorului DB, backupurile, WAF/CDN, SMTP/DNS, toate dependențele pentru toate CVE-urile sau istoricul Git. Nu am citit secretele de producție pentru această analiză. Nu am făcut teste de volum, fuzzing agresiv ori simulări de exfiltrare pe hosting.

## SEC-01 — scopes ocolite prin administrarea HTML

**Dovezi:** `app/Controllers/ManageController.php:11` folosește `Auth::principal()` cu scope implicit `read`. POST verifică CSRF la linia 13, apoi apelează comenzile de domeniu. `app/Core/Auth.php:28` și `:29` verifică rolul pentru scriere/aprobare, fără scope-ul tokenului. `Catalog::publishLabel()` verifică rolul de aprobare. Exportul HTML de la `ManageController.php:49` nu cere scope `export`.

**Rezultat local:** un browser fără utilizator autentificat a primit o sesiune anonimă cu CSRF. Cu aceeași sesiune și o cheie `read` a unui owner sintetic, API v2 de creare a răspuns 403 `scope`, dar comanda HTML de creare a răspuns 303 și a creat produsul. Comanda HTML de publicare a răspuns 303, iar citirea publică a etichetei a confirmat HTTP 200. Exportul HTML a răspuns 200 cu schema de transfer. Nu este nevoie de parola ownerului pentru acest caz; este necesară o cheie/grant `read` valid și rolul curent potrivit al emitentului.

**Impact:** o integrare care ar trebui să citească poate modifica/publica datele organizației care i-a acordat accesul. Sesiunea CSRF aparține apelantului și nu dovedește dreptul de publicare. Nu s-a demonstrat acces la alt tenant. `ResolverManageController.php:9` și `IntegrationManageController.php:9` prezintă același tipar de admitere a tokenului; impactul complet al fiecărei acțiuni trebuie inclus în regresie, fără a presupune că toate au fost probate.

**Remediere:** rutele HTML de administrare să accepte numai sesiunea de utilizator în browser și să respingă explicit Bearer, inclusiv combinația sesiune validă + Bearer. Integrările folosesc rutele API documentate. În plus, autorizarea comenzilor de domeniu să verifice central acțiunea, rolul, scope-ul și organizația, cu politică implicită de refuz; această verificare trebuie să acopere și comenzile de import/export, alerte, documente și resolver. Nu este suficientă înlocuirea tuturor verificărilor cu un singur `write`: publicarea, alertele, importul și exportul au permisiuni distincte. Principiul verificării la fiecare acces este descris de [OWASP Authorization](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html).

**Compatibilitate și acceptare:** fără schimbarea identificatorilor, URL-urilor QR sau DB. Se păstrează API v1/v2 și formularele autentificate. Orice utilizare nedocumentată a Bearer pe HTML va primi 403 și trebuie mutată pe API. Matrice HTTP pentru fiecare acțiune: anonim, sesiune, sesiune + Bearer, cheie și OAuth cu fiecare scope, rol viewer/editor/quality/owner, grant revocat și alt tenant. Un `read` nu poate produce schimbări, publica, închide alerte sau exporta pe nicio rută.

## SEC-02 — parola acceptată diferă de parola verificată integral

**Dovezi:** `app/Domain/Accounts.php:9` acceptă lungimi 12–200 prin `strlen`, iar linia 11 folosește `PASSWORD_DEFAULT`. Pe runtime-ul local acesta este bcrypt. Proba a confirmat că două șiruri diferite, cu aceiași primi 72 de octeți și sufixe diferite, trec verificarea aceluiași hash. Comportamentul este documentat în [manualul PHP pentru password_hash](https://www.php.net/function.password-hash). Limita este în octeți, relevantă inclusiv pentru diacritice.

**Impact:** sufixul unei parole lungi este ignorat. Acest fapt nu permite ghicirea primilor 72 de octeți și nu reprezintă acces anonim general, dar contrazice politica afișată și poate slăbi parolele cu prefix comun.

**Remediere:** serviciu unic pentru parole. Preferabil Argon2id, după verificarea suportului și calibrarea pe hosting. Dacă bcrypt rămâne necesar, noile parole trebuie limitate explicit la 72 de octeți, cu validare identică pe server și mesaj corect în interfață. Loginul trebuie să primească un string cu limită explicită; să nu convertească arbitrar array-uri și să nu permită intrări nebounded. Hashul dummy pentru utilizator necunoscut, acum cost 10 la linia 15, trebuie aliniat cu algoritmul/costul real pentru reducerea diferențelor de timp; această enumerare nu a fost măsurată pe producție.

**Migrare:** păstrarea verificării hashurilor bcrypt existente; migrare controlată la autentificare sau schimbarea parolei. Nu se poate recupera sufixul ignorat dintr-un hash existent. Pentru parole legacy peste limită este necesar un flux explicit de înlocuire; nu se trunchiază și nu se schimbă în tăcere parolele. Teste pentru limite 71/72/73 octeți, UTF-8 și compatibilitatea conturilor existente.

## SEC-03 — HSTS absent

**Dovezi:** niciunul dintre cele șapte răspunsuri HTTPS eșantionate nu a inclus `Strict-Transport-Security`. Codul trimite CSP, nosniff și protecție anti-încadrare; redirecționarea la HTTPS există în `public/.htaccess`, însă HSTS nu este configurat acolo.

**Remediere:** HSTS la nivelul serverului, inclusiv pentru resurse statice și răspunsuri de eroare, cu verificare pe LiteSpeed. Activare graduală cu durată scurtă, apoi creștere după validarea TLS. `includeSubDomains` numai după inventarierea subdomeniilor, iar preload numai ca decizie separată. HSTS instruiește browserul să folosească HTTPS după recepționarea politicii; mecanismul și consecințele preload sunt explicate de [OWASP HSTS](https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Strict_Transport_Security_Cheat_Sheet.html).

**Acceptare:** antet prezent pe HTTPS pentru pagini, redirect resolver, API, statice și 404; dezvoltarea locală HTTP continuă să funcționeze. Nu se modifică destinațiile QR.

## SEC-04 — retenția datelor temporare și cote de autentificare

**Dovezi:** `Auth::rate():34` adaugă în fiecare fereastră înregistrări în `rate_limits`; nu am găsit o ștergere pentru acestea. `tools/worker.php:5` curăță numai fișierele limiterului nou. Cererile OAuth se păstrează în `api_oauth_requests` (`OAuth.php:62`), refresh-urile creează noi rânduri în `api_oauth_tokens` (`:129`), iar linkurile de conectare în `home_login_tokens` (`HomeIdentity.php:13`). Cheile de idempotentă rețin răspunsurile în `Mutations.php:30`. Expirarea accesului există; eliminarea înregistrărilor expirate nu există în implementarea analizată.

**Impact:** creșterea bazei, date personale păstrate inutil și degradarea serviciului în timp. Limitele per IP reduc viteza, fără să limiteze volumul total sau atacuri distribuite. Cele 20 de aplicații/utilizator și 30 de chei active/organizație sunt controale existente, dar nu limitează toate cererile pending, granturile, dispozitivele ori refresh-urile păstrate.

**Remediere:** cleanup privat în loturi mici, cu indici pe expirare și un raport al ultimei execuții, separat de activarea AI. Cote pentru cereri OAuth pending/client și IP, granturi active/cont/client, dispozitive și bugete totale. Rate-limit înregistrare și email după IP, destinație și buget global, cu notificare pentru operator la abuz. Alternativă cu buget foarte mic dacă hostingul nu oferă cron; QR-ul nu trebuie să depindă de curățare sau SMTP.

**Compatibilitate:** păstrarea informației despre refresh folosit cât timp grantul asociat poate fi reutilizat, pentru a menține detectarea replay. Retenția idempotentei trebuie publicată înainte de schimbare; după expirare, aceeași cheie nu mai poate promite rezultatul vechi. Nu se șterg istoricul loturilor, alertele ori auditul comercial în același job. Teste cu ceas controlat, relații FK, job repetat și volum limitat per rulare.

## SEC-05 — protecția conturilor privilegiate fără fricțiune pentru Acasă

**Dovezi:** `Auth.php:7` aplică numai inactivitate de două ore. `Session::login():12` păstrează `authenticated_at`, dar nu există expirare absolută a sesiunii sau registru de sesiuni producător care să permită revocarea tuturor dispozitivelor. Nu există MFA și flux complet de schimbare/recuperare a parolei. Crearea/rotația cheilor are deja confirmare recentă a parolei prin `ApiKeys::recent()`.

**Remediere:** expirare absolută și reautentificare pentru administrarea platformei, schimbarea datelor de cont și emiterea accesului privilegiat; MFA/passkey pentru administratori și, opțional, owneri. Adăugarea unui mecanism de revocare globală a sesiunilor și a unui flux sigur de recuperare. Separarea parolei confirmate recent de vechimea sesiunii, pentru a păstra limitele clare. Recomandările privind durata și reautentificarea sunt în [OWASP Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html).

**Acasă:** păstrarea conectării persistente solicitate de produs. Cele 90 de zile glisante nu sunt clasificate singure drept vulnerabilitate. Se pot adăuga rotație coordonată a credentialului de dispozitiv, revocare, notificări de conectare și confirmare prin email numai pentru acțiuni sensibile. Pentru dispozitive partajate este utilă o opțiune clară de deconectare cu eliminarea copiei locale; deconectarea actuală păstrează alimentele local în mod intenționat (`public/assets/home/cloud.js`). Nu se șterg implicit date offline nesincronizate.

**Compatibilitate:** registrul/versiunea sesiunilor necesită migrare aditivă. Revocarea trebuie să funcționeze pe server; blocarea doar prin JS nu este suficientă. Teste pentru sesiune furată simulată, expirare, logout, recuperare și toate granturile afectate.

## SEC-06 — accesul de mentenanță HTTPS

**Dovezi:** `tools/deploy/bridge.template.php:148` acceptă o cheie Bearer administrativă proprie în antet, cu hash pe server și termen de expirare. Generatorul configurează implicit 90 de zile. Căile sunt limitate, există verificare hash, backup, rollback și refuzul symlinkurilor. Totuși, dreptul de a publica PHP este efectiv drept de execuție cu identitatea aplicației, chiar dacă protocolul nu are o comandă SQL/shell.

**Remediere:** ferestre scurte de deployment, rotație/expirare controlată și restricție de rețea când operatorul are adrese stabile. Pentru automatizare durabilă: manifest semnat, verificare a identității release-ului și, dacă se alege autentificare HMAC, timestamp + nonce persistent împotriva replay. Un simplu hash de fișier transmis de același apelant nu autentifică autorul release-ului. Limitarea/rotația copiilor și a jurnalelor, înregistrarea încercărilor respinse la nivelul serverului și avertizarea la modificări neașteptate ale codului.

**Compatibilitate:** fluxul actual este cerut de operator și nu trebuie oprit arbitrar. Tranziție coordonată client/server, cu noua pereche verificată înaintea retragerii celei vechi. Nu am dezactivat sau rotit cheia și nu am publicat adresa privată ori credentialul în raport. Copiile bridge-ului nu înlocuiesc backupul independent.

## SEC-07 — disponibilitatea la scanări masive și abuz

**Dovezi:** limiterul nou are maximum 256 de fișiere și blocări, iar API are cote pe clase, paginare și limite de payload. Acestea sunt controale utile. Paginile HTML `/p`, `/t`, `/b`, descărcarea HTML și paginile de administrare nu trec toate prin aceleași cote ca API. `ResolverController.php:30` încarcă toate destinațiile publicate pentru lista globală de contexte. Rate-limitarea per IP nu plafonează costul total al unui botnet și poate afecta clienți legitimi din aceeași rețea.

**Remediere:** limitare la marginea hostingului/WAF pentru volume și conexiuni, plus bugete în aplicație pentru operații costisitoare. Listă de contexte materializată sau cache invalidat la publicare. Cache pentru proiecții publice aprobate și indexuri, cu invalidare sigură pentru alerte; înainte de activare, trebuie probat că o alertă curentă nu rămâne ascunsă de un cache vechi. Nu se cachează răspunsuri private ori sesiuni. Exporturi mari cu limite de concurență/durată, preferabil NDJSON sau job privat.

**Acceptare:** teste de încărcare pe staging, nu pe shared hosting activ; măsurarea memoriei, latenței și conexiunilor DB. Pagina QR și alertele funcționează când AI/SMTP/cleanup sunt indisponibile. Rate-limitarea trebuie testată și în spatele proxy-ului real; `trusted_proxy_cidrs` nu se setează universal.

## SEC-08 — detectarea incidentelor și auditul actorului

**Dovezi:** `public/index.php:35` înregistrează doar excepții neașteptate, cu clasă/cod și request ID. Refuzurile normale 401/403/419/429 și loginurile eșuate nu produc evenimente de securitate în aplicație. `Catalog::audit()` păstrează user/tenant/acțiune, dar nu credential ID, client ID sau tip de autentificare. Nu am inspectat logurile LiteSpeed.

**Remediere:** evenimente structurate pentru autentificare, refuzuri, emitere/rotație/revocare, consimțământ, modificarea drepturilor și deployment. Actorul trebuie să includă tipul accesului și ID-ul cheii/grantului/clientului, fără secret. Agregare și deduplicare pentru refuzuri, rotație și cotă de loguri, acces restrâns și notificări cu praguri. Mesaje externe generice; diagnostic intern suficient, cu redactare explicită a credentialelor, SMTP, payloadurilor private și datelor personale. Nu se adaugă logging integral al antetelor sau corpurilor.

**Acceptare:** un flux sintetic de incident poate fi urmărit prin request ID; exporturile de audit nu divulgă secrete și nici datele altui tenant. Logarea nu trebuie să umple discul la un atac.

## SEC-09 — fișiere, import și fotografii

**Dovezi:** uploadul normal verifică MIME și 15 MB (`Documents.php:6`), fișierele sunt private și descărcate ca atașament. Importul de transfer (`Portability.php:70`) verifică hashul și dimensiunea, dar calculează MIME fără aceeași listă de tipuri acceptate. Pilotul istoric are în mod legitim tipuri suplimentare și carantină. `HomePhotos.php:9` validează JPEG/dimensiuni și păstrează bytes originali; nu elimină metadate EXIF. Nu există scanare antivirus sau CDR în aplicația analizată.

**Remediere:** politică comună pentru tip, dimensiune, stare de scanare și publicare; diferență explicită între arhivare istorică privată și fișier publicabil. Scanare asincronă înaintea publicării; integrare opțională cu scanerul furnizorului sau un serviciu ales, fără instalare root obligatorie. Pentru fotografii personale noi, eliminarea metadatelor înainte de sincronizare și verificare server, cu limite de memorie. Originalele PBT trebuie păstrate nemodificate, inclusiv proveniența și avertizările despre corupere. Un hash confirmă integritatea, nu siguranța conținutului. Principiile sunt descrise de [OWASP File Upload](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html).

**Compatibilitate:** migrare aditivă pentru starea scanării; documentele existente nu sunt declarate automat curate. Fișierul istoric poate rămâne disponibil autorizat în arhivă privată, cu o proiecție publicabilă distinctă. Teste pentru import/upload echivalente, fișiere poliglot, MIME fals, EXIF și eșecul scanerului. Descărcarea ca atașament și CSP sandbox trebuie păstrate.

## SEC-10 — uniformitatea HTTP și a configurării

**Dovezi:** `Http::input():12` recunoaște JSON prin substring și, în rest, revine la formular. Nu am demonstrat o ocolire a schemei v2 prin aceasta; `ApiInput` continuă să valideze datele. Unele protocoale acceptă legitim formular, de exemplu token endpoint OAuth. `public/index.php:6` încarcă bootstrapul înaintea handlerului central de erori. Configurarea absentă revine la exemplu în `Config::load()`. `/hosting-check.php` este blocat corect după instalare (404 în producție), dar a trimis `X-Powered-By: PHP/8.4.27`, spre deosebire de front controller.

**Remediere:** parsare exactă a media type-ului și 415 pentru formate nesuportate, cu excepții documentate pentru OAuth și multipart. Limite de corp și adâncime uniforme, verificarea tipurilor înainte de cast și erori previzibile. Bootstrap/config verificat în mecanismul controlat de pornire, cu răspuns extern generic și diagnostic privat. Configularea de producție incompletă să fie refuzată explicit; mod instalare separat. Antete de securitate și eliminarea versiunii PHP la nivel server pentru toate entry pointurile.

**Compatibilitate:** inventarierea clienților v1 și formularelor înainte de a impune 415. Expunerea versiunii PHP este o observație de nivel redus, nu dovadă de acces sau motiv de schimbare a hostingului. Se mențin schema erorilor API și antetele necesare CORS/resolver.

**Destinații externe GS1:** `ResolverRegistry::target()` acceptă HTTP și HTTPS, cu verificări pentru credențiale în URL, localhost și IP-uri private literale. Preferă HTTPS pentru destinațiile nou publicate, cu excepții explicite și auditabile pentru parteneri care necesită HTTP. Destinațiile externe aprobate trebuie urmărite pentru expirarea domeniului ori schimbarea proprietarului. Un resolver care redirecționează browserul nu realizează prin aceasta un fetch pe server; nu clasific lipsa unei verificări DNS drept SSRF în fluxul actual. Dacă se adaugă ulterior preluarea automată a datelor de la alți furnizori, va fi necesară o politică separată pentru DNS, adrese private, redirecționări, timeout și mărimea răspunsului.

## SEC-11 — pregătirea publicării și lanțul de dependențe

**Dovezi:** `.gitignore` și builderul exclud configurarea privată, `.tools`, runtime și accesul de mentenanță generat. CI are permisiuni minime, acțiuni fixate prin SHA și `npm ci --ignore-scripts`. Totuși, nu are job dedicat de secret scanning, SAST PHP și verificare a advisories pentru bibliotecile incluse manual. `npm ci --no-audit` nu este el însuși o vulnerabilitate; lipsește verificarea separată. Manifestul include PHPMailer 7.1.1 și resurse cu hashuri. Nu am confirmat în acest audit un CVE aplicabil; [lista oficială PHPMailer](https://github.com/PHPMailer/PHPMailer/security/advisories) nu înlocuiește verificarea continuă a tuturor dependențelor.

**Remediere:** secret scanning înaintea primului push și apoi în CI, inclusiv istoricul dacă există; canal privat de raportare activ și contact real. SAST PHP cu reguli relevante pentru autorizare, SQL și fișiere, analiză JS și verificare OSV/advisories pentru inventarul complet. Actualizări automate controlate, inclusiv pentru componente copiate fără Composer. Inventar/SBOM și hashuri verificate la build; probele de autorizare negativă SEC-01 în CI. Nu se includ fixtures cu credentiale, chiar sintetice reutilizabile, în artefactele publice de test.

**Acceptare:** o cheie sintetică de test introdusă într-un fixture dedicat declanșează scanerul fără a apărea în log; pachetul verifică din nou absența secretelor. Rezultatele nu se confundă cu certificarea de securitate. Raportul curent de vulnerabilitate nu se publică înainte de remediere și verificare.

## SEC-12 — recuperare, confidențialitate și limitele shared hostingului

**De verificat cu operatorul/furnizorul:** backup criptat independent de contul care rulează PHP, retenție și restaurare probată într-o bază separată; drepturile DB minime și, unde hostingul permite, identitate separată pentru migrări; fișierele `config/*.local.php` și sesiunile PHP cu permisiuni restrictive, save_path privat aplicației; izolarea altor site-uri din același cont; TLS și actualizări PHP/MariaDB; bugete CPU/memorie/conexiuni și opțiuni WAF. Aceste proprietăți nu pot fi deduse din versiunile returnate de diagnostic.

**Confidențialitate:** ștergere controlată de cont Acasă, dispozitive, fotografii și invitații; politică de retenție distinctă pentru emailuri, loguri, bonuri, audit comercial și istoricul public al produselor. Aceste funcții sunt deja menționate ca incomplete în `docs/ACASA_SINCRONIZARE.md:33`. Se păstrează separat documentația publică și datele personale. Copiile deja exportate nu pot fi retrase de pe dispozitive străine. Nu se consideră exportul disponibil echivalent cu un flux de ștergere sau backup.

**Email:** verificarea SPF/DKIM/DMARC, limite de expediere și rotația credentialului dacă a fost divulgat; nu am confirmat o problemă DNS sau o divulgare în această analiză. SMTP folosește deja TLS cu verificarea certificatului. Nu se transmit parole prin chat ori email pentru a efectua verificarea.

**Acceptare:** exercițiu de restaurare documentat, obiective RPO/RTO alese de operator, recuperarea loturilor și alertelor verificată, ștergere Acasă testată fără afectarea altui cont. Migrații numai aditive și backup înaintea activării.

## Măsuri existente care trebuie păstrate

| Domeniu | Controale observate |
|---|---|
| Bază de date | PDO cu prepare real, utf8mb4, tabele permise explicit și relații tenant în model |
| Date publice | Etichete aprobate/versionate, lot separat de rețetă, proiecții publice, alerte curente |
| Sesiune | Cookie Secure/HttpOnly/SameSite=Lax, strict mode, regenerare ID la login, CSRF |
| API și OAuth | Hashuri pentru chei/tokenuri, expirare, revocare, PKCE S256, callback exact, refresh rotit și replay detection |
| Browser | CSP restrictiv, nosniff, frame-ancestors none, escaping HTML, JS fără interpolare HTML în fluxurile analizate |
| Fișiere | Stocare în afara public/, nume generate, dimensiuni/cote, download autorizat și atașamente |
| Acasă | Cookie personal separat, membership verificat, poze private, link single-use, tokenuri în fragment și eliminarea fragmentului din URL |
| PWA | Service worker limitat la shell/resurse; API, paginile produselor și alertele nu sunt cache-uite de acesta |
| AI | Context numai din proiecții publice moderate; fără interogare a rețetelor private; execuție separată de QR și fără publicare automată |
| Deployment | TLS, hashuri, căi permise, respingere symlink, backup/rollback, cheie în afara pachetului public |
| Instalare | `/setup` și diagnosticul returnează 404 după instalare în eșantionul verificat |

## Ordinea concretă a lucrului

1. **Corecție de autorizare:** SEC-01, inclusiv HTML, API legacy, comenzi de domeniu și teste negative; verificare pe staging înainte de publicare. Fără migrare DB pentru blocarea imediată a Bearer pe HTML.
2. **Parole și HTTPS:** SEC-02 și SEC-03, migrare compatibilă a parolelor și verificare reală a antetelor după publicare.
3. **Date temporare și protecția administratorului:** SEC-04, SEC-05, SEC-06, cu migrări aditive, retenție configurată și procedură de recuperare.
4. **Disponibilitate și observabilitate:** SEC-07 și SEC-08, cu probe de sarcină numai în mediu dedicat și invalidarea alertelor testată explicit.
5. **Fișiere, configurare și publicare:** SEC-09, SEC-10, SEC-11; păstrarea arhivei istorice și scanarea repository-ului înainte de GitHub.
6. **Operare verificată:** SEC-12, restaurare și controlul accesului pe hosting. Verificări de securitate repetabile în CI pentru fiecare release.

Fiecare etapă trebuie închisă cu dovezi de test și limitări explicite. Nu sunt necesare un framework nou, schimbarea URL-urilor persistente sau dependența paginii QR de AI pentru aceste îmbunătățiri.
