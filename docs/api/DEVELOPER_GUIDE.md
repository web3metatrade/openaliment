# OpenAliment API — ghid de integrare

Punct de pornire: `https://openaliment.ro/api/manifest.json`. Referința completă: [OpenAPI](/api/platform-openapi.json). Exemplele sunt simulate; identificatorii, termenele și aprobările reale se obțin din sistemul producătorului.

## Alege tipul de acces

| Utilizare | Acces | Emitere și retragere |
| --- | --- | --- |
| Citești informații publicate despre alimente | Fără cont sau cheie; HTTPS, CORS public | Date aprobate, cote publice, paginare |
| Automatizezi ERP-ul propriei organizații | Cheie Bearer cu scopes minime | `/manage/api`; creare, rotație și revocare disponibile și prin API cu sesiunea ownerului |
| Construiești aplicație Android/iOS/web sau agent AI pentru alții | OAuth Authorization Code + PKCE S256 | Persoana aprobă o organizație sau familie; token de acces 15 minute și refresh rotit |
| Administrezi platforma | Sesiune proprie de administrator | API-urile `/api/v2/admin/*`; cheile și granturile terților nu acordă acest rol |

Cheile ERP expiră în 1–365 zile, implicit 90, maximum 30 active/organizație. Secretul apare o singură dată și nu este recuperabil. Rotația revocă imediat cheia veche și păstrează permisiunile. Revocarea se aplică la cererea următoare, inclusiv când un rol sau membership a fost retras. Nu introduce o cheie ERP comună în APK, JavaScript public, URL, repository sau loguri.

Scopes producător: `read`, `write`, `publish`, `alerts`, `export`, `import`, `community`. Rolul curent limitează suplimentar fiecare scope. `write` creează ciorne; nu autorizează publicarea. Verificarea dreptului GS1 este o decizie separată de administrator.

Paginile HTML `/manage/*` și `/admin/*` acceptă exclusiv sesiunea contului în browser. Un antet Bearer este refuzat chiar dacă există și un cookie valid. Integrările folosesc rutele API v1/v2; permisiunile sunt verificate și în comenzile care modifică datele.

Sesiunea producătorului expiră după două ore de inactivitate sau opt ore de la conectare. Acasă are o identitate separată și păstrează conectarea persistentă.

Schimbarea parolei: `POST /api/v2/identity/password`, cu `current_password`, `new_password`, `confirmation`, cookie și `X-CSRF-Token`. Retragerea celorlalte accesuri: `POST /api/v2/identity/access-revocations`, cu `current_password`, cookie și CSRF. Ambele rotesc sesiunea curentă, întorc noul `csrf`, invalidează celelalte sesiuni și revocă cheile API/granturile producător emise de cont în toate organizațiile sale. Contul Acasă nu este afectat. Nu reîncerca automat aceste operații cu credentialele/CSRF vechi.

Parola nouă trebuie să aibă cel puțin 12 caractere. Limita maximă în octeți este indicată de formular și de schema operației; diacriticele pot ocupa mai mulți octeți. Actualizarea parolei se face din contul propriu, în `/manage/security`. Pentru aplicații terțe folosește OAuth, fără să ceri sau să păstrezi parola producătorului.

## Citire publică

```sh
curl --fail-with-body 'https://openaliment.ro/api/v2/public/food?code=9520000000011'
curl --fail-with-body 'https://openaliment.ro/api/v2/public/trade-items?limit=20'
curl --fail-with-body 'https://openaliment.ro/01/09520000000011/10/DEMO-PBT-01?linkType=linkset' \
  -H 'Accept: application/linkset+json'
```

GTIN-ul 952 este exclusiv demonstrativ și nu se înregistrează ca articol comercial. Scanarea sa nu validează automat un articol în POS. Pentru coduri cu caractere speciale, folosește o bibliotecă URL și codifică parametrul `code` o singură dată. GTIN-ul este șir, cu zero-urile inițiale păstrate. Un cod generic nu identifică un lot.

`GET /api/v2/public/food?code=…` acceptă GTIN, URL nativ OpenAliment sau GS1 Digital Link. Returnează `source`, `food_data`, `expiry`, `alerts`, `alerts_checked_at`, `alert_scope` și avertizări. Necunoscut nu înseamnă zero, fără alergeni sau sigur. Rețetele private și documentele neaprobate nu intră în răspuns. Codurile unor produse negăzduite în OpenAliment pot fi interpretate, dar platforma nu interoghează baze de produse externe. Manifestul indică `external_product_sources=false`; datele absente rămân necunoscute.

Pentru până la 20 de coduri:

