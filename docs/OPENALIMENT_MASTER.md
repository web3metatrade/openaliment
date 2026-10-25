# OpenAliment — specificația activă

Versiune 1.1 · 23.10.2026 · domeniu `https://openaliment.ro` · fus de afișare `Europe/Bucharest`.

Acest document și contractele din `contracts/` aplică `OPENALIMENT_CHANGE_REQUEST_01.md`. Dosarul OpenBread este păstrat ca sursă istorică. Cerințele sale compatibile continuă să se aplice modulelor ulterioare; regulile înlocuite mai jos nu sunt folosite în paralel.

## Actualizare activă — marketplace și serializare, 25 octombrie 2026

Release-ul 0.7.0 adaugă marketplace-ul extensibil pentru conectori ERP, echipamente și alte aplicații ale comunității, fără acordarea automată a accesului la date. Oferta și fiecare versiune software au aprobări distincte; publicul vede numai revizia aprobată. Licența și condițiile fiecărei versiuni sunt păstrate în manifestul său. Plățile și achizițiile comerciale sunt inactive cât timp proiectul este în formare.

Migrarea aditivă 008 separă identitățile ambalajelor și comenzile de tipărire de datele alimentului. GTIN + serie este unic și comparat binar; lotul și versiunea etichetei sunt păstrate în snapshot. QR-ul conține URI-ul HTTPS complet, AI 01/10/21 și datele aplicabile AI 11/15/17. Credentialele echipamentelor sunt limitate la un singur echipament și revocabile. Expedierea datelor, confirmarea echipamentului și retipărirea sunt acțiuni diferite. Nu se retrimit automat comenzile cu rezultat incert.

Referințele normative fixate sunt URI Syntax 1.7.0, GS1-Conformant Resolver 1.2.1, EPC Compression 1.0.0 și General Specifications 26.0.0. PDF-urile oficiale sunt păstrate nemodificate, cu proveniență și SHA-256, în afara document root. Nu sunt relicențiate sub MIT. [Catalogul și procedura](standards/GS1.md), [contractele operaționale](api/pages/marketplace.md) și [starea implementării și probele](implementation/MARKETPLACE_GS1_0.7.0.md) sunt normative pentru această extensie. Existența codului nu presupune activarea migrării sau certificarea fizică a unui POS; raportul consemnează separat starea reală.

## Extensie planificată — 24 octombrie 2026

[Planul complet de implementare](OPENALIMENT_IMPLEMENTATION_PLAN_2026-10-24.md) completează această specificație cu aplicația consumatorului și finalizarea tuturor modulelor discutate. Include 28 de pachete, 56 de cerințe, 12 dependențe externe și [132 de scenarii de acceptare](plans/OPENALIMENT_ACCEPTANCE_MATRIX_2026-10-24.md), urmărite în [backlogul structurat](plans/openaliment-backlog-2026-10-24.json).

Planul păstrează rutele, contractele și istoricul existente. Identitatea personală, ambalajele din gospodărie, datele alimentare structurate, bonurile, sincronizarea și clienții Android/iPhone/web sunt extensii cu versiuni explicite și migrări aditive. Planificarea lor nu schimbă starea implementării descrise mai jos; contractele și dovezile se actualizează punctual la executarea fiecărui pachet. Funcțiile indicate mai jos ca etape distincte au acum responsabili și criterii de finalizare în plan, fără a fi considerate deja livrate.

## Servicii și prioritate

A: găzduirea informațiilor aprobate despre produs/articol/lot, pagini persistente, QR și API ERP. Nu cere rețetă, publicarea procesului, licență deschisă sau evaluare.

B: publicare voluntară a rețetei/procesului și dovezilor, licență adoptată explicit pentru versiunea exactă. Publicarea unei pagini de produs nu acordă drepturi de reutilizare asupra rețetei sau fișierelor sale.

C: evaluarea unui dosar înghețat, potrivit standardului și profilului de categorie. O verificare GTIN, publicarea unei pagini, eliberarea unui lot și acordarea unei sigle sunt decizii distincte.

Prima implementare prioritizează A și fundația B, feedback, propuneri și AI. Fluxul complet de evaluare OB-01–OB-24 și emiterea siglei rămân etape distincte; nu există emisie automată sau interfață care pretinde acordarea lor.

## Identități și relații

