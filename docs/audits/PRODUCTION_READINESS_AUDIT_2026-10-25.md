# Audit de implementare și pregătire pentru producție — OpenAliment

25 octombrie 2026 · cod auditat: **0.5.5** · mediu vizat: cPanel, PHP 8.4+, MariaDB, document root `/home/apelero/openaliment.ro/public/`.

**OpenAliment are o aplicație funcțională și publicată, dar planul integral nu este finalizat, iar acceptarea unei lansări comerciale complete nu este demonstrată.** Nucleul produs–articol–etichetă–lot–QR, resolverul pentru profilul GTIN, API-ul și o parte consistentă din Acasă sunt implementate și testate. Dosarul alimentar complet, evaluarea pentru siglă, notificările, sursele externe și aplicațiile native rămân lucrări de dezvoltare. Restaurarea, monitorizarea, capacitatea, livrarea emailului și probele fizice rămân lucrări de verificare și operare.

Recomandarea este continuarea cu un **pilot controlat al funcțiilor existente**, după închiderea condițiilor operaționale și de protecție a datelor de mai jos. Publicarea unui site, un răspuns HTTP 200 sau o suită locală trecută nu închid aceste condiții. Funcțiile nelivrate rămân în planul complet; auditul nu le elimină și nu aprobă o reducere de scop.

Acest audit nu modifică aplicația, configurațiile, utilizatorii, DNS-ul sau datele din producție. Nu s-au trimis emailuri, creat produse de test pe hosting ori executat teste de încărcare în producție.

## 1. Ce a fost comparat

Punctul de pornire a fost [START_HERE.txt](../../OpenBread_Codex_v1_2026-10-23/START_HERE.txt), urmat de [masterul original OpenBread](../../OpenBread_Codex_v1_2026-10-23/OPENBREAD_MASTER.md), contractele și criteriile originale relevante. Pachetul inițial era o specificație și o arhivă de produs, nu o aplicație care trecuse deja acceptarea.

Au fost corelate:

- [Cererea de schimbare OpenAliment](../../OPENALIMENT_CHANGE_REQUEST_01.md), [masterul activ](../OPENALIMENT_MASTER.md) și [nivelul resolverului](../GS1_IMPLEMENTATION.md).
- [Planul integral](../OPENALIMENT_IMPLEMENTATION_PLAN_2026-10-24.md), cele **56 de cerințe**, **28 de pachete**, **132 de cazuri de acceptare** și **12 dependențe externe** din [backlog](../plans/openaliment-backlog-2026-10-24.json).
- Cele **49 de scenarii OpenBread** și **16 cazuri QR** originale, urmărite separat de scenariile noi. `AT-001` din arhivă și `AT001` din planul extins sunt identificatori diferiți.
- Codul PHP/JavaScript, cele șase migrări și 52 de tabele, contractele API, testele, pachetele și rapoartele de publicare disponibile.
- Auditurile anterioare de [structură](../STRUCTURE_AUDIT_2026-10-24.md), [UI/UX](../ux/UI_UX_REVIEW_2026-10-24.md), [securitate](../security/SECURITY_REVIEW_2026-10-24.md), [remedierea 0.5.3](../implementation/SECURITY_HARDENING_0.5.3.md) și [soluțiile suplimentare propuse](../security/SECURITY_IMPROVEMENT_PLAN_02_2026-10-24.md).

Registrul integral al constatărilor este [JSON-ul auditului](production-readiness-2026-10-25.json). Pentru lectură: [situația fiecărui pachet, cerință și acces extern](IMPLEMENTATION_COVERAGE_2026-10-25.md) și [acoperirea tuturor scenariilor de acceptare](ACCEPTANCE_COVERAGE_2026-10-25.md). Aceste registre adaugă evaluarea actuală fără să rescrie stările istorice ale specificațiilor.

### Reguli de interpretare

Instrucțiunile explicite ale utilizatorului au prioritate. Au fost aplicate următoarele clarificări:

