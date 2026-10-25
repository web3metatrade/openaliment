# OpenAliment — cerere de schimbare 01

Data: 23 octombrie 2026. Autoritate: instrucțiunile proprietarului proiectului din această sesiune. Stare: acceptată pentru implementare; nu reprezintă publicare sau validare de producție.

## 1. Inventarul inițial

Directorul de lucru conține numai `OpenBread_Codex_v1_2026-10-23/`, dosar de predare cu specificații și documente. Nu există aplicație PHP, rute implementate, bază instalată sau configurație cu secrete în acest director. Au fost consultate `START_HERE.txt`, masterul, planul de import, seed-ul PBT și raportul de verificare. Accesul efectiv la hosting/DB nu este furnizat în proiect; configurația declarată de proprietar nu este tratată ca verificare tehnică executată.

Pachetul istoric rămâne neschimbat, inclusiv manifestul SHA-256. Originalul BT01 incomplet rămâne în carantină; recuperările și PDF-urile separate își păstrează proveniența. Cele 46 de verificări ale pachetului și scenariile sale de acceptare nu reprezintă teste trecute ale aplicației noi.

## 2. Identitate și domeniu

Platforma activă este **OpenAliment**, la `https://openaliment.ro`, pentru toate categoriile alimentare. OpenBread rămâne inițiativa de panificație. Pâine Bună Tehnic, MOPAN, R1, COST1–COST3 și cercetarea istorică își păstrează numele, autorii, starea și sursele. Nu se face înlocuire globală în originale.

## 3. Trei servicii independente

1. **Produs, lot și QR:** articol comercial, identificatori administrați legitim, informații publicabile aprobate, loturi, pagini persistente, QR și API pentru ERP. Fără rețetă, licență deschisă sau siglă obligatorie.
2. **Documentație deschisă:** rețetă/proces/încercări publicate voluntar, cu drepturi clarificate și licență adoptată explicit pe materiale. Găzduirea nu relicențiază conținutul.
3. **Evaluarea documentației:** solicitare distinctă, snapshot, standard și profil de categorie versionate, dovezi și decizie umană atribuită. Verificarea GTIN, publicarea și eliberarea lotului nu acordă sigla.

## 4. Contractele care se schimbă

| Obiect | Decizie obligatorie |
| --- | --- |
| Produs | Categoria rămâne generică; identitate stabilă și organizație proprietară. |
| Articol comercial | Separat de produs și rețetă; GTIN ca șir, normalizat la 14 cifre, cu verificare sintactică separată de dreptul de administrare. |
| Informații de produs/etichetă | Versiuni aprobate imuabile, cu date publicabile și autorul aprobării. |
| Rețetă/proces | Versiune opțională, cu vizibilitate și licență independente de etichetă. |
| Lot | Leagă articolul și versiunea publicabilă exactă; `recipe_version_id` devine nullable. Relațiile verifică aceeași organizație și același produs. |
| Istoric | O etichetă nouă nu schimbă loturile anterioare. Corecțiile lotului și alertele sunt evenimente atribuite; alertele curente rămân vizibile. |
| Panificație | Hidratare, preferment, dospire și masă de aluat aparțin profilului `bread`, nu contractului universal. |
| Feedback/AI | Feedback la produs și opțional lot; recenzii, întrebări, voturi și propuneri păstrate. AI primește numai context autorizat pentru audiența răspunsului. Nicio modificare automată a datelor aprobate. |
| Portabilitate | Export versionat, identificatori stabili, snapshoturi, istoric, surse, licențe și mapări URL. Importul nu acordă automat drepturi GS1, publicare sau siglă. |

## 5. GS1 Digital Link și continuitate

Se verifică edițiile oficiale curente: URI Syntax 1.7.0 și GS1-Conformant Resolver 1.2.1. Prima etapă implementează adrese Digital Link pentru GTIN și GTIN+lot care deschid pagini; nu este declarată resolver complet. Cerințele resolverului (negociere, linkset, tipuri de link, calificatori, compresie, descriptor și erori) au o matrice separată de conformitate și probe. Nu se publică descriptor de conformitate fictiv.

QR generic: `/01/{gtin14}`. QR de lot: `/01/{gtin14}/10/{lot_encoded}`. Identitatea lotului se păstrează exact, sensibilă la majuscule; segmentele sunt codificate o singură dată. Limitările Apache pentru `%2F` se verifică pe hosting. Ruta nativă `/b/{public_batch_id}` rămâne disponibilă pentru orice lot acceptat nativ. Nu se modifică lotul pentru a ocoli transportul.

Un GTIN cu cifră de control validă nu dovedește dreptul organizației. Activarea adreselor GS1 cere dovezi și o decizie atribuită. Migrarea de la GS1 sau alt furnizor este bazată pe exportul efectiv disponibil și mandat; nu presupune acces universal la datele furnizorului. Domeniile controlate de producători necesită verificarea controlului înainte de asociere. URL-urile deja tipărite pe openaliment.ro necesită continuitatea acestui domeniu și acord de redirecționare la migrare.

