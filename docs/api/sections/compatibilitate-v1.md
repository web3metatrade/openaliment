## Utilizarea API v1

Baza este `https://openaliment.ro/api/v1`. Pentru integrări noi folosește v2; clienții v1 își păstrează rutele și operațiile documentate în [referința API](/api/docs). Cheile API, rolurile, verificarea drepturilor GS1 și idempotenta se aplică și în v1.

| Scop | Rută după baza v1 |
| --- | --- |
| Catalog public cu page/limit | GET `/products` |
| Produs, articol sau lot public | GET `/products/{id}`, `/trade-items/{id}`, `/batches/{id}` |
| Rețetă publică licențiată | GET `/recipe-versions/{id}` |
| Creare produs și articol | POST `/manage/products`, `/manage/products/{id}/trade-items` |
| Etichetă și lot | POST `/manage/trade-items/{id}/labels`, `/manage/trade-items/{id}/batches` |
| Publicare explicită | POST `/manage/labels/{id}/publish`, `/manage/batches/{id}/publish` |
| Eliberare lot | POST `/manage/batches/{id}/release` |
| Alerte | POST `/manage/batches/{id}/alerts`, `/manage/alerts/{id}/resolve` |
| Adresă QR | GET `/manage/qr/item/{id}`, `/manage/qr/batch/{id}` |
| Export sau import | GET `/manage/export`, POST `/manage/import` |
| Starea feedbackului autorului | GET `/feedback/{id}/status`, cu antetul `X-Status-Token` |

V1 poate întoarce snapshoturi în șiruri JSON, inclusiv `snapshot_json`. V2 expune proiecțiile decodate descrise în schema fiecărei operații. Nu înlocui doar prefixul unei rute fără să adaptezi tipurile, paginarea și validarea cererii.

Încărcarea multipart a documentelor folosește `POST /api/v2/manage/products/{id}/documents`. Consultă [documentele și feedbackul](/api/docs/guides/documente-feedback) pentru publicare și drepturi. API v1 păstrează operația de publicare a unui document deja încărcat.

Adresele native `/p/{id}`, `/t/{id}` și `/b/{id}` și adresele GS1 Digital Link nu sunt prefixate cu versiunea API. Resolverul public folosește `/01/{gtin}` și descriptorul `/.well-known/gs1resolver`. Exporturile păstrează proveniența și reviziile fără să transfere automat aprobările.

## Permisiuni pentru publicarea directă

O rețetă sau o propunere creată direct ca publică necesită atât `write`, cât și `publish`, plus rolul owner sau quality. O cheie sau un grant cu `write` poate crea versiunea privată. Permisiunile suplimentare cer emiterea unei chei noi sau consimțământ OAuth nou; un grant existent nu se extinde automat.

## Actualizarea clientului

Verifică [manifestul](/api/manifest.json), contractul OpenAPI și schema operației folosite. Păstrează `operationId` pentru identificarea operațiilor și tratează câmpurile noi de răspuns fără să trimiți câmpuri de cerere necunoscute. Înainte de schimbarea versiunii, verifică o citire, o scriere idempotentă, publicarea, istoricul lotului, revocarea cheii și exportul, într-un mediu separat de datele comerciale.
