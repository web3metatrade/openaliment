# OpenAliment — analiză UI/UX pe pagini și dimensiuni

24 octombrie 2026 · versiunea analizată: 0.5.3 · **analiză, fără modificarea interfeței sau a datelor din producție**.

Platforma are o identitate vizuală coerentă: verdele, fundalul cald, iconurile SVG și butoanele principale se recunosc între pagini. În general, conținutul se încadrează în lățimea disponibilă. Îmbunătățirea cu cel mai mare efect este uniformizarea structurii paginilor: axe comune, spațiu în interiorul cardurilor, navigare consecventă și ordine clară a acțiunilor.

Am identificat **20 de oportunități: 7 de prioritate P1, 12 P2 și una P3**. Prioritățile descriu efectul asupra interfeței și utilizării; nu sunt clasificări de vulnerabilități de securitate. Cinci constatări se bazează pe cod pentru stări care nu au fost populate în browser și sunt marcate explicit.

## 1. Acoperire și metodă

- Inventarul include **toate cele 29 de template-uri PHP**, inclusiv componentele incluse în alte pagini, variantele de rută și formularul de instalare. Inventarul separat include și utilitarul local `tools/setup-key.html`.
- **27 de adrese reprezentative** au fost deschise în browserul integrat. Au rezultat **125 de probe de geometrie și captură**, incluzând stări deschise ale meniului API, exploratorului și preferințelor Acasă.
- Cele **225 de pagini API — 17 ghiduri și 208 operații — sunt inventariate individual** și asociate componentelor comune. Șase adrese reprezentative au fost deschise vizual: prezentarea generală, fluxul ERP, crearea produsului, un titlu lung, schimbarea parolei și sincronizarea Acasă, cu 114 câmpuri de cerere. Nu declarăm 225 de pagini verificate vizual individual.
- Au fost inspectate DOM-ul randat, pozițiile și dimensiunile elementelor, capturile, CSS-ul comun și condițiile din template-uri. Nu s-au creat produse, loturi, chei, granturi sau date personale pentru audit; nu s-au trimis formulare și nu s-a activat camera.

| Profil | Viewport CSS | Acoperire |
|---|---:|---|
| Telefon îngust | 320 × 568 | Toate cele 27 de adrese reprezentative, plus cele trei secțiuni Acasă |
| Telefon | 390 × 844 | Aceleași adrese și secțiuni; meniuri, explorator și preferințe |
| Tabletă | 768 × 1024 | Toate cele 27 de adrese și cele trei secțiuni Acasă |
| Tabletă orizontală / laptop mic | 1024 × 768 | Landing, spațiul producătorului, actualizări și două pagini API |
| Desktop | 1440 × 1000 | Toate cele 27 de adrese și cele trei secțiuni Acasă; preferințe |

Acestea sunt dimensiuni responsive într-un singur browser Chromium, nu probe pe cinci dispozitive fizice. Bara de derulare poate reduce lățimea utilă cu 15 px; raportul JSON păstrează atât viewportul, cât și lățimea utilă. Tastatura telefonului, Safari/iOS, atingerea reală, cititoarele de ecran, zoomul browserului și tipărirea efectivă nu au fost testate.

Paginile populate pentru produse reale, loturi, rețete, conectori și consimțământ OAuth nu erau disponibile în contul inspectat. Pentru ele s-au analizat template-urile și condițiile de afișare. Demonstrația pâinii folosește un template distinct de pagina unui produs real; nu este tratată drept probă pentru acel template.

Capturile complete pot conține imagini încă neîncărcate în afara porțiunii vizibile. Încărcarea întârziată a ilustrației de pe landing a fost verificată separat, prin afișarea secțiunii. Spațiile dintr-o captură înaintea încărcării nu sunt raportate ca imagini lipsă.

Date: [inventarul complet](UI_UX_PAGE_INVENTORY_2026-10-24.json) · [măsurători și amprentele surselor](../verification/ui-ux-review-2026-10-24.json). Capturile brute și DOM-ul administrativ rămân în `tests/runtime/ui-ux-audit-2026-10-24/`, exclus din Git; pot conține etichete de cont sau contacte. Cele șase capturi incluse mai jos au fost selectate fără listele administrative de persoane sau secrete.

## 2. Problemele cu efect transversal

### UX-01 · P1 · Cardurile își pierd spațiul lateral

**Confirmat în browser și cod:** `/manage/security`, `/confidentialitate` și secțiunile de jos din `/admin/updates`.

În CSS, `.reading-page section { padding:30px 0 }` prevalează asupra `.panel { padding:28px }`, respectiv asupra paddingului mobil de 22 px. Rezultatul este un card cu chenar, dar cu titlul, textul și câmpurile aproape lipite de margine. Pe telefon, cardul de securitate are lățimea de 331 px și paddingul efectiv `30px 0px`; câmpurile ajung până la laturile cardului.

**Recomandare:** separarea stilului de articol de stilul cardului. De exemplu, regula pentru secțiunile editoriale se limitează la secțiuni care nu sunt `.panel`, iar cardul își păstrează propriile spațieri. Corecția trebuie să se aplice componentelor, fără adăugarea în lanț a unor reguli `!important`.