| Decizie | Consecință pentru audit |
|---|---|
| OpenAliment pentru toate alimentele; OpenBread rămâne inițiativa pentru pâine | Nu se solicită hidratare/preferment tuturor categoriilor și nu se redenumesc originalele. |
| QR independent de rețetă, licență și siglă | Lotul fără rețetă este comportament cerut, nu lipsă de documentație obligatorie. |
| Resolver cerut ulterior explicit | Limitarea istorică la o pagină Digital Link este depășită de implementarea resolverului pentru profilul declarat. |
| ERP și magazin simulate acceptate explicit | R25/R26, WP13 și AT071/AT072 se verifică prin contracte/mapări și simulatoare generice; lipsa unui contract cu un partener fix nu blochează acest scop software. |
| Probele fizice și migrarea de la un furnizor real | Nu sunt înlocuite de simulatoare. Citirea POS, tiparul și un export privat real rămân dovezi distincte. |
| Fără conturi Apple Developer/Google Play | Blochează distribuția nativă, nu pagina QR sau web/PWA. Nu înseamnă că aplicațiile native există. |
| Licența codului adoptată: MIT | Recomandarea AGPL din arhiva veche nu se aplică automat codului actual. Rețetele, imaginile și componentele terțe își păstrează drepturile distincte. |
| ONG în curs de înființare; Alex Patrut, fondator și contact | Clarificarea publică este aplicată. Nu substituie stabilirea responsabilului real, a documentelor contractuale și a procedurilor de protecție a datelor. |

Matricea veche conține încă formulări precum „nicio integrare cerută nu este înlocuită cu simulator”. Registrul nou consemnează excepția autorizată pentru ERP/magazin, fără să falsifice o probă comercială sau să relaxeze celelalte cerințe.

## 2. Dovezi obținute în acest audit

| Verificare | Rezultat | Limită |
|---|---|---|
| Suita locală completă, cu reset explicit al DB izolate `openaliment_tests` | **40 de suite, 11.337 verificări, zero suite eșuate** | Windows, PHP 8.4.27 și MariaDB 11.4.10. Nu este o nouă execuție a matricei Linux/MariaDB 10.6. |
| API public prin HTTPS | **21 verificări trecute** | Cereri delimitate, fără chei reale ori mutații. |
| Portal și referință API publicată | **42 verificări trecute** | Contract, Markdown, exemple, SDK, Postman și capabilități; nu sesiuni autentificate ale tuturor rolurilor pe hosting. |
| Anteturi și suprafețe publice de securitate | **52 verificări trecute** | Nu reprezintă pentest independent sau căutare intruzivă de vulnerabilități. |
| Conținut public și corpus API | **247 verificări trecute** | Compară sursa publicată cu documentația menținută; nu este studiu de utilizabilitate. |
| Inventar de vulnerabilități OSV | **49 versiuni de pachete, zero avize returnate** | `qrcodegen.js` este snapshot cu hash și necesită revizie upstream manuală. OSV nu acoperă întreaga configurație cPanel/PHP/OS. |
| Descoperire publică | Status `operational`; OCR, sincronizare și bon digital disponibile | Lista de cumpărături nu se sincronizează; surse externe, API notificări, evaluare siglă și domenii proprii sunt declarate indisponibile. |
| Catalog public, cerere fără filtre cu `limit=1` | **Zero articole returnate, `has_more=false`** | Nu este inventar al datelor private. Demonstrația PBT este separată; nu există în această probă un articol comercial public care să demonstreze pilotul cerut. |

Totalul verificărilor HTTPS reluate este **362**. Numărul mare al verificărilor locale include multe verificări de documentație, scheme și proprietăți; nu trebuie citit ca 11.337 fluxuri umane sau ca procent de acoperire a întregului plan.

Dovezile sunt păstrate în [directorul auditului](evidence/2026-10-25/): [QA local](evidence/2026-10-25/local-qa.json), [API](evidence/2026-10-25/production-api.json), [referință](evidence/2026-10-25/production-api-reference.json), [anteturi](evidence/2026-10-25/production-headers.json), [conținut](evidence/2026-10-25/production-content.json), [OSV](evidence/2026-10-25/dependency-advisories.json), [manifest/capabilități](evidence/2026-10-25/production-discovery.json) și [inventar cu hashuri](evidence/2026-10-25/inventory.json).

Nu au fost inspectate în această execuție baza de date de producție, contul cPanel, configurația sa privată, backupurile sau întregul filesystem al serverului. Codul local este 0.5.5; dovezile de publicare anterioare și răspunsurile publice concordă pentru suprafețele verificate. Nu se afirmă că fiecare fișier de pe hosting a fost recitit și comparat în acest audit.

## 3. Unde suntem față de produsul cerut

