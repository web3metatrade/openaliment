# Open Food Facts — implementarea 0.8.0

Data: 27 octombrie 2026. Destinația acestei modificări este ramura `dev` și `dev.openaliment.ro`. Publicarea în producție și scrierea în OFF nu sunt incluse în activarea de dezvoltare.

## Livrabile

| Zonă | Comportament livrat |
| --- | --- |
| Catalog | 009: sursă separată, observații imuabile, proveniență, media, cozi, bugete, pachete de date, asocieri și diferențe private |
| Acorduri | 010: conexiuni, consimțământ pe câmpuri și imagini, versiuni și revocare, pachete ale producătorului |
| Identificare | Validare GTIN cu checksum, normalizare pe 14 cifre, refuzul identificatorului demonstrativ propriu și al codurilor de circulație restrânsă |
| Citire OFF | API 3.6, scheme 1000–1004, hosturi fixe, TLS, DNS asincron, timeout, bugete persistente, cooldown și coadă deduplicată cu lease |
| Nutriție și alergeni | Date raportate, derivate și estimate distincte; fără conversii masă–volum, formule de insulină sau declarații de absență deduse din lipsuri |
| Acasă | Adăugare imediată, completare asincronă separată, istoric, corectări personale și undo păstrate, observații offline și transfer v2 |
| Producător | Comparație pe câmpuri, import numai în ciornă, resetarea declarațiilor structurate afectate și decizii private asupra diferențelor |
| Contribuții | ZIP cu CSV conservator, JSON complet și instrucțiuni; verificarea mandatului și acordului și la descărcare; fără confirmare remote fictivă |
| API | 35 operații noi, ghid, scheme JSON, referințe, exemple și colecție Postman generate din surse |
| Operare | Subset JSONL/gzip pregătit în streaming, NDJSON verificat, checkpoint/retry, export public cu digest, enumerare revizii, arhivare și backup criptat |

Nucleul GS1 Digital Link nu folosește OFF ca autoritate de identificare. Lotul, seria, CPV și datele observate în cod sunt păstrate; fișa generică OFF nu rescrie eticheta aprobată sau lotul istoric. Nu se rezervă un GTIN în registrul producătorilor prin importul catalogului extern.

## Ajustări față de plan

- Auditul administrării globale are tabel separat `external_provider_events`: auditul existent cere `tenant_id` și nu trebuie atribuit fals unui producător.
- Payloadul normalizat are hash separat, pentru verificarea arhivei înainte de compactarea DB și la citire.
- Au fost adăugate două operații auxiliare: enumerarea publică a reviziilor și reluarea administrativă a unui dataset eșuat.
- API-ul nativ și `home.v1` rămân compatibile. `ConsumerProduct` documentează și sursa `external`, deja admisă de validatoarele inventarului; această proiecție este livrată în noul înveliș de rezoluție. GET-ul legacy nu contactează OFF.
- Acasă exportă v1 în absența suplimentelor și v2 când include observații licențiate. Importul v1 rămâne acceptat; importul v2 nu afirmă reverificarea sursei.
- Catalogul fixează `source_environment`. Dev folosește implicit mediul oficial de test OFF; producția folosește sursa lor de producție. Schimbarea configurației nu amestecă cele două cataloage.
- CSV-ul automat mapează numai `code`, `product_name_ro`, `ingredients_text_ro` și `quantity`. Celelalte câmpuri aprobate rămân în JSON, cu lista mapărilor necesare; operatorul legal nu este confundat cu serviciul de relații clienți.
- Scrierea automată, confirmările remote și reconcilierile după timeout de scriere nu sunt declarate operaționale în absența unui transport agreat și probat cu OFF. Pachetul producătorului este transportul utilizabil livrat.

## Verificare și activare

Testele sunt sintetice și nu trimit date către OFF. Sursa poate fi oprită fără a bloca fluxul nativ. Validarea prin HTTP verifică fiecare operație nouă și contractul public efectiv; testele browser verifică scanarea, sursa vizibilă, telefonul, offline și transferul pe alt dispozitiv. Backupul se probează prin restaurare într-o bază locală izolată; nu reprezintă proba unei copii externe a hostingului.

Rapoartele executate sunt în `tests/runtime/reports/`, iar capturile private în `tests/runtime/previews/`. Verificarea locală completă a trecut: **63 suite, 14.310 verificări, zero suite eșuate**. Cele 293 operații API au răspunsuri HTTP reușite validate, inclusiv toate cele 35 operații OFF. Testul browser OFF are 12 verificări: scanare caldă/rece, actualizare asincronă fără duplicare, undo, transfer v2 cu licențe, dispozitiv nou și offline. Restaurarea criptată are 15 verificări trecute. O citire separată, reală, a produsului public 3274080005003 din mediul oficial OFF staging a răspuns HTTP 200, schema 1004. Nu au fost efectuate scrieri OFF. CI și activarea pe hosting sunt pași separați; acest document nu afirmă că publicarea pe dev a avut deja loc.

Înainte de utilizarea publică se completează formularul cerut de OFF pentru utilizarea API-ului, cu identitatea și contactul autorizate ale proiectului. Înregistrarea nu este trimisă automat. Activarea sursei cere și confirmarea administrativă a atribuirii și reutilizării licențiate. Configurarea cronului în cPanel este o acțiune distinctă de instalarea codului.

Referințe: [operare](../OPEN_FOOD_FACTS.md), [API](../api/pages/surse-externe.md), [plan](../plans/OPEN_FOOD_FACTS_IMPLEMENTATION_PLAN_2026-10-27.md), [documentația oficială OFF](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/).