**Acceptare:** aceleași margini interioare pentru titlu, paragraf, etichetă și câmp; 28 px pe desktop și 20–24 px pe mobil, după tokenul ales. Niciun input nu atinge chenarul cardului.

![Cardurile de securitate fără spațiu lateral](evidence/2026-10-24/security-mobile.png)

### UX-02 · P1 · Actualizările au două cadre și titlul apare târziu

**Confirmat în browser și cod:** `/admin/updates`.

Primele două carduri sunt în containere separate, înaintea titlului paginii. Pe desktop, acestea au aproximativ **1176 px**, iar secțiunile următoare **786 px**. Titlul începe la aproximativ **y=626 px pe desktop**, respectiv **y=694 px pe telefon**, fără mesajul temporar de confirmare. Pagina pare alcătuită din două pagini lipite, cu o schimbare mare a axei stângi.

**Recomandare:** un singur container de pagină; întâi titlul și întoarcerea la administrare, apoi un sumar al stării și cardurile. Aceeași grilă pentru securitate, API, resolver, OCR, conectori, Acasă și SMTP. Formularele tehnice pot ocupa un rând întreg, iar cardurile de stare pot avea două coloane când există spațiu.

**Acceptare:** un singur H1 înaintea secțiunilor; toate cardurile folosesc aceeași axă de conținut și aceeași politică de padding. Un mesaj flash nu trebuie să mute titlul sub mai multe operațiuni.

![Schimbarea de lățime și poziția titlului în actualizări](evidence/2026-10-24/updates-desktop.png)

### UX-03 · P1 · Tabelele API încap, dar devin greu de citit

**Confirmat în browser și cod:** referința operațiilor și componenta `api-fields.php`.

La lățimi mici, cele patru coloane își împart o zonă foarte îngustă. Denumiri precum `category`, `operations` și exemplele de chei se rup în fragmente. În schema mare, prima coloană măsurată avea aproximativ 60 px, incluzând paddingul. Faptul că pagina nu depășește viewportul nu asigură lizibilitatea tabelelor.

**Recomandare:** pe telefon, câmpurile se prezintă ca înregistrări verticale: nume, tip/obligativitate, explicație și limite. Pentru tabelele care necesită comparație, se păstrează o lățime minimă lizibilă și derularea locală, cu un indiciu vizibil. Codul și identificatorii trebuie să poată fi copiați integral. Tabelul comparativ din ghidul GS1 folosește deja derulare locală, cu lățime minimă de 650 px; acolo trebuie îmbunătățită descoperirea derulării.

**Acceptare:** un nume de câmp uzual nu se fragmentează în silabe de 2–4 caractere; descrierile se citesc pe lățimea utilă. Derularea locală nu trage întreaga pagină lateral.

![Tabelele API pe telefon](evidence/2026-10-24/api-reference-mobile.png)

### UX-04 · P1 · Închiderea preferințelor Acasă dispare la derulare

**Confirmat în browser și cod:** dialogul „Pentru mine”.

La 390 × 844 px, dialogul avea **816 px înălțime**, cu aproximativ **1566 px de conținut**. După o derulare, butonul de închidere era la **y=-719 px**, în afara zonei vizibile. Același dialog combină conectarea, sincronizarea, alergenii, exportul și importul.

**Recomandare:** antet persistent al dialogului sau un panou de setări cu întoarcere mereu disponibilă. Grupuri separate și ușor de recunoscut pentru cont, preferințe și copii de date. O acțiune de salvare se așază lângă grupul pe care îl salvează; conectarea nu trebuie confundată cu salvarea alergenilor.

**Acceptare:** închiderea rămâne accesibilă la orice poziție de derulare; nici antetul, nici eventualele acțiuni persistente nu acoperă câmpurile sau ultima opțiune. Preferințele existente nu se schimbă prin simpla deschidere a panoului.

### UX-05 · P1 · Butoanele pentru import și verificare sunt lipite

**Confirmat în browser și cod:** `/manage/import`.

Formularele consecutive nu au spațiu între ele. Cele două butoane ale pilotului au fiecare 51 px înălțime și **0 px distanță verticală**. Și al doilea formular pentru fișier începe exact unde se termină primul. Ambele operațiuni au aceeași greutate vizuală, deși una doar verifică și alta scrie date.

**Recomandare:** grupuri distincte „Verifică” și „Importă”, cu 12–16 px între acțiuni și mai mult între etape. Verificarea poate avea stil secundar, iar importul aprobat stil principal. Pe termen scurt, spațierea formularelor consecutive este o corecție mică; reutilizarea fișierului deja verificat este o îmbunătățire separată de flux.

**Acceptare:** nicio pereche de acțiuni independente nu se atinge; este clar ce verifică și ce importă. Nu se schimbă implicit comportamentul de dry-run sau aprobările.

![Formularele și butoanele consecutive din import](evidence/2026-10-24/import-mobile.png)

### UX-06 · P2 · Navigarea spațiului producătorului nu este consecventă