`products` identifică alimentul generic și categoria. `trade_items` identifică varianta comercială. `product_info_versions` păstrează snapshotul explicit publicabil al numelui, categoriei, ingredientelor, alergenilor, cantității, operatorului, păstrării, nutriției, originii și documentelor selectate. Aprobarea fixează versiunea și hashul; actualizarea înseamnă o înregistrare nouă. Indicatorul `current_info_id` al articolului se schimbă la publicarea noii versiuni.

`recipe_versions` păstrează separat formula și procesul, inclusiv datele generice vechi în snapshot. Rețeta poate lipsi, poate fi privată sau poate avea publicare/licență explicită. Niciun endpoint public de etichetă/lot nu serializează automat rândul rețetei.

`batches.product_info_version_id` este obligatoriu; `batches.recipe_version_id` este nullable. Articolul, eticheta, rețeta opțională și lotul trebuie să fie ale aceluiași producător și produs. FK compuse protejează relațiile critice în plus față de autorizarea din servicii. Codurile de lot sunt șiruri comparate binar; `LotA` și `LOTA` sunt distincte. Datele calendaristice nu sunt convertite de fusul orar.

Publicarea aprobă pagina lotului, fără eliberare automată. Eliberarea are acțiune, rol și eveniment proprii. Alertele sunt suprapuneri curente, cu mesaje publice, stare și istoric. Închiderea unei alerte nu este o nouă eliberare. Rechemarea nu este anulată automat. Modificarea etichetei curente nu schimbă snapshotul loturilor anterioare.

## Acces și securitate

Organizația vine din membership/sesiune sau din tokenul serverului. Un `tenant_id` din JSON nu conferă acces. Roluri inițiale: `owner`, `quality`, `editor`, `viewer`. Owner/quality aprobă publicarea, eliberarea și alertele; editor creează date și ciorne; viewer citește. Administratorul platformei verifică identitatea organizației și mandatul GS1, fără acordare implicită de competență pentru evaluarea alimentară.

Parole hash-uite, rotația ID-ului de sesiune la login, cookie HttpOnly/Secure/SameSite, CSRF pentru mutațiile cu sesiune, tokenuri API hash-uite cu scope și expirare, rate limit, prepared statements native PDO, escaping HTML, CSP fără cod inline. Formularul public de feedback nu acordă autoritate asupra datelor aprobate; este limitat și idempotent. Fișierele sunt private, numite de server, descărcate autorizat ca atașamente; HTML/SVG activ și executabilele nu sunt acceptate la uploadul obișnuit.

Numai `public/` este document root. `config/local.php`, arhiva și storage sunt în afara lui. Instalarea web folosește tokenul configurat privat și se blochează după primul cont/lock. Nu există credențiale universale. Configurația reală și datele locale de test nu intră în release.

## GS1 și adrese persistente

Rute native: `/p/{id}` pentru produs, `/t/{id}` pentru articol, `/b/{id}` pentru lot, `/p/{id}/v/{version_key}` pentru documentația explicit publică, `/d/{id}` pentru fișierul permis. Identificatorii noi sunt 128 biți aleatori, reprezentați prin 32 de caractere hexazecimale. UUID-urile și identificatorii istorici rămân în proveniența importurilor, cu mapare explicită; nu există rute implementate anterior de păstrat.

Rute GS1 inițiale: `/01/{gtin14}` și `/01/{gtin14}/10/{lot_encoded}`. Registrul de drepturi acceptă un singur articol autorizat per GTIN; candidaturile neverificate nu rezervă global identificatorul. Calculul cifrei de control este separat de verificarea mandatului. Generarea Digital Link este activată numai pentru drepturi verificate și profilul de transport validat pe hosting; altfel se generează QR nativ, fără modificarea codului lotului.

Actualizarea 02 implementează resolver GS1 1.2.1 pentru profilul declarat GTIN (01), cu calificatorii săi, negociere, linkset și decompresie EPC. Registrul versionat și migrarea 002 sunt separate de snapshoturile etichetelor. Matricea normativă și limitele sunt în `GS1_IMPLEMENTATION.md`. Domeniile producătorilor au loc rezervat în model, dar activarea lor cere verificarea controlului, TLS și rutarea hostului pe infrastructură. Nu se acceptă Host sau destinații arbitrare din cerere.

