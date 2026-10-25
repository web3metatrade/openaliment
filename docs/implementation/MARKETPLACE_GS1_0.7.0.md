# Marketplace, serializare și ediții GS1

25.10.2026 · release 0.7.0 · migrare aditivă 008 · API 2.4.0.

Implementarea păstrează PHP MVC, cPanel, rutele și contractele existente, independența produsului/lotului față de rețetă, precum și originalele OpenBread. Nu inițializează Git și nu conectează GitHub. Starea publicării este consemnată în [registrul verificării](../verification/marketplace-0.7.0/summary.json); pregătirea locală și activarea pe hosting sunt stări distincte.

## Comportament livrat

- Marketplace public, pagină de ofertă, spațiu pentru dezvoltator și moderare administrativă. Categorii, capabilități, platforme și compatibilități extensibile pentru ERP, imprimante și alte integrări.
- Ciornă separată de revizia aprobată, control optimist al reviziei, contact verificat, aprobarea distinctă a fiecărei versiuni și jurnal de decizii. Retragerea oprește distribuția prin platformă.
- Manifest imuabil de versiune: HTTPS public, dimensiune, SHA-256, changelog, platforme, semnătură opțională și licență/condiții păstrate la publicare. O versiune gratuită nu devine retroactiv comercială prin editarea ofertei.
- Distribuția versiunilor gratuite aprobate se face către sursa HTTPS a dezvoltatorului. Platforma nu descarcă și nu execută binare. Ofertele comerciale sunt descrise, dar nu există checkout, comisioane sau emitere automată a licențelor cât timp proiectul este în formare.
- Autorizarea API/OAuth este distinctă de selectarea unei oferte. Numele unui conector și aprobarea marketplace nu conferă acces la date sau drepturi GS1.
- Echipamente JSON/ZPL, credential dedicat afișat o singură dată și stocat numai ca hash, rotație și revocare. Agentul inițiază HTTPS din infrastructura producătorului; parolele ERP și driverele rămân acolo.
- Serii rezervate atomic sau furnizate de ERP, unicitate binară GTIN/serie, snapshot imuabil și URI GS1 Digital Link complet. Loturile și rețetele existente nu sunt modificate.
- Cozi, preluare cu secret, confirmări, rezultat incert și retipărire cu motiv. Expedierea nu dovedește tipărirea fizică; preluările expirate nu se retransmit automat. Alertele lotului se reverifică înainte de expediere.
- Separare între organizații atât în servicii, cât și prin chei străine compuse ale echipamentelor, unităților, comenzilor și evenimentelor.
- UI de previzualizare/descărcare SVG QR și API-uri paginate pentru comenzi, unități și export. Limite explicite: 500 unități/comandă, 100 comenzi noi/oră/organizație, cote persistente de unități/comenzi, ajustabile prin configurația privată.

## Fundația GS1

Cele patru PDF-uri oficiale sunt descărcate nemodificate și păstrate local în `storage/private/standards/gs1/`, cu cache în `.tools/standards/gs1/` și proveniență, dimensiuni și SHA-256 în [catalog](../../contracts/gs1/standards-catalog.json). URI Syntax 1.7.0 și Resolver 1.2.1 sunt edițiile ratificate în august 2026. EPC Compression 1.0.0 și General Specifications 26.0.0 completează referința.

Pagina `/standarde/gs1` oferă edițiile, sursele originale, metadatele și exemplul QR Pâine Bună Tehnic. `/admin/standards` poate păstra fiecare copie și pe hosting, în `storage/private/standards/gs1/`, cu TLS, sursă fixă, limită de transfer și hash verificat înainte de înlocuire. Nu există fetch de URL arbitrar, redirecturi sau documente normative modificate. Documentele GS1 nu intră în codul MIT și nu sunt redistribuite public de platformă. Procedura reproductibilă este în [referința GS1](../standards/GS1.md).

```text
https://openaliment.ro/01/09520000000011/10/DEMO-PBT-01/21/DEMO000001?11=261025&15=261030
```

Identificatorul pilotului, seria și datele din exemplu sunt simulate. În producție se folosesc numai identificatori cu drept verificat și date aprobate. AI 15 și 17 nu sunt interschimbabile. Lotul și seria sunt codificate separat în path; caracterele speciale cer profilul de transport verificat pe hosting. Domeniul complet și datele sunt codificate în QR, nu doar într-un fișier JSON trimis imprimantei.

