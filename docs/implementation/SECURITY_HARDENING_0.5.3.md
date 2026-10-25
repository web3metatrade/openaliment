# OpenAliment 0.5.3 — îmbunătățiri de securitate

Data: 24.10.2026. **Publicat pe openaliment.ro, cu migrarea aditivă 006 activată din contul administratorului.** Transferul celor 46 de fișiere a fost verificat prin hashuri și baseline, iar probele HTTPS de după publicare au trecut. Curățarea datelor temporare nu a fost executată și cronul nu este configurat prin acest release.

## Schimbări implementate

- Rutele HTML de administrare refuză Bearer, inclusiv combinația cookie valid + Bearer. Permisiunile sunt verificate suplimentar în domeniu: write, publish, alerts, import și export sunt distincte. Publicarea nu cere artificial write când credentialul are publish și rolul potrivit.
- Parolele noi folosesc Argon2id unde este disponibil, fallback bcrypt limitat în octeți. Hashurile legacy rămân verificabile; sufixele bcrypt peste 72 de octeți nu sunt migrate în tăcere. Verificarea conturilor necunoscute efectuează lucru criptografic limitat.
- Sesiuni producător cu expirare absolută de opt ore și inactivitate de două ore, legate de verificatorul parolei. `/manage/security` oferă schimbarea parolei și retragerea celorlalte sesiuni/chei/granturi ale contului, după confirmarea parolei actuale. Identitatea Acasă rămâne separată și persistentă.
- Două API-uri de cont, numai cu sesiune/CSRF: `POST /api/v2/identity/password` și `POST /api/v2/identity/access-revocations`. Documentația are acum 208 operații, exemple și scheme validate.
- Migrare aditivă 006: un tabel pentru rezultatul mentenanței și indici pentru date temporare. Curățare în loturi limitate, independentă de AI. Verificatorii refresh folosiți sunt păstrați pe toată durata grantului, iar istoricul comercial nu este șters.
- Bugete globale/client pentru email, înscrieri și cereri OAuth; limite pentru dispozitive și granturi active. Citirea/descărcarea HTML are limite suplimentare.
- Evenimente de securitate redactate, plafonate pe zi, fără corpuri/antete/credentiale. Auditul modificărilor include identitatea cheii/grantului/clientului, fără secret.
- Politică comună de tipuri publicabile pentru fișiere; originalele istorice nesuportate rămân private. Noile fotografii personale JPEG sunt stocate fără EXIF/XMP/IPTC/comentarii, cu păstrarea datelor imaginii și retry idempotent.
- Parsare exactă Content-Type, 415 pentru formate nesuportate, configurare de producție refuzată dacă este incompletă, erori de bootstrap fără detalii publice.
- Configurație HSTS inițială de o zi, fără preload/subdomenii; blocarea interpretoarelor PHP din assets și a unor copii de siguranță publice. HSTS, nosniff și absența X-Powered-By au fost confirmate pe hosting pentru pagini dinamice, resurse statice, redirect QR și răspunsuri de eroare.
- Verificare țintită de surse/secrete, verificarea versiunilor publice în OSV, CI și Dependabot configurate. Pillow local actualizat la 13.0.0. Clientul HTTPS de mentenanță poate impune baseline-ul așteptat înainte de staging; termenul implicit pentru cheile nou generate este șapte zile.

## Verificări efectuate

Regresia completă locală a trecut: **39 suite, 10.001 verificări raportate, zero suite eșuate**. Include 33 verificări unitare de securitate, 37 de domeniu și 28 HTTP pentru noile controale. Cele 208 operații documentate au întors local răspunsuri de succes validate; testul referinței a validat 214 răspunsuri HTTP. După extinderea clientului cu baseline obligatoriu opțional, suita de mentenanță a fost rerulată: 66 verificări trecute, un caz de symlink omis din cauza permisiunilor Windows.

Toate probele de scriere au folosit baza separată `openaliment_tests` sau arbori de fișiere sintetici. Niciun email real, apel AI sau cont de producție nu a fost utilizat pentru aceste probe. Rapoartele complete noi rămân în `tests/runtime/reports/`, excluse din Git; sumarul de release este în [security-hardening-0.5.3.json](../verification/security-hardening-0.5.3.json).

După publicare au trecut **115 verificări HTTPS de citire**: 21 API, 42 documentație și 52 transport/securitate. Contractul OpenAPI public are același hash cu artefactul testat; catalogul public, aplicația Acasă și linksetul GS1 pentru pâinea demonstrativă funcționează. Cookie-ul producătorului are Secure, HttpOnly și SameSite=Lax; CSP este prezent; setup și hosting-check răspund 404, iar un nume PHP inexistent din assets este refuzat cu 403. Nu s-au folosit chei reale și nu s-au creat produse sau conturi pe producție pentru aceste probe.

