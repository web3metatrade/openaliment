# Licențe și drepturi asupra materialelor

Codul propriu al platformei OpenAliment este disponibil sub [MIT](LICENSE), conform alegerii proprietarului din 24 octombrie 2026. Aceasta include implementările proprii PHP/JavaScript/CSS, uneltele, testele, schemele proprii și documentația tehnică asociată software-ului.

Licența MIT a platformei nu relicențiază următoarele materiale:

| Material | Regula aplicabilă |
| --- | --- |
| `app/Vendor/PHPMailer/` | Licența PHPMailer inclusă în director; versiunea și hashurile sunt în `docs/DEPENDENCIES.json`. |
| Bibliotecile și datele din `public/assets/vendor/` și `public/assets/ocr/` | Licențele și notificările componentelor, păstrate lângă ele. |
| `contracts/gs1/` | Proveniența și notificările din `NOTICE.json`; acestea sunt copii ale unor resurse externe. |
| `OpenBread_Codex_v1_2026-10-23/` | Proveniența și licențele explicite ale materialelor istorice. Drepturile de redistribuire se verifică separat înainte de publicarea arhivei într-un repository public. |
| Fotografii, ilustrații, identitate vizuală și mărci | Drepturile autorilor și titularilor; nu se acordă automat licența MIT asupra imaginilor prin includerea lor în proiect. |
| Date și documente încărcate de producători, rețete, inventare personale și copii de siguranță | Permisiunile și licențele alese explicit pentru fiecare material. Datele private nu intră în repository sau în release. |

Lista tehnică a componentelor este în [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md). Publicarea codului nu acordă automat drepturi GS1, licențe pentru rețete sau sigla de documentație deschisă. Înaintea publicării repository-ului, selecția materialelor care nu sunt cod software trebuie revizuită de titularul drepturilor.

Standardele GS1 păstrate privat și catalogul lor de proveniență sunt descrise în [referința GS1](docs/standards/GS1.md). PDF-urile originale nu sunt relicențiate MIT; lectura publică folosește distribuția oficială GS1. Integrările marketplace își declară propriile licențe software, fără licențiere implicită prin simpla publicare a ofertei.

## Date Open Food Facts

Codul adaptorului urmează licența software a proiectului. Datele derivate din Open Food Facts rămân sub ODbL 1.0 (bază) și DbCL 1.0 (conținut), cu atribuire Open Food Facts și contributorilor. Fotografiile sursei rămân sub CC BY-SA 3.0 și își păstrează proveniența. Nu sunt relicențiate sub MIT. Vezi [politica de utilizare și operare](docs/OPEN_FOOD_FACTS.md) și [licențele oficiale OFF](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/tutorials/license-be-on-the-legal-side/).

Distribuirea datelor proprii ale unui producător către OFF cere acord și drepturi explicite. Configurarea unui produs, găzduirea QR sau importul unei fișe nu licențiază automat rețeta ori documentația sa privată.

## Materiale de formare și programul partenerilor

Materialele proprii ale cursului inițial din `contracts/program/learning-content.json` sunt publicate explicit sub CC BY 4.0, cu atribuire OpenAliment și indicarea modificărilor. Licența lor nu relicențiază standardele GS1, mărcile sau materialele terților indicate ca referințe. Codul motorului, contractele și fixture-urile sintetice urmează licența software.

Băncile reale de examen și datele cursanților sunt private și nu fac parte din repository sau pachetele publice. Profilurile, fotografiile și logo-urile participanților se publică numai cu drepturi și acord; publicarea nu le acordă automat licența MIT sau CC BY. Autorizația OpenAliment este distinctă de acreditarea GS1 și de evaluarea documentației alimentare.
