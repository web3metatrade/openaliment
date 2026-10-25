# API OpenAliment și compatibilitate ERP v1

Pentru integrări noi folosește [ghidul API complet](api/DEVELOPER_GUIDE.md) și [contractul agregat OpenAPI](../contracts/platform-openapi.json). Acestea acoperă API public, producători, chei revocabile, OAuth PKCE, Acasă și aplicații terțe. Secțiunile de mai jos descriu compatibilitatea v1.

Baza de producție: `https://openaliment.ro/api/v1`. Specificația principalului flux: `contracts/openapi.json`; pagină de lectură: `/api/docs`.

Inventarul transversal al tuturor paginilor și completărilor propuse se află în [inventarul din 24 octombrie 2026](api/API_ACTION_INVENTORY_2026-10-24.md), cu [catalog JSON](api/API_ACTION_INVENTORY_2026-10-24.json). Acesta păstrează situația dinaintea implementării; disponibilitatea curentă se verifică în manifestul `/api/manifest.json` și contractul agregat, nu prin presupunerea că toate propunerile istorice sunt active.

## Contract general

JSON UTF-8, `Authorization: Bearer TOKEN`, HTTPS. Tokenurile se creează și revocă din spațiul ownerului. Scopes: read/write/publish/alerts/export/import. Acestea nu înlocuiesc rolul curent din membership. Un token nu poate crea alte tokenuri sau verifica singur drepturi GS1. Implicit expiră la 90 zile; poate fi limitat la 1–365 zile.

Mutațiile private necesită `Idempotency-Key` (8–128 caractere `[A-Za-z0-9_.:-]`). Cheia este legată de token/utilizator și organizație. Aceeași cerere întoarce același rezultat; alt conținut cu aceeași cheie primește 409. Publicarea și eliberarea se solicită explicit. Browserul cu sesiune folosește suplimentar CSRF; tokenurile Bearer nu folosesc cookie ca autoritate.

Limita inițială pentru corpul obișnuit este 2 MB; pentru transfer 24 MB. Cererile de modificare private sunt limitate la 120/minut/principal. Autentificarea are limite pe IP și cont. Pentru volum ERP mai mare, limitele se calibrează pe hosting și se folosesc loturi de operații planificate, fără a elimina izolarea.

Erori: `{error_code,message,request_id}`; 400 format, 401 autentificare, 403 scope/rol/origine, 404 lipsă sau neautorizat, 409 conflict, 413 dimensiune, 419 CSRF, 422 validare, 429 limită, 503 indisponibilitate. Nu se returnează SQL, parole sau stack trace.

## Flux principal

| Metodă / rută după baza API | Scope | Corp |
| --- | --- | --- |
| POST `/manage/products` | write | name, category |
| POST `/manage/products/{id}/trade-items` | write | name, gtin opțional, claim_evidence opțional |
| POST `/manage/trade-items/{id}/labels` | write | `product-label.schema.json` |
| POST `/manage/labels/{id}/publish` | publish | `{}` |
| POST `/manage/trade-items/{id}/batches` | write | `batch.schema.json` |
| POST `/manage/batches/{id}/publish` | publish | `{}` |
| POST `/manage/batches/{id}/release` | publish | `{}` |
| POST `/manage/batches/{id}/alerts` | alerts | message, state=on_hold/recalled |
| POST `/manage/alerts/{id}/resolve` | alerts | resolution |
| GET `/manage/qr/item/{id}` | read | Fără corp |
| GET `/manage/qr/batch/{id}` | read | Fără corp |

Datele de lot folosesc ISO YYYY-MM-DD. Nu trimite ambele best_before și use_by. Codul lotului se păstrează exact; nu îl normaliza în ERP. Nu trimite cifre GTIN ca număr JSON: identificatorii sunt șiruri. O modificare de etichetă creează altă versiune, fără PATCH al snapshotului publicat.

