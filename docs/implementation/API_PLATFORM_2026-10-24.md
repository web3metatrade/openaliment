# Mecanismul API OpenAliment

Stare: mecanism API implementat, testat și publicat; migrarea 005 a fost aplicată pe producție la 24 octombrie 2026. Portalul documentației este în release 0.5.1. Punct de plecare: inventarul API din 24 octombrie 2026.

Livrarea acoperă mecanismul comun HTTP, limitele publice, cheile producătorilor, OAuth Authorization Code cu PKCE pentru aplicații publice, accesul Acasă delegat, resursele funcționale existente și lipsurile API ale interfeței actuale. Rutele v1, datele istorice, configurațiile private și resolverul GS1 se păstrează. Funcțiile de domeniu care nu există încă (evaluarea pentru siglă, notificări, OCR de bon, domenii proprii) nu sunt expuse ca endpointuri fictive; manifestul declară capabilitățile disponibile.

Ordinea execuției:

1. Migrare aditivă 005 pentru aplicații, autorizări, coduri, tokenuri și metadatele cheilor.
2. Acces, revocare, rate limiting, CORS și erori coerente.
3. Resurse publice și private, liste paginate, upload, feedback, voturi și rapoarte.
4. Autorizare web și OAuth PKCE, consimțământ, reînnoire și revocare; integrare Acasă.
5. Registru de operații, OpenAPI complet, manifest și exemple pentru dezvoltatori/AI.
6. Probe de izolare, scopes, revocare, retry, concurență și compatibilitate; pachet și publicare controlată.

