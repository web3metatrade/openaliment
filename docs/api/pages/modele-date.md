# Modele de date și reguli de integrare

## Produs, articol, etichetă și lot

| Resursă | Rol | Identificator |
| --- | --- | --- |
| Produs | Alimentul și categoria sa internă | product_id |
| Articol comercial | Ambalarea sau varianta comercializată, cu GTIN opțional | trade_item_id |
| Etichetă | Versiunea imutabilă a informațiilor publicabile | product_info_version_id |
| Rețetă | Documentație tehnică opțională, privată sau explicit licențiată | recipe_version_id |
| Lot | Unități produse împreună, legate de eticheta aplicabilă | batch_id și lot_code |
| Destinație resolver | Legătură versionată, selectată prin relații și calificatori GS1 | id, link_key, version_no |

Un produs poate avea mai multe articole. Un articol poate avea mai multe versiuni de etichetă și loturi. Nu folosi GTIN sau lot_code în locul ID-ului intern dintr-o rută `{id}`. Unicitatea lotului se verifică în articol și organizație, nu la nivel global.

`GET /api/v2/public/products/{id}` selectează primul articol publicat după created_at și id. Pentru ambalarea exactă, enumeră `/public/products/{id}/trade-items` și citește articolul ales. API-ul nu ghicește ambalarea intenționată.

## Tipuri, valori absente și timp

- ID-urile interne sunt șiruri de 32 caractere hexazecimale mici, cu 128 biți aleatori. Ordinea lor nu reprezintă ordinea creării.
- GTIN este întotdeauna șir, niciodată număr JavaScript. GTIN-8/12/13 sunt normalizate la GTIN-14 prin zero-uri inițiale după validarea cifrei de control.
- `null` înseamnă informație absentă/neaplicabilă conform câmpului. Un câmp opțional poate lipsi complet. `[]` este o colecție goală, nu o declarație că produsul este sigur sau fără alergeni.
- În `food_data`, `status=unknown` păstrează explicit necunoscutul. Pentru calcule nutriționale sunt obligatorii baza, prepararea, jurisdicția și unitățile; nu amesteca per 100 g cu per porție.
- Datele de lot sunt `YYYY-MM-DD`, fără oră. `use_by` și `best_before` sunt exclusive, iar termenul nu poate precede producția.
- Câmpurile SQL ca `created_at`, `approved_at`, `expires_at` sunt UTC în format `YYYY-MM-DD HH:mm:ss`, fără offset în șir. Interpretează-le explicit ca UTC. Câmpurile ISO, precum `checked_at`, `exported_at` și timpii inventarului, includ offset sau Z.
- Unele metadate vechi folosesc 0/1 întreg, de exemplu `is_current`, `email_verified` și `active` din listări. Rezultatele operațiilor pot folosi boolean, de exemplu `active=false` după suspendare. Urmează schema operației, fără conversii globale.

## Publicare și istoric

Crearea produsului sau articolului nu îl face public. Crearea unei etichete întoarce `state=draft`. Publicarea etichetei trece acea versiune în `published` și actualizează articolul curent. Pentru o corecție creezi E2 și o publici; nu rescrii E1.

Lotul cere o etichetă deja publicată a aceluiași articol. `recipe_version_id` poate lipsi sau poate fi null. Publicarea lotului schimbă `visibility`, iar eliberarea schimbă separat `state`. Un lot public poate fi planned, on_hold sau recalled; existența paginii sale nu autorizează consumul.

Alertele active sunt citite la momentul cererii și se aplică peste eticheta istorică. Închiderea alertei păstrează motivul și nu eliberează automat lotul. Nu reutiliza o copie veche a datelor ca dovadă că nu există alerte curente.

## Permisiuni și proprietate

Cheia fixează organizația, iar OAuth fixează organizația sau familia aleasă. Nu trimite tenant_id în cererile de creare. Serverul verifică apartenența și rolul curent la fiecare cerere. Un ID inaccesibil întoarce 404 pentru a nu confirma existența în alt spațiu.

| Acțiune | Scope Bearer | Rol producător |
| --- | --- | --- |
| Citire privată | read | Orice apartenență activă |
| Creare ciornă | write | owner, editor sau quality |
| Publicare/eliberare/moderare | publish | owner sau quality |
| Alertă/închidere alertă | alerts | owner sau quality |
| Export/import | export / import | owner sau quality |
| Rețetă sau propunere publicată direct | write și publish | owner sau quality |
| Vot | community | Contact verificat |
| Administrarea cheilor | Sesiune web | owner, cu CSRF și eventual reautentificare |

Licențierea deschisă, verificarea mandatului GTIN și evaluarea pentru siglă sunt procese separate. Evaluarea pentru siglă nu este disponibilă prin API; manifestul indică `badge_assessment_api=false`.

## Liste și filtre

Colecțiile v2 noi folosesc `limit` 1–100, implicit 20, și cursor exclusiv. Trimite `next_cursor` numai dacă `has_more=true`; păstrează aceleași filtre pentru toate paginile. Cursorul nu este un snapshot al bazei: inserările concurente pot necesita reconciliere după identificator.

Catalogul public combină filtrele prin AND. `q` este prefix literal al numelui aprobat, iar `category`, `producer` și `gtin` sunt potriviri exacte. Nu există căutare semantică sau wildcard SQL oferit clientului. Comparațiile text depind de collation; lot_code folosește collation binar și distinge majusculele.

Excepții: Acasă folosește cursor numeric `after`; bindings folosește `external_id` și `next_external_id`; v1 `/products` folosește page/limit. Listările de conectori, mapări, aplicații, granturi, familii și membri au limite fixe explicate în paginile proprii. Nu inventa cursor pentru o listă care nu îl întoarce.

## Compatibilitatea versiunilor

V2 expune snapshoturile prin câmpul `snapshot`. Proiecțiile pentru result, details, provenance, target, qualifiers și mapping sunt descrise în schema fiecărei operații. V1 păstrează șirurile JSON istorice. Metadatele OAuth `scopes_json` și `redirect_uris_json` rămân șiruri chiar în listările v2; răspunsul de creare a aplicației are arrays `scopes` și `redirect_uris`.

Clienții pot ignora câmpuri noi de răspuns, dar nu trebuie să trimită arbitrar câmpuri de cerere necunoscute. Respectă schema operației, inclusiv `additionalProperties` și regulile câmpurilor imbricate. Cererile sunt supuse atât validării formatului, cât și verificării relațiilor și permisiunilor.