| Componentă | Situație actuală | Ce împiedică închiderea completă |
|---|---|---|
| Produs, articol, etichetă și lot | Implementate independent de rețetă; snapshoturi, aprobare, documente și alerte | Producător non-pâine cu date reale aprobate, exercițiu de operare, tipar și telefon real. |
| Identificatori și drepturi | Validare GTIN și decizie administrativă de mandat distinctă | Cifra de control nu dovedește proprietatea. Trebuie verificat un mandat real și procedura de contestare/retragere în exploatare. |
| Resolver GS1 | Funcții de resolver, nu simplă redirecționare; profil GTIN `01`, calificatori și EPC documentați | Revizie normativă independentă, monitorizare, continuitate, capacitate și probe fizice. Nu toate cheile GS1 și nu certificat GS1. |
| API și documentație | 208 operații, 17 ghiduri, 225 pagini; chei revocabile, scopes, OAuth PKCE, SDK, Markdown și acces explicit pentru AI | Acceptare de integrator nou, CI real, politici de deprecare și funcțiile viitoare care încă nu au API. |
| Acasă | Fără cont la prima utilizare, inventar local, scanare, data OCR, porții, fotografii, istoric și sync opțional | Recuperare și email reale, ștergere, roluri limitate și testare pe dispozitive reale. |
| Bonuri și ERP | Conector generic, mapping versionat, preview/ingest, simulatoare și bon digital privat | Fotografie de bon/OCR linii, retenție; transport webhook semnat dacă este introdus. Agentul nu citește singur orice bază ERP. |
| Notificări | Alertele pot fi citite în pagină/API | Nu există motor de memento, Web Push, APNs/FCM, digest/email sau preferințe de livrare. |
| Alimente din afara OpenAliment | Adăugare manuală posibilă | Nu există integrare cu resolvere externe/Open Food Facts; standardul nu garantează că fiecare cod găsit are informație publică completă. |
| Documentație alimentară deschisă | Snapshot de rețetă/proces JSON, documente, licență explicită și import | Editorul complet, cele 24 secțiuni, costuri/calcul, furnizori, măsurători, afirmații și reproducere. |
| Evaluarea pentru siglă | Criterii în arhivă; nimeni nu primește automat sigla | Fluxul de depunere/evaluare, emitent/evaluatori, conflicte, registru, OB-24 atomic, contestații și revocare. |
| Feedback și AI | Recenzii, întrebări, voturi și propuneri; `feedback_triage` cu surse și rezultat draft | Vot retras/versionat și note pe criterii; celelalte cinci joburi, RAG/căutare, contabilizarea costului și probă reală de provider. |
| Import/export | Import JSON, export JSON/NDJSON, hashuri/istorice; copie personală | Import NDJSON reluabil, deduplicare de blob/proveniențe, arhivă publică autonomă, round-trip complet, export real de furnizor și ștergere. |
| Domenii ale producătorilor | Rezervare în model | Verificare control DNS/HTTP, TLS, rutare/izolare pe host, revalidare și continuitate la migrare. |
| Aplicații native | Web/PWA; exemplu de client Kotlin | Nu există builduri Android/iOS, conturi, semnare, aprobări sau publicare în magazine. |
| Operare și scalare | Site online, limite, loguri, instrucțiuni și worker | Restore, cron, livrare email, alerte externe, staging și măsurarea capacității nu au dovada de închidere. |

### Ce s-a păstrat din OpenBread și ce încă trebuie dezvoltat

Originalele Pâine Bună Tehnic, rețeta R1, scenariile COST1/COST2/COST3, extrasele și istoricul cercetării sunt păstrate cu proveniența lor. Importatorul clasifică materialele și nu transformă estimările în rezultate. R1 rămâne experimentală; intrările nominale de 3.790 g și contabilizarea inoculului trebuie păstrate. BT01 deteriorat/recuperat nu devine probă de ambalaj validat.

Pâinea publică demonstrativă folosește GTIN-13 `9520000000011`, forma GTIN-14 `09520000000011` și lotul `DEMO-PBT-01`, separat de registrul comercial. Nu reprezintă identificator comercial atribuit, lot alimentar eliberat, termen validat sau dovadă că importul privat PBT a fost aplicat efectiv în DB de producție.

Partea cea mai mare rămasă din proiectul inițial este **dosarul tehnic și evaluarea lui**, nu pagina QR. Snapshotul JSON de rețetă nu este editorul structurat al celor 24 secțiuni. Tabelele pentru evaluări, standarde adoptate, decizii și acreditări nu există încă în cele șase migrări. Nu trebuie afișată o siglă pentru a compensa această lipsă.

Pentru AI, sunt prevăzute `document_map`, `dossier_gap_check`, `feedback_triage`, `feedback_digest`, `experiment_proposal` și `public_qa`. În cod există numai `feedback_triage`. Limitarea numărului de joburi/zi nu este un plafon monetar complet. Trebuie înregistrate versiunea promptului/modelului, sursele exacte, tokenii/costul și bugetele; implementarea actuală nu demonstrează întregul mecanism din plan.