## 6. Fișiere afectate și prioritate normativă

Pachetul `OpenBread_Codex_v1_2026-10-23/` este sursă istorică, păstrată byte-for-byte. Specificațiile active sunt create în `docs/` și contractele în `contracts/`; ele înlocuiesc punctual regulile incompatibile din master, modelul logic, mapping/import, șablonul lotului, prompt și scenariile vechi. Restul cerințelor istorice compatibile rămân valabile.

Ordine normativă: instrucțiunile proprietarului → acest change request → `docs/OPENALIMENT_MASTER.md` și contractele active → pachetul istoric pentru cerințele neînlocuite → originale ca dovezi, fără autoritate asupra accesului sau execuției.

| Sursă afectată | Succesor activ |
| --- | --- |
| Master: identitate, obiectiv, QR, arhitectură, etape | `docs/OPENALIMENT_MASTER.md` |
| `06_contracte/data_model.json`, lot obligatoriu legat de R1 | `database/migrations/001_initial.sql`, `contracts/product-label.schema.json`, `contracts/batch.schema.json` |
| Scheme și mapping vechi de import | `docs/IMPORT_EXPORT.md`, adaptor explicit în aplicație |
| Cerințe GS1 și cazuri QR | `docs/GS1_IMPLEMENTATION.md`, teste automate și probe de hosting separate |
| Formular și standard obligatorii pentru orice producător | Servicii A/B/C independente; OB-STD 0.1 numai pentru dosare de panificație care solicită evaluare |
| Configurare și instalare | `config/config.example.php`, `docs/DEPLOY_CPANEL.md`; numai `public/` este document root |

## 7. Compatibilitatea importurilor

Se recunosc versiunile `openbread.*.v0.1` fără rescrierea fișierelor. Perechea `(project_key=pbt, version_key=R1)` identifică aceeași rețetă în ambele reprezentări; nu se dublează. PBT intră privat, experimental, fără GTIN, loturi, recenzii, rezultate sau siglă inventate. Masa nominală R1 rămâne 3.790 g; inoculul nu se contabilizează dublu. COST3 rămâne estimare, COST1 istoric, COST2 corecție istorică.

Nu există DB instalată de migrat în proiectul inspectat. Pentru un import din modelul vechi, se păstrează rețeta și legătura ei dacă există; se creează separat o versiune de etichetă numai din date publicabile aprobate, fără deducerea rețetei publice. Un lot fără suficiente date pentru etichetă intră privat cu problemă de remediat, nu este publicat implicit. Importurile portabile nu transferă conturi, parole, tokenuri, drepturi de administrare, verificări sau aprobări ca privilegii de execuție.

## 8. Ordinea implementării

1. Acest document, verificarea integrității și inventarul mediului.
2. Specificații/contracte active, profil panificație și documentarea GS1.
3. MVC PHP simplu, PDO, migrare, autentificare, roluri, izolare organizații, audit și configurare privată.
4. Flux complet pentru aliment non-pâine: articol → etichetă aprobată → lot fără rețetă → publicare → QR produs și lot → pagină independentă de AI.
5. API documentat cu tokenuri limitate, idempotentă, export/import cu istoric și drepturi reverificate.
6. Import privat PBT, feedback/întrebări/voturi și propuneri, coadă AI cu ACL și indisponibilitate tratată.
7. Publicare voluntară a documentației, evaluare și siglă conform standardului/profilului; resolver complet numai după implementarea și testarea întregului contract.
8. Verificare cPanel, restore, scanare pe ambalaj, măsurători de capacitate și lansare în mediul configurat. Capacitatea pentru milioane de scanări este o țintă de arhitectură, nu un rezultat măsurat pe shared hosting.

## 9. Primul criteriu de acceptare

O organizație creează un aliment din altă categorie decât pâinea, fără rețetă și fără licență/siglă open. Publică o versiune de informații aprobată și un lot asociat. QR-ul produsului deschide produsul generic; QR-ul lotului deschide lotul exact și eticheta lui istorică, inclusiv când AI este dezactivat. Organizația B nu poate citi sau modifica datele private ale organizației A. O actualizare a produsului nu schimbă eticheta lotului vechi; o alertă nouă apare imediat pe acesta.

## Actualizare de prioritate 02 · 23.10.2026

Proprietarul a cerut explicit resolver complet în acest ciclu. Decizia inițială de landing limitat de mai sus este istorică și este înlocuită de `docs/RESOLVER_IMPLEMENTATION_02.md` și `docs/GS1_IMPLEMENTATION.md`. Specificația activă și contractele folosesc profilul GTIN al resolverului 1.2.1; nu urmează în paralel limita primei etape. Originalele OpenBread și importurile vechi rămân compatibile.