## Feedback și AI

Recenzii, întrebări, idei și sesizări sunt asociate produsului, versiunii informațiilor și opțional lotului. Nota este facultativă. Originalul este privat; numai copia revizuită poate deveni publică. Notele mici sunt eligibile. Semnalele locale de calitate rutează spre revizuire restricționată fără AI. Scanarea nu dovedește achiziția.

Propunerile au jurnal de decizie separat și nu modifică eticheta/rețeta/lotul. Voturile au unicitate per cont verificat; nu se importă identități sau privilegii dintr-un fișier de transfer.

Workerul AI primește numai feedbackul deja revizuit și versiunea publicabilă exactă a etichetei. Nu interoghează rețetele private. Ieșirea strictă este verificată pentru schemă, identități, citări și autoritate. ACL/hashul contextului se reevaluează înainte de salvare. Rezultatul rămâne draft pentru om. Coada are lease, încercări limitate și plafon de joburi/zi; un apel real necesită modelul, cheia și bugetul configurate. Plafonul de joburi nu este o limită monetară garantată. Nu se afirmă testarea serviciului extern fără apel real.

AI este dezactivat implicit; toate paginile QR, citirea, salvarea și alertele funcționează independent. Fără cron/CLI, workerul se poate rula manual din cPanel dacă furnizorul oferă această funcție; nu există endpoint public de execuție.

## Panificație și pilot

`contracts/profiles/bread.json` delimitează hidratarea, prefermentul, dospirea și bilanțul aluatului. Nu se solicită aceste câmpuri în fluxul unui iaurt, suc sau conserve. OB-STD 0.1 rămâne standard propus de documentare pentru panificație, cu cele 24 de cerințe păstrate. Nu este transformat în standard universal de alimente prin redenumire.

PBT: import privat, R1 experimentală unică, 3.790 g intrări nominale, drepturi neconfirmate, fără loturi/recenzii/rezultate/GTIN/siglă inventate. COST3 este estimare de scenariu; COST1 și COST2 își păstrează rolul istoric. Originalele, extrasele și recuperările sunt păstrate cu hash/proveniență. BT01 incomplet nu se extrage și nu se publică drept probă de tipar.

## Performanță și operare

PHP 8.4+ suportat, MySQL/MariaDB InnoDB, PDO utf8mb4, Bootstrap 5 local, JavaScript simplu, SVG pentru iconuri și QR. Serverul nu cere Node/Composer/root. Nu se încarcă SDK/AI pe traseul scanării; nu se scrie un contor DB la fiecare scanare; vizitatorii fără cookie nu pornesc sesiune pentru lectura paginii QR. Interogările folosesc identități indexate, proiecții compacte și documente la cerere.

Pagini de lot/alerte: `Cache-Control: no-store`; fără service worker care să ascundă retragerile. Resursele statice sunt cacheabile separat. Pentru trafic mare: cache de snapshoturi imuabile, cache/CDN cu invalidare verificabilă pentru metadate, pool DB/read replica și infrastructură dedicată când măsurătorile o cer. Nu promitem milioane de scanări pe planul shared fără testarea limitelor de procese, I/O, conexiuni și trafic ale furnizorului.

Backup DB + storage + configurație privată, criptare/copie externă, restore într-un mediu separat și păstrarea mapărilor QR. RPO/RTO se stabilesc după măsurători. Mutațiile ERP sunt tranzacționale și idempotente; fără proces AI sincron în HTTP.

## Etape și dovezi

1. CR01 și contracte active; integritatea pachetului.
2. Fundație și primul flux A, API, QR nativ/Digital Link limitat, import/export, PBT privat, feedback și AI draft.
3. Editor structurat complet al dosarului, cele 24 de secțiuni, evaluatori eligibili, registru și flux de siglă; alte profiluri de categorie numai după definire și adoptare.
3a. Actualizare 02: resolver complet pentru profilul GTIN, UI/API destinații, export de istoric, landing comercial și SEO; probe locale și HTTPS documentate.
4. Importuri specifice furnizorilor, domenii proprii; test de tipar, restore și capacitate pe infrastructura efectivă.

`VERIFICATION.md` diferențiază testele executate de scenariile istorice și de probele necesare pe cPanel. O verificare structurală a arhivei nu validează pâinea sau aplicația de producție.