**Confirmat în browser și cod.** În familia `manage.php`, numai „Produse și loturi” primește `aria-current`. Feedback, API și Import nu au pagina activă marcată. Conectoare, Aplicații și Securitate folosesc alte antete și pierd navigarea comună. În paginile bazate pe `manage.php`, H1 rămâne numele organizației, indiferent de secțiune.

Pe telefon, cele opt legături ale navigării ocupau aproximativ 98 px și trei rânduri. Linkurile sunt mici, iar „Exportă” este amestecat cu destinațiile de navigare, deși declanșează o descărcare.

**Recomandare:** componentă comună pentru antet, organizație, secțiune activă și breadcrumb; titlul secțiunii să fie evident. Exportul se grupează cu operațiunile de date. Pe mobil, navigarea poate avea un selector clar de secțiune sau o bară compactă, fără a ascunde acțiunea curentă în mai multe niveluri.

### UX-07 · P1 · Interfața trebuie să reflecte rolul utilizatorului

**Analiză de cod; nu s-au schimbat rolurile unor conturi reale.** Mai multe formulare din `manage.php` se afișează fără aceeași condiționare pe rol ca operațiunea din domeniu. De exemplu, crearea/publicarea și formularul de emitere a cheii pot fi afișate unui utilizator care va fi refuzat la trimitere. Resolverul și conectorii au deja condiții explicite pentru unele acțiuni.

**Recomandare:** aceeași matrice de capabilități pentru afișarea acțiunilor și explicații scurte pentru accesul numai la citire. Verificarea de securitate pe server rămâne obligatorie. Nu se cere utilizatorului să completeze un formular pe care nu îl poate trimite.

### UX-08 · P1 · Validarea pe server pierde contextul formularului

**Analiză de cod; nu s-au trimis date invalide în producție.** Erorile HTML sunt trimise prin pagina comună `message.php`, cu întoarcere numai la prima pagină. O eroare de validare după un formular lung întrerupe sarcina și nu oferă corectarea câmpului în context.

**Recomandare:** erori lângă câmpuri și sumar de validare în același formular; păstrarea valorilor care pot fi reafișate în siguranță, fără parole sau tokenuri. Pentru 403, 419 și indisponibilitate, revenire controlată la sarcina autorizată și explicație adecvată. Schema JSON a erorilor API rămâne separată și compatibilă.

### UX-09 · P2 · Meniul Copy Page schimbă înălțimea butoanelor

**Confirmat prin deschidere și măsurare.** Regula globală `details[open] > summary { margin-bottom:24px }` ajunge și în meniul API. La deschidere, butonul „Copiază pagina” ajunge la 57 px, iar săgeata rămâne la 33 px. Titlul este împins în jos cu 24 px.

**Recomandare:** stilurile pentru acordeoanele de conținut se limitează la acele componente. Meniurile, sidebarul API și schemele au reguli proprii. Perechea Copy Page/săgeată trebuie să aibă o înălțime comună, iar meniul să se suprapună fără salt de conținut.

### UX-10 · P2 · Scara titlurilor se inversează pe mobil

**Confirmat în browser și cod.** Regula `.reading-page h1` de pe mobil poate înlocui dimensiunea `.page-title`. „Securitatea contului” și „Confidențialitate” ajung la 43,2 px pe telefon, față de 36,8 px pe desktop; alte pagini administrative folosesc 30,4 px pe telefon. La 320 px, „Confidențialitate” își depășește caseta de text și ocupă marginea dreaptă.

**Recomandare:** scări distincte pentru landing, pagini de lucru și articole, cu dimensiuni fluide și limite explicite. Pentru interfețele de lucru, aproximativ 28–32 px pe telefon este un punct bun de pornire, supus verificării vizuale. Titlurile lungi, numele produselor și identificatorii au reguli de încadrare fără tăierea informației importante.

### UX-11 · P2 · Landingul cere o tranziție mai bună la tabletă și telefon

**Confirmat în browser și cod.** La 768 px, hero-ul păstrează două coloane înguste; titlul ocupă patru rânduri, iar fotografia este relativ mică. Titlul are 48 px la această dimensiune și aproximativ 45 px la 1024 px, din cauza unor reguli suprapuse. Cardurile de funcționalități rămân trei pe rând la tabletă și au mult text pe linii scurte.

La 390 × 844 px, prima acțiune comercială începe aproximativ la **y=906 px**, după titlu, fotografie și paragraful lung. Fotografia exemplului este accesibilă mai sus și funcționează ca legătură, dar acțiunea principală comercială cere derulare.

**Recomandare:** prag de trecere la o coloană determinat de spațiul necesar conținutului, nu numai de 767 px; scară fluidă a titlului; text introductiv mai scurt și acțiunea principală mai aproape de imagine. Se păstrează fotografia pâinii selectată de utilizator, proporția ei și mesajele corecte despre compatibilitatea POS.

### UX-12 · P2 · Mărimea textului și a țintelor trebuie standardizată