Există și diferențe mai mici, concrete, față de criteriile originale. `Community::vote` folosește `INSERT IGNORE` la activare și `DELETE` la retragere; nu are starea `withdrawn` și `mutation_version` cerute de **AT-049**. Un retry vechi poate reactiva un vot retras. Feedbackul are o singură notă, fără modelul și agregatele separate pe criterii din acel scenariu. Soluția este o migrare aditivă pentru vot versionat/retragere și validare/agregare pe criteriu, păstrând compatibilitatea rutelor și necunoscutul distinct de zero.

La import, repetarea aceluiași pachet este recunoscută, însă **AT-015** cere suplimentar reutilizarea blobului după tenant + SHA-256, cu proveniențe multiple; această deduplicare nu există în modelul actual al documentelor. **AT-041/AT-045** cer și un pachet public autonom, lizibil, licențiat și utilizabil independent, nu doar exportul privat al producătorului. La round-trip trebuie verificate explicit licențele, atribuirea, timpii și referințele: revenirea în ciornă pentru reverificarea autorității nu trebuie să piardă metadatele istorice păstrate astăzi în payloadul importului.

Pentru bonuri, linkul actual este o capabilitate confidențială: cine îl primește îl poate folosi până la expirare. Aceasta este documentația comportamentului livrat; nu dovedește restricționarea la cumpărătorul identificat din toate formulările AT070. Contractul efectiv de acces și distribuirea linkului trebuie armonizate înainte de acceptare, păstrând adăugarea simplă fără cont acolo unde această politică este acceptată.

## 4. Ce trebuie făcut, în ordinea utilă

**P0** înseamnă condiție pentru extinderea controlată a operării cu utilizatori/date reale în scopul relevant. **P1** înseamnă funcție sau probă obligatorie pentru promisiunea completă a platformei. **P2** înseamnă întreținere și îmbunătățire care nu trebuie să blocheze singură QR-ul public. Prioritățile nu sunt o listă de vulnerabilități demonstrate.

