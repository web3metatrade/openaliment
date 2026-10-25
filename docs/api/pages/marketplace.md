# Marketplace, serializare și linia de producție

Marketplace-ul include conectori ERP, echipamente de etichetare, aplicații și alte integrări ale comunității. Categoriile, funcțiile și compatibilitățile sunt extensibile. Declarațiile unui dezvoltator nu acordă acces la datele unui producător.

## Publicarea unei integrări

Dezvoltatorul se conectează la `/developers/marketplace`, completează oferta și o trimite pentru verificare. Sunt necesare descrierea, compatibilitățile, suportul și licența software explicită. Contactul contului trebuie verificat. Publicul vede numai revizia aprobată; modificarea unei ciorne nu înlocuiește oferta publicată.

Fiecare versiune are un manifest imuabil: versiune semantică, URL HTTPS al pachetului, dimensiunea exactă, SHA-256, platformele, changelogul și semnătura, dacă există. Fișierul compilat sau arhiva sunt distribuite de dezvoltator. Nu folosi URL-uri cu parole ori tokenuri private. Serverul nu descarcă, nu extrage, nu compilează și nu execută pachetele comunității.

Administratorul verifică separat oferta și fiecare versiune, consemnând dovezile. Un hash declarat nu reprezintă singur o analiză antimalware sau o certificare. O corecție cere altă versiune. Suspendarea sau retragerea oprește endpointul OpenAliment de descărcare; nu poate retrage copiile deja descărcate.

Ofertele pot fi gratuite, comerciale sau la cerere. Plățile, comisioanele, achizițiile și emiterea automată de licențe comerciale sunt dezactivate cât timp proiectul este în formare. Descărcarea prin marketplace este disponibilă pentru pachetele gratuite aprobate, prin redirecționare către dezvoltator. Licența MIT a platformei nu se aplică implicit integrărilor sau documentelor alimentare.

## Agentul local și autorizarea

Conectorul rulează la producător. Credentialele bazei ERP, driverele și adresele imprimantelor rămân în infrastructura lui. Agentul inițiază HTTPS către OpenAliment; hostingul nu deschide conexiuni către ERP sau către echipamentele din rețeaua locală.

ERP-ul folosește API-urile și mapările versionate existente. Aplicațiile interactive folosesc OAuth Authorization Code cu PKCE S256. Aplicația OAuth declarată în ofertă trebuie să aparțină autorului. Selectarea unei integrări nu creează credentiale; utilizatorul autorizează explicit accesul și îl poate revoca separat de licența software.

## Conținutul QR tipărit

În QR se codifică URL-ul HTTPS complet GS1 Digital Link, cu domeniul și identificatorii GS1. Exemplu **exclusiv demonstrativ**, cu identificatorul pilotului Pâine Bună Tehnic și date simulate:

```text
https://openaliment.ro/01/09520000000011/10/DEMO-PBT-01/21/DEMO000001?11=261025&15=261030
```

| AI | Date | Poziție |
| --- | --- | --- |
| 01 | GTIN, exact 14 cifre și cifră de control validă | path |
| 10 | Lot, maximum 20 caractere GS1 admise | path |
| 21 | Serie individuală, maximum 20 caractere GS1 admise | path |
| 11 | Data producției, YYMMDD | query |
| 15 | Durabilitate minimală / best before, YYMMDD | query |
| 17 | Expirare / use by, YYMMDD | query |

Numai datele aplicabile, aprobate, sunt incluse. Lipsa unei date nu produce o valoare inventată. AI 15 și AI 17 au semnificații diferite. Fiecare valoare din path este codificată separat: `/` din lot devine `%2F`, plusul devine `%2B`, iar `%` devine `%25`. URI-ul ambalajului și snapshotul etichetei rămân nemodificate după actualizarea produsului.

Se tipărește QR Model 2 care conține URI-ul, fără FNC1 și fără parantezele folosite în prezentarea AI. Sintaxa corectă nu dovedește citirea fizică sau compatibilitatea unui POS concret. Proba pe linie verifică modelul imprimantei, firmware-ul, DPI, viteza, dimensiunea modulului, zona liberă, suportul de imprimare și scanerele țintă.

Șablonul ZPL de referință folosește o suprafață de 600 × 650 puncte, QR-L cu 5 puncte/modul și text separat de zona QR. Acceptă maximum 512 octeți în URI, fără trunchiere. Aceste dimensiuni sunt în puncte ale imprimantei, nu în milimetri; verifică DPI-ul și eticheta fizică înainte de utilizare. Pentru alt format, dimensiune sau adresă mai lungă folosește JSON și adaptorul echipamentului. Limita șablonului nu restrânge sintaxa GS1 a adresei. Configurația anterioară a unei imprimante trebuie reverificată la adoptarea acestui șablon.

## Flux de tipărire

