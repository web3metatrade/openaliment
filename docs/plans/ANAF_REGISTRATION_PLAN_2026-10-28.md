# Înregistrarea organizațiilor cu date ANAF

Data analizei: 28 octombrie 2026. Acesta este planul inițial pentru `dev.openaliment.ro/register`. Implementarea 0.9.0, migrarea aditivă 011, activarea distinctă pe staging și condițiile de revenire sunt descrise în [documentația implementării](../ANAF.md). [Ghidul API](../api/pages/inregistrare-anaf.md) și [matricea acțiunilor](../api/anaf-action-coverage.json) documentează contractele livrate. Formulările de mai jos păstrează perspectiva planului, fără a reprezenta confirmarea instalării pe un hosting.

Înregistrarea unei organizații românești va porni de la CUI. După consultarea ANAF, persoana va vedea firma găsită și toate informațiile primite, va putea corecta CUI-ul și va crea contul pentru firma confirmată. Datele fiscale se salvează din răspunsul păstrat pe server. Denumirea sau adresa trimise din browser nu înlocuiesc sursa ANAF.

## 1. Serviciul oficial și proba reală

Pagina oficială [Servicii web sincron ANAF](https://static.anaf.ro/static/10/Anaf/Informatii_R/Servicii_web/servicii_sincron.html) indică versiunea **v9**, publicată la **04.02.2025**. [Instrucțiunile oficiale](https://static.anaf.ro/static/10/Anaf/Informatii_R/Servicii_web/doc_WS_V9.txt) descriu cererea POST către:

```text
https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva
Content-Type: application/json
```

```json
[{"cui":14399840,"data":"2026-10-28"}]
```

ANAF stabilește maximum 100 de CUI-uri într-o cerere și maximum o cerere pe secundă pentru un client. Formularul va consulta un singur CUI. Data de referință este ziua curentă în `Europe/Bucharest`, stabilită de server.

Proba din 28.10.2026, ora 19:33:55 în București, a folosit o singură cerere cu trei CUI-uri, fără autentificare ANAF. A primit HTTP 200, `application/json`, 3.704 octeți, în aproximativ 0,125 secunde, din mediul local de analiză. Aceasta nu reprezintă încă o probă a conexiunii cPanel → ANAF.

| CUI | Rezultat real |
| --- | --- |
| 14399840 | DANTE INTERNATIONAL SA, în `found` |
| 1590082 | OMV PETROM SA, în `found` |
| 1234 | În `notFound`; este unul dintre CUI-urile fictive din exemplul documentației ANAF |

Cele două răspunsuri de firmă au aceleași **7 secțiuni și 58 de căi de câmp**; o secțiune TVA poate conține mai multe perioade. Nu sunt 58 de valori obligatoriu completate. În prima firmă, 23 de valori au venit ca șir gol.

Diferențe confirmate față de exemplul din instrucțiuni:

- Răspunsul reușit conține `found` și `notFound`, fără `cod` și `message`.
- `inregistrare_scop_Tva.perioade_TVA` este o listă de obiecte, nu un singur obiect.
- `date_generale.data_inreg_Reg_RO_e_Factura` există în răspuns, deși nu apare în exemplul documentat.
- Exemplul documentat este explicativ și are erori de sintaxă; nu se utilizează ca JSON executabil sau ca schemă validată.

[Dovada tehnică și inventarul câmpurilor](../verification/anaf-v9-probe-2026-10-28.json) păstrează sursele, data, tipurile și hashurile. Răspunsul integral și copiile documentației sunt locale în `tests/runtime/anaf-2026-10-28/`, director exclus din Git. Firmele consultate sunt exemple de date publice, fără afirmații de afiliere la OpenAliment.

## 2. Ce există în aplicație

- `/register` și `/login` împart `app/Views/auth.php`. Pe dev, pagina inspectată afișează că înscrierile se fac prin administrator; formularul este oprit prin `registration_enabled`.
- Formularul implementat cere numele persoanei, denumirea organizației, email și parolă. Nu există CUI, profil fiscal sau client ANAF.
- `Accounts::register()` creează organizația, utilizatorul și apartenența `owner` într-o tranzacție. Organizația are `display_name` și `identity_status`, fără câmpuri fiscale.
- Înregistrarea este disponibilă și prin `POST /api/v2/identity/registrations`. Fluxul nou trebuie acoperit de API, nu doar de formular.
- Identitatea și mandatul se verifică separat în `Administration::decide()`. Verificarea GTIN depinde de această verificare, nu de consultarea unui registru fiscal.

## 3. Fluxul propus

### Prima afișare

Un formular scurt: **CUI** și butonul **OK**, cu numele accesibil „Verifică CUI-ul la ANAF”. Acceptă cifre și prefixul opțional `RO`; acesta se elimină pentru interogare. Nu se cer încă parola sau numele firmei. `Enter` execută verificarea. CUI/CIF nu se confundă cu un CNP.

În timpul cererii apare „Verificăm datele la ANAF…”. Butonul este protejat de dublu clic. Modificarea CUI-ului invalidează rezultatul anterior imediat; un răspuns întârziat pentru vechiul CUI nu îl poate înlocui pe cel nou.

### După găsirea firmei

Pe aceeași adresă, interfața arată:

1. CUI-ul editabil și același buton OK, pentru corectare și reverificare.
2. Un rezumat cu denumirea oficială, CUI, sediul social, numărul de registru, starea de înregistrare, situația TVA și data sursei.
3. Câmpurile persoanei: nume, email și parolă; nu se cer din nou datele firmei.
4. Un singur buton final: **Creează contul pentru această firmă**. Apăsarea confirmă firma afișată; nu se adaugă o pagină intermediară de confirmare.
5. Toate cele șapte secțiuni ANAF, grupate și afișate deschis, inclusiv câmpurile fără valoare și toate perioadele TVA. Nu sunt necesare extensii de acordeon pentru a vedea informațiile.

Pe desktop, rezumatul și datele contului stau în două coloane aliniate; toate datele ANAF sunt sub ele. Pe telefon, ordinea este CUI → rezumat → datele contului → toate datele ANAF. Această ordine păstrează acțiunea principală aproape de firma identificată, iar detaliile complete rămân disponibile prin derulare. Datele ANAF sunt text de citit, nu 58 de inputuri care par editabile. Se poate adăuga o ancoră „Toate datele ANAF”, fără ca aceasta să fie singura cale de acces la detalii.

[Macheta locală](../ux/ANAF_REGISTER_CONCEPT_2026-10-28.html) folosește datele reale ale primei probe. Nu interoghează ANAF, nu colectează parole și nu creează conturi. În machetă se pot proba CUI-ul găsit, schimbarea lui și rezultatul negăsit pentru `1234`.

### Rezultate și întreruperi

| Situație | Comportament propus |
| --- | --- |
| CUI cu format incorect | Eroare lângă câmp; fără cerere externă. |
| CUI în `notFound` | „Nu am găsit acest CUI în ANAF. Verifică cifrele și încearcă din nou.” Câmpul rămâne editabil. |
| Timeout, TLS, HTTP 5xx, JSON nevalid | „ANAF nu răspunde momentan.” Se păstrează datele introduse; nu se afișează „firma nu există”. |
| Limită de interogări | Se afișează timpul de așteptare din `Retry-After`; reîncercarea automată este limitată și nu generează polling continuu. |
| Câmp gol, lipsă sau `null` | „Nefurnizat de ANAF”; un boolean `false` se afișează „Nu”, distinct de lipsă. |
| Firmă inactivă sau dată de radiere prezentă | Avertisment vizibil și cont cu verificare de reprezentare în așteptare; nu se acordă drepturi de publicare sau GTIN pe baza rezultatului. Regulile existente de aprobare rămân aplicabile. |
| Rezultat expirat înainte de submit | Se reverifică, păstrând câmpurile persoanei. Dacă datele oficiale diferă, se cere reconfirmarea firmei afișate. |
| Organizație fără CUI românesc | Traseu separat „Organizație fără CUI românesc”, prin administrare/verificare manuală. Nu se inventează un CUI pentru ONG în formare, inițiativă sau organizație străină. |

## 4. Semantica datelor

**Sediul social nu este domiciliul fiscal.** În proba DANTE, primul este în Sectorul 6, al doilea în Sectorul 2. `date_generale.adresa` este adresa domiciliului fiscal, conform documentației; nu precompletează automat sediul social.

Numărul de registru se păstrează ca identificator opac: proba întoarce `J2002000372404`, nu vechiul format cu bare oblice. Codul CAEN și codurile poștale se păstrează ca șiruri, fără completări sau corecturi presupuse: proba OMV conține CAEN `610`, iar unele coduri poștale au cinci cifre. Țara poate veni goală; jurisdicția CUI `RO` nu transformă acel câmp ANAF într-o valoare furnizată.

`statusRO_e_Factura=false` înseamnă că firma nu figurează în registrul respectiv la data căutată. Nu se afișează „nu folosește e-Factura” sau „nu are obligații e-Factura”. Nu se deduc CAEN Rev. 2/3, autorizații alimentare, reprezentanți, email, acționari, IBAN curent sau drepturi GS1 din câmpuri care nu oferă aceste informații.

ANAF este sursa datelor fiscale, nu sursa rețetei, produsului, lotului sau etichetei. Consultarea nu modifică `identity_status` în `verified`, nu verifică emailul persoanei și nu acordă sigle sau drepturi asupra GTIN. Datele fiscale ale profilului nu rescriu retrospectiv etichete și loturi aprobate.

## 5. Persistență și istoric

Migrare aditivă propusă: `011_organization_anaf.sql`, numai dacă numărul rămâne disponibil la implementare. Nu se rescriu migrările aplicate.

| Tabel nou propus | Conținut și reguli |
| --- | --- |
| `anaf_observations` | Răspunsul complet pentru CUI-ul consultat, rezultatul found/not_found, data solicitată, API v9, data preluării UTC, hash SHA-256 și versiunea mapării. Observații imuabile; cache și index pe CUI/data interogată. În runtime, un request privește un singur CUI. |
| `organization_registration_checks` | Identificator aleator, legare la sesiune, CUI, observație, expirare și consumare. Rezultatul poate fi confirmat numai de sesiunea care l-a obținut. Identificatorul singur nu acordă acces. |
| `organization_legal_profiles` | Un profil curent pentru fiecare organizație: `tenant_id`, țara fiscală, CUI și referința versiunii curente. Nu înlocuiește numele de afișare sau `identity_status`. |
| `organization_legal_profile_versions` | Versiuni imuabile cu denumirea oficială integrală, cele două adrese, datele generale și fiscale, observația sursă, confirmarea persoanei și data ei. Indexuri și referințe compuse pe tenant pentru izolarea datelor. |

Se păstrează toate câmpurile, inclusiv cele necunoscute mapării curente. În interfață, câmpurile viitoare apar într-o secțiune suplimentară cu numele sursă și valoarea escapate; o schemă nouă nu trebuie să ducă la eliminarea tăcută a datelor. Răspunsurile prea mari sau structural nesigure sunt respinse explicit.

Propuneri inițiale configurabile: observație reutilizabilă 6 ore, numai pentru aceeași zi de referință; rezultat negăsit 5 minute; confirmare de înregistrare 20 de minute. Data reală a consultării este vizibilă și când rezultatul vine din cache. Observațiile nefolosite și confirmările expirate se curăță prin mentenanța existentă. Observațiile folosite de versiuni fiscale nu se șterg prin simpla expirare a cache-ului; ștergerea contului aplică separat politica platformei.

Nu se impune unicitate globală de CUI pe o revendicare neverificată. O persoană nu trebuie să poată rezerva firma altcuiva și bloca titularul. CUI-ul nu permite aderarea la un tenant existent; administrarea comună se obține prin invitație sau verificarea mandatului. Unicitatea profilului este pe tenant; conflictele de reprezentare se soluționează separat, fără afișarea datelor private ale altor conturi.

La confirmare, backendul recitește observația, verifică sesiunea, CUI-ul și termenul, apoi creează contul, organizația și prima versiune fiscală în aceeași tranzacție. Datele fiscale din inputuri ascunse sunt ignorate. Consumarea confirmării, unicitatea emailului și protecția la dublu submit împiedică duplicarea; un CUI nu poate fi folosit pentru conectarea într-un cont existent.

## 6. View-uri și fișiere afectate

| Zonă | Modificare propusă |
| --- | --- |
| `app/Views/auth.php` | Separă înregistrarea de login/setup; loginul și instalarea păstrează comportamentul actual. |
| View nou `app/Views/register.php` | CUI, rezultat, datele contului, corectare, toate secțiunile ANAF; și flux POST fără JavaScript. |
| Partial nou pentru date ANAF | Același renderer complet în înregistrare, profil și administrare. Etichete românești și reguli uniforme pentru gol/false/date/liste. |
| `public/assets/registration.js`, `public/assets/app.css` | Îmbunătățire progresivă, mesaje accesibile, controlul răspunsurilor întârziate și layout adaptat telefonului. SVG pentru pictograme. |
| `/manage/organization` și view nou de profil | Datele salvate, sursa și istoricul; reverificare ANAF și confirmarea diferențelor de către owner. CUI-ul nu se schimbă printr-un refresh obișnuit. |
| `app/Views/admin.php`, `AdminController.php` | Datele fiscale ca informație pentru verificator; mandatul și aprobarea identității rămân distincte. |
| `PublicController.php`, `Accounts.php` | Flux CUI și înregistrare atomică pe baza referinței serverului. |
| Module noi pentru ANAF și profil fiscal | Client HTTPS fix, validare, cache/bugete, observații și istoric, fără bibliotecă mare și fără dependență de AI. |
| `Migrations.php`, migrarea nouă, configurare exemplu | Instalare aditivă și limite explicite; se păstrează fișierele private existente. |
| `Portability.php`, `TransferStream.php`, contracte și ghiduri | Exportul proprietarului include profilul și proveniența, iar importurile vechi fără CUI rămân acceptate. Un import nu recreează un mandat verificat automat. |

## 7. API și documentație incluse în implementare

Aceste operații sunt **propuse**, nu endpointuri deja disponibile:

| Metodă și rută propusă | Acces | Utilizare |
| --- | --- | --- |
| `POST /api/v2/identity/organization-lookups` | Sesiune anonimă de înscriere + CSRF, înscrieri active și limite | Consultă un CUI românesc și întoarce rezultatul complet, proveniența și identificatorul de confirmare. Nu este un proxy public de interogare în masă. |
| `GET /api/v2/identity/organization-lookups/{id}` | Aceeași sesiune, identificator neexpirat | Recitește rezultatul propriu; nu returnează rezultate doar pe baza ghicirii unui ID. |
| `POST /api/v2/identity/registrations` existent | Regulile existente de sesiune/CSRF | Extensie aditivă `organization_lookup_id`; pentru fluxul românesc nou, profilul se construiește din observația serverului. |
| `GET /api/v2/manage/organization/legal-profile` | Tenant propriu, `read` | Profilul fiscal și proveniența sa. |
| `GET /api/v2/manage/organization/legal-profile/history` | Tenant propriu, `read`, paginare | Versiunile istorice; fără datele altor organizații. |
| `POST /api/v2/manage/organization/legal-profile/checks` | Owner cu `write`, bugete ANAF | Reverifică același CUI și pregătește diferențele, fără înlocuirea directă a profilului. |
| `POST /api/v2/manage/organization/legal-profile/confirm` | Owner cu `write`, confirmare recentă a contului | Aplică observația verificată într-o versiune nouă, cu protecție la modificări concurente. |

Contractul existent al înregistrării fără CUI rămâne compatibil pentru organizațiile și clienții vechi: acestea nu primesc implicit profil fiscal sau identitate verificată. Noua interfață pentru firmă românească cere referința ANAF. Conturile existente nu sunt blocate și pot completa profilul ulterior. Ghidurile disting explicit aceste trasee; un client vechi nu poate fabrica statutul „date confirmate din ANAF”.

Se actualizează sursele generatoare, nu doar JSON-ul produs: `ApiRoutes.php`, `PlatformApiController.php`, `tools/build/api_contracts.py`, `api_reference.py`, modulele de documentație și registrul de acțiuni. Livrabilele includ OpenAPI, exemple cURL/JavaScript/Python, Postman, pagini Markdown, Copy Page, indexurile pentru AI și exemple de erori/reîncercare. Exemplul de înregistrare explică cookie jar, CSRF și obținerea rezultatului; nu sugerează că o cheie API poate fi obținută înainte de existența contului. Numărul de operații se recalculează din registrul efectiv.

## 8. Transport, securitate și operare

- Browserul comunică numai cu OpenAliment; PHP consultă adresa ANAF fixă. Fără URL extern introdus de utilizator, fără proxy arbitrar, redirecționări sau dezactivarea TLS.
- Se transmit doar CUI și data de referință; numele persoanei, emailul și parola nu sunt trimise la ANAF.
- Se validează tipul răspunsului, dimensiunea, structura și egalitatea CUI-ului returnat cu cel cerut. Nu se preia pur și simplu primul rezultat din `found`.
- Limite locale pe sesiune/IP și un buget de cereri externe, plus protecție la răspunsuri identice. Valori inițiale de evaluat pe dev: 6 verificări/15 minute/sesiune, 20/15 minute/IP, 300 de cereri externe/oră, cu cache-ul reutilizat.
- Cererile externe OpenAliment sunt distanțate cu cel puțin 1,1 secunde printr-un mecanism comun pentru dev și producție. Dacă mai multe aplicații folosesc același IP de ieșire, ele trebuie să coordoneze bugetul: un limiter OpenAliment nu poate controla traficul celorlalte aplicații. Așteptarea nu blochează tranzacții SQL sau sesiunea pe durata cererii externe.
- Propunere de transport: conexiune 3 secunde, timp total 10 secunde, răspuns maximum 1 MiB; fără reîncercări nelimitate. Un eșec ANAF nu afectează QR-ul, resolverul sau alimentele deja înregistrate.
- Răspunsul ANAF este tratat ca input extern: text escapat, fără HTML executabil; logurile nu includ parole, sesiuni, nonce sau întregi profiluri.
- Datele complete sunt pentru confirmarea persoanei, profilul propriu și administrarea autorizată. Nu se publică automat telefonul, IBAN-ul sau domiciliul fiscal pe paginile QR. Publicarea informațiilor operatorului pe etichetă rămâne o decizie aprobată a producătorului.
- Nu este necesar un import de firme, un cron de populare ANAF sau o bază națională copiată. Se stochează numai observațiile solicitate și versiunile folosite efectiv.

## 9. Verificări înainte de activare

1. Format CUI, prefix RO, spații, CUI gol, litere, lungime neacceptată și identificatori care ar putea fi CNP.
2. Răspunsurile reale found/notFound, câmpuri goale, booleene false, null/lipsă, toate cele 58 de căi observate și câmpuri viitoare.
3. Mai multe perioade TVA și variații controlate de structură; fără pierderea istoricului.
4. Sediu social diferit de domiciliu fiscal; identificatori fără slash și coduri cu zerouri; fără corecții inventate.
5. Timeout, 429, 5xx, HTML în loc de JSON, JSON trunchiat, CUI returnat diferit și răspuns prea mare.
6. Corectarea CUI-ului și răspunsuri sosite în ordine inversă; firma veche nu poate fi înregistrată accidental.
7. Modificarea datelor fiscale în request, reutilizarea unei confirmări, identificatorul altei sesiuni/organizații, expirare și dublu submit.
8. Înregistrare atomică: un eșec SQL nu lasă cont, organizație sau profil parțial create. Nu se conectează un cont existent prin simplul CUI.
9. Identitate, email, mandat, drepturi GTIN și sigle rămân neaprobate după consultarea ANAF.
10. API: succes, erori, scope/rol, paginare, contracte, exemple corecte și toate acțiunile UI cu echivalent documentat.
11. Interfață la 360/390/768/1440 px, tastatură, etichete și anunțuri accesibile, focus după eroare, fără scroll orizontal; și traseul fără JavaScript.
12. Conturi și importuri vechi, organizații fără CUI românesc, export/import fiscal cu proveniență și refresh fără rescrierea loturilor/etichetelor istorice.
13. Probă HTTPS din hostingul dev către ANAF, limitare comună dev/prod, indisponibilitate ANAF cu resolverul funcțional și verificare de retenție a cache-ului.

Testele automate folosesc fixture-uri sintetice derivate din structura probei; nu apelează ANAF la fiecare rulare CI. Probele live sunt explicite, mici și consemnate separat. API-ul ANAF nu se folosește pentru crearea conturilor de test pe firmele reale consultate.

## 10. Ordinea implementării și evaluarea strategiei

1. Pornire din ultimul `dev`, verificarea modificărilor colaboratorilor, migrare aditivă și contracte pentru profil/observații; fără modificarea documentelor istorice.
2. Client ANAF cu cache, rate limit, validare strictă și teste de transport pe fixture-uri.
3. Înregistrare cu confirmare legată de sesiune, tranzacție și compatibilitatea endpointului existent.
4. View de înregistrare și renderer comun, profilul organizației și ecranul administrativ.
5. Operațiile API, generatorii, exemplele executabile și toate formatele documentației; export/import și mentenanță.
6. QA local și CI, PR către dev, instalare din pachetul GitHub verificat și probă live pe dev. Activarea `registration_enabled` este o operație separată de simpla publicare a codului, cu configurarea privată păstrată.
7. Numai după verificarea online: promovare dev → main, backup, migrare și publicare controlată pe producție, urmate de probele de regresie.

Strategia susține misiunea OpenAliment: reduce introducerea manuală, păstrează proveniența și diferențiază profilul firmei de documentația alimentului. Nu creează o dependență ANAF pentru scanare, nu schimbă standardul GS1 Digital Link și nu confundă existența fiscală cu mandatul sau dreptul de utilizare a identificatorilor. Modelul aditiv permite firme românești, organizații străine și conturi existente fără migrare forțată. Principalele condiții de reușită sunt izolarea sesiunii de confirmare, sensul corect al adreselor/stărilor și documentarea API simultan cu interfața.

## Inventarul complet al câmpurilor observate

Tipurile de mai jos provin din cele două răspunsuri reale ale probei. Nu garantează că orice firmă va avea fiecare câmp sau valoare completată. `[]` marchează elementele listei de perioade TVA.

| Cale în răspunsul firmei | Etichetă propusă în interfață | Tip observat |
| --- | --- | --- |
| `date_generale.data` | Data de referință | `string` |
| `date_generale.cui` | CUI | `integer` |
| `date_generale.denumire` | Denumire oficială | `string` |
| `date_generale.adresa` | Domiciliu fiscal, adresă completă | `string` |
| `date_generale.telefon` | Telefon | `string` |
| `date_generale.fax` | Fax | `string` |
| `date_generale.codPostal` | Cod poștal fiscal | `string` |
| `date_generale.act` | Act de autorizare | `string` |
| `date_generale.stare_inregistrare` | Stare de înregistrare, text ANAF | `string` |
| `date_generale.data_inreg_Reg_RO_e_Factura` | Data înscrierii în Registrul RO e-Factura | `string` |
| `date_generale.organFiscalCompetent` | Organ fiscal competent | `string` |
| `date_generale.forma_de_proprietate` | Forma de proprietate | `string` |
| `date_generale.forma_organizare` | Forma de organizare | `string` |
| `date_generale.forma_juridica` | Forma juridică | `string` |
| `date_generale.data_inregistrare` | Data înregistrării | `string` |
| `date_generale.statusRO_e_Factura` | Figurează în Registrul RO e-Factura | `boolean` |
| `date_generale.nrRegCom` | Număr Registrul Comerțului | `string` |
| `date_generale.cod_CAEN` | Cod CAEN, valoarea ANAF | `string` |
| `date_generale.iban` | IBAN | `string` |
| `inregistrare_scop_Tva.scpTVA` | Înregistrată în scopuri de TVA | `boolean` |
| `inregistrare_scop_Tva.perioade_TVA[].data_inceput_ScpTVA` | Începutul perioadei TVA | `string` |
| `inregistrare_scop_Tva.perioade_TVA[].data_sfarsit_ScpTVA` | Sfârșitul perioadei TVA | `string` |
| `inregistrare_scop_Tva.perioade_TVA[].data_anul_imp_ScpTVA` | Data operării anulării TVA | `string` |
| `inregistrare_scop_Tva.perioade_TVA[].mesaj_ScpTVA` | Temeiul sau mesajul anulării TVA | `string` |
| `inregistrare_RTVAI.dataActualizareTvaInc` | Data actualizării TVA la încasare | `string` |
| `inregistrare_RTVAI.dataPublicareTvaInc` | Data publicării TVA la încasare | `string` |
| `inregistrare_RTVAI.dataInceputTvaInc` | Început TVA la încasare | `string` |
| `inregistrare_RTVAI.dataSfarsitTvaInc` | Sfârșit TVA la încasare | `string` |
| `inregistrare_RTVAI.tipActTvaInc` | Tipul actualizării TVA la încasare | `string` |
| `inregistrare_RTVAI.statusTvaIncasare` | Aplică TVA la încasare | `boolean` |
| `stare_inactiv.dataInactivare` | Data inactivării | `string` |
| `stare_inactiv.dataReactivare` | Data reactivării | `string` |
| `stare_inactiv.dataPublicare` | Data publicării în registrul inactivilor | `string` |
| `stare_inactiv.dataRadiere` | Data radierii | `string` |
| `stare_inactiv.statusInactivi` | Figurează în registrul contribuabililor inactivi | `boolean` |
| `inregistrare_SplitTVA.statusSplitTVA` | Aplică plata defalcată a TVA | `boolean` |
| `inregistrare_SplitTVA.dataInceputSplitTVA` | Începutul plății defalcate TVA | `string` |
| `inregistrare_SplitTVA.dataAnulareSplitTVA` | Anularea plății defalcate TVA | `string` |
| `adresa_sediu_social.sdenumire_Localitate` | Localitate | `string` |
| `adresa_sediu_social.sdenumire_Judet` | Județ | `string` |
| `adresa_sediu_social.scod_Judet` | Cod județ | `string` |
| `adresa_sediu_social.scod_JudetAuto` | Indicativ auto județ | `string` |
| `adresa_sediu_social.sdetalii_Adresa` | Detalii adresă | `string` |
| `adresa_sediu_social.scod_Postal` | Cod poștal | `string` |
| `adresa_sediu_social.sdenumire_Strada` | Stradă | `string` |
| `adresa_sediu_social.snumar_Strada` | Număr | `string` |
| `adresa_sediu_social.scod_Localitate` | Cod localitate | `string` |
| `adresa_sediu_social.stara` | Țară | `string` |
| `adresa_domiciliu_fiscal.ddenumire_Localitate` | Localitate | `string` |
| `adresa_domiciliu_fiscal.ddenumire_Strada` | Stradă | `string` |
| `adresa_domiciliu_fiscal.dnumar_Strada` | Număr | `string` |
| `adresa_domiciliu_fiscal.dcod_Localitate` | Cod localitate | `string` |
| `adresa_domiciliu_fiscal.ddenumire_Judet` | Județ | `string` |
| `adresa_domiciliu_fiscal.dcod_Judet` | Cod județ | `string` |
| `adresa_domiciliu_fiscal.dcod_JudetAuto` | Indicativ auto județ | `string` |
| `adresa_domiciliu_fiscal.ddetalii_Adresa` | Detalii adresă | `string` |
| `adresa_domiciliu_fiscal.dcod_Postal` | Cod poștal | `string` |
| `adresa_domiciliu_fiscal.dtara` | Țară | `string` |
