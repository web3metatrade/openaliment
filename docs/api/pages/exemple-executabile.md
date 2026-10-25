# Integrare completă și exemple executabile

## Pregătire

Pentru ERP propriu, ownerul emite o cheie din `/manage/api`. Pentru fluxul complet de mai jos sunt necesare read, write și publish, plus rol owner/quality. Începe într-o organizație de test sau într-o instalare locală izolată. Demonstrația de pâine permite numai citirea și scanarea publică.

Descarcă [clientul Python](/api/sdk/python) ca `openaliment.py` și [exemplul ERP executabil](/api/docs/examples/erp-workflow.py) în același director. Python folosește numai biblioteca standard; nu cere pip.

Configurează `OPENALIMENT_API_KEY` în mediul privat al procesului. Nu pune cheia în comanda salvată, cod, fișierul de stare sau un asistent AI. `--base-url` este originea HTTPS; HTTP este permis numai pe loopback pentru test.

## Ciornă, apoi publicare explicită

```sh
python erp-workflow.py --base-url https://openaliment.ro --state ./private/erp-example.json
```

Prima rulare creează produsul „Iaurt natural — exemplu ERP”, articolul „Pahar 200 g” și eticheta E1 în ciornă. Nu înregistrează GTIN, rețetă sau licență deschisă. Verifică datele exemplului înainte de a autoriza publicarea în contul tău.

```sh
python erp-workflow.py --base-url https://openaliment.ro --state ./private/erp-example.json --publish
```

A doua rulare continuă aceeași operație, publică eticheta, creează un lot fără rețetă și îi publică pagina. Citește QR-ul și verifică prin API public că profilul alimentar identifică lotul corect. Nu eliberează automat lotul pentru consum.

Fișierul de stare păstrează originea, jobul și ID-urile rezultatelor, fără credentiale. Este necesar pentru reluarea după timeout. Rulează un singur proces pentru același fișier de stare. Folosește fișiere distincte pentru medii și păstrează-le în afara public/. Retrimiterea aceleiași comenzi nu trebuie să creeze alte produse.

## Ordinea cererilor și rezultatele folosite

| Pas | Cerere | Folosește mai departe |
| --- | --- | --- |
| 1 | POST /api/v2/manage/products | id ca product_id |
| 2 | POST /api/v2/manage/products/{id}/trade-items | id ca trade_item_id |
| 3 | POST /api/v2/manage/trade-items/{id}/labels | id ca product_info_version_id |
| 4 | POST /api/v2/manage/labels/{id}/publish | state=published |
| 5 | POST /api/v2/manage/trade-items/{id}/batches | id ca batch_id; recipe_version_id=null |
| 6 | POST /api/v2/manage/batches/{id}/publish | visibility=public |
| 7 | GET /api/v2/manage/qr/batch/{id} | url exact pentru imaginea QR |
| 8 | GET /api/v2/public/batches/{id} | snapshot istoric și alerte curente |
| 9 | GET /api/v2/public/food?code=URL_CODIFICAT | profil pentru aplicația clientului |

Publicarea nu cere GTIN. Ulterior, pentru Digital Link, articolul comercial trebuie înregistrat cu GTIN asupra căruia producătorul are drepturi, iar mandatul se verifică separat. Exemplul 952 al Pâine Bună Tehnic nu poate fi revendicat ca articol comercial. POS și validarea comercială cer probe cu magazinul și identificatori autorizați.

## Verifică istoricul și alertele

Creează E2 prin același endpoint labels și public-o. GET public/trade-items va arăta E2; GET public/batches pentru lotul creat anterior trebuie să păstreze E1. Creează o alertă cu scope alerts și stare recalled; citirea lotului trebuie să arate alerta curentă fără să schimbe eticheta istorică. Închide alerta cu resolution; apoi decide separat dacă starea permite release.

Scanarea, rezolvarea adresei și citirea informațiilor aprobate nu depind de serviciul AI.

## Integrare ERP și magazin fără format rigid

Pentru un partener nou creează conector catalog sau retail_receipt și o versiune de mapare declarativă. Folosește preview înainte de ingest. Mapează ID-urile externe, fără să înlocuiești identitățile interne; persistă event_key și ID-ul mapării folosite.

Un conector catalog poate primi noi versiuni de etichetă; lotul importat trebuie să indice label_version_key publicată explicit. Un conector retail_receipt întoarce un URL confidențial cu expirare. Preview-ul bonului nu adaugă automat produse în inventar. Mapările din exemple sunt demonstrative; folosește formatul și datele aprobate ale partenerului tău.

## Postman și exploratorul din pagini

[Descarcă colecția Postman](/api/docs/postman.json). Importă fișierul, configurează base_url și credentialele în variabile locale private. Colecția păstrează operationId în descriere și separă rutele după modul. Nu include tokenuri reale, cookie-uri sau un cont demonstrativ universal.

Fiecare pagină de operație permite afișarea exemplelor fără rețea. Pentru GET public și operații Bearer eligibile există și cerere reală către aceeași platformă. Scrierile cer confirmarea explicită din pagină. Explorerul nu folosește cookie-ul administrativ, nu urmărește redirect-uri și nu persistă cheia. Fluxurile de sesiune, consimțământ și fișiere se execută cu exemplele de client corespunzătoare.

Exemplele afișate sunt date sintetice conforme cu schema. Nu sunt răspunsuri capturate din conturi reale și nu simulează automat o bază de date care se modifică. În producție, o cerere reală operează în contul tău.
