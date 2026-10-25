# Resolver GS1 complet și landing comercial

Cerere actualizată de proprietar la 23.10.2026: implementarea unui GS1-Conformant Resolver complet, nu păstrarea nivelului `digital_link_landing` ca limită a proiectului. Rămâne activă cererea pentru landing page, SEO, ofertă comercială și integrare ERP/API. Activarea și probele se fac prin conexiunea HTTPS deja instalată.

## Domeniu normativ

- GS1-Conformant Resolver 1.2.1, URI Syntax 1.7.0, Compression EPC 1.0.0, TDS 2.3.
- Profilul declarat: AI (01), cu toți calificatorii GTIN (22, 10, 21 și forma UPUI 235) și transmiterea atributelor/query string. Standardul permite un subset declarat de chei primare.
- GET/HEAD/OPTIONS, HTTPS/CORS, 400/404 reale, destinație implicită, selecție `linkType`, linkset RFC 9264 și schemele oficiale, moștenire și uniunea calificatorilor, preferințe limbă/media/context, descriptor și context JSON-LD, decomprimare EPC `eh` și `ex` inclusiv schemele GTIN din TDS 2.3.
- Fără declarație de certificare sau afiliere GS1. Implementarea completă se declară după probe, cu matricea cerințelor și limitele exacte ale profilului.

## Implementare și compatibilitate

1. Parser și decomprimare EPC în PHP, cu tabele GS1 locale; fără serviciu extern sau Node pe hosting.
2. Migrare aditivă 002 pentru legături versionate și verificarea transportului; aplicare administrativă autentificată, fără SQL arbitrar.
3. Registru cu draft/aprobare, mandat GTIN, izolare între organizații, audit și retragere fără pierderea istoricului. UI și API ERP pentru administrare.
4. Rezolvare publică și fallback către paginile existente. `/t`, `/b` și snapshoturile istorice rămân funcționale. Alertele de lot și disputele au destinație implicită sigură. AI nu participă la rezolvare.
5. Import/export păstrează noile legături și istoricul; importul nu acordă drepturi sau publicare.
6. Teste locale normative și de securitate, apoi deploy, migrare și probe HTTPS de transport pe hosting. Nu se înregistrează GTIN-uri fictive în producție.
7. Landing orientat spre producători: alternativă independentă de furnizor pentru GS1 Digital Link, administrare QR produs/lot, integrare ERP prin API, automatizare, portabilitate, contact comercial. Titlu/meta/canonical/OG/JSON-LD/robots/sitemap și verificare responsive.

## Evidență inițială

Sursele oficiale sunt salvate privat în `.tools/gs1-reference/` pentru lucru; nu conțin date ale producătorilor. Probele inițiale pe domeniu au confirmat că rutele cu `%2F` și `%2B` ajung la aplicație. Matricea completă de transport urmează după implementarea handlerului.

Nicio modificare de landing sau resolver nu fusese încă publicată la începutul acestei cereri. Corecția de autentificare publicată anterior rămâne păstrată.

## Rezultat executat

Implementarea și landing-ul sunt publicate pe openaliment.ro. Migrarea 002 a fost aplicată și raportul de transport înregistrat. 298 verificări locale, 178 transport HTTPS și 28 landing/SEO au trecut. Au fost păstrate toate rutele native, istoricul, importurile vechi și corecția de autentificare. Detalii de producție, fișiere și revenire: `updates/PRODUCTION_RESOLVER_2026-10-23.md`.
