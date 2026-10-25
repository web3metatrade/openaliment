# Acasă: conectare, familie și sincronizare

Implementare web pentru PHP/MySQL pe shared hosting. Identitatea personală este separată de utilizatorii și organizațiile producătorilor. Scanarea, inventarul local și paginile QR funcționează fără cont, email sau AI.

## Flux

Din „Pentru mine”, persoana poate cere o legătură pe email. Legătura are 15 minute și o singură utilizare. Deschiderea ei arată adresa și cere o confirmare înainte de conectare; scanerele de securitate ale emailului nu consumă tokenul prin GET. Browserul rămâne conectat 90 de zile, cu prelungire la utilizare, prin cookie HttpOnly/Secure/SameSite=Lax. Cookie-ul personal nu autentifică în administrarea producătorilor.

Confirmarea permite alegerea alimentelor și fotografiilor locale care vor fi păstrate în cont. Preferințele personale pentru alergeni rămân pe dispozitiv. Lista de cumpărături este momentan locală; capabilitatea `shopping_sync` este explicit falsă.

Proprietarul familiei poate copia o invitație, valabilă 7 zile și o singură utilizare. Platforma nu o trimite automat altcuiva. Persoana invitată se conectează prin propriul email și confirmă alăturarea. Adăugarea propriilor alimente în familie este debifată inițial. Membrii pot actualiza inventarul, fără drepturi de producător sau transfer de proprietate. Proprietarul poate retrage accesul. Copiile deja descărcate pe dispozitive nu pot fi retrase de la distanță.

Familiile se pot denumi și selecta din secțiunea opțională „Familia mea”. Inventarele locale ale spațiilor sunt păstrate separat; schimbarea spațiului nu publică automat alimentele vechi în altă familie. Dispozitivele se pot deconecta individual.

## Contract și consistență

- [Contract OpenAPI](../contracts/home-openapi.json), publicat la `/api/home-openapi.json`.
- [Schema sincronizării](../contracts/home-sync.schema.json), publicată la `/api/schemas/home-sync.schema.json`.
- Migrarea aditivă `004_home.sql`, aplicată din `/admin/updates`, păstrează tabelele existente.
- `GET session` indică disponibilitatea și starea autentificării. Scrierile private necesită aceeași origine, cookie și `X-Home-CSRF`; fără CORS privat.
- Fiecare ambalaj are identitate proprie, revizie de server și evenimente de istoric. Cererile repetate folosesc același ID și același conținut. Un răspuns pierdut nu dublează ambalajele sau istoricul.
- Loturile de maximum 50 de operațiuni și 50 de evenimente se aplică atomic. La revizii concurente serverul întoarce 409 și variantele curente; utilizatorul alege explicit. Nu se folosește „ultima scriere câștigă”.
- Cursorul este separat pentru fiecare familie. Pagina conține cel mult 100 de modificări; clientul persistă coada și cursorul separat de copia personală exportată.
- Fotografii JPEG private: maximum 1 MB, 6000×6000, 5.000 de fișiere și 32 MB per familie. Limita inventarului este 2.000 de ambalaje și 50.000 de evenimente. Fotografierea și OCR rămân locale până la alegerea sincronizării.
- Datele de siguranță curente declarate de dispozitive nu devin autoritative. Clientul reîncarcă alertele publice; lipsa conexiunii nu înseamnă verificare recentă. Rețetele private nu sunt acceptate în inventarul personal.

## Operare și limite verificate

`home_sync_enabled=false` dezactivează API-ul privat și păstrează utilizarea locală. Pentru o instalare existentă fără această cheie, API-ul devine disponibil după migrarea 004. Un SMTP incomplet ascunde conectarea pentru vizitatori; inventarul local și QR-ul rămân disponibile. Configurația email se păstrează în afara `public/`.

Testele folosesc exclusiv `openaliment_tests` și un outbox privat, fără trimitere reală. Rapoartele de domeniu, client și HTTP sunt în `docs/verification/home-cloud-*.json`. Autentificarea SMTP pe hosting a fost verificată separat, fără trimiterea unui mesaj. Aceasta nu dovedește livrarea în inbox.

Acest increment nu declară finalizarea planului complet. Distribuția nativă, notificările de fundal, sincronizarea cumpărăturilor, transferul proprietății, ștergerea integrală a contului și politica automată de retenție necesită implementare și verificare distincte. Codul nu pretinde că retragerea accesului șterge copii deja exportate. Exportul personal nu conține cookie-uri, tokenuri, CSRF sau invitații.