**Confirmat pentru dimensiuni; efectele pe dispozitive reale rămân de testat.** Inputurile comune au text de 14 px, unele selecturi 13 px, iar emailul Acasă 15 px. Linkurile de lucru și permisiunile sunt dense; un link din navigarea producătorului măsura 18 px înălțime. Butoanele principale au, în general, dimensiuni confortabile.

**Recomandare:** 16 px pentru textul câmpurilor pe telefon, etichete lizibile și rânduri întregi apăsabile pentru checkboxuri. Pentru acțiuni uzuale pe telefon, țintă de confort de 44–48 px. Linkurile din subsol și textele auxiliare pot rămâne secundare vizual, fără a deveni foarte mici. Validarea trebuie făcută și cu tastatura virtuală deschisă.

Pragul WCAG 2.2 AA pentru ținte este de 24 × 24 CSS px, cu excepții inclusiv pentru spațiere, linkuri în text și controale ale browserului. Un control măsurat sub 24 px nu constituie singur dovada unei încălcări; aici se recomandă și o îmbunătățire de confort. [Explicația oficială W3C](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html).

### UX-13 · P2 · Exemplul pâinii ar trebui să aducă QR-ul mai sus

**Confirmat în browser.** Pe telefon, cardul QR începe aproximativ la y=1184 px, iar imaginea QR la y=1250 px, după explicații și identificatori. Pentru cine a ales să vadă un cod scanabil, acțiunea centrală apare târziu.

**Recomandare:** titlu și marcaj DEMO, alegere clară Produs/Lot, apoi QR și descărcare. Identificatorii detaliați și instrucțiunile POS urmează într-o zonă secundară. Starea simulată rămâne evidentă, inclusiv la descărcare și tipărire. Marginea liberă, contrastul și modulele codului nu se reduc pentru a potrivi un card.

![Poziția QR-ului în demonstrația mobilă](evidence/2026-10-24/demo-product-mobile.png)

### UX-14 · P2 · Stările goale au nevoie de o regulă comună

**Confirmat în browser.** Catalogul are o stare goală structurată și centrată. Unele liste administrative au doar un paragraf, iar „Drepturi GS1” poate rămâne un card cu titlu și fără explicație. Când nu există aplicații sau tokenuri, coloana scurtă din stânga lasă mult spațiu lângă formularul lung din dreapta.

**Recomandare:** aceeași structură de stare goală — titlu, o explicație și o acțiune relevantă, când există. Pentru prima utilizare, un formular bine încadrat poate ocupa coloana principală, iar lista apare după prima înregistrare. Nu se egalizează artificial înălțimile unor carduri cu roluri diferite. Catalogul poate oferi separat o legătură către exemplul demonstrativ, fără a-l prezenta ca produs comercial publicat.

### UX-15 · P2 · Formularele tehnice trebuie grupate pe sarcini

**Analiză de cod pentru stările populate.** Articolul comercial combină lista versiunilor, o etichetă lungă, date structurate, documente și loturi. Formularul de date pentru aplicații conține două liste de alergeni și mai multe grupuri nutriționale. Conectorul și resolverul au formulare lungi, cu opțiuni avansate.

**Recomandare:** grupuri vizuale explicite pentru identitate, etichetă, alergeni/nutriție, păstrare, documente și revizuire. Rețeta rămâne opțională. Opțiunile avansate pot fi extinse la nevoie; starea de ciornă și următorul pas trebuie să fie vizibile. În conectori, JSON-ul este justificat pentru integratori; maparea, verificarea și importul se diferențiază clar ca etape.

Se păstrează numele câmpurilor și contractele; pentru o primă corecție sunt suficiente componente și grupare în interfață, fără rescrierea API-ului.

### UX-16 · P2 · Pagina reală de produs/lot cere validare cu date populate

**Risc identificat în cod, fără probă vizuală pe produs real.** Pe telefon, simbolul decorativ de 80 px împarte rândul cu un titlu mare, reducând spațiul numelui. Datele lotului sunt în coloana laterală, care coboară după ingredientele și informațiile produsului pe mobil. Publicarea lotului și eliberarea lui sunt două acțiuni importante apropiate ca stil.

**Recomandare:** nume pe lățimea utilă, simbol decorativ discret; lot, stare și dată într-un sumar imediat după titlu. Alertele curente rămân primele și integral vizibile. Publicarea și eliberarea se disting prin etichete, grupare și starea afișată. Nu se schimbă sensul datelor sau istoricul pentru obținerea simetriei.

### UX-17 · P2 · Administrarea are nevoie de un context mai clar

**Confirmat în browser și cod.** Câmpuri precum „Dovezi verificate pentru identitate și reprezentare” au puțin ajutor contextual. Într-un ecran cu mai multe organizații, drepturi și contacte, cititorul trebuie să distingă imediat obiectul verificării și starea sa. Pe actualizări, formularele tehnice primesc greutate vizuală similară cu stările deja pregătite.

**Recomandare:** grupuri cu titlu, stare și obiectul verificării; o explicație scurtă și un exemplu pentru dovezi, cu ajutor detaliat extensibil. Filtrare după starea de lucru când listele cresc. Actualizările deja active se prezintă compact, iar operațiunile de mentenanță se grupează separat de instalare.