1. Proprietarul organizației înregistrează echipamentul în `/manage/printing`, cu format JSON sau ZPL. Credentialul `oa_print_…` este afișat o singură dată; serverul stochează numai hashul. El permite numai comenzile echipamentului respectiv.
2. Producătorul creează comanda pentru un lot publicat, fără alertă activă, cu GTIN verificat. Se acceptă 1–500 ambalaje/comandă și maximum 100 comenzi noi/oră/organizație. Volumele mai mari cer împărțirea comenzilor și dimensionarea capacității.
3. Seria fiecărui ambalaj este rezervată atomic sau furnizată explicit de ERP. Unicitatea `(GTIN, serie)` este impusă în DB, sensibil la majuscule. Anularea nu eliberează seriile pentru reutilizare.
4. Agentul apelează `POST /api/v2/printing-agent/claims` cu Bearer-ul echipamentului și primește o comandă plus secretul preluării. Contractul JSON este neutru; adaptorul local îl transformă în protocolul imprimantei. Formatul ZPL adaugă un șablon fix de referință, cu câmpuri codificate hexadecimal.
5. Agentul păstrează local ID-ul și secretul înainte de retry. `dispatched` confirmă expedierea datelor; `confirmed` cere un răspuns al echipamentului și dovezi. Succesul TCP nu dovedește tipărirea fizică.
6. O preluare fără răspuns timp de 5 minute devine `uncertain` la următoarea verificare a cozii. Nu este retrimisă automat. Operatorul verifică rezultatul.
7. Retipărirea creează altă comandă, autorizată separat, cu motiv și `same_serials_confirmed=true`. Folosește aceleași identități pentru înlocuirea etichetelor. Ambalajele noi primesc serii noi.

Credentialul poate fi înlocuit sau revocat de proprietar. Comanda poate fi anulată numai înainte de preluare. API-ul pentru creare cere `Idempotency-Key`; repetarea aceleiași cereri nu rezervă alte serii.

## Exemplu API

ID-urile sunt sintetice. Folosește propriile resurse și identificatorii asupra cărora ai drepturi.

```json
{
  "device_id": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
  "batch_id": "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb",
  "quantity": 100
}
```

Trimite obiectul la `POST /api/v2/manage/printing/jobs`, cu Bearer-ul autorizat și un `Idempotency-Key` stabil. Pentru serii externe adaugă `serials`, cu exact `quantity` valori distincte. Agentul folosește apoi credentialul dedicat și confirmă prin `POST /api/v2/printing-agent/jobs/{id}/acknowledgements`, cu `claim_token`, `state` și `evidence`. Nu transmite credentialele ERP în acest flux.

## Standarde și probe

- [GS1 URI Syntax 1.7.0, august 2026](https://ref.gs1.org/standards/digital-link/uri-syntax/1.7.0/): identificatori, calificatori, atribute și codificarea URI.
- [GS1-Conformant Resolver 1.2.1](https://ref.gs1.org/standards/resolver/): rezoluție, linkset și păstrarea query-ului, separat de tipărire.
- [GS1 Application Identifiers](https://ref.gs1.org/ai/): AI 11, 15, 17, 21.
- [Zebra Programming Guide](https://cpws.zebra.com/cpws/docs/zpl/zpl_manual.pdf): QR `^BQ` și câmpuri hexadecimale `^FH`.

Raportul implementării separă probele software de simulări și de probele fizice. Nu se declară certificarea unei imprimante sau a unui POS fără verificarea lor. QR-ul și resolverul funcționează independent de marketplace și AI.

## Export și migrare

Parcurge GET /api/v2/manage/printing/units cu limit=100 și next_cursor până la has_more=false pentru a păstra seriile, URI-urile exacte și snapshoturile. GET /api/v2/manage/printing/jobs și detaliile fiecărei comenzi completează istoricul; echipamentele sunt listate fără credentiale. Aceste date completează exportul alimentar existent și nu sunt omise implicit într-o migrare a identităților tipărite. Pentru un transfer consistent, oprește temporar crearea comenzilor pe durata colectării sau reconciliază înregistrările după ID înainte de schimbarea furnizorului. Schimbarea furnizorului de date nu transferă automat controlul domeniului din QR; destinațiile resolverului se actualizează separat, păstrând adresa tipărită.

Lista tuturor versiunilor proprii este paginată la GET /api/v2/developer/marketplace/apps/{id}/releases; panoul afișează cele mai recente 20. Autorul poate retrage imediat o versiune sau oferta întreagă, cu motiv. Selecțiile organizației se parcurg prin GET /api/v2/manage/marketplace/selections și nu acordă acces API. Cotele inițiale sunt 1.000.000 de identități și 20.000 de comenzi per organizație, configurabile privat prin print_limits numai după dimensionarea stocării și a capacității.

La retipărire, device_id este opțional și poate selecta alt echipament propriu activ, păstrând identitățile și snapshoturile. Proprietarul poate înlocui credentialul din panoul echipamentului; vechiul secret nu mai este acceptat. Valorile . și .. ca întreg segment de lot/serie sunt refuzate pentru a evita normalizarea URL de către browser; caracterele speciale se folosesc numai după verificarea transportului pe domeniul real.

Licența, adresa termenilor, dezvoltatorul și tipul ofertei sunt fixate în manifestul fiecărei versiuni, din oferta aprobată la înregistrarea ei. Pentru prima versiune fără ofertă aprobată se folosește ciorna curentă. O schimbare ulterioară a ofertei nu relicențiază versiunile vechi și nu transformă retroactiv un pachet gratuit într-unul comercial. Autorul poate retrage explicit o versiune. Un manifest comercial nu expune URL-ul pachetului în proiecția publică; hashul manifestului original este destinat provenienței și nu este hashul acelei proiecții redactate.
