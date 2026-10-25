# Completarea conținutului public GS1 Digital Link

24.10.2026 · publicat pe https://openaliment.ro/gs1-digital-link

Cerere: compararea paginilor GS1 cu OpenAliment și completarea explicațiilor, imaginilor și exemplelor relevante. Proprietarul a cerut explicit Pâine Bună Tehnic ca exemplu.

Au fost consultate GS1 România, pagina globală GS1 Digital Link, FAQ-urile oficiale despre GTIN, suporturi, adrese persistente și tranziția 2D, plus ghidul GS1 retail POS. Auditul structurat, sursele și proveniența pilotului sunt în `../verification/gs1-content-audit-2026-10-24.json`.

S-au adăugat ghidul `/gs1-digital-link`, ilustrația SVG originală cu pâinea pilot, comparația GTIN/EAN/QR, exemplul comutabil produs/lot, utilizările pentru client și aplicații, actualizarea după tipărire, contextul Sunrise 2027/POS și clarificări DPP/trasabilitate. Homepage-ul are introducerea ilustrată, întrebări suplimentare și link către exemplu. Ghidul are metadate, canonical, WebPage/BreadcrumbList și intrare în sitemap.

Exemplul folosește numele Pâine Bună Tehnic, inițiativa OpenBread, baza cerealieră secară și stadiul R1 experimental, conform surselor istorice. `{GTIN}` este placeholder, iar `DEMO-PBT-01` este lot ilustrativ. Nu au fost create articole, loturi ori drepturi GTIN în baza de producție și nu s-au publicat originalele sau rețeta integrală. Nu s-au atribuit gramaj aprobat, termen de valabilitate sau rezultate de producție.

Grafica și textele sunt originale; imaginile, logo-urile, mărcile de clienți și numărul participanților GS1 nu au fost reutilizate ca active sau realizări OpenAliment. SVG: 3.965 octeți; JavaScript pentru exemplu: 803 octeți. Fără librării noi. Cu JavaScript dezactivat, ambele exemple rămân în HTML, iar controalele sunt ascunse.

Publicare prin conexiunea HTTPS existentă: `5b856f197ac54514983ed5d9478681f5`, 8 fișiere, aplicat la 05:22:52 UTC (08:22:52 Europe/Bucharest). Baseline-ul remote a fost verificat înaintea scrierii; backup și verificare SHA-256 după upload. Nu s-au modificat schema DB, contractele ERP, resolverul sau rutele native existente.

Verificări: sintaxa celor 5 fișiere PHP modificate și a noului JS; 10 verificări HTTP locale; 32 verificări de producție/SEO; desktop 1440×950 și mobil 390×844, fără depășire orizontală; comutare produs/lot cu mouse și Enter, starea aria-pressed și vizibilitatea panourilor. Consola paginii nu a înregistrat erori sau avertismente. Capturi în `../previews/production-gs1-*2026-10-24.jpg`.

Revenirea se face folosind actualizarea HTTPS identificată mai sus. Pentru recuperare este păstrat și pachetul incremental `release/OpenAliment_public_guide_patch_2026-10-24.zip`, fără configurația reală sau credențiale. Pachetul cere versiunea anterioară a platformei; nu este o instalare completă.