### UX-18 · P3 · Etichetele de probă pot avea rânduri interne comune

**Confirmat în afișarea pe ecran; tipărirea fizică nu a fost probată.** Cele două carduri au contururi aliniate, dar liniile GTIN/lot, URL-urile și descărcările nu se aliniază între coloane, deoarece lotul are mai mult conținut.

**Recomandare:** zone comune pentru titlu, QR, identificatori, URL și descărcare, cu conținutul lăsat să crească. Se păstrează dimensiunile fizice ale modulelor SVG; egalizarea vizuală nu trebuie să redimensioneze arbitrar codurile. Acceptarea finală include o probă A4/PDF la 100%, fără ajustare automată.

![Alinierea celor două etichete de probă](evidence/2026-10-24/demo-print-desktop.png)

### UX-19 · P2 · Rețeta publică trebuie să poată fi citită ca document

**Analiză de cod:** `recipe.php` prezintă snapshotul într-un bloc JSON cu înălțime maximă. Este util pentru dezvoltatori, dar dificil pentru cititorul interesat de rețetă și proces.

**Recomandare:** secțiuni lizibile pentru ingrediente, proces, încercări, rezultate, costuri și proveniență, în măsura în care există date. JSON-ul, hashul și fișierele originale rămân disponibile ca resurse tehnice. Stadiul experimental și licența rămân lângă titlu. Nu se completează valori lipsă și nu se transformă estimările în rezultate validate.

### UX-20 · P2 · Documentația lungă are nevoie de repere locale

**Confirmat în browser și cod.** Pe telefon, titlul unei pagini API începe aproximativ la y=332 px după antetul general și antetul documentației. Cuprinsul lateral este ascuns pe ecrane mici. Pagina de sincronizare ajunge la aproximativ 26.500 px înălțime la 390 px lățime; schema cererii are 114 rânduri.

**Recomandare:** antet de documentație mai compact, acces permanent și simplu la căutare, cuprins local extensibil și gruparea câmpurilor pe obiecte. Excepțiile și schemele foarte lungi pot fi extinse progresiv. Se păstrează titlurile, ancorele, adresele Markdown, Copy Page și legăturile către AI. Reducerea zgomotului vizual nu trebuie să elimine informație din documentația copiată sau exportată.

## 3. Analiza pagină cu pagină

**B** = deschisă și măsurată în browser; **S** = analiză de sursă pentru starea respectivă. Pentru paginile marcate B, verificarea de bază include 320, 390, 768 și 1440 px. Mostrele la 1024 px și stările interactive suplimentare sunt identificate în raportul JSON.

### Pagini publice și scanare

| Pagină / stare | Acoperire | Încadrare și simetrie | Acțiune recomandată |
|---|---|---|---|
| Prima pagină `/` | B | Grilă și paletă coerente; hero și trei carduri înguste la tabletă; acțiune comercială sub prima vedere pe telefon | UX-11, UX-12; păstrarea imaginii selectate |
| Catalog gol `/produse` | B | Stare goală bine centrată și margini consistente | Păstrarea structurii; legătură distinctă spre demonstrație |
| Catalog populat | S | Grilă de carduri; nume, producători și cantități variabile pot schimba înălțimile | Titlu și metadate în zone comune; fără tăierea numelui alimentului; probă cu 1/2/3/7 produse |
| Despre `/despre` | B | Coloană de lectură bună; titlu mare pe telefon, mai ales la 320 px | UX-10; păstrarea secțiunilor clare și a provenienței OpenBread |
| Confidențialitate | B | Carduri fără padding lateral; titlu prea mare la lățime mică | UX-01 și UX-10 |
| Ghid GS1 Digital Link | B; comutările exemplului S | Tabelul comparativ are derulare locală; grilele se comprimă la tabletă | UX-11, UX-12; indiciu de derulare; două stări ale exemplului cu același cadru |
| Profil resolver | B | Coloană de lectură consecventă; titlu disproporționat pe telefon | UX-10; grupare clară a informației tehnice |
| Lista de legături a resolverului | B pentru demo; S pentru 300/404 | Legăturile încap; titlu foarte mare pe telefon; săgețile urmează lungimea textului | UX-10; icon și text cu țintă comună; săgeată pe aceeași axă dreaptă |
| Pâine demo — produs | B | QR și descărcare prea jos pe telefon; conturul cardului este bine încadrat | UX-13; QR înaintea explicațiilor detaliate |
| Pâine demo — lot | B; lot necunoscut S | Structură consecventă cu produsul; distincția lotului este mai ales în text | Selector Produs/Lot și marcaj de lot lângă titlu; avertizarea DEMO rămâne vizibilă |
| Fișa de scanare/tipărire | B ecran; S print | Carduri exterioare simetrice; rânduri interne și descărcări la înălțimi diferite | UX-18; probă de tipar separată |
| Produs/articol real `/p/{id}`, `/t/{id}` | S | Simbolul reduce spațiul titlului pe mobil; coloana laterală poate muta metadatele târziu | UX-16; probă cu nume lung și toate informațiile aprobate |
| Lot real `/b/{id}` | S | Datele specifice lotului trebuie prioritizate pe telefon | UX-16; test cu alertă, lot lung/codificat și versiune istorică |
| Recenzii, întrebări și propuneri publice | S, incluse în produs | Formular lung și consimțământ dens; carduri dependente de lungimea textului | UX-08, UX-12; acțiune principală clară și stări de moderare lizibile |
| Rețetă publică | S | JSON-ul domină pagina și cere derulare internă | UX-19; document lizibil cu anexe tehnice |

