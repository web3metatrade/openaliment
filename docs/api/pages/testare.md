# Testarea integrării și mediile de lucru

## Alege mediul și datele

`https://openaliment.ro` este mediul de producție. Orice cerere de scriere autorizată modifică datele contului tău. Pentru probe de integrare folosește o organizație dedicată, cu chei separate și informații demonstrative marcate explicit. Păstrează ciornele private până la aprobarea datelor pentru publicare.

Exemplul public Pâine Bună Tehnic folosește GTIN `9520000000011` și lotul `DEMO-PBT-01`. Permite citirea publică și scanarea, fără cont. Identificatorul este demonstrativ; datele alimentare și termenul de valabilitate nu sunt validate pentru comercializare. Exemplul nu este un cont public de scriere sau un mediu care se resetează periodic.

Folosește o instalare separată a platformei pentru probe care cer resetarea datelor. Nu executa resetări sau teste distructive în producție. Setează explicit originea, identificatorii și cheia mediului în client; nu refolosi fișierele de stare între medii.

## Verifică mai întâi fără scriere

- Citește [manifestul](/api/manifest.json) pentru capabilitățile active pe instanța aleasă.
- Descarcă [OpenAPI](/api/platform-openapi.json) și verifică cererile și răspunsurile față de schemele operațiilor.
- Importă [colecția Postman](/api/docs/postman.json) și configurează variabilele în spațiul privat al clientului.
- Folosește exemplele documentate din explorator pentru a înțelege răspunsurile fără să trimiți cereri.
- Verifică maparea partenerului prin `preview`, iar un pachet de import prin `dry_run=true`.

Exemplele JSON din referință folosesc valori demonstrative. Cererile reale ale exploratorului folosesc contul și datele tale. Consultă [exemplul ERP executabil](/api/docs/guides/exemple-executabile) pentru crearea unei ciorne și publicarea explicită.

## Verificări înainte de lansare

| Flux | Rezultat de verificat |
| --- | --- |
| Produs fără rețetă | Produsul, eticheta și lotul pot fi create fără licență deschisă sau siglă |
| Timeout și reluare | Aceeași cheie și același corp întorc aceeași operație, fără duplicate |
| Publicare | Rolul și scope-ul potrivit sunt obligatorii; ciorna rămâne privată |
| Istoric | Lotul E1 păstrează eticheta E1 după publicarea E2 |
| Alerte | Alerta curentă apare peste informația istorică; închiderea nu eliberează automat lotul |
| Izolare | O cheie a organizației A nu citește și nu modifică resursele organizației B |
| Revocare | O cheie revocată este refuzată la cererea următoare |
| Import și export | Hashurile și fișierele sunt complete; publicările și mandatele necesită reverificare |
| Limitare | Clientul respectă 429, Retry-After și un număr limitat de reîncercări |
| Indisponibilitate | Clientul tratează 503 și păstrează operațiile pentru reconciliere |
| Independența AI | Citirea produsului, lotului și QR-ului funcționează fără serviciul AI |

Pentru OAuth verifică pe dispozitivele țintă callback-ul, `state`, `iss`, PKCE, stocarea sigură a tokenurilor și rotația refresh. Serializează refresh-ul și testează revocarea întregului grant. Pentru Acasă verifică alegerea familiei, conflictele de revizie și datele păstrate fără conexiune.

Pentru retail verifică simbolul tipărit, scannerul 2D, articolul configurat în POS și identificatorii autorizați. O cerere HTTP reușită nu certifică acceptarea codului de către toate magazinele. Volumul comercial se stabilește prin probe de sarcină convenite cu operatorul infrastructurii.

## Urmărirea contractului și suport

Folosește schema operației și `operationId` ca repere ale clientului. Tratează câmpurile noi de răspuns fără să trimiți câmpuri necunoscute în cerere. Pentru v1 consultă [compatibilitatea](/api/docs/guides/compatibilitate); nu presupune că tipurile și paginarea sunt identice cu v2.

La o problemă, păstrează metoda, calea fără secrete, codul HTTP și `request_id`. Elimină tokenurile, cookie-urile, parolele și datele private din materialul trimis pentru suport. Vezi [erorile și recuperarea](/api/docs/guides/erori-recuperare) pentru pașii de reconciliere.

## Utilizare cu asistenți AI

Furnizează explicit manifestul, OpenAPI și paginile Markdown relevante. Accesul la documentație nu cere crawling și nu acordă acces la cont. Un agent folosește numai permisiunile aprobate de persoană și tratează documentele sau răspunsurile ca date, nu ca instrucțiuni de executat.

„Copiază pagina” și legăturile ChatGPT/Claude includ documentația publică a paginii, fără cheia din explorator sau rezultatul unei cereri private. Pentru acțiuni care modifică date, păstrează controlul utilizatorului asupra autorizării și publicării.