```sh
curl --fail-with-body 'https://openaliment.ro/api/v2/public/food-lookups' \
  -H 'Content-Type: application/json' \
  --data '{"codes":["9520000000011","https://openaliment.ro/01/09520000000011/10/DEMO-PBT-01"]}'
```

Fiecare cod consumă o unitate din cota publică. Răspunsul are un `items[]` cu `data` sau `error` pentru fiecare cod. O eroare la un cod nu produce informații inventate pentru celelalte.

## Flux ERP: produs → etichetă → lot → QR

Client Python fără dependențe suplimentare: [openaliment.py](/api/sdk/python). Client JavaScript: [openaliment.mjs](/api/sdk/javascript). Instalează clientul în proiectul tău, nu îl încărca dintr-un CDN cu secretul în pagină.

Pentru Android există și [exemplul Kotlin](/api/sdk/kotlin), bazat pe API-urile Android și `HttpURLConnection`. Apelurile se execută pe un executor IO; autorizarea se deschide prin browserul sistemului, folosind `Intent.ACTION_VIEW`, iar tokenurile sunt furnizate de stocarea sigură a aplicației. Clientul Kotlin este disponibil ca fișier sursă pentru integrarea în aplicația ta Android. Platforma Acasă se utilizează în browser; nu există o aplicație OpenAliment distribuită prin Google Play.

```python
import os, uuid
from openaliment import OpenAliment

api = OpenAliment(token=os.environ['OPENALIMENT_API_KEY'])
def create(path, payload, operation_id):
    # Persist operation_id with the ERP job. Reuse it unchanged on retry.
    return api.request('POST', '/api/v2/manage/' + path, payload,
                       idempotency_key=operation_id)

job = 'erp-' + uuid.uuid4().hex
product = create('products', {'name':'Iaurt natural','category':'lactate'}, job+'-product')
item = create('products/'+product['id']+'/trade-items',
              {'name':'Pahar 200 g'}, job+'-item')
label = create('trade-items/'+item['id']+'/labels', {
    'version_key':'E1','name':'Iaurt natural','category':'lactate',
    'ingredients':'Lapte, culturi lactice.','allergens':'Lapte.',
    'net_quantity':'200 g','operator':'Denumirea și adresa operatorului'
}, job+'-label')
# Run these approval commands only with the producer's explicit authority.
create('labels/'+label['id']+'/publish', {}, job+'-approve-label')
batch = create('trade-items/'+item['id']+'/batches', {
    'lot_code':'EXEMPLU-001','product_info_version_id':label['id'],
    'recipe_version_id':None
}, job+'-batch')
create('batches/'+batch['id']+'/publish', {}, job+'-approve-batch')
qr = api.request('GET', '/api/v2/manage/qr/batch/'+batch['id'])
print(qr['url'])  # Public URL, not an access token.
```

Exemplul nu înregistrează GTIN și nu presupune rețetă, licență deschisă sau siglă. Pentru GS1, articolul primește `gtin` și `claim_evidence`; administratorul verifică mandatul. QR API returnează adresa, tipul, URL-ul nativ, motivul și nivelul resolverului. SVG-ul se poate genera local în ERP din `url`, la fel ca în interfața platformei. API-ul QR furnizează adresa de codificat; imaginea SVG se generează în client.

Etichetele și rețetele sunt versiuni imutabile. Pentru schimbare creezi `E2`; loturile vechi rămân legate de `E1`. `production_date`, `best_before`, `use_by` folosesc `YYYY-MM-DD`; nu trimite simultan `best_before` și `use_by`. Eliberarea lotului pentru consum folosește separat `POST /api/v2/manage/batches/{id}/release`.

## Alerte și retrageri

```sh
curl --fail-with-body -X POST 'https://openaliment.ro/api/v2/manage/batches/ID_LOT/alerts' \
  -H "Authorization: Bearer $OPENALIMENT_API_KEY" \
  -H 'Content-Type: application/json' -H 'Idempotency-Key: erp-alerta-001' \
  --data '{"message":"Mesajul aprobat al producătorului","state":"recalled"}'
```

Necesită `alerts` și owner/quality. Pentru închidere: `POST /api/v2/manage/alerts/{id}/resolve`, cu `resolution`. Închiderea păstrează istoricul; nu șterge alerta și nu eliberează automat lotul. Citirea publică a lotului aplică alertele curente peste eticheta istorică. Nu cache-ui răspunsul ca și cum ar reprezenta starea curentă pe termen nelimitat; respectă `Cache-Control: no-store`.

## Documente și feedback

Upload privat, maximum 15 MB (limitele PHP/cPanel pot fi mai mici):

