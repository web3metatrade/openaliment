## GS1 Digital Link: identificare și rezolvare

Profilul OpenAliment folosește GS1-Conformant Resolver 1.2.1 pentru cheia primară GTIN (01), cu sintaxa GS1 Digital Link URI 1.7.0 și linkset RFC 9264. Descriptorul public [/.well-known/gs1resolver](https://openaliment.ro/.well-known/gs1resolver) declară profilul serviciului. OpenAliment este un furnizor independent; nu atribuie GTIN-uri și nu deține o certificare emisă de GS1 pentru acest serviciu.

| Element | Identificator de aplicație | Utilizare |
| --- | --- | --- |
| GTIN | 01 | Articol comercial; obligatoriu în profil |
| Variantă de produs, CPV | 22 | Califică varianta articolului |
| Lot | 10 | Identifică lotul, cu respectarea exactă a valorii |
| Serie | 21 | Identifică unitatea serializată |
| TPX | 235 | Califică GTIN-ul în forma TPX |

Combinațiile acceptate la înregistrarea destinațiilor sunt: fără calificatori, 22, 10, 22+10, 21 sau 235. Acestea sunt regulile de asociere a legăturilor din Resolver §2.5.10, nu restricții asupra URL-ului scanat. Un URL poate conține împreună CPV, lot și serie, în ordinea 22 → 10 → 21. La rezolvare se reunesc legăturile asociate fiecărui nivel; GTIN + serie identifică unitatea independent de CPV și lot. TPX rămâne separat de ceilalți calificatori. SSCC, GLN și cheile primare diferite de 01 nu fac parte din profilul serviciului.

### Produs sau lot

```text
https://openaliment.ro/01/09520000000011
https://openaliment.ro/01/09520000000011/10/DEMO-PBT-01
```

Aceste adrese folosesc exclusiv identificatorul demonstrativ Pâine Bună Tehnic. Prima identifică produsul; a doua include lotul simulat. Un GTIN comercial necesită verificarea dreptului organizației de a-l administra.

Generatorul QR returnează `url` pentru datele efective ale articolului sau lotului. Folosește această valoare în fluxul de tipărire. Dacă GTIN-ul, mandatul, transportul URL sau valoarea lotului nu permit Digital Link, răspunsul indică `native_url` și `reason`. Adresele native `/t/{id}` și `/b/{id}` rămân disponibile.

Loturile GS1 folosesc setul de caractere X, maximum 20 de caractere. Codifică fiecare valoare de segment o singură dată, după separarea segmentelor. Păstrează literele mari/mici, plusul, slashul și procentul din valoarea originală. Loturile care nu se încadrează sau sunt literal `.` ori `..` folosesc adresa nativă, deoarece browserul poate normaliza segmentele punct.

Sintaxa acceptă `&` și `;` ca separatori de query; generatoarele OpenAliment emit `&`. Parametrii sunt transmiși integral destinației. Profilul alimentar public include opțional `gs1_qualifiers`, cu valorile observate ale calificatorilor, și `observed_dates`. Aceste metadate nu sunt o aprobare a producătorului sau o dovadă de achiziție; informațiile aprobate ale lotului rămân distincte. Inventarul Acasă și transferul său păstrează identitatea ambalajului și codul scanat original.

### Solicitarea unei resurse

| Cerere | Rezultat |
| --- | --- |
| Scanare fără selecție | Destinația implicită aprobată |
| `?linkType=gs1:allergenInfo` | Resursa publicată pentru alergeni |
| `?linkType=linkset` | Setul legăturilor disponibile |
| `Accept: application/linkset+json` | Linkset JSON |
| Relație absentă | 404, cu alternative disponibile în linkset |
| Mai multe alegeri echivalente | 300, cu alternativele |
| Destinație selectată | 307 și antetul Location |

Sunt disponibile GET, HEAD, OPTIONS și CORS public. Linksetul poate fi cerut ca JSON, `application/linkset+json`, JSON-LD sau HTML. Antetele de limbă și format și parametrul de context selectează dintre resursele publicate. Parametrii suplimentari din query sunt transmiși destinației; ei nu modifică informațiile aprobate ale lotului. Nu transmite tokenuri în query și nu transfera antetul Authorization către destinația unui redirect.

Decompresia EPC acceptă formele `eh` și `ex` pentru SGTIN-96, SGTIN-198, SGTIN+, DSGTIN+, SGTIN++ și DSGTIN++. Compresia Digital Link legacy și validarea semantică a tuturor atributelor suplimentare din query nu sunt oferite de acest profil.

### Destinații versionate

Folosește [operațiile resolverului](/api/docs/reference/manageResolverDraft) pentru a înregistra adresa, titlul, relațiile și calificatorii. `link_key` identifică legătura stabilă; `expected_version` protejează actualizările concurente. O schimbare creează o revizie, iar publicarea cere confirmarea proiecției publice și permisiunea `publish`.

Destinația implicită necesită o relație descriptivă și nu acceptă limbă, format sau context. Publică mai întâi pagina produsului ori destinația GTIN de bază. Relațiile `gs1:` provin din vocabularul GS1; sunt acceptate și relațiile IANA alternate, describedby, license, help, service, related, item, collection și about.

Lotul cunoscut păstrează versiunea sa de etichetă. Lotul necunoscut poate deschide informația generală, cu avertizare explicită; nu este asociat unui lot aproximativ. Alertele active și disputele privind mandatul păstrează pagina nativă ca destinație implicită de siguranță. Respectă `Cache-Control: no-store` pentru informațiile și alertele curente.

### Scanare și tipărire

Generarea unui QR și deschiderea paginii nu validează automat citirea la POS. Verifică simbolul pe ambalajul tipărit, scannerul 2D, configurarea POS și articolul înregistrat de retailer. Pentru o probă fără vânzare folosește [fișa demonstrativă](/demo/paine-buna-tehnic/etichete).

Referințe: [standardul resolver 1.2.1](https://ref.gs1.org/standards/resolver/1.2.1/), [sintaxa URI 1.7.0](https://ref.gs1.org/standards/digital-link/uri-syntax/1.7.0/), [profilul OpenAliment](https://openaliment.ro/resolver/profile).