| ID / prioritate | Soluție concretă | Responsabil de desemnat | Dovadă de închidere |
|---|---|---|---|
| PROD-01 · P0 | Creează staging separat, cu DB/storage/config distincte, indexare blocată și email/push extern oprite implicit. Testează instalare nouă și upgrade 001–006 pe MariaDB 10.6. | OPS + BE + QA | Instalare și upgrade repetabile, date reconciliate, nicio atingere a producției. |
| PROD-02 · P0 | Backup coordonat DB + storage + configurație + release/mapări QR, criptat și copiat în afara contului. Restaurează-l într-un mediu gol. | OPS | Timp măsurat, hashuri/documente și QR funcționale, alerte/revocări reconciliate; RPO/RTO aprobate și probate. |
| PROD-03 · P0 | Instalează monitorizare externă pentru pagină/descriptor/resolver/DB-ready, TLS/domeniu, erori, spațiu/cote, backup și worker. Separă logul de audit de erorile aplicației. | OPS + BE | Alertă primită efectiv de persoana de gardă, exercițiu de incident și runbook executabil. |
| PROD-04 · P0 | Verifică cPanel cron și calea PHP CLI pentru workerul limitat; programează cleanup și măsoară ultima execuție. Nu expune un executor de comenzi public. | OPS + BE | Heartbeat în interval, coadă/cleanup procesate, cron oprit produce alertă. |
| PROD-05 · P0 | Probează magic link în inboxuri reale și pe al doilea telefon; verifică SPF/DKIM, aliniere DMARC, TLS, bounce și rate limits. | OPS + QA | Email primit, link single-use/expirat, scannerul de email nu consumă linkul; conectare și sync reale. Autentificarea SMTP din 24.10 nu este suficientă. |
| PROD-06 · P0 | Stabilește cine operează și contractează efectiv înaintea înființării ONG-ului; finalizează termeni, privacy, roluri de prelucrare, furnizori, retenții, moderare/contestații, suport și incidente. | OWNER + juridic | Documente aplicabile serviciului real și proceduri efectiv executabile. Necesitatea DPIA se stabilește pe datele/funcțiile reale, nu presupunând automat că este sau nu obligatorie. |
| PROD-07 · P0 pentru conturi personale | Implementează ștergere de cont/date cu confirmare recentă, transfer/închidere gospodărie, retenția fotografiilor/bonurilor și tratamentul backupurilor. Păstrează separat istoricul public justificat. | BE + OWNER + QA | Cerere de acces/export/ștergere rezolvată pe cont și gospodărie, fără datele altor membri; restaurarea nu anulează cererile deja executate. |
| PROD-08 · P0 pentru administrare | Întărește accesul privilegiat: MFA și recuperare sigură, revocare sesiuni, confirmări recente pentru chei/destinații; verifică user DB runtime cu privilegii minime și storage/session privat. | SEC + BE + OPS | Probe negative HTML/API și recuperare fără ocolirea factorului; document de privilegii și accesuri nominale. Nu se cer pași suplimentari pentru citirea publică a QR-ului. |
| PROD-09 · P0 operațional | Inventariază accesul de mentenanță HTTPS și expirarea reală; rotește secretele expuse vreodată și închide accesul temporar inutil după release. Protejează cPanel și copiile locale. | OPS + SEC | Accesurile și expirările documentate privat, test de refuz și procedură de reactivare. Un endpoint care poate publica PHP are autoritate de release. |
| PROD-10 · P0 pentru documente publice noi | Adaugă stare de scanare/carantină legată de hash și adaptor AV/CDR potrivit hostingului; nu trimite documente private către servicii publice implicit. | BE + SEC + OPS | Fișier ostil, MIME fals, timeout și hash schimbat nu ajung publice; originalele istorice nu sunt rescrise. |
| PROD-11 · P0 înainte de volum contractual | Măsoară resolverul și API-ul în staging cu date realiste, NAT comun și modificări de alerte în timpul testului. Dimensionează sau mută infrastructura dacă shared nu trece. | OPS + BE + QA | Raport latență/erori/cost, limite furnizor și prag de extindere; fără promisiune de milioane de scanări bazată doar pe arhitectură. |
| PROD-12 · P0 pentru pilot comercial | Parcurge cu un producător non-pâine fluxul articol → etichetă aprobată → lot fără rețetă → QR; schimbă eticheta și emite/închide o alertă. | QA + producător + OWNER | Date reale autorizate, mandat GTIN, istoric V1/V2, AI oprit și scanare pe telefon; tipar/POS separat. |
| PROD-13 · P1 | Construiește dosarul structurat complet și registrele de formule/proces/furnizori/cost/măsurători/încercări/afirmații, cu licențe, versiuni și profil de panificație separat. | BE + FOOD | WP16, cazurile AT076–AT083 și cerințele istorice aferente probate fără estimări prezentate drept rezultate. |
| PROD-14 · P1 | Construiește evaluarea și registrul siglei: depunere, evaluator eligibil, conflict, dovezi, decizie atomică, verificare publică, revocare și contestație. | BE + OWNER + evaluator | WP17, AT084–AT093; emitent/standard adoptat și dosar eligibil real înainte de acordare. |
| PROD-15 · P1 | Livrează notificările și coada: local, Web Push, APNs/FCM/email, deduplicare, anulare după consum, ore liniștite și preferințe. | BE + MOBILE + OPS | AT055/058–AT065, inclusiv refuz și provider/cron căzut; fără date sensibile pe lockscreen implicit. |
| PROD-16 · P1 | Implementează sursele externe cu client HTTP securizat, proveniență/licențe, rezolvarea conflictelor și confirmare umană pentru necunoscute. | BE + SEC + FOOD | Două resolvere independente și sursa publică aleasă; SSRF/DNS/redirect/timeout probate înainte de activare. |
| PROD-17 · P1 | Completează OCR bon, coduri locale/greutate, intrarea vocală, lista comună și sugestiile de consum/alternative cu reguli de siguranță. | BE + MOBILE + UX + FOOD | Bonuri și telefoane reale; ambiguitățile cer minimul de confirmări, fără lot/termen inventat sau recomandarea unui produs retras. |
| PROD-18 · P1 | Completează AI/RAG și cele șase joburi cu ACL pe context, citări, audit prompt/model/cost, bugete și aprobare umană. | BE + AI + SEC | Corpus privat sintetic, prompt injection, ACL schimbat în timpul jobului, eșec provider și apel real autorizat; QR continuă independent. |
| PROD-19 · P1 | Completează portabilitatea: import NDJSON mare/reluabil, round-trip complet și adaptor pe un export autorizat real; implementează domeniile proprii DNS/TLS. | BE + OPS + QA | AT103–AT110, pierderi explicite, drepturi reverificate, controlul domeniului și QR vechi păstrat după migrare. |
| PROD-20 · P1 | Construiește și distribuie aplicațiile native dacă se închide planul integral; semnare, deep links, upgrade și declarații de confidențialitate. | MOBILE + OWNER + QA | Builduri reale și instalări din Apple/Google; PWA sau fișierul Kotlin nu înlocuiesc această probă. |
| PROD-21 · P1 | Închide auditul UI/UX și accesibilitatea pe stări populate, nu numai pagini goale/demo. Testează publicul țintă. | UX + QA | Padding/axe/tabele/API/mobile corectate; 20 persoane, pragul de 3 s și teste de tastatură/cititor/zoom/telefon. |
| PROD-22 · P0 înainte de GitHub; apoi continuu | Publică repo curat numai după scanarea secretelor/drepturilor; activează CI real, verificări obligatorii, raportare privată de vulnerabilități și inventar de release. | LEAD + SEC + OWNER | Pipeline GitHub executat pe 10.6/11.4, scanare tree/istoric/artefacte și licențe; fără `.tools`, config privat, DB dump sau capturi administrative. |
| PROD-23 · P2, împreună cu următorul release | Unifică statusul documentelor, metadatele de versiune, deprecările API și changelogul; păstrează rapoartele istorice datate. | LEAD + BE | README fără număr vechi de operații; `package.json` 0.4.0 explicat/sincronizat cu sursa de adevăr 0.5.5; un singur registru actual de acceptare. |
| PROD-24 · P0 pentru declararea lansării complete | Execută acceptarea pe versiunea finală, pilotul și cele 14 zile de stabilizare; predă suportul, accesurile și responsabilitățile. | QA + OPS + OWNER | Registru cu rezultate per caz, incidente închise, restore și semnarea deciziei de lansare. |
| PROD-25 · P1 | Completează votul versionat/retras, refuzul retry-urilor vechi și notele/agregatele pe criterii; completează deduplicarea documentelor și arhiva publică autonomă. | BE + QA | AT-049, AT-015, AT-041 și AT-045 probate; fără rescrierea loturilor, pierdere de proveniență sau schimbarea drepturilor. |