### Spațiul producătorului și integratori

| Pagină / stare | Acoperire | Încadrare și simetrie | Acțiune recomandată |
|---|---|---|---|
| Prezentare `/manage` | B, fără produse | Indicatorii sunt aliniați; listă scurtă lângă formular mai înalt; meniu pe trei rânduri pe telefon | UX-06, UX-14; prima utilizare cu un pas principal evident |
| Produs administrat | S | Articole și documente în coloane; rețetă/propuneri în secțiuni suplimentare | UX-07, UX-15; separarea zonelor, fără rețetă obligatorie |
| Articol comercial | S | Formular de etichetă lung lângă versiuni/loturi; multe subgrupuri | UX-07, UX-15; etape vizuale și rezumat al stării |
| Revizuire etichetă | S | Definiții verticale, apoi date structurate și publicare | UX-07, UX-15; acțiune de aprobare distinctă și previzualizare ușor de urmărit |
| Date structurate pentru consum | S, componentă inclusă | Două liste de alergeni și mai multe grile folosesc aceeași clasă ca layoutul paginii | Grile dedicate câmpurilor, axe comune ale inputurilor și grupuri nutriționale; UX-15 |
| Lot administrat | S | Două coloane pentru date/istoric și alerte; publicare/eliberare au greutate apropiată | UX-07, UX-16; stare și acțiune următoare explicite |
| QR generic / QR de lot | S | Card centrat, SVG cu max-width și două acțiuni; URL-uri variabile | Identitate Produs/Lot clară; acțiuni spațiate; păstrarea quiet zone și a adresei integrale |
| Acces API | B, liste goale; S cu chei existente | Coloana de creare domină; checkboxuri dense; navigarea nu marchează pagina | UX-06, UX-07, UX-12, UX-14; stări active/expirate/revocate distincte |
| Import | B fără raport; S cu raport | Formulare consecutive lipite; verificare și import la fel de dominante | UX-05; rezultate ca sumar lizibil, cu JSON extensibil |
| Feedback | B gol; S cu intrări/AI | Contextul paginii se bazează pe numele organizației; multe formulare când sunt intrări | UX-06, UX-08; statut și filtrare, copie publicabilă separată de original |
| Conectoare — listă și creare | B | Card gol înaintea formularului pe telefon; antet și navigare diferite de manage | UX-06 și UX-14 |
| Conector — mapări și test/import | S | Două formulare lungi și istoric JSON; rezultate pot dezechilibra mult coloanele | UX-15; grupuri Mapare → Verificare → Import și rezultat separat |
| Destinații resolver | S | Istoric și formular în coloane; multe opțiuni tehnice și stări de drepturi | UX-06, UX-15; informații de bază întâi, opțiuni avansate grupate |
| Aplicații și autorizări | B gol; S cu aplicații/granturi | Formular lung și permisiuni tehnice dense; două niveluri de liste | UX-06, UX-12, UX-14; descrieri românești alături de codurile scopes |
| Securitatea contului | B, fără trimiteri | Padding lateral pierdut și titlu foarte mare pe mobil | UX-01, UX-06, UX-10; efectul fiecărei acțiuni rămâne clar înaintea butonului |
| Consimțământ OAuth — organizație/familie | S | Numele aplicației este H1; permisiuni, resursă, Permite/Refuză și adresă de revenire | Probe cu nume/URI lungi; butoane spațiate și efect clar, fără mascarea Refuză |

### Administrare, autentificare și mesaje

| Pagină / stare | Acoperire | Observație și acțiune |
|---|---|---|
| Identitate și drepturi `/admin` | B, fără decizii trimise | UX-14, UX-17; mesaje explicite în listele fără cereri; ajutor pentru dovezi și grupare după stare |
| Actualizări `/admin/updates` | B, migrarea deja activă | UX-01, UX-02, UX-17; un singur cadru și titlu înaintea operațiunilor |
| Login | S în acest audit | Cardul de 490 px este o bază bună; 16 px în inputuri mobile și validare în context; fără a închide sesiunea existentă pentru audit |
| Înregistrare | S | Aceeași structură ca login, cu mai multe câmpuri; marcarea cerințelor și erorilor lângă câmp |
| Setup | S; ruta rămâne închisă pe producție | Același card; instrucțiuni clare pentru token și formular, fără redeschiderea instalării |
| 404 / mesaj comun | B pentru 404; S pentru celelalte rezultate | Centrare bună; UX-08 pentru revenire la sarcină, 403, sesiune expirată și erori de formular |
| Utilitar local de cheie | S | Încadrare simplă; ieșiri etichetate și copiere separată ar reduce confuzia. Generarea secretelor rămâne locală |

