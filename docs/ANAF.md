# Înregistrare și profil fiscal ANAF

OpenAliment 0.9.0 consultă serviciul public sincron ANAF v9 după CUI și data curentă în Europe/Bucharest. [Instrucțiunile oficiale](https://static.anaf.ro/static/10/Anaf/Informatii_R/Servicii_web/doc_WS_V9.txt) și [proba din 28 octombrie](verification/anaf-v9-probe-2026-10-28.json) fixează versiunea folosită și diferențele observate față de exemplul oficial.

Proba separată din 29 octombrie a confirmat că ANAF răspunde HTTP 404 atunci când singurul CUI solicitat este negăsit, cu JSON `{"found":[],"notFound":[1234]}`. Integrarea acceptă acest răspuns numai după validarea completă a corpului și a CUI-ului. API-ul OpenAliment păstrează HTTP 200 cu `state=not_found`. Un 404 HTML, un JSON nevalid, un alt CUI sau un răspuns HTTP 5xx rămâne eroare 503.

## Înregistrare și confirmare

`/register` cere întâi CUI și OK. Rezultatul arată firma și toate datele ANAF disponibile, inclusiv valorile nefurnizate. CUI-ul poate fi corectat; verificările noi înlocuiesc confirmările vechi din aceeași sesiune. Datele persoanei se cer după găsirea firmei. La creare, serverul folosește observația păstrată, nu datele fiscale din inputuri ascunse. Funcționează și fără JavaScript.

Datele generale, perioadele TVA, TVA la încasare, inactivitatea, plata defalcată TVA, sediul social și domiciliul fiscal sunt păstrate separat. Câmpurile viitoare sunt păstrate și afișate cu denumirea sursă. Numărul de registru, CAEN și codurile poștale nu sunt completate prin presupuneri. Un câmp lipsă sau null nu devine false ori zero.

Profilul fiscal este privat, versionat și separat de identitatea/mandatul reprezentantului, email, drepturi GTIN și sigle. Consultarea ANAF nu acordă aceste verificări. Reverificarea nu modifică informațiile aplicabile produselor și loturilor vechi și nu schimbă CUI-ul unui profil existent. Numele public al spațiului rămâne distinct de denumirea legală integrală; dacă aceasta depășește 180 de caractere, doar numele de afișare inițial este prescurtat, iar profilul păstrează denumirea completă.

## API și portabilitate

[Ghidul API](api/pages/inregistrare-anaf.md) descrie sesiunea anonimă cu CSRF, verificarea și corectarea CUI-ului, înregistrarea, citirea profilului, istoricul, reverificarea, confirmarea și consultarea administrativă. [Matricea acțiunilor](api/anaf-action-coverage.json) leagă interfața de operațiile din contract. Modificările sunt incluse în OpenAPI, Postman, referințele Markdown, Copy Page și indexurile AI.

Înregistrarea veche prin API, fără CUI, rămâne compatibilă și nu primește automat un profil fiscal. Conturile existente pot completa profilul ulterior. Organizațiile fără CUI românesc folosesc administrarea manuală.

Exportul privat JSON/NDJSON include `organization_legal_history`, cu răspunsul original și hashul său. Importul păstrează istoricul și îl marchează `origin=imported`, `directly_verified=false`; nu importă drepturi sau apartenențe și nu alimentează cache-ul observațiilor directe. Un profil existent nu este suprascris prin transfer. Dacă pachetul aparține altei firme, proveniența fiscală rămâne în istoricul importului, fără schimbarea profilului curent.

## Instalare și activare

Publicarea codului și activarea înscrierilor sunt separate. Migrarea aditivă `011_organization_anaf` adaugă patru tabele, fără rescrierea migrărilor anterioare sau a datelor existente. Din `/admin/updates`, „Instalează profilul fiscal ANAF” instalează resursele fixe ale release-ului și aplică migrarea.

Pe `dev.openaliment.ro`, administratorul poate activa apoi înregistrarea cu CUI. Acțiunea cere confirmarea recentă a contului și creează fișierul privat `config/registration.local.php` cu `enabled=true`. Nu rescrie `config/local.php` și nu poate activa alt hostname. Dacă fișierul privat există cu altă valoare, administratorul îl actualizează prin hosting. Pentru a opri înscrierile, setează `enabled=false` în acel fișier. Configurația privată de producție nu este modificată de activarea stagingului.

`config/anaf.local.php` este opțional; exemplul se livrează în `config/anaf.local.example.php`. Nu necesită cheie ANAF. Opțiunile pot reduce TTL-urile și ajusta bugetele în limitele validate de cod. Valori implicite: cache 6 ore în aceeași zi, negăsit 5 minute, confirmare 20 de minute, 6 verificări/15 minute/sesiune, 20/15 minute/IP și 300 de cereri externe/oră/instalare. `network_enabled=false` oprește apelurile noi, păstrând observațiile deja disponibile în cache.

Intervalul de minimum 1,1 secunde între apelurile externe este coordonat în fișierul privat `openaliment-anaf-rate.json`, din directorul părinte comun al celor două instalări cPanel. Alte aplicații care folosesc același IP trebuie să își coordoneze traficul separat. Limitarea nu ține sesiunea PHP sau o tranzacție SQL blocată în timpul cererii. Conexiunea are limită de 3 secunde, cererea 10 secunde, iar răspunsul este limitat la 1 MiB. TLS este verificat și redirecționările sunt refuzate.

Mentenanța existentă curăță confirmările expirate și observațiile nefolosite mai vechi de 30 de zile. Observațiile referite de versiuni fiscale sunt păstrate cu istoricul. Nu se configurează un cron de populare a firmelor. ANAF nu este apelat la scanarea QR sau la citirea paginilor produsului.

## Verificare și revenire

Testele folosesc exclusiv `openaliment_tests` și transportul sintetic din `tests/fixtures/anaf-v9.json`. CI nu interoghează ANAF. Suita include domeniu, HTTP cu scheme validate și browser, corectarea CUI-ului, cross-session/cross-tenant, dublu submit, expirare, valori necunoscute, export/import și rollbackul înregistrării.

Înainte de activare pe un hosting se verifică răspunsul live prin acel hosting, salvarea profilului și reîncărcarea datelor, iar observațiile reale sunt diferențiate de testele sintetice. Eșecul ANAF este 503 și nu este interpretat ca firmă negăsită. Oprirea înscrierilor nu șterge profilurile. Revenirea codului nu șterge migrarea sau istoricul; se păstrează copia de siguranță și compatibilitatea tabelelor adăugate.