Rolurile din tabel sunt responsabilități de atribuit, nu persoane deja angajate sau confirmate. Niciun element nu se închide numai prin scrierea unui document sau prin existența unui buton în UI.

## 5. Verificări obligatorii la fiecare lansare în producție

Această listă se aplică release-ului concret și funcțiilor pe care le activează. Nu se cere implementarea siglei sau a aplicației iOS pentru a servi o pagină QR deja aprobată; se cer acele funcții pentru acceptarea planului complet și înainte de a le promite ca disponibile.

### Înainte de publicare

- [ ] **Scop și identitate release:** versiune, commit/manifest, hashuri, changelog, contracte și feature flags consecvente; configurația și datele de test excluse din pachet.
- [ ] **Regresie relevantă:** suite locale trecute; CI pe DB/OS suportate; HTML/API v1/v2, chei/scopes, revocare, OAuth, import/export și upgrade din versiunea instalată. UI populată și dispozitive reale pentru fluxurile afectate.
- [ ] **Compatibilitate:** migrări aditive testate pe copie; mărime/durată/lock estimate; codul vechi și nou pot coexista în fereastra de upload; procedură de revenire compatibilă cu schema.
- [ ] **Protecția accesului:** două organizații, două gospodării, roluri distincte, ID-uri străine și fișiere private. Revocare, replay, CSRF, rate limits și confirmări sensibile. Scopes nu se acordă din payload/import.
- [ ] **Date și drepturi:** proiecție publică aprobată; licență aleasă explicit unde este publicare deschisă; documente cu drepturi incerte rămân private; demo separat de articole comerciale.
- [ ] **Continuitate:** backup complet verificat și restore anterior recent pe același model de infrastructură; copie release precedent; persoană și criterii de rollback; RPO/RTO asumate.
- [ ] **Infrastructură:** ramură PHP suportată și patchuri furnizor, extensii, PDO/utf8mb4, storage privat/scris corect, cote CPU/RAM/I/O/inodes/conexiuni, TLS/renewal și cron. Configurația publică nu expune erori sau secrete.
- [ ] **Servicii externe:** email/push/AI/date externe probate numai pentru funcțiile activate; timeout/buget și degradare controlată. QR-ul și datele aprobate rămân independente de AI/email/push.
- [ ] **Politici și suport:** responsabil real, contact funcțional, termeni/privacy/retention potrivite datelor reale, procedură drepturi persoane și incidente; cine răspunde în afara programului.
- [ ] **Măsurători:** trafic țintă și costuri probate înainte de contractarea volumului; nu se confundă limitarea antiabuz cu testul de capacitate.

### În timpul publicării

- [ ] Se înregistrează începutul, operatorul, release-ul, backupul și configurația relevantă fără valori secrete.
- [ ] Se execută pașii de cod/migrare în ordinea validată în staging. Uploadul atomic per fișier nu face automat întregul release atomic; trebuie evitat amestecul incompatibil de clase, contracte și asseturi.
- [ ] Se verifică rezultatul fiecărei migrări și hashurile fișierelor. O eroare oprește activarea funcției; nu se repetă mutații fără idempotency și reconciliere.
- [ ] Se verifică document root, OPcache/cache și versiunea service worker/IndexedDB, ca un client cu tab vechi să nu piardă inventarul sau să folosească o schemă incompatibilă.

