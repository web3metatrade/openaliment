# Prezentare generală

Construiește integrări ERP, aplicații pentru consumatori și instrumente AI folosind API-ul OpenAliment. Informațiile publicate despre produse și loturi se pot citi fără cont. Datele private se accesează numai cu permisiunea producătorului sau a persoanei.

## Începe aici

- [Citește informații publice](/api/docs/guides/api-public) — interpretează un cod și obține ingredientele, alergenii, datele și alertele disponibile.
- [Conectează un ERP](/api/docs/guides/flux-erp) — creează produsul, aprobă eticheta, adaugă lotul și obține adresa QR.
- [Construiește o aplicație](/api/docs/guides/oauth) — autorizează accesul prin OAuth cu PKCE, fără parole incluse în aplicație.
- [Integrează Acasă](/api/docs/guides/acasa) — sincronizează alimentele în familia aleasă de utilizator.

## Primul apel

Exemplul folosește Pâine Bună Tehnic și un GTIN demonstrativ. Nu necesită autentificare și nu modifică date.

```sh
curl --fail-with-body 'https://openaliment.ro/api/v2/public/food?code=9520000000011'
```

Acest identificator este destinat demonstrației. Nu conferă drepturi GS1 și nu reprezintă validarea unui produs la casa de marcat. Un cod de produs nu identifică automat un lot.

## Alege accesul potrivit

| Scop | Acces |
| --- | --- |
| Citești informații aprobate despre alimente | API public, fără cheie, cu limite de utilizare |
| Automatizezi propria organizație | Cheie API cu permisiuni, expirare și revocare |
| Lucrezi pentru utilizatorii aplicației tale | OAuth Authorization Code cu PKCE și consimțământ |
| Administrezi platforma | Sesiune de administrator, fără delegare prin chei ERP |

Vezi [accesul și permisiunile](/api/docs/guides/acces), [cheile revocabile](/api/docs/guides/chei) și [limitele API](/api/docs/guides/limite).

## Cum citești referința

Fiecare operație are propria pagină: adresă, metodă HTTP, permisiuni, parametri, corpul cererii, exemple și scheme de răspuns. Navigarea grupează operațiile pe resurse. Căutarea găsește adrese, nume de operații și metode; API v1 are o categorie separată pentru compatibilitate.

Exemplele folosesc valori demonstrative. Înlocuiește identificatorii și valorile de autentificare cu cele proprii. Publicarea cere aprobările și rolurile documentate; o cerere de creare nu publică automat produsul sau lotul.

## Exemple complete și testare

- [Modele de date](/api/docs/guides/modele-date) — identificatori, valori necunoscute, UTC, publicare și istoric.
- [Integrare executabilă](/api/docs/guides/exemple-executabile) — produs din categoria lactate, lot fără rețetă și verificarea QR-ului.
- [Erori și recuperare](/api/docs/guides/erori-recuperare) — timeout, idempotentă, conflicte, refresh și depanare.
- [Medii și testare](/api/docs/guides/testare) — alegerea mediului, limitele demonstrației și verificări pentru parteneri.
- [Colecție Postman](/api/docs/postman.json) — operații și exemple de răspuns, fără credentiale reale.

În paginile operațiilor poți explora exemplele și, unde este permis, trimite cereri reale cu tokenul propriu. Exemplele sunt sintetice; cererile reale operează în mediul și contul ales.

## Documentație pentru instrumente și AI

Meniul fiecărei pagini permite copierea conținutului Markdown, vizualizarea lui și deschiderea unei conversații în ChatGPT sau Claude cu adresa documentației publice. Accesul la un asistent nu acordă acces la contul tău OpenAliment. Nu trimite chei, cookie-uri, parole sau date private în conversație.

- [Manifest API](/api/manifest.json) — capabilitățile disponibile pe această instanță.
- [OpenAPI 3.1](/api/platform-openapi.json) — contractul complet pentru clienți și generatoare.
- [Index pentru AI](/llms.txt) — ghidurile și operațiile, cu adrese Markdown.
- [Ghid complet Markdown](/api/guide.md) — ghidul de integrare într-un singur fișier.

## Reguli de bază

Folosește HTTPS și JSON UTF-8. Păstrează GTIN-urile ca șiruri, inclusiv zero-urile inițiale. Trimite tokenurile în antetul Authorization. Pentru mutații, păstrează aceeași cheie de idempotentă la retry. Respectă răspunsurile HTTP și Retry-After la limitare.

Loturile pot exista fără rețetă. Etichetele sunt versionate, iar loturile vechi păstrează informațiile lor istorice; alertele curente rămân vizibile. Găzduirea, licența deschisă, dreptul de administrare GS1 și evaluarea pentru siglă sunt decizii separate.
