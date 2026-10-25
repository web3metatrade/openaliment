# Auditul compatibilității nucleului GS1

25.10.2026 · bază publicată 0.7.0 · corecții 0.7.1 · API 2.4.1.

Concluzie: modelul intern OpenAliment și serviciile anexe nu înlocuiesc identitatea GS1. Nucleul resolverului păstrează regulile profilului GTIN declarat. Auditul a identificat diferențe de interpretare și pierderi de metadate în proiecțiile consumatorului, filtrarea scanărilor, cache și șablonul de tipărire; corecțiile sunt descrise mai jos. Starea exactă a probelor și publicării este în [registrul verificării](../verification/gs1-core-0.7.1/summary.json).

## Referințe și metodă

- [GS1 Digital Link URI Syntax 1.7.0](https://ref.gs1.org/standards/digital-link/uri-syntax/1.7.0/): în special §4.2, §4.5–4.12.
- [GS1-Conformant Resolver 1.2.1](https://ref.gs1.org/standards/resolver/1.2.1/): §2.1–2.14, §3 și cele 25 puncte din §5.
- [GS1 Digital Link URI: Compression 1.0.0](https://ref.gs1.org/standards/digital-link/compression/) și tabelele TDT/TDS 2.3 deja fixate.
- [GS1 General Specifications 26.0.0](https://ref.gs1.org/standards/genspecs/26.0.0/): semnificația AI-urilor, caractere, date și simboluri QR.

Au fost citite edițiile PDF originale păstrate privat, cu hashurile din [catalog](../../contracts/gs1/standards-catalog.json), și verificate paginile oficiale curente. PDF-ul normativ prevalează asupra prezentărilor HTML. Revizia acoperă codul PHP, parserul JavaScript, schemele SQL/JSON, contractele API, import/export, PWA, QR/ZPL, documentația publică și probe HTTP. Acesta este un audit intern cu teste proprii; nu reprezintă certificare sau audit independent GS1.

## Separarea corectă între standard și aplicație

GS1 nu impune structura bazei de date a unui resolver. Tabelele `products`, `trade_items`, `batches`, versiunile etichetei, comenzile de tipărire și marketplace-ul pot avea modele proprii. Contractul de interoperabilitate este reprezentarea identificatorilor, interpretarea și răspunsul resolverului. Rețeta, licența documentației, sigla, drepturile de acces și aprobările sunt funcții OpenAliment distincte.

În implementare, GTIN-ul rămâne text ASCII cu 14 cifre și zerouri păstrate, lotul se compară exact, iar seria este `VARBINARY(20)`. Unicitatea unității este GTIN + serie, independent de lot și CPV. Actualizarea etichetei nu modifică snapshotul lotului sau URI-ul unităților existente. Identificatorii locali și mapările importurilor nu înlocuiesc GTIN-ul. Verificarea cifrei de control nu acordă mandat de administrare, iar o scanare nu dovedește achiziția sau autenticitatea fizică a ambalajului.

Domeniul `openaliment.ro` este permis pentru un GS1 Digital Link URI. Termenul normativ „canonical GS1 Digital Link URI” are însă reguli suplimentare, inclusiv domeniul `id.gs1.org` (§4.12). Eticheta HTML `rel=canonical` folosită pentru SEO nu este o revendicare că domeniul OpenAliment reprezintă forma canonică GS1. Nu se mută QR-urile existente pe alt domeniu pentru a adopta o asemenea formă; continuitatea domeniului tipărit rămâne esențială.

## Corecții aplicate

| ID | Situația găsită în 0.7.0 | Soluția 0.7.1 și proba |
| --- | --- | --- |
| GS1-01 | Parserul Acasă offline accepta calificatori necunoscuți sau în ordine greșită, nu interpreta `;` în query și respingea slashul final tolerat de resolver. Normalizarea URL putea elimina un segment punct înainte de interpretare. | Același set X/82, lungimi, ordine 22→10→21 și exclusivitate TPX ca pe server; separatori `&`/`;`, decodare o singură dată și citirea pathului original înainte de normalizarea browserului. 362 vectori comuni PHP/JS. Generatorul continuă să emită `&` și să evite segmentele punct în QR. |
| GS1-02 | Proiecția alimentară și curățarea inventarului nu expuneau/păstrau separat seria și CPV. Codul scanat brut exista în inventar, dar metadatele structurate puteau dispărea. | `gs1_qualifiers` opțional, distinct de informația aprobată, plus păstrarea `observed_dates` în inventar, import/export și sincronizare. Codul original rămâne păstrat. Probe de domeniu, HTTP și browser. |
| GS1-03 | Filtrul de scanări repetate putea considera două serii diferite ale aceluiași GTIN/lot drept aceeași scanare. | Identitate de scanare GTIN + AI 21, respectiv GTIN + TPX; produsul/lotul generic rămân la granularitatea lor. Seria nu este condiționată de prezența lotului sau CPV. |
| GS1-04 | Cache-ul offline reutiliza profilul altui ambalaj cu același GTIN/lot, inclusiv o dată observată în cod. | Calificatorii și datele observate se iau din scanarea curentă; o serie sau o dată din codul altui ambalaj nu se copiază. Cache-ul diferențiază CPV. Datele producătorului rămân distincte și verificarea alertelor este marcată ca neactualizată. |
| GS1-05 | Generatorul de date pentru tipărire avea o condiție artificială `20xx`, deși fereastra de interpretare GS1 poate include și ani anteriori lui 2000. | Verificare calendaristică pe anul complet și round-trip prin fereastra de 49 ani în urmă / 50 înainte. Anii ambigui nu sunt trunchiați ori recodificați. |
| GS1-06 | Poziția fixă a textului inferior din ZPL nu rezerva suficient spațiu pentru orice URI acceptat. | Șablon explicit 600×650 puncte, QR-L cu 5 puncte/modul, text separat și limită de 512 octeți. Testul cu modul byte verifică maximum 77 module și minimum 4 module libere. O adresă prea lungă este refuzată pentru acest șablon, fără trunchiere; adaptorul JSON rămâne disponibil. |
| GS1-07 | Formularea „seria și TPX individual” din ghid putea fi interpretată ca o interdicție de a combina seria cu lotul în URL. | Ghidul separă explicit regulile de înregistrare a legăturilor de sintaxa URI-ului scanat. Asocierea serială se înregistrează GTIN+serie, iar cererea poate include CPV+lot+serie. |

Ordinea de prioritate `use_by` față de `best_before` este păstrată și în proiecția defensivă. Crearea/importul unui lot aprobat refuzau deja două tipuri de termen simultan; această ajustare nu înseamnă că au fost găsite loturi de producție corupte.

## Matricea resolverului — §5

| Punct | Verificare aplicabilă profilului GTIN | Rezultat / probă |
| --- | --- | --- |
| 1 | Toți calificatorii cheii declarate | 22, 10, 21, 235; caractere/lungimi și combinații probate. |
| 2–4 | GET, HEAD, OPTIONS, HTTPS, CORS | Probe HTTP și transport HTTPS; HEAD fără corp, preflight fără cookie. |
| 5–6 | EPC și forma decomprimată | `eh`/`ex`, cele șase scheme GTIN declarate; vectori independenți și anchor decomprimat. Nu se afirmă suport pentru toate cheile EPC din ecosistem. |
| 7–9 | Validare, 400/404, erori reale | Identificatori malformați respinși, valid necunoscut 404; fără pagină de eroare 200. |
| 10 | Linkset și moștenire | Cerere explicită/header, schemă GS1 și uniunea GTIN/CPV/lot/CPV+lot/serie/TPX aplicabile. |
| 11–13 | URL, titlu, tip descriptiv, media, context | Validări la înregistrare, IANA local, linkset JSON/JSON-LD/HTML și context declarat. |
| 14–16 | Un default efectiv, fără metadate opționale | Publicare fără conflicte, moștenire și fallback nativ; alertele și disputele protejează pagina de siguranță. |
| 17–18 | Tipul cerut sau 404 | Selecție `linkType`, 307 pentru destinație, 300 pentru alegeri egale, 404 pentru tip absent. |
| 19 | Query transmis integral | `Resolver::location` păstrează octeții query-ului, inclusiv extensii duplicate, combinând cu query-ul și fragmentul destinației. |
| 20 | Negociere deterministă | Limbă/media/context probate; prioritizarea implementatorului este permisă de §2.6.3. |
| 21–22 | Descriptor valid și chei declarate | Schema oficială, `supportedPrimaryKeys=["01"]`, context și namespace publice. |
| 23 | Asocieri GTIN și uniunea corectă | Seria și TPX se înregistrează separat; CPV+lot permis; interogarea completă nu pierde legăturile independente ale lotului sau seriei. |
| 24 | Namespace GS1 | CURIE `gs1:` și URI complet, plus relațiile IANA documentate. Funcțiile OpenAliment nu primesc AI-uri numerice sau nume GS1 inventate. |
| 25 | Slash final | Tolerat de resolver și scanner; generatoarele nu îl adaugă. |

În afara cheii 01, profilul nu declară SSCC/GLN sau toate celelalte chei GS1. Validarea profundă a fiecărui AI din query și compresia Digital Link legacy sunt opționale și nu sunt revendicate. Query-ul se păstrează pentru destinatar; serviciile care interpretează datele validează separat câmpurile folosite. Aceste limite sunt publicate în [profilul implementării](../GS1_IMPLEMENTATION.md).

## Organizare, export și servicii anexe

- Registrul este separat de versiunile etichetei și de identitățile unităților. Rezolvarea nu cere rețetă, licență deschisă, marketplace sau AI.
- Importul păstrează loturi, GTIN-uri și istoric, verifică hashurile și recreează ID-uri locale cu proveniență; destinațiile intră în ciornă și mandatele nu se importă ca aprobări.
- Exportul JSON/NDJSON include reviziile resolverului. Exportul inventarului păstrează codul brut și metadatele GS1 observate. Unitățile și comenzile au endpointuri paginate, fără credentiale.
- Selectarea unui conector nu schimbă identificatorii și nu acordă acces API. Un adaptor ERP/imprimantă trebuie să trimită URI-ul întreg și să păstreze șirurile, inclusiv zerouri/case. Nu poate substitui un cod intern în AI 01.
- QR-urile native sunt etichetate ca native. Dacă un lot nu intră în GS1 X/20 sau transportul nu este verificat, se folosește pagina nativă, fără cosmetizarea valorii pentru a pretinde compatibilitate GS1.
- Generatoarele emit numai AI-urile semnificative implementate. Extensiile proprii rămân distincte; numele numerice din query și namespace-ul GS1 nu sunt un spațiu de nume OpenAliment.

## Publicare și verificare prin infrastructura reală

0.7.1 este publicat pe openaliment.ro: 29 fișiere în șase transferuri HTTPS cu hashuri și copii pentru revenire, fără conflicte sau migrare SQL. Administratorul a instalat manifestul operațional fix, inclusiv metadatele release-ului. Nu au fost create produse, unități, oferte sau comenzi de test în producție.

Au trecut **540 probe HTTPS**: matricea existentă de 178 probe pentru descriptor/CORS/EPC/transport și 362 probe ale release-ului. Acestea din urmă includ toate cele 82 caractere X în fiecare dintre AI 22, 10, 21 și 235; fiecare anchor din linkset a fost comparat cu URI-ul așteptat, pentru a detecta decodarea dublă sau modificarea pathului de infrastructură. Au fost verificate și metadatele seriale/CPV din API, separatorul `;`, contractele publicate, PWA v8 și compatibilitatea paginilor de produs/lot. Identificatorii folosiți sunt exclusiv demonstrativi sau exemple neînregistrate, fără revendicări de mandat.

Raportul a fost înregistrat autentificat în `/admin/updates`, cu confirmare **25.10.2026 17:11 Europe/Bucharest**. Cele 182 intrări ale atestării includ 178 probe individuale și patru grupuri X/82; dovezile complete rămân în cele două rapoarte sanitizate. [Registrul](../verification/gs1-core-0.7.1/summary.json), [probele release-ului](../verification/gs1-core-0.7.1/production-smoke.json), [matricea resolverului](../verification/gs1-core-0.7.1/resolver-production-transport.json), [publicarea](../verification/gs1-core-0.7.1/publication.json) și [confirmarea vizuală](../verification/gs1-core-0.7.1/transport-confirmed.png) disting probele de orice certificare externă.

## Compatibilitate și controlul schimbărilor

Regresia finală a trecut 54 suite și 12.790 verificări contorizate, inclusiv succes HTTP validat după schemă pentru toate cele 258 operații documentate. Corpusul comun are 362 cazuri; verificarea PHP a trecut 1.051 aserțiuni, iar cea JavaScript 1.047. Fluxul real de browser a verificat păstrarea seriilor diferite online/offline și absența copierii datei din codul altui ambalaj. Nu s-au folosit date sau emailuri de producție în regresie.

Separat a fost executat [motorul oficial GS1 Barcode Syntax Engine 1.4.1](https://github.com/gs1/gs1-syntax-engine/releases/tag/1.4.1). Arhiva a fost verificată după digestul SHA-256 publicat de GitHub, iar cele trei fișiere executate au hashuri fixate în `tests/fixtures/gs1-engine-1.4.1.json`. Motorul oficial a acceptat toate cele 335 URI-uri produse de constructorii PHP reali, acoperind caracterele X/82 și datele tipărite. Este un oracle suplimentar pentru sintaxa generată; nu verifică resolverul, drepturile GTIN, infrastructura sau imprimanta. Biblioteca nu a fost adăugată în producție. Proba este reproductibilă cu `tests/gs1-compatibility.php`, apoi `node tests/gs1-official-engine.mjs <directorul-local-al-motorului-oficial>`; motorul se descarcă separat din release-ul fixat.

Nu este necesară migrare SQL. Rutele persistente, identificatorii, istoricul și schema inventarului v1 rămân compatibile. Metadatele API sunt aditive și opționale; profilurile vechi sunt acceptate, fără inventarea unei serii lipsă. IndexedDB nu este resetată; PWA v8 înlocuiește resursele din cache și păstrează datele locale.

Schimbarea suprafeței ZPL este explicită și necesită verificarea configurației imprimantei înainte de utilizare. Limita șablonului este o limită de adaptor, nu o redefinire a standardului. Nu se rescriu comenzile sau URI-urile deja stocate.

[Regulile pentru contribuții](../../CONTRIBUTING.md) cer sursa normativă și probe pentru orice schimbare GS1. Vectorii comuni sunt incluși în runner și CI. Contractele și exemplele API se verifică față de răspunsuri HTTP reale. Edițiile normative nu se actualizează automat peste cele vechi; o ediție nouă cere revizie de compatibilitate.

Probele software nu confirmă tiparul, materialul etichetei, DPI-ul, viteza liniei, un POS concret, un audit GS1 extern sau milioane de scanări pe shared hosting. Acestea rămân verificări de acceptanță pe infrastructura și echipamentele efective. Nicio asemenea performanță sau certificare nu este declarată prin simpla existență a PDF-urilor ori a testelor locale.