QR API returnează `kind`, `url`, `native_url`, `digital_link`, `reason`, `resolver_level`. Cu migrarea 002, `resolver_level` este `gs1_conformant_resolver_1.2.1` pentru profilul GTIN declarat. ERP-ul poate genera QR din URL; interfața OpenAliment generează local SVG. QR generic și QR lot au endpointuri distincte.

## Alte operații

- GET `/products?page=1&limit=20`, `/products/{id}`, `/trade-items/{id}`, `/batches/{id}`: proiecții publice, fără rețete private. Limita listei este 100.
- GET `/recipe-versions/{id}`: numai versiune publică, drepturi clarificate și licență explicită.
- GET `/manage/products`, `/manage/{products|trade-items|labels|batches|recipes|documents}/{id}`: citire autorizată a organizației.
- POST `/manage/products/{id}/recipes`: version_key, snapshot cu formula și process; visibility private implicit. Pentru publicare: license_id, rights_confirmed=true, license_confirmed=true și public_projection_confirmed=true; owner/quality obligatoriu.
- POST `/manage/documents/{id}/publish`: rights_confirmed, public_projection_confirmed; license_id opțional pentru simpla găzduire. Uploadul inițial se face prin formular; endpointul de upload multipart ERP urmează într-o etapă ulterioară.
- POST `/manage/products/{id}/proposals`, `/manage/proposals/{id}/decide`: propuneri și decizii atribuite, fără mutația produsului aprobat.
- POST `/feedback`: text, kind, rating opțional, trade_item_id sau batch_id, product_info_version_id opțional pentru versiunea istorică afișată, consent=true și submission_key ori Idempotency-Key. Nu necesită cont; începe pending. Retur: id/status/status_token (tokenul numai la prima salvare). Pentru retrimitere păstrează aceeași cheie.
- GET `/feedback/{id}/status`, antet `X-Status-Token`: doar starea autorului, fără date private sau recomandări de moderare.
- POST `/manage/feedback/{id}/moderate`: status, public_text când este publicat, reason. Reclamațiile de calitate rămân restricționate; o critică legitimă nu este respinsă pentru nota mică.
- GET `/manage/export`; POST `/manage/import` cu `{package: EXPORT, dry_run: true}`; POST `/manage/import-pbt` cu `{dry_run: true}`. Importul efectiv folosește false și cheie de idempotentă.

Exemplele și datele din testele locale nu reprezintă produse reale, GTIN-uri cu drept de utilizare sau rezultate alimentare validate. Nu le transfera în producție.

Export complet în flux: `GET /manage/export?format=ndjson`, cu scope export. Formatul și digestul terminal sunt descrise în IMPORT_EXPORT.md.

## Registrul resolverului · actualizare 02

- GET `/manage/trade-items/{id}/resolver-links` (read): istoricul autorizat al destinațiilor.
- POST aceeași adresă (write): `href`, `title`, `types`, opțional `qualifiers`, `is_default`, `hreflang`, `type`, `context`; o actualizare trimite `link_key` și `expected_version`.
- POST `/manage/resolver-links/{id}/publish` (publish): `public_projection_confirmed=true` obligatoriu.
- POST `/manage/resolver-links/{id}/withdraw` (publish): `reason`, cu istoric păstrat.

Necesită drept GTIN verificat. Calificatori la înregistrare: {}, {22}, {10}, {22,10}, {21}, {235}; seria și TPX nu se înregistrează cu alți calificatori. Default-ul necesită un tip descriptiv și nu acceptă limbă/media/context. Publică întâi pagina produsului sau default-ul GTIN. Reviziile importate intră draft. Scopes, roluri, CSRF/same-origin și Idempotency-Key rămân aplicabile.

Resolver public pe domeniul de bază, fără prefixul `/api/v1`: `/01/{GTIN}` cu calificatori, `/eh…`, `/ex…`, `/.well-known/gs1resolver`. GET/HEAD/OPTIONS, CORS; 307, 300, 400, 404. JSON/linkset/JSON-LD/HTML după cerere. Descriere publică și exemple: `/api/docs`; contract descărcabil: `/api/openapi.json`.