## Contracte și compatibilitate

Migrarea 008 adaugă 11 tabele fără redenumirea sau eliminarea tabelelor existente. Instalarea nouă și actualizarea administrativă folosesc același registru de migrări și lock. Nu se schimbă formatul identificatorilor sau sesiunile 0.6.0. Migrarea este necesară înaintea folosirii marketplace-ului și tipăririi.

API 2.4.0 adaugă 29 operații pentru marketplace, echipamente și referințe GS1. Totalul este 258 operații și 19 ghiduri. Răspunsurile comune OpenAPI sunt reutilizate prin `$ref`, fără schimbarea răspunsurilor HTTP existente. Portalul și probele rezolvă aceste referințe. Exportul datelor alimentare rămâne compatibil; metadatele marketplace/tipărire sunt exportabile prin endpointurile paginate, fără credentiale sau secrete de preluare. Migrarea furnizorului păstrează URI-urile deja tipărite și controlul domeniului inițial.

## Verificare

Suita completă a trecut **51 suite, zero eșecuri**. Toate cele **258 operații documentate** au succes HTTP real, validat după schemă, în mediul local izolat. Pentru extensie sunt incluse 53 verificări de domeniu, 54 răspunsuri HTTP validate și 39 verificări de browser la 1440 și 390 px. QR-ul SVG real a fost decodat software și corespunde exact URI-ului cu lot, serie și date. Cele 8 probe ale păstrării edițiilor verifică hash greșit, redirect, răspuns prea mare, TLS, cache și păstrarea copiei anterioare la eșec.

[Dovezile sanitizate](../verification/marketplace-0.7.0/summary.json) includ rezumatul, rapoartele și hashurile sursei verificate. Nu includ configurații private, cookie-uri, tokenuri, baze de date sau loguri brute. Matricea CI este configurată; nu este declarată executată pe GitHub.

Aceste probe nu certifică un model de imprimantă, linia fizică, POS-ul sau capacitatea shared hosting la milioane de scanări. Șablonul ZPL este o referință care cere probă pe DPI, viteză, suport și scanerele partenerului. Conectorii furnizorilor și checkout-ul comercial nu sunt inventate ca produse existente. QR-ul, resolverul și pagina lotului funcționează independent de marketplace și AI.

## Publicare și operare

Release-ul 0.7.0 este publicat pe openaliment.ro: 69 fișiere de sursă în 12 transferuri HTTPS confirmate, fără conflicte față de baseline. Migrarea 008 a fost activată prin sesiunea administrativă, împreună cu cele 12 resurse operaționale din manifestul cu hash fix. Sesiunile existente nu au fost invalidate de acest release.

Toate cele patru PDF-uri au fost apoi salvate separat în directorul privat al hostingului; interfața administrativă confirmă SHA-256 corect pentru fiecare. Cele [32 probe HTTPS după publicare](../verification/marketplace-0.7.0/production-smoke.json) au trecut: capabilități active, catalogul exact al edițiilor, portalul 258/19, marketplace, produs/lot/serie cu date păstrate în redirect, linkset, HEAD/OPTIONS, Acasă și refuzul accesului anonim la operațiile private. Nu au fost create oferte, echipamente sau comenzi de test în producție și nu s-au trimis emailuri. [Jurnalul publicării](../verification/marketplace-0.7.0/publication.json), [confirmarea migrării](../verification/marketplace-0.7.0/migration-008-confirmed.png) și [confirmarea copiilor](../verification/marketplace-0.7.0/gs1-host-copies.json) păstrează dovezile.

Pachetul cPanel exclude copiile PDF private și configurațiile locale. Publicarea HTTPS verifică baseline-ul și hashurile, cu copii pentru revenire și ordonarea dependențelor. Activarea migrării și păstrarea copiilor oficiale sunt operații distincte; păstrarea unei ediții nu schimbă singură comportamentul resolverului sau declarația profilului implementat.

Cronul, backupul extern, scanerul antivirus, organizarea ONG-ului și probele fizice rămân dependente de configurarea reală descrisă în [runbook](../operations/PRODUCTION_RUNBOOK.md). Acest release nu declară închis întregul plan WP00–WP27.
