# Actualizare producție — resolver și landing

23.10.2026 · https://openaliment.ro · /home/apelero/openaliment.ro/public/

## Publicare

Conexiunea HTTPS deja autorizată a verificat baseline-ul remote față de release-ul inițial și corecția anterioară de autentificare. Nicio modificare independentă nu a fost suprascrisă. După activare, toate cele 40 de fișiere afectate au corespuns SHA-256 cu sursele locale.

| Actualizare | ID | UTC | Fișiere |
| --- | --- | --- | --- |
| Componente resolver, contracte GS1, migrare aditivă | 28707f218d654550911f7470f6eeca8b | 20:34:55 | 22 |
| Activare rute, UI/API, portabilitate, landing și SEO | e94edc9614ca45b38f1bada7b7d135c3 | 20:37:18 | 17 |
| Model logic activ 1.1 | 0e3bb0510ceb4a49918cb4ae64f549e0 | după activare | 1 |
| Sincronizare contracte regenerate | 0b80ace729f0474f9c212c261031efb1 | după verificări | 2 |

Componentele noi sunt în app/Controllers/Resolver*, UpdatesController, Core/Migrations și Seo, Domain/Resolver*, EpcDecoder, views/resolver* și updates. Fișierele existente adaptate sunt index.php, controlerele Public/Api/Manage/Admin/Setup, Catalog, DigitalLink, Mutations, Portability și vizualizările home/layout/product/manage/admin/api-docs. Contractele și tabelele GS1 sunt în contracts/gs1; modelul logic și OpenAPI au fost actualizate. CSS rămâne local, iconurile SVG; imaginea socială are 1200×630 și aproximativ 33 KB.

Migrarea exactă 002_resolver.sql a fost aplicată din /admin/updates în sesiunea administratorului, după reautentificarea proprietarului. Adaugă tabele; nu alterează ori șterge snapshoturi, loturi sau rețete. Raportul HTTPS a fost înregistrat în același panou la 23:40 Europe/Bucharest. Opțiunile private din config/local.php nu au fost citite sau rescrise prin conexiunea de mentenanță.

## Resolver

Profilul complet implementat este GTIN (01), conform contractului GS1-Conformant Resolver 1.2.1: calificatori, registru versionat, aprobare, ierarhie, negociere, linkset, EPC, descriptor, HTTP și CORS. Matrice: ../GS1_IMPLEMENTATION.md. GLN/SSCC și compresia legacy nu sunt declarate ca funcții ale profilului.

Rute publice: /.well-known/gs1resolver, /resolver/profile, /resolver/contexts, /resolver/linkset-context, /01/{gtin14} cu calificatori, /eh… și /ex… . Administrare: /manage/resolver/{trade_item_id}. UI și API folosesc drepturile GTIN verificate și aprobare separată; importurile rămân private.

## Dovezi

- 69 domeniu de bază + 54 domeniu resolver/MariaDB + 117 HTTP resolver + 58 HTTP/regresie = 298 verificări locale trecute.
- 178 probe HTTPS producție trecute: descriptor validat cu schema GS1, metode, CORS, erori, setul X GS1 în loturi, zerouri, case, percent encoding, calificatori, forme EPC eh/ex, contexte. Exclusiv identificatori exemplu neînregistrați; fără produse sau drepturi fictive în producție.
- 28 verificări HTTPS landing/SEO trecute: titlu, descriere, H1, canonical fără tracking, meta robots, JSON-LD, sitemap XML, OG, cache resurse, OpenAPI și navigare.
- Sintaxa tuturor celor 47 de fișiere PHP ale aplicației verificată înainte de activare; conexiunea a verificat și sintaxa fișierelor încărcate.
- Browser: desktop 1440×950 și mobil 390×844; fără depășire orizontală. Legătura ERP și CTA contact au fost verificate; CTA folosește mailto cu adresa publică configurată, fără trimitere de email în timpul testului.
- Capturi: ../previews/production-resolver-verified.jpg, production-landing-desktop.jpg, production-landing-mobile.jpg.

Rezolvarea cu succes a produselor înregistrate, selecția destinațiilor și alertele au fost probate în baza izolată. Producția nu avea un articol GTIN real cu mandat verificat pentru această probă. Nu au fost inventate produse, recenzii, loturi, mandate sau rezultate alimentare. Declarația este a implementatorului; nu este certificare ori audit GS1 extern. Tiparul/POS, capacitatea de milioane de scanări și restore-ul bazei de producție rămân probe operaționale distincte.

## SEO și ofertă

Landing-ul explică alternativa independentă de găzduire pentru GS1 Digital Link, resolverul, produs versus lot, integrarea ERP prin API, automatizarea după aprobare, export/import și continuitatea adreselor. Nu pretinde conectori ERP preconstruiți, prețuri sau acreditări inexistente. Documentația API se poate descărca din /api/openapi.json. robots.txt și sitemap.xml sunt servite de aplicație, fără fișiere publice de configurare suplimentare. Sitemap-ul enumeră paginile publice de prezentare; nu încarcă milioane de adrese de lot într-un singur XML. Paginile publice ale produselor sunt descoperibile prin catalog.

Nu s-a făcut o trimitere în Search Console și nu există promisiune privind poziția sau termenul de indexare. Textele și legăturile publice sunt disponibile pentru crawl.

## Revenire

Copiile și recepisele actualizărilor sunt păstrate privat de conexiunea HTTPS. Revenirea la codul precedent folosește mai întâi rollback pentru activarea e94edc…, apoi componentele 28707f… și modelul 0e3bb… după necesitate. Nu se șterg tabelele 002; codul anterior le ignoră. O revenire nu șterge datele sau istoricul resolverului create între timp. Restaurarea verifică modificările remote înainte să suprascrie fișiere. Pentru migrarea datelor se folosește exportul autorizat și un backup complet al bazei, separat de backupul de cod.

Pachet de recuperare: `release/OpenAliment_resolver02_cPanel_2026-10-23.zip`, generat cu manifest și verificare de integritate, fără configurația reală, chei, DB locală sau fixture-uri. Arhiva stage1 rămâne neschimbată.