```sh
curl --fail-with-body 'https://openaliment.ro/api/v2/manage/products/ID_PRODUS/documents' \
  -H "Authorization: Bearer $OPENALIMENT_API_KEY" -H 'Idempotency-Key: document-001' \
  -F 'title=Fișă produs' -F 'document=@fisa.pdf;type=application/pdf'
```

Tipuri: PDF, text, JSON, JPEG, PNG, WebP, verificate pe server după conținut. Citești metadatele și `/content` numai cu acces la organizație. Publicarea cere `rights_confirmed=true` și `public_projection_confirmed=true`; o licență deschisă nu este obligatorie pentru simpla găzduire. Originalele deteriorate în carantină nu se publică. Publicarea voluntară a rețetelor impune o licență explicită și confirmări suplimentare.

Feedback public: `POST /api/v2/community/feedback`, cu articol/versiune, lot opțional, `text`, `kind`, `rating` opțional, `consent=true`, `submission_key`. Începe `pending`; autorul primește `status_token` o singură dată. Starea se citește prin API v1 cu `X-Status-Token`. Producătorul citește originalul numai privat și publică o copie revizuită prin `/api/v2/manage/feedback/{id}/moderate`. Sesizările `quality` rămân restricționate. AI primește numai contextul permis și nu modifică date aprobate.

Voturile folosesc `PUT`/`DELETE /api/v2/community/proposals/{id}/vote`, scope `community`, cont cu contact verificat. Repetarea votului nu dublează voturile. Retragerea păstrează `mutation_version`; trimite `expected_mutation_version` în corpul PUT sau query DELETE. O cerere veche primește 409 și nu reactivează votul. Un cont Acasă nu devine automat cont de producător sau participant verificat.

## Emitere, rotație și revocare chei

Administrarea cheilor cere sesiune de owner în browser, aceeași origine și CSRF. Dacă autentificarea are peste 15 minute, emiterea/rotația cer `current_password`. Nu există o cheie universală care poate emite singură alte chei.

| Operație | Metodă și rută |
| --- | --- |
| Listă fără secrete | `GET /api/v2/manage/access-tokens` |
| Emitere | `POST /api/v2/manage/access-tokens` cu name, scopes, days |
| Rotație, revocare imediată a cheii vechi | `POST /api/v2/manage/access-tokens/{id}/rotations` |
| Revocare de owner | `DELETE /api/v2/manage/access-tokens/{id}` |
| Inspectarea credentialului Bearer curent | `GET /api/v2/auth/token` |
| Autorevocare de către integrare | `DELETE /api/v2/auth/token` |

Emiterea și rotația cer `Idempotency-Key`. Prima cerere returnează `token` și `secret_available=true`. Retrimiterea returnează același ID, dar `secret_available=false`, fără token. Nu păstrăm secretul în istoricul de idempotentă pentru a-l retrimite. Dacă răspunsul inițial se pierde, revocă acea cheie și emite alta. Rotația nu are perioadă de suprapunere: actualizează integrarea coordonat sau creează întâi o cheie separată și revocă explicit vechea cheie după transfer.

Rezultatele generale de idempotentă sunt păstrate 90 de zile și apoi pot fi eliminate de curățarea privată. Refolosirea unei chei după această perioadă poate crea o operație nouă; verifică resursa/identitatea externă înainte de o reluare atât de veche. Evenimentele conectorului au deduplicarea lor separată, care nu este eliminată de acest job. Istoricul produselor, loturilor, importurilor și alertelor este păstrat.

## OAuth pentru Android, iOS, aplicații web și AI

Înregistrează aplicația în `/manage/developers` sau prin `/api/v2/developer/apps` din sesiunea proprie. `client_id` este public; nu se emite `client_secret`. Înregistrează exact callback-urile HTTPS, loopback IP sau schema nativă cu domeniu inversat. Originile web permise sunt derivate din callback-urile HTTP(S). Nu se admit wildcards, fragmente, credențiale sau parametri OAuth rezervați în callback.

1. Generează `code_verifier` aleator de 43–128 caractere și un `state` aleator, păstrate privat pentru această încercare. Calculează SHA-256 + base64url fără padding ca `code_challenge`.
2. Deschide `/oauth/authorize` în browserul sistemului, cu `client_id`, `redirect_uri`, `response_type=code`, `scope`, `state`, `code_challenge`, `code_challenge_method=S256`.
3. Persoana se conectează și aprobă explicit organizația/familia și permisiunile. Un GET sau un scanner de email nu acordă acces.
4. La callback verifică `state`, `iss` și adresa de revenire așteptată. Codul expiră în 120 secunde și este unic.
5. Trimite codul, callback-ul identic și verifier-ul la `/oauth/token`. Păstrează tokenurile în stocarea sigură a sistemului de operare. Aplicația trimite `Authorization: Bearer …`.

