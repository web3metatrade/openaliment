# Harta codului

OpenAliment păstrează un singur MVC PHP, fără framework și fără container de servicii. `App\` se mapează la `app/`; numele claselor și directoarelor respectă majusculele inclusiv pe Linux.

| Zonă | Rol |
| --- | --- |
| `public/index.php` | Intrarea HTTP, ordinea rutelor, antetele și răspunsurile la erori. |
| `app/autoload.php` | Autoload și includerea helperelor; nu citește configurații private și nu deschide DB. |
| `app/bootstrap.php` | Inițializează configurația dacă nu a fost furnizată explicit și păstrează fusul Europe/Bucharest. |
| `app/Support/view_helpers.php` | Escapare, CSRF, URL-uri, afișări locale și iconuri SVG. |
| `app/Controllers/` | Adaptează cererile/răspunsurile și aplică autorizarea. |
| `app/Core/` | PDO, configurare, HTTP, sesiuni, autentificare, email și migrări. |
| `app/Domain/` | Catalog/versionare, resolver, documente, comunitate/AI, integrări și Acasă. |
| `app/Views/` | Șabloane care afișează date deja autorizate. |
| `contracts/` | Scheme publice și OpenAPI; resursele externe au proveniență separată. |
| `database/migrations/` | Migrări aplicabile în ordine; fișierele aplicate rămân imuabile. |
| `tools/build/` | Generatoare și pachete, fără responsabilități runtime web. |
| `tools/qa/`, `tests/` | Verificări independente de producție. |

Modulele de domeniu rămân în locațiile existente: catalog (`Catalog`, `Mutations`, `FoodData`, `FoodDate`), resolver (`DigitalLink`, `Resolver*`, `EpcDecoder`), documentație (`Documents`, `Portability`, `DemoBread`), comunitate (`Community`, `Ai`), integrări (`Integrations`, `PartnerMapping`, `DigitalReceipt`) și inventar personal (`Home*`, `ConsumerFood`).

Nu se redenumesc clase, tabele sau endpointuri pentru a reflecta o reorganizare internă. Un strat suplimentar de servicii se introduce numai când reduce o responsabilitate complexă concretă. Directoarele `Views/components/` și modulele pe subnamespace nu sunt create artificial înainte de a exista o extragere verificată.

Ordinea dispatch-ului rămâne importantă: resolverul înaintea rutelor generale și bonul confidențial înaintea prefixului API personal. Cookie-ul producătorului și cel personal rămân independente. Configurațiile și fișierele private rămân în afara `public/`; funcționarea QR nu depinde de AI sau SMTP.

Migrațiile și copiile cu hashuri de proveniență sunt excluse de la normalizarea automată a liniilor. Codul propriu nou este lizibil, cu convenții PSR-12 pentru PHP și module JS simple. Formatarea codului istoric și extragerile mai ample se fac în schimbări separate, cu regresii.