### După publicare

- [ ] Homepage, login/logout, spațiul producătorului, API docs și manifest, catalog, pagina produsului și lotului, export și endpointul de stare funcționează.
- [ ] QR vechi de lot arată eticheta sa istorică; QR generic nu pretinde lot cunoscut; alertă nouă apare înaintea conținutului. Linkset/negociere/HEAD/OPTIONS și codificarea loturilor rămân corecte prin hosting.
- [ ] Ciornă/rețetă privată/document intern nu devin publice prin pagină, API, export, cache sau AI. Lipsa AI nu oprește QR sau Acasă.
- [ ] Cronul, emailul și alertele operaționale au rezultat observat, nu doar configurație salvată. Nu se provoacă mesaje reale în masă pentru smoke.
- [ ] Se urmăresc 5xx/429, latență, conexiuni DB, cote, cozi și reclamații. Pragurile de rollback sunt decise înainte; revenirea codului se deosebește de restaurarea DB, care poate pierde mutații recente.
- [ ] Accesurile temporare sunt închise/expirate conform procedurii, iar rezultatele și eventualele incidente sunt arhivate cu buildul. Funcțiile noi intră în stabilizare și suport.

### Praguri care trebuie probate, nu presupuse

Planul extins propune 1.000.000 scanări/zi, 250 cereri/s timp de 60 min și rafală 1.000 cereri/s timp de 5 min; p95 resolver 300 ms, 5xx ≤0,1%, disponibilitate lunară 99,9%, salvare locală p95 ≤1 s și sync online p95 ≤5 s. Pentru coada alertelor este prevăzută ținta de 60 s. Un milion pe zi înseamnă aproximativ 11,6 cereri/s în medie; media nu acoperă vârfurile și limitele de procese ale shared hostingului.

Țintele de recuperare din planul extins sunt **RPO 1 h / RTO 4 h**. Arhiva inițială propunea 24 h / 24 h pentru primul MVP; nu se folosesc valorile mai relaxate pentru a declara atins planul nou. Disponibilitatea se evaluează pe fereastra lunară a țintei, nu printr-o singură cerere reușită.

În prezent paginile/alertele folosesc `no-store`, evitând cacheul care ar ascunde retrageri, dar păstrând dependența de PHP/DB la citire. Orice cache/CDN nou trebuie să probeze invalidarea și prospețimea alertelor. Rate limits pe IP trebuie calibrate și pentru multe persoane din aceeași rețea, fără CAPTCHA pe scanarea obișnuită. Dacă găzduirea nu atinge țintele, soluția este capacitate adecvată și retestare, păstrând URL-urile imprimate.

## 6. Ordinea dezvoltării până la închiderea integrală

| Secvență | Pachete și rezultat | Condiția de trecere |
|---|---|---|
| 1. Bază operațională | WP00, WP21, WP23: staging, backup/restore, monitorizare, mail, acces privilegiat, privacy și date personale | PROD-01–PROD-10 aplicabile pilotului au probe, responsabili și runbook. |
| 2. Acceptarea nucleului | WP01–WP06, WP08, WP10, WP13, WP20, WP24: flux real non-pâine, web/PWA simplă și API | Utilizatori/telefoane reale, regresie, mandate/date și tipar probate; simulatoarele se declară ca atare. |
| 3. Modulele alimentare inițiale | WP16, WP17 și partea WP15: dosar complet, evaluare/siglă, feedback și AI/RAG | Criteriile OB adoptate și fluxurile de versiuni, licențe, decizie/revocare trecute; niciun certificat fictiv. |
| 4. Acasă complet | WP09, WP11, WP12, WP14 și completările WP04/WP05/WP08/WP10: surse externe, notificări, bon foto, voce și sugestii | Cazuri de incertitudine/siguranță, privacy și dispozitive; eșecurile externe nu pierd inventarul. |
| 5. Portabilitate și mobil | WP18, WP19, WP07, WP25: importuri reale/mari, domenii producători, aplicații native și distribuție | Round-trip și continuitate QR; DNS/TLS controlat; instalare/upgrade din magazine. |
| 6. Capacitate și închidere | WP22, WP24, WP26, WP27: load, audit final, pilot și 14 zile stabilizare | Toate R01–R56 și probele aplicabile închise, fără defecte critice/majore; responsabil nominal acceptă lansarea. |