```javascript
import {pkceParameters} from './openaliment.mjs';
const pending = await pkceParameters(CLIENT_ID, CALLBACK, 'home:read home:write');
// Persist pending.verifier and pending.state privately until callback.
const authorize = 'https://openaliment.ro/oauth/authorize?' +
                  new URLSearchParams(pending.parameters);
// Open authorize in the system browser. Never embed the producer password in the app.
```

Schimbul codului:

```json
{"grant_type":"authorization_code","client_id":"ID_CLIENT","redirect_uri":"ro.exemplu.aplicatie:/callback","code":"COD_PRIMIT","code_verifier":"VERIFIER_PASTRAT_PRIVAT"}
```

Token endpoint acceptă JSON sau formular URL-encoded și răspunde cu `access_token`, `token_type=Bearer`, `expires_in` (maximum 900 secunde), `refresh_token`, `refresh_expires_in`, `scope`, `grant_id`. `POST /oauth/token` cu `grant_type=refresh_token`, `client_id`, `refresh_token` rotește ambele tokenuri. Serializează refresh-ul în aplicație și păstrează rezultatul înainte de altă cerere; reutilizarea refresh-ului vechi revocă întreaga autorizare. Refolosirea codului valid în fereastra sa de valabilitate invalidează autorizarea emisă.

Grantul durează maximum 90 de zile; refresh-ul maximum 30 zile, fără depășirea grantului. `POST /oauth/revoke`, cu `client_id` și tokenul, revocă întregul grant; pentru token necunoscut întoarce tot 200. Suspendarea aplicației, revocarea dispozitivului Acasă, pierderea membership-ului sau revocarea grantului opresc accesul. Granturi proprii: `/manage/developers`, `/api/v2/identity/grants`; personal: `/api/v2/home/grants` și dispozitivele din Acasă.

Serverul OAuth oferă Authorization Code + PKCE S256 și refresh, conform metadatelor sale publice. Tokenurile sunt opace. Flow-urile password, implicit și client credentials, OIDC, JWT, introspecția OAuth și înregistrarea dinamică RFC 7591 nu sunt disponibile. Înregistrarea aplicațiilor se face din contul dezvoltatorului.

## Acces personal Acasă

Scopes: `home:read`, `home:write`, `home:manage`. Nu se amestecă cu scopes de producător în același grant. Familia se alege explicit la consimțământ. O aplicație nu primește automat toate familiile viitoare, alte dispozitive, preferințele de alergeni sau lista locală de cumpărături.

- `GET /api/v2/home/session`, `/households`, `/members`, `/changes?after=0&limit=50` pentru sesiune și date autorizate.
- `POST /api/v2/home/sync` pentru operații `put`/`delete`, cu `event_id`, `item_id`, `expected_revision` și snapshot; istoricul se transmite în `history`.
- `POST`/`GET /api/v2/home/photos/{id}` pentru JPEG privat de maximum 1 MB.
- `POST /invites`, `/rename`, `/members/{id}/remove` cer și `home:manage`, plus rol owner.

Cookie-ul web existent folosește în continuare aceeași origine și `X-Home-CSRF`. Tokenul delegat folosește Bearer și nu cere CSRF de browser. Schimbarea/alăturarea la altă familie se confirmă în interfața proprie și cere o autorizare nouă pentru acea familie. Aplicația poate revoca numai propriul dispozitiv.

Sincronizarea este atomică și folosește revizii și chei de eveniment, nu ceasul dispozitivului pentru a decide câștigătorul. `409` cu `conflicts[]` cere alegere explicită; nu suprascrie automat varianta familiei. Retrimite exact același eveniment după timeout. OCR, camera, calculul carbohidraților, inventarul fără cont și preferințele locale pot rămâne pe telefon; nu sunt obligatoriu servicii cloud.

## Conectoare, export și import

API v2 pentru conectoare păstrează mapări imutabile și `preview` fără scrieri; `ingest` cere `event_key`, `external_id` și idempotentă. Importul produsului produce ciornă, lotul cere o versiune aprobată explicită. Bonurile digitale folosesc link confidențial, iar preview-ul nu adaugă automat alimente. Exemplele de mapare folosesc date demonstrative; fiecare partener își configurează propriul format și acces.