Activarea reală a migrării a folosit separat sesiunea administratorului, după reconectarea efectuată de utilizator. Panoul `/admin/updates` a confirmat „Protecțiile sunt pregătite. Curățarea nu a fost executată încă.” Marcajul migrării este scris de migrator numai după crearea tabelului și a indicilor; confirmarea a fost citită prin panoul aplicației, fără shell sau acces SQL extern.

Verificarea OSV a inventarului declarat de 49 versiuni s-a încheiat fără alerte după actualizarea Pillow. Aceasta nu dovedește absența tuturor vulnerabilităților sau acoperirea fiecărei componente native/transitive din bundle-uri; snapshotul qrcodegen necesită verificare manuală. Notele de securitate relevante pentru Pillow sunt [publicate de proiect](https://pillow.readthedocs.io/en/stable/releasenotes/13.0.0.html).

## Compatibilitate și activare

URL-urile QR, identificatorii, loturile vechi, alertele și importurile istorice sunt păstrate. Datele private de conectare nu se transferă în pachetul public. API v1/v2 și cheile existente rămân utilizabile pe rutele documentate, în limita scopes acordate. Folosirea nedocumentată a Bearer pe HTML este refuzată. Sesiunile producător existente necesită o singură reconectare; Acasă nu este deconectat.

Retenția generală Idempotency-Key este acum explicită: 90 de zile. O reluare după acest interval trebuie să verifice resursa/identitatea externă înainte de a crea din nou o operație. Închiderea accesurilor contului revocă cheile/granturile sale în toate organizațiile; efectul este explicat în interfață înainte de confirmare.

Actualizarea codului poate fi publicată independent de migrarea 006. Migrarea este activată ulterior din `/admin/updates`, cu sesiune administrator, fără shell. Curățarea se poate rula manual acolo; executarea automată cere configurarea unui cron privat. Procedura și limitele sunt în [SECURITY_OPERATIONS.md](../security/SECURITY_OPERATIONS.md).

## Starea constatărilor din audit

| Constatare | Rezultat / limită |
|---|---|
| SEC-01 | Reprodus înainte, refuzat după remediere; verificat local pe HTML, API și domeniu. Remedierea este publicată; fără exploatarea conturilor reale pe producție. |
| SEC-02 | Corectat pentru parole noi; migrare compatibilă. Parolele legacy bcrypt peste 72 de octeți cer înlocuire explicită. |
| SEC-03 | HSTS de o zi confirmat pe LiteSpeed pentru HTML, static, redirect și erori, fără antete duplicate. |
| SEC-04 | Cleanup limitat și cote publicate, migrarea 006 activă; curățarea și cronul real nu au fost executate/configurate. Nu se declară ștergere integrală de cont. |
| SEC-05 | Durată absolută, schimbare parolă și revocare implementate. MFA/passkeys și recuperarea completă rămân distincte. |
| SEC-06 | Client cu baseline verificat, termen mai scurt pentru endpointuri noi. Endpointul existent nu a fost rotit/oprit. |
| SEC-07 | Limite suplimentare și bugete; fără probe masive pe producție, fără WAF/CDN și cache public nou. |
| SEC-08 | Jurnalizare redactată și audit al credentialului; fără alertare externă sau audit al logurilor furnizorului. |
| SEC-09 | Publicare și metadate foto întărite; nu există antivirus/CDR, iar fișierele vechi nu au fost declarate curate. |
| SEC-10 | Formate HTTP și bootstrap/config verificate local; antetele HTTPS și închiderea diagnosticelor confirmate pe hosting. |
| SEC-11 | Scanare țintită, inventar OSV, CI și Dependabot; nu este SAST complet și CI GitHub nu a fost încă executat. |
| SEC-12 | Procedură operațională documentată; nu s-au confirmat backupul, restaurarea, WAF, cron sau izolarea în cPanel. |

## Publicare și verificare pe hosting

Comanda inițială de pregătire HTTPS a fost respinsă de verificarea automată deoarece scriptul conține și calea de publicare, iar verificarea a cerut autorizare explicită pentru riscul întreruperii producției. Utilizatorul a aprobat ulterior explicit publicarea 0.5.3 și activarea migrării 006. Publicarea s-a efectuat după această aprobare.

Baseline-ul remote nu a avut conflicte. Trei transferuri prin endpointul HTTPS limitat au aplicat 46 de fișiere (3.299.206 octeți), cu backup/rollback și verificare de hash. Fișierele private de configurare și datele de producție nu au fost înlocuite. `hosting-check.php` nu este actualizat de bridge, conform allowlistului; închiderea lui după instalare și eliminarea antetului PHP prin `.htaccess` au fost confirmate prin HTTPS.

Migrarea 006 a fost activată și confirmată din panoul administrativ la 24.10.2026, după reconectarea utilizatorului. Un nou instrument repetabil, `tools/qa/security_smoke.py`, verifică transportul și închiderea diagnosticelor fără credentiale sau scrieri. Documentația și pachetele locale includ această stare finală; rămân aplicabile limitele operaționale din tabel și din ghidul de securitate.