### Documentație API

| View / stare | Acoperire | Observație și acțiune |
|---|---|---|
| Prezentare și ghid ERP | B | Structură bună pe desktop; antetul ocupă mult pe telefon; UX-20 pentru cuprins local |
| Operație simplă de creare | B | UX-03 pentru tabele; exemplele și răspunsurile au cadre coerente |
| Operație cu titlu lung | B | Titlul se încadrează pe mai multe rânduri; trebuie păstrată această posibilitate |
| Operație de cont numai cu sesiune | B | Mesajul explică accesul; schema/formatele păstrează cadrul comun; UX-03, UX-20 |
| Sincronizare cu 114 câmpuri | B | Pagină foarte lungă, multe rânduri; UX-03 și UX-20 au cel mai mare efect aici |
| Copy Page / meniu deschis | B | UX-09; lățimea meniului încape, dar butoanele își pierd simetria la deschidere |
| Explorator extins | B, fără cereri trimise | Încadrare în lățime; UX-12 pentru câmpuri; separarea exemplelor de cereri reale trebuie păstrată |
| Câmpuri și răspunsuri extinse | B pentru mostre; S pentru toate variantele | Același component deservește toate operațiile; fixarea lui trebuie regresată pe cereri, răspunsuri și scheme imbricate |
| Restul paginilor API | S, inventariate individual în JSON | Beneficiază de remedierea componentelor comune; nu sunt declarate testate vizual individual |

### Acasă

| Stare | Acoperire | Observație și acțiune |
|---|---|---|
| Ce am — inventar gol | B | Cel mai simplu cadru din platformă: titlu, trei secțiuni și o acțiune principală; păstrat |
| De cumpărat — gol | B, la patru dimensiuni | Formular și listă în coloana aplicației; de testat și cu nume lung, bifate și tastatură virtuală |
| Terminate — gol | B, la patru dimensiuni | Încadrare simplă; mesajul trebuie să explice revenirea la alimentele disponibile |
| Preferințe / cont neconectat | B desktop și telefon | UX-04 și UX-12; dialog foarte lung și închidere care dispare |
| Inventar populat, alergeni, expirat, dată necunoscută | S | Numele are reguli de încadrare, iar cardul distinge stări; probe necesare pentru texte lungi și avertizări multiple |
| Detalii aliment și editor | S | Titluri și câmpuri variabile; închidere persistentă, axe comune și cantitate/unitate aliniate |
| Scanner, cod manual, fotografie/OCR | S; camera neactivată | Alternativa fără cameră să fie ușor de găsit; status în apropierea cadrului camerei, fără a împinge acțiunile în afara ecranului |
| Bon digital și confirmarea importului | S | Listă și acțiune finală; sumar, număr de produse și rezultat lizibil fără înghesuirea liniilor |
| Conectare prin link și invitație | S | Efectul conectării/alăturării și copierea alimentelor locale trebuie să fie clare; închidere mereu accesibilă |
| Familie, membri și dispozitive | S | Liste cu nume lungi, acțiuni de retragere și roluri; grupuri independente, fără amestecarea lor cu alergeni |
| Conflicte, offline, notificări și rezultate OCR | S | Stările au nevoie de mesaj și acțiune principală simple; nu sunt validate doar prin aspectul inventarului gol |

## 4. Reguli comune propuse pentru încadrare și simetrie

Acestea sunt ținte pentru implementare și verificare, nu schimbări deja aplicate:

| Element | Regulă propusă |
|---|---|
| Cadru | Trei familii: pagini comerciale, pagini de lucru, pagini de lectură. Fiecare are un singur container principal și o axă de titlu |
| Lățimi | Cadru larg de aproximativ 1200–1240 px; lectură 760–850 px; autentificare 460–500 px. Lățimea se alege după sarcină și rămâne consecventă în familie |
| Gutter | 20–24 px pe telefon și 32 px pe ecrane largi; aceeași margine pentru titlu, navigare și conținutul aceleiași pagini |
| Carduri | Padding comun 20–24 px mobil / 28 px desktop; copiii cardului folosesc aceeași axă interioară |
| Spațiere | O scară mică, de exemplu 4/8/12/16/24/32/48; distincție între spațiul din grup și spațiul dintre sarcini |
| Formulare | Câmpuri pe o coloană pe telefon; două coloane numai pentru câmpuri scurte și legate între ele; checkboxul se aliniază cu primul rând al textului |
| Acțiuni | O acțiune principală pe grup; distanță de minimum 12 px între acțiuni independente; priorități vizuale diferite pentru verificare și execuție |
| Grile | Praguri determinate de lățimea minimă utilă a conținutului; carduri cu același rol au structură internă comună, fără înălțimi fixe care taie texte |
| Titluri | Scări distincte marketing/lucru/lectură; numele și avertizările pot crește pe mai multe rânduri |
| Ferestre | Antet și închidere accesibile; derulare controlată a conținutului; fără acțiuni acoperite de footer sau tastatură |
| Date tehnice | URL, GTIN, lot și cod copiable integral; blocurile tehnice pot derula local, fără a deforma pagina |

