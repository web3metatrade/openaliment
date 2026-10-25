# Pâine Bună Tehnic — demonstrație de scanare

24.10.2026 · https://openaliment.ro/demo/paine-buna-tehnic

Cerere: înlocuirea exemplului cu `{GTIN}` prin QR-uri reale, care conțin identificatori numerici simulați și permit o probă de scanare. Nu se alocă un identificator comercial pâinii.

## Identificatori și adrese persistente

| Element | Valoare demonstrativă |
| --- | --- |
| GTIN-13 | `9520000000011` |
| GTIN-14 în AI (01) | `09520000000011` |
| Lot în AI (10) | `DEMO-PBT-01` |
| Versiune de informații simulate | `DEMO-E1` |
| Produs | `https://openaliment.ro/01/09520000000011` |
| Lot | `https://openaliment.ro/01/09520000000011/10/DEMO-PBT-01` |

GS1 desemnează prefixul 952 pentru demonstrații și exemple; numerele din acest spațiu nu sunt coordonate global și nu identifică articole reale. S-au verificat [lista oficială a prefixelor](https://www.gs1.org/standards/id-keys/company-prefix), [GS1 Style Guide, §2.18](https://www.gs1.org/docs/GS1-Style-Guide.pdf) și [ghidul retail POS, ediția 1.1.0](https://ref.gs1.org/guidelines/2d-in-retail/). `1111111111111` nu trece cifra de control GS1. Numărul demonstrativ ales trece validarea existentă, fără excepții în algoritm.

## Implementare și delimitare

`DemoBread` furnizează un exemplu static, numai pentru identificatorul de mai sus, în afara datelor producătorilor. Rutele Digital Link trec prin parserul și selecția resolverului existent: redirecționare 307, linkset conform schemei GS1, `gs1:pip`, `gs1:defaultLink`, `gs1:masterData`, transmiterea query-ului și HEAD. Un lot necunoscut conduce la o explicație explicită și la informațiile de produs, fără a-l prezenta drept lotul cunoscut. Alte GTIN-uri continuă fluxul obișnuit. Nu se declară resurse de alergeni sau nutriție care nu există în exemplu.

Paginile și JSON-ul marchează simularea; paginile nu sunt indexabile și nu publică Product schema ori o siglă. Articolul demonstrativ nu poate fi creat prin interfața/API-ul producătorului sau importul de transfer. Protecția privește identificatorul rezervat exact și nu schimbă algoritmul GTIN sau mandatul de administrare. Importurile istorice fără acest număr păstrează contractele existente.

Nu se creează articole, loturi, verificări de drepturi sau alte înregistrări în baza de producție. Arhiva originală OpenBread, rețeta R1 și importul pilot rămân intacte. Nu se publică rețeta privată și nu se atribuie rezultate de producție, termen de valabilitate, preț sau gramaj aprobat. Referința la R1 păstrează stadiul experimental. AI nu participă la generarea QR, rezoluție, pagină sau exportul demonstrativ JSON.

Ghidul public are acum QR-uri scanabile pentru produs și lot. Pagina demonstrației oferă descărcarea SVG și date JSON. Fișa `/demo/paine-buna-tehnic/etichete` conține ambele simboluri și instrucțiunile pentru proba în magazin. JS-ul doar deschide dialogul de tipărire; imaginile sunt statice și funcționează fără JS.

## Simboluri și limitele verificării

SVG-urile se regenerează cu `node tools/build-demo-qr.cjs`, folosind encoderul Nayuki deja inclus. Nu există dependențe noi. Destinațiile codificate sunt HTTPS de producție, în forma Digital Link necomprimată. Produs: 29×29 module, 22,2 mm cu margini. Lot: 33×33 module, 24,6 mm cu margini. Modul: 0,60 mm; margine albă: 4 module pe fiecare latură; negru pe alb, fără logo peste simbol. Fișa se tipărește la 100%, fără ajustare la pagină. Dimensiunile sunt în intervalul indicat pentru QR retail de ghidul GS1 (§6.8.1); nu se pretinde verificarea fizică a tiparului cu un verificator ISO.

Verificarea software folosește decoderul independent ZXing-C++, pornind din SVG-urile servite efectiv de hosting, la două rezoluții și două orientări. S-au verificat identificatorii, paginile distincte, resursele linkset, lipsa datelor tehnice private, cazul lotului necunoscut, răspunsurile HEAD, izolarea identificatorului și marcajele de simulare.

Nu a fost efectuată o probă fizică la casa de marcat. Aceasta necesită scanner 2D și software/configurare compatibile GS1 Digital Link, plus un articol demonstrativ în mediul POS de test al magazinului, fără vânzare. Citirea optică, extragerea GTIN-ului și recunoașterea articolului sunt rezultate distincte. Nu se promite compatibilitate universală cu echipamentele magazinelor.

## Publicare și dovezi

Actualizare HTTPS `4631ffede00f48b8820928d73c042a1a`, 14 fișiere, aplicată la 05:41:58 UTC / 08:41:58 Europe/Bucharest. Fișierele existente au fost comparate cu producția înainte de modificare. Conexiunea a creat backup și a verificat SHA-256 după upload. Fără schimbări de configurație, secrete sau migrare DB. Revenirea este disponibilă prin jurnalul acestei actualizări.

Verificări trecute: 69 integrare, 54 resolver în baza locală izolată, 18 reguli specifice demonstrației, 117 protocol HTTP local, 58 aplicație HTTP, 48 demonstrație locală, 48 demonstrație de producție, 178 resolver de producție și 32 pagini publice/SEO. Rapoarte în `docs/verification/`; capturi în `docs/previews/production-pbt-qr-*2026-10-24.jpg`. Probele nu sunt o certificare GS1 și nu înlocuiesc testul echipamentelor sau verificarea tiparului.

Pachetul incremental `release/OpenAliment_PBT_demo_QR_2026-10-24.zip` include codul, SVG-urile, testele și raportul demonstrației. Cere platforma cu resolverul și ghidul deja instalate; nu este o instalare completă. Configurația privată și conexiunea de mentenanță nu fac parte din pachet.