WP02 și WP03 susțin modulele noi prin migrări și contracte compatibile; nu se reconstruiește nucleul care funcționează. Documentele/criteriile și auditul se actualizează odată cu codul. Rapoartele vechi își păstrează data și rezultatele, iar registrul nou primește dovezi per caz.

Nu există o bază verificabilă pentru un procent global de finalizare sau o dată fermă. Calendarul de 40 săptămâni până la 30.07.2027 din plan este explicit un scenariu cu trei ingineri, un QA și specialiști programați; resursele nu sunt confirmate nominal. Data de lansare completă se aprobă pe resurse și probe, nu din numărul de endpointuri sau de teste.

## 7. Ajustări necesare în documentație și colaborare

Structura actuală MVC este potrivită stackului lightweight și nu justifică o rescriere generală. Există autoload/bootstrap comun, domenii, controllere, views, contracte, migrări, teste, generatoare și pachete fără Composer/Node obligatorii pe hosting. Reorganizarea utilă pentru contribuții a fost deja aplicată.

Mai trebuie:

1. Un singur status actual de livrare, legat de acest registru. `EXECUTION_2026-10-24.md` și secțiuni din `VERIFICATION.md` descriu incremente mai vechi; nu sunt inventar complet al 0.5.5. Nu se schimbă rapoartele istorice în pretinse rezultate noi.
2. Numere și versiuni coerente: README conține încă 206 operații, catalogul curent are 208; `package.json` are versiune 0.4.0, iar release-ul aplicației 0.5.5. Metadatele de dezvoltare trebuie explicate ori derivate din sursa de adevăr.
3. Transformarea constatărilor UI/UX și a planului suplimentar de securitate în sarcini cu dovezi. Un raport creat nu înseamnă remediere. Revizia editorială 0.5.4/0.5.5 a schimbat texte; problemele de layout și accesibilitate trebuie reverificate în stările populate.
4. Inițializarea/publicarea GitHub doar după revizia secretelor și drepturilor. Această copie nu are `.git`; existența workflow-ului nu dovedește că există repository public, branch protection ori execuție CI reușită.
5. Licențe separate pentru codul propriu, biblioteci, fotografie și materialele producătorilor; nu se publică automat toate originalele private în repository. Capturile și rapoartele care conțin conturi/contacte interne rămân private.
6. Un colaborator nou trebuie să poată instala din documentație, testa cu configurație izolată și executa exemplul ERP fără ajutorul autorului. Predarea include unde sunt accesurile, cine le poate roti și cine poate restaura serviciul, fără a pune secrete în documente.

## 8. Surse oficiale folosite pentru criteriile de lansare

Sursele au fost consultate la 25.10.2026. Aplicarea lor la OpenAliment este evaluarea acestui audit, nu certificare și nici aviz juridic final.

- [GS1 Resolver 1.2.1](https://ref.gs1.org/standards/resolver/1.2.1/) și [URI Syntax 1.7.0](https://ref.gs1.org/standards/digital-link/uri-syntax/1.7.0/): diferența dintre adresă, redirecționare și resolver; profilul și matricea proprie trebuie declarate exact.
- [OWASP ASVS 5.0.0](https://asvs.owasp.org/): bază recomandată pentru verificarea cerințelor de securitate aplicabile; pentru conturi și date private se propune o matrice cu țintă L2, justificată pe cerințe.
- [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html): logurile aplicației, protecția și monitorizarea lor fac parte din operare; existența unui fișier log nu dovedește primirea alertelor.
- [PHP — Supported Versions](https://www.php.net/supported-versions.php): PHP 8.4 este încă suportat; suport activ până la 31.12.2026 și de securitate până la 31.12.2028. Ramura suportată nu înlocuiește verificarea patchurilor reale ale furnizorului.
- [cPanel — Backup](https://docs.cpanel.net/cpanel/files/backup-for-cpanel/): un full account backup nu se restaurează automat numai din cPanel; recuperarea completă poate necesita furnizorul/WHM. Procedura reală trebuie probată pentru acest hosting.
- [EDPB — roluri și responsabilități](https://www.edpb.europa.eu/sme/learn-the-basics/data-controller-or-data-processor_en) și [drepturile persoanelor](https://www.edpb.europa.eu/sme/be-compliant/respect-individuals-rights_en): rolurile, furnizorii și procedurile de exercitare a drepturilor trebuie să corespundă prelucrărilor reale. Exportul, deconectarea și ștergerea sunt operațiuni diferite.
- [NIST SSDF 1.1](https://csrc.nist.gov/pubs/sp/800/218/final): dezvoltare, verificare și răspuns la vulnerabilități integrate în procesul de release și întreținere.