Asimetria intenționată a unui hero sau a unei coloane de lucru poate rămâne. Simetria urmărită înseamnă margini, ritm și relații previzibile între elemente. Nu cere ca un formular și o listă goală să aibă artificial aceeași înălțime.

La verificarea reflow-ului, tabelele și unele alte suprafețe care au nevoie de două dimensiuni pot avea derulare locală; conținutul obișnuit trebuie să rămână utilizabil la o lățime echivalentă cu 320 CSS px. Aceasta este baza alegerii probei înguste, fără a declara certificare WCAG pe baza prezentului audit. [Explicația oficială W3C pentru Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html).

## 5. Ordinea recomandată de remediere

1. **Corecții comune mici:** UX-01, UX-02, UX-05 și UX-09. Rezolvă paddingul cardurilor, cadrul actualizărilor, spațiul dintre formulare și meniul API, cu efect mare și risc redus.
2. **Interfețe folosite pe telefon:** UX-03, UX-04, UX-10, UX-12 și UX-20. Tabele lizibile, dialoguri ușor de închis, titluri și câmpuri încadrate.
3. **Orientare și revenire la sarcină:** UX-06, UX-07 și UX-08. Navigare comună, acțiuni potrivite rolului și validare în context.
4. **Pagini comerciale și prima utilizare:** UX-11, UX-13 și UX-14. Hero la tabletă, acțiune principală vizibilă, QR mai sus și stări goale consecvente.
5. **Stări populate și pagini tehnice:** UX-15, UX-16, UX-17, UX-18 și UX-19. Verificare cu date demonstrative în mediul de test, apoi îmbunătățiri pentru etichete, loturi, resolver, conectori, rețetă și tipărire.

Prima etapă poate păstra arhitectura PHP MVC, Bootstrap și JavaScript simplu. Sunt suficiente componente comune și reguli CSS delimitate. Auditul nu justifică adăugarea unui framework de interfață.

## 6. Condiții de acceptare pentru implementarea ulterioară

- Rutele, numele câmpurilor, CSRF, scopes, aprobările și contractele API rămân compatibile.
- Nu se modifică istoricul produselor/loturilor, prioritizarea alertelor, drepturile documentelor sau caracterul experimental al pilotului.
- Niciun scroll orizontal al paginii pentru text obișnuit; derularea locală a tabelelor/codului este intenționată și vizibilă.
- Titlul, breadcrumbul, navigarea și cardurile urmează aceeași axă în fiecare familie de pagini.
- Paddingul cardurilor și distanța dintre acțiuni sunt identice în stările goale și populate.
- Meniurile deschise nu măresc accidental butoanele sau mută conținutul. Dialogurile rămân ușor de închis după derulare.
- Fiecare secțiune de lucru are pagina activă marcată și o acțiune principală potrivită rolului.
- Se probează cel puțin: nume de produs de 180 de caractere, titlu de document lung, lot cu caractere codificate, URL de destinație lung, liste goale/1/mai multe intrări și toate stările de eroare relevante.
- Pentru Acasă: aliment expirat, dată necunoscută, alergeni multipli, denumire lungă, cantitate/unitate, bon cu multe linii, offline și conflict. Datele sintetice se folosesc în mediul de test, fără alerte ori informații de sănătate inventate în conturi reale.
- Verificare manuală cu tastatura, zoom și două dispozitive reale, inclusiv tastatura virtuală și orientarea orizontală; probă separată de tipărire. Auditul prezent nu pretinde aceste verificări.
- Se repetă probele responsive și verificările de flux relevante după schimbări. Nu este suficient un număr automat de teste pentru a declara interfața perfectă.

## 7. Fișiere vizate la implementare

- `app/Views/layout.php` și o componentă comună nouă pentru navigarea spațiului producătorului.
- `public/assets/app.css`: selectori pentru lectură/card, titluri, formulare consecutive, breakpointuri și componente details.
- `app/Views/updates.php`, `account-security.php`, `privacy.php`, `manage.php`, `connectors.php`, `developers.php`, `admin.php`.
- `public/assets/api-docs.css`, `app/Views/api-fields.php`, `api-reference.php`, `api-docs.php`; generatorul documentației numai dacă apar metadate de grupare, fără modificarea adreselor publice.
- `public/assets/home/home.css`, `app/Views/consumer-home.php` și modulele Acasă pentru gruparea setărilor și gestionarea dialogurilor.
- `app/Views/home.php`, `digital-link-guide.php`, `demo-bread.php`, `demo-bread-print.php`, `product.php` și `recipe.php`, în ordinea priorităților de mai sus.
- Controlerele HTML și gestionarea erorilor, strict pentru reafișarea formularelor în context; politica de autorizare și erorile API se păstrează.

Aceste fișiere sunt enumerate ca ținte de remediere. În această etapă au fost adăugate numai analiza, inventarul, dovezile selectate și legătura din indexul documentației.