Surse de securitate: [RFC 7636](https://www.rfc-editor.org/rfc/rfc7636), [RFC 7009](https://www.rfc-editor.org/rfc/rfc7009), [RFC 9700](https://www.rfc-editor.org/rfc/rfc9700), [OWASP REST Security](https://cheatsheetseries.owasp.org/cheatsheets/REST_Security_Cheat_Sheet.html).

## Livrare

- Contract agregat: 206 operații, 187 de adrese parametrizate, identificatori unici `operationId`; 120 de operații în noul registru central. Contractele v1, integrare, consumator și Acasă rămân disponibile.
- Migrare aditivă `005_api`: opt tabele pentru chei, aplicații, origini, cereri, granturi, coduri, tokenuri și indexul public al etichetelor aprobate. Migrările 001–004 și arhiva OpenBread nu sunt rescrise.
- Chei per organizație: numai owner, autentificare recentă sau reconfirmare, scopes minime, maximum 30 active, expirare 1–365 zile, secret nerecuperabil afișat o dată, rotație și revocare imediată, autorevocare și metadate de utilizare. Retry-ul emiterii păstrează numai metadatele; nu stochează secretul în istoricul de idempotentă.
- OAuth: aplicații publice, PKCE S256 obligatoriu, callback exact, consimțământ explicit pentru o singură organizație/familie, acces 15 minute, refresh rotit maximum 30 zile, grant maximum 90 zile. Refolosirea codului/refresh-ului și revocarea opresc accesul. Conturile, rolurile, aplicațiile și dispozitivele sunt reverificate la fiecare cerere. Nu sunt pretinse certificări OAuth/OIDC.
- API public: numai proiecții aprobate, catalog și căutare pe numele/categoria etichetei aprobate, documente autorizate, rețete publice cu licență, feedback moderat, alerte curente, vocabular, interpretare coduri și lookup de maximum 20 de produse. Nu există export anonim integral sau acces indirect la rețete private.
- API privat: produse, articole, versiuni de etichetă, loturi, QR, documente multipart, rețete, resolver, feedback/moderare, propuneri, voturi, audit, import/export, rapoarte, joburi AI, conectoare și volume/cote. Comenzile reutilizează regulile de domeniu ale interfeței. Administrarea platformei cere sesiune admin, fără acces prin chei ERP/granturi terțe.
- Acasă: granturi Bearer pentru familia aleasă, sincronizare și fotografii private, scope separat pentru administrare. Cookie-urile păstrează CSRF și aceeași origine. Cache-ul aplicației este actualizat la generația 6 fără ștergerea inventarului local sau a resurselor OCR.
- Abuz/resurse: bugete IP și credential, limite pentru corpuri, paginare maximum 100, cote per organizație pentru înregistrări/documente și spațiu; rollback SQL și curățarea fișierelor nou create la eșec. Limitarea publică folosește 256 de shard-uri cu intrări expirabile și plafon de capacitate. Proxy-urile nu sunt considerate de încredere implicit.
- Documentație: ghid românesc, referință căutabilă la `/api/docs`, OpenAPI 3.1, scheme JSON, manifest pentru consum explicit fără crawling, 58 de exemple validate, clienți Python/JavaScript și exemplu Android/Kotlin. Nu există un server MCP declarat implementat.

## Compatibilitate

Rutele v1 și paginile existente se păstrează. Versiunile istorice ale etichetelor și loturilor nu se înlocuiesc; alertele rămân un strat curent. Importurile continuă să intre în spațiul privat și nu acordă drepturi GS1 sau sigle.

Restrângere intenționată: publicarea directă a unei rețete ori propuneri cere scope `publish` pe lângă `write` și rolul autorizat. O integrare afectată trebuie să primească o cheie nouă sau un grant nou aprobat; nu extindem automat permisiunile vechi. Rotația invalidează imediat cheia anterioară, fără suprapunere implicită. Noile rute v2 validează strict câmpurile; formatele v1 sunt păstrate.

## Probe executate

La 24 octombrie 2026, runnerul local a încheiat 27 de suite și 1.103 verificări, fără eșec: lint PHP/JavaScript, contracte generate, ambalare, domeniu, HTTP, două organizații, scopes, revocare/rotație, PKCE, cod/refresh refolosit, CORS, CSRF, cote, idempotentă, fișiere la rollback, Acasă și regresiile produs/lot/QR/resolver GS1. Setul API adaugă 53 de verificări de domeniu, 86 HTTP și câte șase probe reale pentru clienții Python și JavaScript. Datele, DB și transportul email sunt izolate de producție. Proba symlink specifică Windows este omisă când sistemul nu permite crearea lui; nu este raportată ca executată.

Separat, cele 58 de exemple de cerere din OpenAPI agregat au fost validate cu JSON Schema Draft 2020-12, fără erori. Exemplul Kotlin nu a fost compilat ca APK. Nu au fost executate probe de trafic la capacitatea hostingului, certificări externe sau publicare în magazinele de aplicații.

## Publicare și activare

70 de fișiere au fost actualizate prin conexiunea HTTPS cu verificarea baseline-ului înaintea scrierii și verificarea hashurilor după scriere. Configurațiile DB/SMTP, secretele, datele producătorilor și originalele pilot nu au fost încărcate sau suprascrise. Workerul existent a fost comparat separat înaintea adăugării curățării contoarelor expirate. Copiile pentru revenire și jurnalele private sunt păstrate.

Loturi de publicare: `a7c381a2b47b4b548a050bd99f82c6ad`, `9e39af7c0be44ebbb795394193e341c2`, `a289bd2152974442bdd7393f9b705873`.

21 de probe HTTPS pe producție au trecut fără autentificare, fără granturi/chei reale și fără scrierea datelor de produs: manifest, hash OpenAPI, ghid, SDK, scheme, vocabular, refuzul accesului privat, CORS, HEAD, pagina Acasă, lookup PBT și linkset GS1. Documentația căutabilă și operația de rotație au fost verificate și în browser. Testul vizual localhost a fost blocat de politica browserului; verificarea vizuală s-a făcut pe HTTPS, după publicare. Aceasta nu afectează probele HTTP locale executate de runner.

**Activare confirmată pe hosting:** migrarea 005 a fost aplicată din sesiunea administratorului în `/admin/updates`, la 24 octombrie 2026. Panoul confirmă disponibilitatea cheilor revocabile și a autorizării aplicațiilor. Manifestul raportează `oauth.available=true` și `consumer_oauth=true`; noul catalog răspunde 200. Au trecut toate cele 21 de probe `python tools/qa/api_smoke.py`, fără `--pending-schema`. Nu au fost create chei, aplicații sau granturi reale pentru verificare. SQL-ul nu a fost executat prin puntea de upload. [Dovada activării](../verification/API_ACTIVATION_2026-10-24.md) consemnează limitele verificării pe producție.

Rapoartele locale/private sunt în `tests/runtime/reports/qa-summary.json`, `api-platform-domain.json`, `api-platform-http.json`, `api-sdk-python.json`, `api-sdk-javascript.json` și `api-production-smoke.json`. Captura publică a paginii este `tests/runtime/screenshots/api-docs-production.jpg`. Acestea nu includ chei reale și nu intră automat în release; fixtures-urile sintetice și configurațiile de test rămân excluse.
