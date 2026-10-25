# Resolver GS1 Digital Link — implementare și probe

Actualizare 23.10.2026. Înlocuiește decizia inițială de landing limitat, păstrată în `history/GS1_IMPLEMENTATION_stage1.md`. Cererea proprietarului este consemnată în `RESOLVER_IMPLEMENTATION_02.md`.

Revizie 25.10.2026: [auditul compatibilității nucleului](audits/GS1_CORE_COMPATIBILITY_2026-10-25.md) verifică edițiile PDF oficiale și traseul până la scanner, inventar, export și ZPL. Corecțiile 0.7.1 păstrează profilul resolverului, rutele și identitățile existente; nu schimbă ediția standardului declarat. Registrul probelor separă verificarea locală de publicare.

## Profil și surse

Implementare GS1-Conformant Resolver 1.2.1 pentru cheia primară GTIN (01). Standardul permite un subset declarat de chei primare (§2.1). Profilul acceptă toți calificatorii GTIN: CPV (22), lot (10), serie (21), TPX (235), combinațiile permise și atributele din query. Nu declară cheile SSCC, GLN sau toate schemele EPC pentru alte chei primare.

Surse oficiale consultate:

- [Resolver 1.2.1](https://ref.gs1.org/standards/resolver/1.2.1/), în special §2 și §5.
- [URI Syntax 1.7.0](https://ref.gs1.org/standards/digital-link/uri-syntax/1.7.0/).
- [Compresie EPC 1.0.0](https://ref.gs1.org/standards/digital-link/compression/) și [TDS 2.3](https://ref.gs1.org/standards/tds/2.3.0/).
- [GS1 TDT](https://github.com/gs1/TDT): tabelele normative de codificare, cu proveniență și licența Apache-2.0 în `contracts/gs1/NOTICE.json`.
- [Schema descriptorului](https://ref.gs1.org/standards/resolver/description-file-schema), [schema linkset](https://ref.gs1.org/standards/resolver/linkset-schema), [vocabular GS1](https://ref.gs1.org/voc/), [registrul media IANA](https://www.iana.org/assignments/media-types/media-types.xhtml).

Găzduirea este independentă; OpenAliment nu atribuie GTIN-uri și nu declară afiliere, acreditare sau certificare GS1. Aceasta este declarația implementatorului pentru profilul și probele de mai jos. Nu este un certificat emis de GS1.

## Matrice funcțională

| Cerințe aplicabile din §5 | Implementare | Evidență |
| --- | --- | --- |
| Calificatori ai cheii declarate | 01, 22, 10, 21, 235; ordine și seturi de caractere | Teste domeniu și HTTP |
| GET, HEAD, OPTIONS, HTTPS, CORS | Răspunsuri publice fără credențiale CORS, HEAD fără corp | HTTP local + raport transport producție |
| Decompresie EPC obligatorie | eh/ex: SGTIN-96, SGTIN-198, SGTIN+, DSGTIN+, SGTIN++, DSGTIN++ | Exemplu oficial + vectori independenți, toate partițiile legacy și primitivele +data |
| Validare și erori | 400 format/identificator invalid; 404 identificator valid necunoscut; fără 200 fals | Domeniu + HTTP + HTTPS |
| Linkset la cerere | linkType=linkset, alias legacy all, Accept application/linkset+json | Schema GS1 validată cu jsonschema în testare |
| Granularitate și anchor | Uniunea GTIN, CPV, lot, CPV+lot, serie sau TPX; anchor decomprimat | Matrice de moștenire, EPC și hostname |
| Metadate obligatorii | Adresă publică, titlu și relație descriptivă | Validare la înregistrare |
| Limbă/media | Etichete de limbă și tip media din registrul local IANA; liste de contexte | Validare, negociere și test q |
| Un singur default efectiv | Fără metadate opționale; moștenire și granularitate | Teste conflict și selecție |
| Default multi | Variante de limbă/format; fallback către default simplu | Teste domeniu |
| Tip solicitat | Redirecționare 307 când există, 404 când lipsește, 300 la alegeri egale | HTTP + domeniu |
| Query transmis integral | Octeții query păstrați, duplicate extensii, query țintă și fragment | HTTP, teste rezervate |
| Formate linkset | JSON, application/linkset+json, JSON-LD, HTML cu date semantice | HTTP, schema oficială |
| Descriptor | /.well-known/gs1resolver, primary keys 01, namespace gs1:, context | Schema GS1 local + HTTPS |
| Namespace | Relații GS1 extinse și CURIE gs1:, plus relațiile IANA documentate | Registru și teste de selecție |

Validarea profundă a tuturor AI-urilor din query și compresia Digital Link legacy sunt opționale în standard și nu sunt implementate. Query-ul este verificat pentru structură/codificare și transmis integral; nu rescrie informațiile aprobate ale lotului. Importurile și mutațiile validează separat datele pe care le stochează.

## Registru și istoric

Migrarea aditivă `002_resolver.sql` adaugă `resolver_links` și `resolver_transport`. Rutele native `/p`, `/t`, `/b` și modelul nullable al rețetei rămân compatibile. Instanțele existente aplică migrarea în `/admin/updates`; instalările noi o aplică în setup.

Destinațiile au `link_key` stabil, versiuni, calificatori, metadate, ciornă, actor și aprobare separată. Actualizarea nu suprascrie revizia precedentă. `expected_version` respinge scrierea concurentă depășită. Numai organizația autorizată și un rol potrivit pot administra un GTIN verificat. UI: `/manage/resolver/{trade_item_id}`; API: `docs/API.md` și `contracts/openapi.json`.

Paginile native furnizează implicit informația aprobată. O destinație externă poate deveni default numai după aprobare explicită. Lotul cunoscut păstrează eticheta lui istorică. Lotul necunoscut poate moșteni informația generală, cu avertizare că nu este pagina acelui lot; nu selectează un lot aproximativ. Alertele active și disputele de mandat păstrează pagina nativă ca default sigur. Recenziile și AI nu modifică registrul public.

## Transport și QR

`rawurlencode` se aplică valorii, segmentarea precede o singură decodare, plus rămâne plus, codurile se compară exact. Raportul HTTPS probează întregul set X în valori de lot, slash/procent literal, zerouri, case, calificatori și EPC, prin infrastructura reală. Se înregistrează în `/admin/updates` după executare. Înaintea acestei verificări, generatorul păstrează profilul conservator anterior.

Loturile care nu intră în GS1 X/20 sau sunt literal `.` ori `..` folosesc QR nativ `/b/{id}`. Segmentele punct pot fi normalizate de browser; valoarea nu se modifică și nu se dublează codificarea pentru a ocoli hostingul. `/01` și `/b` identifică același lot publicat prin maparea registrului, când toate condițiile sunt îndeplinite.

SVG-ul QR, marginea, contrastul și decodarea digitală nu certifică tiparul sau citirea POS. Probele fizice pe ambalaj rămân distincte.

## Probe și limite practice

Rapoarte: `verification/resolver-domain-report.json`, `resolver-http-report.json`, `epc-vectors.json` și `resolver-production-transport.json`. Testele locale folosesc exclusiv DB izolată `openaliment_tests`. Producția nu primește produse, loturi sau GTIN-uri fictive pentru teste. Succesul rezolvării pentru articole înregistrate, istoricul, aprobările și alertele sunt probate în mediul izolat; pe hosting se probează descriptorul, protocolul și transportul identificatorilor exemplu neînregistrați.

Setul propriu este construit după standard și schemele oficiale; nu pretinde executarea neadaptată a întregii suite de fixture-uri GS1 pentru alt resolver și nici audit extern. Milioane de scanări, restore de producție și tipar POS nu au fost testate. Nu există AI, serviciu terț sau scriere de analytics pe traseul public al resolverului. Cache-ul paginilor și rezolvării rămâne no-store pentru vizibilitatea imediată a alertelor; resursele statice sunt cacheabile. Capacitatea planului shared se măsoară înaintea unui volum comercial mare.

## Portabilitate

Exporturile JSON/NDJSON includ reviziile resolverului. Importul păstrează proveniența și istoricul, reface ID-urile locale și intră în ciornă, fără aprobări ori mandat transferate automat. Exporturile vechi fără colecția `resolver_links` rămân acceptate. Un linkset public poate ajuta inventarul, însă nu înlocuiește exportul privat al furnizorului. Adaptoarele GS1/alt furnizor se definesc după formatul real și autoritatea clientului. Continuitatea domeniului din QR-ul tipărit se planifică separat.

## Referința locală și tipărirea serializată

[Edițiile oficiale și proveniența lor](standards/GS1.md) sunt fixate în catalogul platformei. [Contractul pentru linia de producție](api/pages/marketplace.md) codifică URL-ul HTTPS complet în QR, cu GTIN, lot, serie și datele GS1 aplicabile; comenzile păstrează snapshoturi istorice și separă expedierea de confirmarea fizică. Această extensie nu schimbă declarația profilului resolverului și nu declară probe pe imprimante sau POS-uri neexecutate.