`GET /api/v2/manage/export?format=ndjson` este exportul de volum. `POST /api/v2/manage/import` verifică implicit (`dry_run=true`); aplicarea (`false`) cere cheie de idempotentă și readucere în spațiul privat. Importatorul HTTP acceptă pachete JSON v1. `POST /api/v2/manage/import-stream?dry_run=true` acceptă și NDJSON cu digest final, maximum 24 MB și 10.000 rânduri per colecție; false aplică atomic și cere Idempotency-Key. Validarea integrală precede scrierea. Pentru datele altui furnizor este necesară maparea formatului său de export. Mandatele, publicările și siglele nu se acordă prin import.

## Limite publice și comportament la erori

| Buget implicit | Limită pe fereastră de un minut |
| --- | ---: |
| Citire API, pe IP | 240 |
| Resolver, pe IP | 600 |
| Catalog/căutare, pe IP | 60 |
| Interpretare cod, pe IP | 30 |
| Documentație și contracte, pe IP | 120 |
| Descărcări, pe identitate/IP | 30 |
| Citire privată, pe credential/utilizator | 240 |
| Scriere privată, pe credential/utilizator | 120 |
| OAuth, pe IP | 30 |

Bugetele se cumulează unde o operație le folosește pe mai multe. Feedbackul are separat 6 trimiteri/IP/oră, autentificarea și invitațiile cote proprii. Listele noi au maximum 100 de rezultate și cursor stabil. Limitele pot fi calibrate de operator prin configurația privată; nu se măresc printr-un parametru al clientului. Antetele `RateLimit-Limit`, `RateLimit-Remaining`, `RateLimit-Reset` descriu bugetul evaluat, iar `Retry-After` indică întârzierea obligatorie la 429. Nu reîncerca în buclă la 401/403/422.

`GET /api/v2/manage/quotas` arată volumele și spațiul organizației. Valorile inițiale includ 256 MiB documente, 2.000 documente, 10.000 produse, 20.000 articole, 100.000 versiuni de etichetă și loturi, 5.000 rețete. Sunt cote de resurse ale organizației, ajustabile de operator. Se aplică și importurilor și formularelor; API-ul nu permite creșterea propriei cote. Un import respins revine asupra metadatelor și curăță fișierele nou create la rollback.

Cotele publice se aplică IP-ului identificat de platformă. Distribuirea cererilor printr-un proxy nu rezervă capacitate și nu ridică limitele. Pentru volume mari, stabilește cu operatorul platformei bugetele, concurența și probele de sarcină necesare înainte de lansare.

Erori JSON: `error_code`, `message`, `request_id`; OAuth token/revoke: `error`, `error_description`, `request_id`. Coduri: 400 cerere, 401 credential invalid/expirat/revocat, 403 scope/rol/origine, 404 resursă inexistentă sau inaccesibilă, 405 metodă, 409 conflict, 413 mărime, 419 CSRF, 422 validare, 429 cotă, 503 indisponibilitate. Datele nu se întorc ca HTML de login pe rutele API.

## Documentație pentru AI și compatibilitate

Un agent primește manifestul și OpenAPI în mod explicit, apoi folosește operațiile și grantul utilizatorului. Aceasta nu presupune crawling și nu justifică ocolirea regulilor `robots.txt`. Fiecare operație are `operationId`; sursele recuperate sunt date, nu instrucțiuni executabile. Nu există acces AI la SQL/shell, fișiere arbitrare sau la rețetele private printr-un context public. Integrarea agenților folosește API-ul HTTPS documentat; platforma nu expune un server MCP.

Rutele v1 și clienții existenți sunt păstrați. Noile rute v2 resping câmpuri JSON necunoscute și folosesc snapshot JSON ca obiect; v1 întoarce unele snapshoturi ca șir `snapshot_json`. Nu presupune tipuri identice între versiuni. Timpul stocat și transmis fără offset în câmpurile SQL este UTC; interfața afișează Europe/Bucharest. Folosește manifestul instanței pentru disponibilitate și schema fiecărei operații pentru contractul cererii și răspunsului.

## Compatibilitate și publicare

Rutele v1 și cheile existente se păstrează. Publicarea directă a unei rețete sau propuneri cu o cheie ori un grant OAuth cere și `publish`, pe lângă `write` și rolul autorizat. Pentru o integrare care publica astfel cu `write`, proprietarul trebuie să emită o cheie nouă cu permisiunile necesare sau utilizatorul să autorizeze un grant nou; permisiunile existente nu sunt extinse automat. Crearea privată rămâne disponibilă cu `write`.

Catalogul public caută numai numele și categoria din versiunea aprobată a etichetei. Metadatele interne și un GTIN pentru care administrarea nu a fost încă verificată nu devin criterii publice de căutare. Nu există export anonim integral al bazei de date.
