# OpenAliment — plan complet de implementare

Plan OPENALIMENT-FULL-2026-10-24 · 24 octombrie 2026 · Europe/Bucharest  
Stare: plan de execuție propus; funcțiile noi descrise aici nu sunt declarate implementate.

## Precizare pentru execuția începută la 24 octombrie 2026

Proprietarul a autorizat simulatoare ERP și magazin, cu arhitectură flexibilă de conectoare/agenți și mapări per partener. Pentru această execuție, obligația de probă pe un ERP și retailer concret din WP13 poate fi demonstrată prin simulatoare declarate; nu se afirmă integrare reală. Probele fizice POS rămân distincte. Nu există încă conturi Apple/Google; distribuția publică rămâne dependentă de ele. Emailul se va configura prin SMTP cu o bibliotecă PHP și contul creat în cPanel de proprietar. Această precizare prevalează punctual asupra cerințelor de partener real de mai jos. [Registrul de execuție](implementation/EXECUTION_2026-10-24.md) păstrează starea și dovezile.

## 1. Rezultatul care trebuie livrat

OpenAliment trebuie să funcționeze ca platformă independentă pentru producători și ca aplicație foarte simplă pentru oamenii care cumpără și consumă alimente. La finalul acestui plan, un producător poate administra produse, loturi, informații aprobate, QR, resolver și integrări ERP; un client poate adăuga cumpărăturile, vedea ce trebuie folosit întâi, primi avertizări relevante și folosi aceeași gospodărie pe mai multe dispozitive. Documentația deschisă, evaluarea pentru siglă, feedbackul și analiza AI au fluxuri complete și independente.

Planul include toate aceste componente până la publicare, probe reale, operare și închidere. Ordinea de construcție nu elimină funcții din livrarea finală. O funcție poate fi facultativă pentru utilizator — de exemplu contul, AI sau recomandările — dar construcția, testarea și documentarea ei sunt incluse.

Livrarea finală cuprinde aplicația web/PWA, aplicațiile Android și iPhone publicate, API documentat, conectoare reale pilot, date portabile, domenii ale producătorilor, mecanismul de evaluare, suport și operare verificată. Un prototip, un simulator ERP, un fișier de aplicație nepublicat sau un export care nu poate fi reimportat nu închid cerința corespunzătoare.

### Documentele de execuție

- Acest document stabilește fluxurile, arhitectura, ordinea, livrabilele și condițiile de închidere.
- [Backlogul structurat](plans/openaliment-backlog-2026-10-24.json) păstrează cele 28 de pachete, 56 de cerințe, responsabilități și dependențe.
- [Matricea de acceptare](plans/OPENALIMENT_ACCEPTANCE_MATRIX_2026-10-24.md) definește probele care trebuie executate și dovezile necesare.
- [Specificația activă](OPENALIMENT_MASTER.md), [schimbarea de direcție](../OPENALIMENT_CHANGE_REQUEST_01.md), [nivelul GS1](GS1_IMPLEMENTATION.md), [API](API.md) și [import/export](IMPORT_EXPORT.md) rămân baza implementării existente. Contractele noi se introduc prin versiuni compatibile și actualizări punctuale.

Pentru extensiile descrise aici, acest plan completează specificația activă. Nu modifică retroactiv starea codului existent, nu acordă drepturi asupra materialelor istorice și nu înlocuiește aprobările alimentare sau evaluarea independentă.

## 2. Baza inspectată și lucrurile care trebuie terminate

Există deja o aplicație PHP MVC, nu doar arhiva OpenBread. Sunt prezente produsul/articolul/versiunea etichetei/lotul, rețeta opțională, aprobările, paginile publice, API, import/export, feedback și o fundație pentru AI. Resolverul are implementare și probe documentate pentru profilul GTIN (01). Identitatea OpenAliment, landingul, ghidul GS1 și exemplul Pâine Bună Tehnic există. Se păstrează rutele și datele acestora.

Verificările istorice din proiect sunt dovezi ale versiunilor la care se referă. Nu țin loc de probe pentru aplicațiile mobile, inventar, bonuri sau alte funcții noi.

| Observație din cod și mediu | Consecință în plan |
|---|---|
| Eticheta păstrează în prezent mai multe informații alimentare ca text liber | Adăugăm câmpuri structurate și proveniență în paralel; API v1 nu își schimbă tipurile pe ascuns. |
| Lotul poate exista deja fără rețetă | Consolidăm fluxul și regresia; nu reintroducem dependența obligatorie. |
| Conturile existente sunt pentru producători, cu organizații și roluri | Construim identitate separată pentru consumatori; crearea unui cont personal nu creează o organizație și nu acordă rol administrativ. |
| Sesiunea web actuală nu oferă mecanismul de dispozitiv persistent cerut | Implementăm acces persistent, recuperare și revocare, cu un regim distinct pentru operațiunile administrative sensibile. |
| Politica web actuală blochează camera și microfonul | Activăm strict permisiunile necesare pe suprafața consumatorului; testăm CSP și Permissions-Policy fără relaxarea globală a portalului. |
| Workerul AI actual este orientat spre CLI | Verificăm programarea reală disponibilă în cPanel și livrăm cozi executabile automat în limitele hostingului. |
| Nu există încă aplicație de inventar, sincronizare sau distribuție mobilă | Acestea sunt construcții noi, cu propriile contracte și probe. |
| Hostingul verificat are PHP 8.4.27, MariaDB 10.6.28 și document root /home/apelero/openaliment.ro/public/ | Păstrăm operarea fără root/shell pe server și măsurăm separat capacitatea efectivă. |

Arhiva originală OpenBread rămâne neschimbată. Rețeta R1, cercetarea, COST1/COST2/COST3, fișierele originale și proveniența recuperărilor se păstrează. BT01 deteriorat nu devine dovadă validată. PBT rămâne pilotul categoriei pâine; OpenBread poate rămâne inițiativa de panificație.

Imaginea de pe landing aleasă de proprietar se păstrează împreună cu [proveniența ei](images/PBT_CHECKOUT_SELECTED_2026-10-24.md). Este un concept ilustrativ. QR-ul din fotografie nu este proba tehnică de scanare. Pentru demonstrații se folosesc QR-urile reale din [exemplul PBT](updates/PBT_DEMO_QR_2026-10-24.md): GTIN demonstrativ 9520000000011 / 09520000000011, lot DEMO-PBT-01, izolate de produsele comerciale.

## 3. Experiența clientului: puține gesturi, fără instruire

Obiectivul este ca omul să recunoască imediat următorul gest. Nu cerem să învețe concepte precum GTIN, resolver, tenant, sincronizare sau versiune de etichetă. Acestea rămân în infrastructură și documentația pentru producători.

### Ecranul principal

Întrebarea principală este „Ce folosesc întâi?”. Se afișează fotografii, denumiri scurte și un mesaj util: „Folosește azi”, „Deschis ieri”, „Data nu este cunoscută”. O retragere relevantă are prioritate vizibilă. Culorile sunt însoțite de text și simbol; nu sunt singurul semnal.

Acțiunile de bază sunt „Adaugă”, „Am deschis” și „Am terminat”. După o acțiune se afișează rezultatul și posibilitatea „Anulează”. Opțiunile rare se găsesc într-un detaliu secundar. Frigiderul, cămara și congelatorul sunt locuri de păstrare, fără formular obligatoriu la fiecare produs.

| Situație | Flux urmărit | Buget de interacțiuni |
|---|---|---|
| Prima adăugare | Adaugă → cameră → scanare → salvat | Fără cont sau instalare înainte de salvare; permisiunea camerei apare când este necesară. |
| Adăugări repetate | Cameră deschisă → scanare → următorul produs | Fără confirmare pentru identificări neambigue; feedback vizual/haptic și Anulează. |
| Cod fără termen | Produs salvat → „Fotografiază data” | O acțiune pentru fotografie; cel mult o confirmare contextuală dacă data este ambiguă. |
| Produs fără cod sau nerecunoscut | Fotografie sau voce → element personal salvat | Fără obligația de a scrie; informațiile lipsă rămân necunoscute. |
| Deschiderea unui ambalaj | Am deschis | Un gest; data se înregistrează automat și se poate corecta. |
| Terminarea unui ambalaj | Am terminat | Un gest; Anulează disponibil, notificările viitoare se opresc. |
| Bon cu potriviri clare | Fotografie/share/QR → sumar → Adaugă cumpărăturile | O confirmare pentru întregul bon; doar liniile neclare cer intervenție. |
| Folosire în familie | Invitație QR/link → acceptare | Rol implicit limitat; fără configurarea manuală a unui server sau a unui cod lung. |
| Scanare cu camera telefonului, la raft | Pagină publică informativă → Adaugă acasă | Salvarea în inventar cere intenția explicită a persoanei. |
| Revenire în aplicație | Deschidere → inventar | Fără ecran de login în utilizarea obișnuită pe un dispozitiv autorizat. |

O scanare nu dovedește cumpărarea sau deținerea produsului. Nu introducem automat în frigider orice produs consultat la magazin.

### Prima utilizare și identitatea

Prima salvare funcționează ca vizitator, local. După ce există valoare, oferim opțional „Păstrează și pe alte telefoane”. Metoda preferată este passkey când dispozitivul o suportă, cu magic link ca alternativă accesibilă; Apple/Google sunt opțiuni suplimentare, nu condiții pentru folosire. Nu cerem o parolă nouă pentru fluxul de bază. Implementarea passkey urmează [WebAuthn](https://www.w3.org/TR/webauthn-3/).

La conectare, inventarul vizitatorului este unit tranzacțional cu cel existent, cu deduplicare și posibilitatea de a vedea ce s-a unit. Linkurile de autentificare au durată limitată, utilizare unică și nu sunt consumate de simpla preîncărcare a unui scanner de email. Dispozitivele persistente folosesc tokenuri rotite, hash-uite pe server, revocabile; linkurile de recuperare nu rămân în URL, loguri sau analytics.

„Rămân conectat” înseamnă acces continuu pe dispozitivul autorizat. Ștergerea stocării, dezinstalarea, revocarea sau pierderea dispozitivului cer recuperare; aplicația explică simplu riscul înainte ca utilizatorul să depindă exclusiv de salvarea locală. Administratorii și schimbările sensibile cer reautentificare.

### Testarea simplității

Prototipul se verifică înaintea construcției și apoi pe aplicația reală. Participă minimum 20 de persoane în runda finală, incluzând vârste diferite, experiență digitală redusă, dificultăți de citire și utilizatori de tehnologii asistive. Nu se oferă instruire înaintea sarcinilor.

Ținte de acceptare: minimum 90% identifică următoarea acțiune în trei secunde pe ecranele de bază; minimum 90% finalizează fără ajutor adăugarea unui produs cu date complete, deschiderea și terminarea. Cele trei secunde măsoară înțelegerea acțiunii, nu întregul proces de acordare a permisiunii sau citire OCR. Se măsoară separat timpul, greșelile și abandonul pentru prima utilizare, scanările repetate și bonuri. Nereușitele conduc la revizie și retestare în WP24.

Interfața folosește cuvinte uzuale, contrast, zone tactile mari, text redimensionabil, focus vizibil și citire asistată. [Recomandările W3C pentru cuvinte clare](https://www.w3.org/WAI/WCAG2/supplemental/patterns/o3p01-clear-words/) și [autentificare accesibilă](https://www.w3.org/WAI/WCAG22/Understanding/accessible-authentication-minimum.html) sunt parte din verificare.

## 4. Arhitectura de livrat

### Platformă și clienți

Serverul rămâne PHP MVC simplu, PDO cu interogări pregătite, MySQL/MariaDB utf8mb4, Bootstrap local și JavaScript simplu. Publicul rămâne în română, cu fusul Europe/Bucharest. Rutele publicate și API v1 se păstrează. Pagina QR se servește fără serviciul AI, fără apeluri externe obligatorii și fără sesiune creată pentru orice vizitator anonim.

Construim o suprafață web dedicată consumatorului, propusă la /acasa, cu intrare din pagina produsului. PWA oferă prima utilizare imediată, instalare facultativă, stocare locală și sincronizare. Aplicațiile Android și iPhone se construiesc cu Flutter, cu adaptoare native pentru cameră, OCR, stocare sigură, linkuri și notificări. Alegerea se validează printr-o probă tehnică pe telefoane reale în WP00/WP08; eventualele limite ale scanării se rezolvă în același pachet, fără eliminarea funcției.

[Flutter publică platformele suportate](https://docs.flutter.dev/reference/supported-platforms); matricea minimă de versiuni și dispozitive se fixează la început și se reverifică înaintea publicării. Nu promitem funcționare pe orice telefon vechi. Buildurile iOS se fac pe macOS/CI autorizat, în afara shared hostingului.

Regulile se definesc în contracte și seturi comune de exemple. Serverul este autoritatea datelor sincronizate; clienții pot calcula offline numai reguli deterministe versionate. Nu expunem regulile doar ca prompt AI.

### Dependențe și construcție

Nu cerem Composer, Node sau shell pe hosting. Dependențele PHP indispensabile se livrează deja pregătite, cu versiuni fixate, licențe și inventar; nu reimplementăm criptografia, WebAuthn, Web Push sau semnarea tokenurilor pentru a economisi un pachet. Alegem biblioteci mici și întreținute, limitate la funcția necesară. Pentru email/PDF se adaugă biblioteca justificată după verificare.

Webul păstrează SVG pentru iconuri și QR. Sistemele mobile pot cere PNG pentru iconul aplicației sau materialele magazinelor; acestea se exportă din sursa vectorială în formatele obligatorii. Scanarea și OCR folosesc componente auditate, cu licențe și formate efectiv verificate. [ML Kit](https://developers.google.com/ml-kit/vision/barcode-scanning) poate citi offline coduri 1D/2D, dar suportul fiecărei simbologii relevante se probează, inclusiv GS1 DataMatrix/DataBar când intră în profilul declarat.

### Contracte noi și migrare

| Contract/entitate | Conținut și regulă |
|---|---|
| product-label v2 | Date alimentare structurate, versiune aprobată, unități, jurisdicție, surse și câmpurile text originale. |
| consumer-product-profile v1 | Proiecție publicabilă normalizată pentru aplicații; capabilități și starea fiecărei informații. Nu exportă rețeta privată. |
| household / membership | Gospodărie separată de organizația producătorului; roluri owner/member/limited, invitații revocabile. |
| inventory-item / package | Ambalaj deținut de consumator, referință la articol/lot când sunt cunoscute, cantitate, loc, sursă, date observate și stare. |
| inventory-event / sync v1 | Adăugare, deschidere, mutare, congelare, consum, corecție și anulare, cu identificator de operațiune și control de concurență. |
| dietary-preference | Preferințe și alergii opționale, private, cu proveniență și consimțământ când este necesar; pot rămâne exclusiv local. |
| recall / safety-notice | Sursă autorizată, produs/lot/interval afectat, urgență, perioadă, actualizări și starea curentă. |
| receipt v1 | Emitent, identificator bon, articole/SKU/GTIN dacă există, cantitate/unitate, preț separat, sursă și câmpuri facultative de lot/termen. |
| external-source-record | Identitatea sursei, versiune, licență, piață, data preluării, încredere pe câmp și conflicte. |
| dossier / review / credential | Dosar înghețat, standard/profil versionat, probe, evaluatori, decizii și registru de sigle. |
| notification / delivery | Motiv, destinatar autorizat, canal, program, încercări, stare și deduplicare; acceptarea furnizorului nu dovedește citirea. |

Un ambalaj personal nu este același lucru cu lotul producătorului. Două cutii din același lot pot fi deschise în zile diferite și păstrate în locuri diferite. Cantitățile pot fi număr de ambalaje, masă sau volum; aplicația nu obligă persoana să cântărească fiecare consum.

Câmpurile necunoscute se reprezintă distinct de zero, șir gol sau „nu conține”. Data păstrează tipul, precizia (zi/lună/an), valoarea originală și sursa. Nu inventăm ziua lipsă. Datele calendaristice alimentare nu se deplasează prin conversie UTC; momentele evenimentelor se păstrează cu o convenție unică și se afișează local.

Migrările sunt aditive, cu dry-run, raport, backup, verificare și plan de revenire. Textul liber existent rămâne disponibil. Parsarea lui produce propuneri, nu adevăr aprobat. API v2 și contractele noi au versiuni explicite, exemple și teste de regresie; v1 continuă să răspundă corect. Câmpurile vechi pot fi retrase numai printr-o procedură documentată de compatibilitate, nu ca efect accidental al noului cod.

## 5. Scanare, identificare și informații alimentare

### Ce înseamnă compatibilitate GS1

Se păstrează separarea dintre sintaxa unui URL GS1 Digital Link și funcțiile resolverului. Baza normativă verificată este [URI Syntax 1.7.0](https://ref.gs1.org/standards/digital-link/uri-syntax/1.7.0/) și [Resolver 1.2.1](https://ref.gs1.org/standards/resolver/1.2.1/). Profilul implementat și testat rămâne declarat în GS1_IMPLEMENTATION.md; un profil GTIN complet nu înseamnă automat suport pentru orice cheie logistică GS1.

Se conservă și se retestează negocierea resurselor, linkseturile, relațiile, versiunea destinațiilor, decompresia, erorile și transportul caracterelor. Nu redenumim o redirecționare simplă „resolver complet”. Identificatorul numeric valid, dreptul organizației de a-l administra și aprobarea datelor sunt trei verificări diferite.

Aplicația citește EAN/UPC, QR nativ OpenAliment, GS1 Digital Link și codurile GS1 2D relevante profilului. Codurile proprii de magazin sau cele cu greutate variabilă cer mapări explicite; nu sunt tratate automat ca GTIN globale. Scanarea unui cod EAN obișnuit nu oferă singură lotul sau termenul.

Pentru Digital Link se interpretează corect GTIN, lot, serie și datele GS1 disponibile, inclusiv 15/17, cu validarea regulilor standardului și a caracterelor. Informația citită dintr-un QR necunoscut rămâne informație observată din ambalaj, nu înlocuiește automat o etichetă aprobată. Codurile suprapuse sau deteriorate nu produc valori inventate.

### Rezolvarea produselor externe

Ordinea de lucru este: validare locală a codului → identificare internă dacă există → resolverul indicat/alte resurse standard declarate → surse publice licențiate → element personal cu date necunoscute. Eșecurile parțiale nu blochează salvarea ambalajului.

La livrarea finală se probează OpenAliment, cel puțin două implementări externe independente de resolver și Open Food Facts, cu produse reale autorizate unde este necesar. Relațiile standard facilitează descoperirea; nu garantăm că fiecare resolver oferă aceeași schemă nutrițională sau o bază completă de produse.

Open Food Facts este o sursă colaborativă, cu [API și limite proprii](https://openfoodfacts.github.io/openfoodfacts-server/api/) și [condiții de reutilizare](https://support.openfoodfacts.org/help/en-gb/12-donnees-api/94-y-a-t-il-des-conditions-pour-utiliser-l-api). Alegerea endpointurilor, cacheului și eventualelor extrase locale se face după verificarea versiunii curente, limitelor și licențelor. Licențele pentru date, conținut și imagini se urmăresc separat; nu presupunem că separarea în tabele anulează obligațiile unei baze derivate.

Sursele sunt comparate pe câmp, piață și versiune. Datele aprobate de producător, datele colaborative, observațiile utilizatorului și estimările sunt diferențiate. Un conflict nu se rezolvă prin înlocuirea tăcută a unui fapt. Se păstrează textul original și dovada.

Camera aplicației nu deschide automat site-uri arbitrare. Fetchul serverului blochează adresele locale/private, schemele periculoase, redirecționările abuzive și DNS rebinding; are limite de timp, dimensiune și tip de conținut. Nu trimite identitatea, alergiile sau istoricul persoanei furnizorului extern.

### Termene, alergeni și diabet

- „A se consuma până la” și „A se consuma de preferință înainte de” au semnificații diferite; aplicația păstrează diferența și sursa, conform [explicației EFSA](https://www.efsa.europa.eu/enIE/safe2eat/food-date-labelling). Un termen nu dovedește singur respectarea lanțului frigorific.
- Pentru un produs fără dată cunoscută, oferim fotografia datei și un memento ales de persoană. Mementoul nu devine termen de valabilitate. OCR incert cere o singură confirmare contextuală și păstrează necunoscut dacă omul nu confirmă.
- După deschidere se aplică instrucțiunile aprobate și condițiile lor. Nu se prelungește termenul de siguranță. Congelarea înregistrează un eveniment; nu inventează automat o nouă dată.
- Alergenii au stări distincte: declarați, posibile urme și informație necunoscută. „Nu apare în baza noastră” nu devine „fără alergen”. Ingredientele compuse și versiunea etichetei se păstrează.
- Pentru persoanele cu diabet, prezentăm carbohidrații și cantitatea efectivă a porției, cu unități și baza de calcul. Zahărul nu este singurul indicator. [Diabetes UK](https://www.diabetes.org.uk/living-with-diabetes/eating/food-shopping-for-diabetes/understanding-food-labels) explică citirea etichetei și a porției. Nu calculăm doze de insulină și nu inventăm indice glicemic sau verdicte medicale.
- Nutriția per 100 g/per 100 ml/per porție și „ca atare”/„preparat” nu se amestecă. Regimurile de declarare, inclusiv diferențele privind fibrele și carbohidrații, se normalizează explicit; referința UE este [Regulamentul 1169/2011](https://eur-lex.europa.eu/legal-content/en/TXT/PDF/?uri=CELEX%3A32011R1169).

Regulile alimentare și mesajele se verifică de specialiști înaintea utilizării reale. AI poate explica o sursă disponibilă, dar nu transformă absența datelor în garanție de siguranță.

### Alerte și funcționare offline

Inventarul păstrează informațiile aplicabile ambalajului și istoricul modificărilor. Retragerile și alertele curente se suprapun peste acel istoric și rămân vizibile pentru loturile vechi. Dacă lotul persoanei nu este cunoscut, afișăm posibilitatea afectării și cerem verificarea lotului; nu declarăm automat că ambalajul este afectat sau sigur.

Offline se pot consulta datele locale și efectua operațiunile de bază, cu o coadă de sincronizare. Ultima verificare a alertelor este vizibilă. Lipsa conexiunii nu poate produce mesajul „nu există retrageri” ca rezultat actual. Cacheul PWA nu servește o pagină veche de alertă drept informație proaspătă.

## 6. Bonuri, ERP, notificări și ajutor la consum

Bonul fotografiat se citește local unde este posibil, se elimină liniile nealimentare și se potrivesc articolele pe nume, SKU, comerciant și dovezi. Discounturile, retururile, ambalajele și liniile cântărite au reguli proprii. Un bon fără GTIN, lot sau termen nu oferă implicit aceste informații. Numai potrivirile neclare cer atenție; confirmarea clară este pentru întregul coș, cu anulare.

Se livrează un contract public receipt v1 și API pentru bon digital, autentificarea emitentului, idempotentă, corectări și un QR/link de bon care nu expune cumpărăturile oricui îl ghicește. Nu prezentăm contractul nostru drept standard universal GS1 pentru bonuri. Accesul la bon și importul în gospodărie sunt autorizate distinct. Datele de plată și cele personale inutile nu intră în inventar.

ERP-ul poate crea/actualiza articole, propune etichete, opera loturi și destinații, consulta QR și exporta date în limitele mandatului și scopeurilor sale. Fluxul păstrează aprobarea sau o politică de aprobare explicită a organizației; un token de integrare nu primește implicit toate rolurile. Livrăm exemple executabile, mapări, webhooks semnate, retry, replay protection, idempotentă și procedură de reconciliere. Închiderea include un ERP real și un magazin real pilot, nu numai servere simulate.

Notificările folosesc alarme locale pentru datele cunoscute și canale server pentru schimbări, gospodării și retrageri: APNs/FCM, Web Push și email facultativ. Alegerea canalului, sumarul zilnic, orele liniștite și textul de pe ecranul blocat sunt controlabile. Produsele consumate nu mai generează mementouri. Alertele urgente relevante nu sunt amestecate cu recomandările comerciale.

Permisiunea se cere după ce beneficiul poate fi înțeles. [Android 13+ cere permisiune pentru notificări](https://developer.android.com/develop/ui/compose/notifications/notification-permission); [Web Push pe iPhone](https://developer.apple.com/documentation/usernotifications/sending-web-push-notifications-in-web-apps-and-browsers) are condiții de instalare/permisiune specifice. Refuzul nu blochează inventarul. Stările programat, trimis, acceptat de furnizor și confirmat se disting; livrarea sau citirea nu este garantată.

Lista de cumpărături, recumpărarea facultativă, sugestiile de folosire a produselor și alternativele comparabile sunt incluse în WP14. Se preferă date și rețete cu drepturi clare. Nu recomandăm produse retrase ori ingrediente incompatibile cunoscute; când informația este insuficientă, explicăm limita. Aplicația nu comandă și nu plătește automat produse în numele persoanei.
## 7. Finalizarea modulelor producătorilor, documentației și evaluării

### A. Produs, lot, QR și resolver

Serviciul este complet utilizabil pentru orice categorie alimentară, fără rețetă și fără siglă. Se verifică separat organizația, reprezentarea ei și mandatul pentru identificator. „Dovezi pentru identitate și reprezentare” înseamnă documente sau verificări care leagă persoana de organizația în numele căreia operează; nu înseamnă publicarea lor și nu dovedește calitatea alimentului. Pentru GTIN este necesară în plus dovada dreptului de administrare. Interfața explică ce se cere, de ce, cine vede dovada și cât timp se păstrează.

Informațiile aprobate, articolul, rețeta opțională și lotul rămân entități distincte. Corecțiile sunt versiuni noi, alertele au stare și istoric, destinațiile resolverului pot fi actualizate fără schimbarea identității imprimate. Există verificare și aprobare pentru publicare, retragere și administrarea linkurilor. Un import sau un token API nu poate ocoli aceste reguli.

Fluxul de referință obligatoriu este un aliment din afara panificației: organizație autorizată → articol/GTIN → informații aprobate → lot fără rețetă → QR de produs și QR de lot distincte → pagină corectă, cu AI oprit → actualizare ulterioară fără rescrierea lotului vechi.

### B. Documentație alimentară deschisă

WP16 finalizează editorul dosarului, nu doar încărcarea unor fișiere. Include formulă cantitativă, ingrediente compuse, bilanț, scalare, specificații ale materiilor prime, furnizori, recepție, echipamente, proces, parametri, igienă și alergeni, loturi efectiv executate, randament, pierderi, masă, ambalare, păstrare, cost, timp, energie, încercări, rezultate, eșecuri, fotografii, surse și versiuni.

Se păstrează unități, precizia măsurării și distincția calcul/estimare/observație/rezultat validat. Costurile pot avea scenarii, prețuri și momente diferite; estimarea nu devine cost real de producție. Afirmațiile publice au sursă, stare și aprobare, inclusiv afirmațiile de impact sau de siguranță.

Publicarea este pe versiune, cu licență explicită, atribuiri și verificarea drepturilor fiecărei componente. Se oferă comparație între versiuni, export reproductibil și legătura documentelor cu probele. Fișierele private rămân private chiar dacă produsul este public.

Profilul de panificație păstrează hidratarea, prefermentul, dospirea și masa aluatului. Se livrează mecanismul pentru profiluri și profiluri inițiale pentru panificație, lactate, băuturi și produse vegetale/de cămară, revizuite de specialiști. Câmpurile tehnice și criteriile de evaluare se adoptă explicit; numele unei categorii nu produce un standard verificat. Lipsa unui profil de evaluare nu blochează serviciul A pentru acea categorie.

PBT R1 rămâne experimental până există dovezi reale. Softwareul finalizat nu înseamnă că cercetarea alimentară a produsului a fost terminată sau că drepturile neconfirmate au devenit confirmate. Originalele și recuperările se păstrează cu hash, proveniență și avertizările lor.

### C. Evaluarea și registrul siglei

WP17 livrează standarde/profiluri versionate, depunere, dosar înghețat, verificarea eligibilității evaluatorului, conflict de interese, solicitări de completare, decizie, emitere, registru public, SVG/PDF, suspendare, revocare, expirare, înlocuire și contestație. Publicul poate vedea exact ce documentație, ce versiune și ce criterii au fost evaluate.

Pentru OB-STD 0.1 se păstrează toate OB-01–OB-24. Cerințele nu pot fi trecute integral „nu se aplică” pentru a obține eligibilitatea; eventualele excepții la câmpuri au motiv și aprobare conform standardului. Stările depunerii și reviziei sunt separate. OB-01–OB-23 trebuie să fie trecute, fără probleme blocante, cu drepturi și accesibilitate confirmate, înainte de emitere.

În aceeași tranzacție se validează decizia, se marchează OB-24 și se creează acreditarea. Nu se cere existența siglei înaintea propriei emiteri. Autorul nu își aprobă dosarul, iar administrarea serverului nu conferă competență alimentară. Pentru un pilot asociat MOPAN se declară interesele și se asigură revizie independentă de promovarea produsului.

Acreditarea identifică emitentul real, standardul, profilul, producătorul, produsul și versiunile evaluate, hashul dosarului, domeniul verificării, persoanele responsabile, datele și starea. O rețetă schimbată, un alt producător sau o copie a dosarului nu moștenește automat sigla. Contestația este tratată de alt evaluator eligibil.

Numirea emitentului, adoptarea standardului și desemnarea evaluatorilor sunt condiții de lansare pentru serviciul C. Fără acestea, interfața nu poate emite acreditări reale. Planul include rezolvarea acestei condiții prin E09; nu o lasă ca o funcție fără responsabil.

Sigla verifică documentația în domeniul declarat. Nu este prezentată drept autorizație sanitară, certificare bio, certificare GS1 sau aprobare medicală. Încadrarea juridică a schemei și afirmațiile comerciale sunt revizuite înainte de lansare. Probele pozitive folosesc dosare eligibile și date clar de test; nu se acordă PBT o siglă nemeritată pentru a demonstra interfața.

### Feedback, căutare și AI

Se finalizează recenziile, întrebările, răspunsurile, voturile, sesizările și propunerile, cu legătura la produs și la lot când este cunoscut. Moderarea, contestarea și auditul nu ascund criticile doar pentru că sunt negative. Scanarea nu justifică eticheta „achiziție verificată”. Propunerile au istoric și decizie; numai o aprobare explicită creează o nouă versiune a datelor.

Căutarea indexează numai conținutul permis, cu filtre pe organizație, gospodărie, vizibilitate și versiune. Se începe cu indexare/chunkuri și MySQL unde acoperă cerința; un serviciu vectorial se introduce numai dacă proba tehnică îl justifică. ACL se aplică înainte de recuperarea contextului și se reevaluează înaintea salvării rezultatului. Citările indică documentul, versiunea și locul real.

Se implementează toate cele șase joburi ale proiectului: document_map, dossier_gap_check, feedback_triage, feedback_digest, experiment_proposal și public_qa. Se adaugă explicațiile personale și asistența OCR în limitele consimțământului și bugetului. Joburile sunt asincrone, cu lease, retry limitat, idempotentă, audit, buget monetar și oprire controlată.

Datele primite din documente, bonuri și site-uri sunt conținut neîncrezut, nu instrucțiuni pentru agent. Rețetele private nu intră în răspunsuri publice. Datele gospodăriei și preferințele alimentare nu ajung la producător printr-o întrebare sau un prompt. Calculul cantităților și regulile de siguranță rămân deterministe. Serviciul AI oprit, fără buget sau indisponibil nu blochează scanarea, paginile QR, inventarul ori alertele.

## 8. Portabilitate, domenii și continuitatea adreselor

WP18 finalizează importul și exportul complet pentru producători și consumatori: date, versiuni, documente, licențe, proveniență, audit transferabil, referințe și mapări de identitate. Include arhivă cu manifest și sume de control, JSON pentru pachete mici și flux NDJSON pentru volume mari, reluare sigură, dry-run și raport pe înregistrare. Checksumul dovedește integritatea transferului, nu autoritatea emitentului.

Importul recunoaște schemele OpenBread vechi și v1 curent. Nu importă parole, tokenuri, roluri administrative, aprobări fără autoritate sau un drept asupra unui GTIN ca efect al unui câmp din fișier. Publicarea, licențele și acreditările transferate se verifică în contextul nou. Imaginile și bazele externe își păstrează condițiile de reutilizare.

Se construiește un adaptor pentru un export real, autorizat, de la alt furnizor. Dacă se dorește migrare dintr-un serviciu GS1, este necesar exportul efectiv și dicționarul său. Nu inventăm un „format GS1 universal de export” și nu declarăm acel adaptor finalizat pe baza unui fișier fabricat. E10 rămâne deschis până la proba aferentă.

Datele portabile și adresele portabile sunt obligații diferite. Migrarea nu poate schimba QR-ul deja imprimat pe ambalaj. Pentru un domeniu controlat de producător, mutarea DNS/rutării poate păstra adresa. Pentru adrese openaliment.ro, continuitatea cere o politică și un acord de menținere/redirecționare administrată de OpenAliment; exportul singur nu mută controlul domeniului.

WP19 livrează verificarea DNS/HTTP, TLS, allowlist de hosturi, activare/dezactivare, monitorizare și proba reală pe domeniu de producător. Dacă cPanel nu permite o rutare corectă în forma necesară, se livrează o componentă edge sau infrastructura adecvată, cu aprobare și cost real, în interiorul planului. Nu acceptăm Host arbitrar și nu oferim proxy deschis.

## 9. Securitate, confidențialitate și limite ale afirmațiilor

Se realizează un model de amenințări pentru producători, gospodării, dispozitive, surse externe, bonuri, autentificare și operatori. Verificarea folosește cerințe selectate din [OWASP ASVS](https://asvs.owasp.org/) și probe mobile specifice. Securitatea intră în fiecare pachet; WP23 închide fundația până în săptămâna 15, iar WP24 face revizia integrală a produsului final.

Datele despre alergii sau condiții medicale pot fi date de sănătate. Se stabilesc temeiul juridic, excepția aplicabilă pentru date sensibile, minimizarea, retenția și necesitatea unei DPIA înaintea procesării reale în cloud, conform [GDPR](https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX%3A32016R0679). O bifă generică nu rezolvă toate aceste obligații. Preferințele pot rămâne locale; partajarea lor cu gospodăria sau cu un furnizor AI este o alegere distinctă și limitată.

Rolurile gospodăriei nu expun automat datele sensibile ale fiecărui membru. Se tratează explicit membrii minori, invitațiile, dispozitivele comune, notificările pe ecranul blocat, exportul și ștergerea. Nu se construiesc profiluri publicitare bazate pe sănătate.

Se verifică izolarea între producători și gospodării, autorizarea obiectelor, accesul la fișiere, CSRF/XSS/SQL injection, session fixation, abuzul magic link, OTP/passkey, rate limit, brute force, webhooks, SSRF, ZIP bomb, path traversal, XXE și importuri ostile. Secretele rămân în configurație privată sau stocare sigură; orice secret de test publicat ori canal de instalare rămas activ este revizuit și rotit/blocat când este necesar.

Extracția PDF/DOCX și OCR nu execută documentele și nu necesită comenzi arbitrare pe server. Fișierele active sau suspecte sunt carantinate. Se limitează dimensiunile, paginile, resursele și timpul de procesare. Backupurile și logurile au aceeași disciplină de acces ca datele originale.

Politicile publice folosesc operatorul, contactele, furnizorii și perioadele reale, nu texte exemplu. Clasificarea funcțiilor pentru diabet, recomandările și afirmațiile de siguranță sunt revizuite juridic și de specialiști. Dacă o funcție ar intra într-un regim medical reglementat, nu se lansează pe baza unui simplu disclaimer.

Publicarea include cerințele curente [Apple App Review](https://developer.apple.com/app-store/review/guidelines/) și [declarația Google Play pentru aplicații de sănătate](https://support.google.com/googleplay/android-developer/answer/14738291?hl=en). Se verifică și [politica Google pentru conținut și servicii de sănătate](https://support.google.com/googleplay/android-developer/answer/16679511?hl=en), declarațiile de date, ștergerea contului și conturile de recenzie. Politicile se reverifică la trimitere, nu doar la data acestui plan.

## 10. Performanță, găzduire și operare

### Ținte măsurabile

Acestea sunt ținte propuse de acceptare, nu capacități deja probate ale planului shared.

| Indicator | Țintă și condiție de probă |
|---|---|
| Capacitate publică | Minimum 1.000.000 de scanări/zi în profilul documentat, cu vârf susținut de 250 cereri/s timp de 60 minute și rafală de 1.000 cereri/s timp de 5 minute. |
| Resolver | p95 al răspunsului server ≤ 300 ms în profilul de încărcare convenit; ratele de eroare internă < 0,1%. Se separă validările 4xx corecte de erori. |
| Pagina publică | LCP p75 ≤ 2,5 s pe profilul mobil și conexiunea stabilite; pagina esențială utilizabilă fără JavaScript. |
| Salvare locală după decodare | p95 ≤ 1 s pentru produs cunoscut cu date locale; prima permisiune, focalizarea și fetchul extern se raportează separat. |
| Sincronizare online | Operațiuni normale vizibile pe al doilea dispozitiv în p95 ≤ 5 s, în mediul de probă; fără pierderi sau dubluri la retry. |
| Alertă publică | O alertă aprobată este vizibilă la următoarea cerere online, fără snapshot vechi servit din cache. |
| Distribuirea alertelor | Mesajele relevante intră în cozile autorizate în ≤ 60 s în operare normală; recepția efectivă pe telefon se măsoară separat și nu este garantată. |
| Disponibilitate | SLO lunar propus 99,9% pentru citirea publică, cu buget de eroare și monitorizare; demonstrația de lansare acoperă perioada observată, nu o lună fictivă. |
| Recuperare | RPO ≤ 1 h, RTO ≤ 4 h în proba reală convenită; se include mecanismul necesar pentru date, fișiere, configurație și mapări. |

Un milion de scanări pe zi înseamnă aproximativ 11,6 pe secundă în medie; media singură nu dimensionează vârfurile. Testele folosesc loturi, alerte, raporturi cache/hit realistice, destinații și surse externe controlate. Se rulează numai pe infrastructură autorizată, cu buget și limite stabilite, nu ca atac asupra producției sau a unui resolver extern.

Se folosesc indexuri și proiecții compacte, snapshoturi imuabile cacheabile separat, resurse statice comprimate și documente încărcate la cerere. Paginile cu alertă și datele private nu devin cache comun. Nu scriem un rând DB per scanare și nu apelăm AI la scanare. Analytics agregat evită urmărirea inutilă a persoanelor.

Dacă shared hostingul nu trece proba, WP22 include mutarea componentei publice și/sau a bazei de date pe capacitate adecvată, cu continuitatea URL, migrare repetată și test de revenire. Constrângerea inițială fără root/shell nu se rezolvă prin pretinderea unei capacități inexistente. Alegerea furnizorului și costul real intră în E01/E12 înaintea angajării cheltuielilor.

### Cozi, mesagerie și mentenanță

Se verifică cronul disponibil prin cPanel sau se livrează un runner HTTPS strict autentificat, cu acțiuni fixe, nonce/semnătură, timeout și lock. Nu se adaugă endpoint de shell, execuție PHP liberă sau upload public arbitrar. Joburile au interval, lease, retry, dead letter și monitorizare; AI, notificările, extragerile și exporturile mari nu țin blocată o cerere web.

Emailul real are domeniu verificat, autentificare DNS, loguri minimale, retry și gestionarea respingerilor. Pushul are chei administrate de operator și rotație. Serviciile externe au timeout, circuit breaker, cost și degradare controlată.

Mecanismul HTTPS de actualizare deja folosit se inventariază și se limitează la distribuția semnată/autorizată, căi permise, jurnal, backup și revenire. După o intervenție se elimină accesul temporar inutil. Nu lăsăm o „ușă de administrare” generică pentru comoditate.

### Backup, monitorizare și incidente

Se livrează backup criptat extern, retenție aprobată, restore într-un mediu separat și verificarea fișierelor față de manifest. Monitorizarea acoperă pagini/QR, cozi, notificări, TLS, cron, spațiu, DB, erori, costuri și surse externe. Alertele operaționale au responsabil nominal, prag și procedură.

Runbookurile acoperă indisponibilitate DB/AI/date externe, retragere alimentară, export, cont compromis, pierdere chei, rollback de cod, recuperare după migrare și incident de date personale. Restaurarea unei baze nu trebuie să ascundă o alertă emisă ulterior; se recuperează și se reconciliază jurnalul alertelor înainte de redeschidere.

## 11. Responsabilități și dependențe externe

Rolurile de mai jos trebuie atribuite unor persoane sau servicii contractate în WP00. Sunt responsabilități, nu conturi noi create prin acest document.

| Rol | Responsabilitate |
|---|---|
| OWNER | Proprietarul OpenAliment |
| LEAD | Responsabil tehnic și coordonator |
| BE1 | Inginer platformă și API |
| BE2 | Inginer date și integrări |
| MOBILE | Inginer Android și iOS |
| UX | Designer și cercetare utilizatori |
| QA | Responsabil testare |
| OPS | Responsabil infrastructură |
| SEC | Responsabil securitate cu specialiști juridici și alimentari |

### Dependențe care trebuie închise

| ID | Acces / decizie / material necesar | Responsabil și termen | Dovadă de închidere |
|---|---|---|---|
| E01 | Limite hosting cron backup staging și upgrade de capacitate | OPS, S2 | Confirmare scrisă a limitelor și probă de execuție programată |
| E02 | Identitatea operatorului contracte privacy și responsabil de operare | OWNER, S2 | Date reale, responsabil nominal și documente aprobate |
| E03 | SMTP sau furnizor email autorizat și control DNS | OPS, S3 | Email real livrat și autentificarea domeniului verificată |
| E04 | Conturi Apple și Google deținute de operator și acces la build macOS | OWNER, S2 | Build semnat și acces delegat fără secrete în chat |
| E05 | Cel puțin un ERP cu format real și persoană tehnică disponibilă | OWNER, S3 | Export de probă autorizat, contract API și test cu sistemul partener |
| E06 | Cel puțin un magazin pentru bon digital și POS de test | OWNER, S3 | Bonuri reale autorizate și acces convenit la mediu sau echipament de test |
| E07 | Produse și etichete reale cu date publicabile aprobate | OWNER, S3 | Acord și date verificate pentru minimum trei categorii, inclusiv aliment non-pâine |
| E08 | Specialist nutriție sau siguranță alimentară și revizie juridică | OWNER, S3 | Responsabili contractați și reguli sau afirmații revizuite |
| E09 | Emitent real standard adoptat și evaluatori fără conflict de interese | OWNER, S4 | Roluri numite, procedură și standard aprobate înaintea emiterii reale |
| E10 | Export autorizat de la furnizor extern inclusiv un serviciu GS1 dacă există | OWNER, S4 | Fișiere și dicționar real, cu mandat; lipsa exportului GS1 nu se ascunde printr-un import simulat |
| E11 | Domeniu de producător și control DNS sau HTTP plus TLS | OWNER, S4 | Cel puțin un domeniu real pentru proba de activare și migrare |
| E12 | Bugete și conturi pentru servicii de date AI push OCR și capacitate | OWNER, S2 | Limite de cost, condiții de utilizare și acreditări gestionate securizat |

Termenele E sunt intenționat devreme: identificăm și rezolvăm blocajele înainte să ajungă pe traseul critic. Credencialele se predau prin canal sigur, cu acces delegat când este posibil. Un domeniu sau o bază de date deja configurată nu se reconfigurează fără o nevoie demonstrată. Acest plan nu trimite mesaje partenerilor și nu lansează servicii plătite.

## 12. Calendar, capacitate și ordine

Scenariul de planificare pornește la 26 octombrie 2026 și urmărește închiderea completă la 30 iulie 2027: 40 de săptămâni, inclusiv validarea, publicarea și stabilizarea. Este o țintă condiționată de echipă și acces, nu un termen deja asumat.

Estimarea inițială este de 421–682 zile-persoană pentru construcție și validare, agregată din pachetele de mai jos. Echipa de bază presupusă are trei ingineri și un responsabil QA, cu participare programată suplimentară pentru UX, infrastructură, securitate, alimentație și juridic. Proprietarul și partenerii furnizează deciziile și probele externe. Rolul de responsabil nu înseamnă că o singură persoană execută toate zilele acelui pachet.

Intervalul este o estimare de sus în jos, nu o măsurătoare a lucrului rămas. WP00 produce calendarul cu resurse reale, costuri și rezerve; dacă accesul sau capacitatea diferă, se schimbă data explicit, nu se elimină discret funcții. Rezervele trebuie să acopere și revizia magazinelor mobile, conectarea partenerilor și remedierile. Conturile și documentele pentru publicare se pregătesc din primele săptămâni.

Dependențele din backlog sunt condiții de acceptare și integrare. Dezvoltarea poate începe în paralel după înghețarea interfeței necesare; un pachet nu poate fi acceptat înaintea dependențelor sale. Aceasta permite lucrul timpuriu pe mobile, QA, securitate și documentație, fără a aștepta finalul întregului backend.

| Reper | Țintă în scenariul de 40 săptămâni | Condiție verificabilă |
|---|---|---|
| M0 — execuție pregătită | S2, 6 noiembrie 2026 | Inventar, backup, responsabili, accesuri și bugete; partenerii au termene. |
| M1 — contracte și operare | S7, 11 decembrie 2026 | Migrații compatibile, staging, execuție programată și primele fluxuri validate. |
| M2 — identificare și identitate | S11, 8 ianuarie 2027 | Scanare/OCR, acces personal persistent, API și publicare aprobate. |
| M3 — inventar și web complet de bază | S18, 26 februarie 2027 | Gospodării/offline, surse externe, PWA și editorul documentației. |
| M4 — module funcționale integrate | S26, 23 aprilie 2027 | Android/iPhone, reguli alimentare, AI, bonuri, evaluare și notificări. |
| M5 — parteneri și portabilitate | S30, 21 mai 2027 | ERP și magazin reale, asistență la consum, migrare și import/export. |
| M6 — domenii și capacitate | S34, 18 iunie 2027 | Domenii reale, conținut final, încărcare și operare dimensionate. |
| M7 — candidat validat | S36, 2 iulie 2027 | Matrice integrală, audit și POS trecute; blocajele remediate. |
| M8 — distribuție publică | S38, 16 iulie 2027 | Aplicații aprobate și disponibile efectiv în ambele magazine. |
| M9 — închidere | S40, 30 iulie 2027 | Lansare, minimum 14 zile de stabilizare, predare și toate cerințele acceptate. |

Data-limită a fiecărui pachet este un reper de integrare în acest scenariu. Criteriile sale rămân obligatorii chiar dacă aprobarea unui magazin sau răspunsul unui partener întârzie. O dependență externă are responsabil și escaladare; nu este considerată rezolvată prin trecerea timpului.

Ordinea principală este: inventar/prototip/contracte → date și identitate → inventar/scanare/interoperabilitate → web/mobile/reguli → bonuri/notificări/AI/dosare → integrări/evaluare/portabilitate → domenii/capacitate/conținut → validare/publicare → stabilizare/închidere. WP21 și WP23 sunt transversale și încep devreme.

## 13. Pachetele de implementare

Fiecare pachet se livrează cu cod, migrări când sunt necesare, interfață finală în română, contracte actualizate, teste relevante, dovadă de acceptare și instrucțiuni de operare. Nu se închide numai pe baza capturilor de ecran sau a faptului că formularul se afișează.

### WP00 — Inventar și pregătirea execuției

Responsabil: LEAD. Dependențe de acceptare: niciuna. Reper: S2. Estimare: 4–7 zile-persoană.

Îngheață inventarul codului, rutelor, migrațiilor, contractelor și rapoartelor curente. Stabilește persoanele, costurile recurente, dispozitivele, domeniile și probele autorizate. Datele private nu se copiază în staging fără minimizare și acord. Nivelează calendarul pe resursele efective înaintea asumării datei.

Livrabile:

- Baseline cod, DB, rute, rapoarte și originalele PBT.
- Registru de acces, bugete, furnizori și parteneri reali.
- Mediu de test, dispozitive și plan de resurse.

Acceptare: Toate dependențele E01–E12 au responsabil și termen; Baseline și copie restaurabilă înainte de orice migrare.

### WP01 — Fluxuri simple și accesibilitate

Responsabil: UX. Dependențe de acceptare: WP00. Reper: S5. Estimare: 10–16 zile-persoană.

Livrează prototip interactiv pentru prima utilizare, scanare, dată lipsă, produs necunoscut, bon, gospodărie, notificare și recuperare. Include toate stările de eroare și refuzul permisiunilor. Cercetarea validează fluxul fără instrucțiuni; concluziile schimbă interfața înainte de implementarea completă.

Livrabile:

- Prototip pentru toate fluxurile, inclusiv erori și lipsa datelor.
- Texte românești, fotografii, pictograme și citire asistată.
- Buget de gesturi și testare fără instruire.

Acceptare: Nicio autentificare sau instalare obligatorie înainte de prima salvare; Minimum 90% dintre participanții finali înțeleg următorul gest în 3 secunde.

### WP02 — Contracte de date și migrări compatibile

Responsabil: BE1. Dependențe de acceptare: WP00. Reper: S7. Estimare: 12–18 zile-persoană.

Introduce schemele versionate, migrațiile și corpusul comun de exemple pentru PHP/web/mobile. Include unități, vocabular de alergeni, precizia datelor, nutrienți, surse și reguli de conflict. Revizuiește punctual masterul, OpenAPI, import/export și profilurile; nu lasă două contracte normative contradictorii.

Livrabile:

- Etichetă structurată v2, profil consumer v1, bon v1 și sincronizare v1.
- Dicționare alergeni, nutrienți, unități și precizia datelor.
- Migrări aditive și compatibilitate cu importurile vechi.

Acceptare: Datele necunoscute rămân necunoscute, distinct de zero sau absent; Importurile și clienții API v1 continuă să funcționeze.

### WP03 — API și administrarea informațiilor alimentare

Responsabil: BE1. Dependențe de acceptare: WP01, WP02. Reper: S10. Estimare: 14–22 zile-persoană.

Extinde editorul producătorului cu validări și aprobare pe versiune, fără a cere date inexistente. Livrează lista capabilităților produsului, resursele descoperibile prin resolver și API pentru date publice/privat autorizate. Mandatele GTIN se verifică separat de cifra de control și au procedură pentru contestații.

Livrabile:

- Editor de etichetă cu date structurate și aprobare explicită.
- API public normalizat și API privat pentru client.
- Capabilități de date și proveniență pe fiecare câmp.

Acceptare: Aliment non-pâine publicat fără rețetă, licență sau siglă; Numai proiecțiile aprobate sunt expuse; versiunile vechi se păstrează.

### WP04 — Identitate și acces persistent

Responsabil: BE1. Dependențe de acceptare: WP01, WP02. Reper: S11. Estimare: 18–28 zile-persoană.

Separă identitatea personală de membershipurile producătorului. Livrează passkey, magic link, opțiunile Apple/Google unde sunt activate, sesiuni de dispozitiv persistente, recuperare, revocare, logout și unire a inventarelor. Testează dispozitiv comun și emailuri preîncărcate; securizează explicit conturile administrative.

Livrabile:

- Mod fără cont și identitate separată pentru consumatori.
- Sesiuni persistente, dispozitive, recuperare și migrarea listei locale.
- Passkeys, magic link și conectare Apple sau Google ca alternative facultative.

Acceptare: Închiderea aplicației și revenirea nu cer autentificare de rutină; Recuperarea și unirea identităților nu pierd și nu dublează inventarul.

### WP05 — Inventar personal și gospodării sincronizate

Responsabil: BE2. Dependențe de acceptare: WP02, WP03, WP04. Reper: S14. Estimare: 22–34 zile-persoană.

Modelează ambalaje și evenimente, nu doar un număr pe produs. Livrează inventar local, API privat, coadă offline, tombstone-uri de ștergere, idempotentă și conflicte. Gospodăria are invitații care expiră, roluri, ieșire și transfer de administrare; preferințele medicale nu devin implicit comune.

Livrabile:

- Ambalaje individuale, cantități, locații și evenimente.
- Inventar local, coadă offline, sincronizare și rezolvarea conflictelor.
- Gospodării, invitații QR, roluri și listă comună.

Acceptare: Două ambalaje identice pot avea date și stări diferite; Operațiile reluate și sincronizarea simultană nu creează dubluri sau acces între gospodării.

### WP06 — Experiența web și PWA

Responsabil: BE1. Dependențe de acceptare: WP01, WP03, WP04, WP05, WP08. Reper: S18. Estimare: 15–24 zile-persoană.

Livrează fluxul web cu cameră, adăugare fără cont, listă, detaliu, deschidere/consum și sincronizare. Service workerul cachează numai resursele permise, marchează vechimea și nu maschează alertele. Politicile de cameră/microfon/CSP se schimbă pe rutele necesare, cu regresie pentru portalul existent.

Livrabile:

- Zona /acasa și intrare din pagina produsului.
- Scanner web, salvare locală și instalare facultativă.
- Service worker limitat la datele permise și indicarea actualității.

Acceptare: Prima adăugare funcționează fără instalare și fără cont; Nicio pagină sau alertă veche nu este prezentată ca verificată în prezent.

### WP07 — Aplicații Android și iPhone

Responsabil: MOBILE. Dependențe de acceptare: WP01, WP03, WP04, WP05, WP08. Reper: S21. Estimare: 30–48 zile-persoană.

Livrează clienții Android/iPhone, reutilizând contractele și exemplele de reguli. Include camera continuă, stocare sigură, offline, deep links/universal links, import prin share, OCR, accesibilitate și notificări. Buildurile și cheile sunt sub controlul operatorului; se verifică actualizarea fără pierderea inventarului.

Livrabile:

- Aplicații Flutter cu cameră, OCR, stocare locală și sincronizare.
- Linkuri către aplicație, partajare, notificări și citire asistată.
- Builduri semnate Android și iOS, instalabile pe dispozitive reale.

Acceptare: Fluxurile funcționează pe dispozitiv modest, iPhone și în lipsa rețelei; Aplicațiile folosesc aceleași contracte și reguli alimentare ca webul.

### WP08 — Scanare universală și citirea datelor de pe ambalaj

Responsabil: MOBILE. Dependențe de acceptare: WP01, WP02. Reper: S11. Estimare: 18–28 zile-persoană.

Probează formatele și dispozitivele înainte de integrarea completă. Include check digit, FNC1 unde este relevant, transport GS1, decompresie și coduri locale de magazin mapate. Debounce-ul previne citirea repetată a aceleiași ținte, dar permite două ambalaje identice printr-un gest explicit sau o nouă scanare distinctă.

Livrabile:

- EAN, UPC, QR nativ, GS1 Digital Link și formate GS1 2D.
- Validare identificatori, calificatori, date, loturi și coduri locale de magazin.
- OCR local, fotografie de dată, intrare vocală și prevenirea scanărilor repetate.

Acceptare: EAN și QR de pe același ambalaj generează o singură adăugare; Citirea ambiguă cere cel mult o confirmare contextuală și nu inventează termenul.

### WP09 — Interoperabilitate și surse externe

Responsabil: BE2. Dependențe de acceptare: WP02, WP03, WP08. Reper: S14. Estimare: 16–26 zile-persoană.

Construiește adaptoare izolate pentru resolvere și date publice, cu identificarea sursei, licențe și cache permis. Probează minimum două resolvere externe independente. Îmbogățirea este asincronă când este lentă și nu ține produsul nesalvat. Protecțiile de fetch sunt testate inclusiv prin redirect și schimbare DNS.

Livrabile:

- Client resolver pentru linkset și resurse publice.
- Adaptor Open Food Facts și două implementări de resolver externe.
- Registru de surse, licențe, cache, conflicte și acces extern sigur.

Acceptare: Produse din afara OpenAliment pot fi adăugate și îmbogățite; Un resolver fără resursa cerută nu produce informații fabricate.

### WP10 — Reguli pentru termene alergeni și nutriție

Responsabil: BE2. Dependențe de acceptare: WP02, WP03, WP09, WP23, WP06, WP07. Reper: S23. Estimare: 15–24 zile-persoană.

Implementează reguli deterministe și texte aprobate pentru data-limită, calitate, deschidere, congelare, alergeni și nutrienți. Expertul validează exemplele și limitele înainte de testele pe persoane. O lipsă de date nu produce indicator verde, verdict medical sau calcul de porție din unități incompatibile.

Livrabile:

- Date de siguranță și de calitate, deschidere și păstrare.
- Alergeni declarați, posibili și necunoscuți, plus preferințe individuale.
- Carbohidrați și porții, unități și valori pentru alimentul vândut sau preparat.

Acceptare: Absența datelor nu înseamnă produs sigur sau valoare zero; Calculele sunt deterministe și verificate de specialist; nu există dozare de insulină.

### WP11 — Notificări și retrageri de produse

Responsabil: BE2. Dependențe de acceptare: WP05, WP06, WP07, WP10, WP21. Reper: S26. Estimare: 18–28 zile-persoană.

Livrează schedulerul și toate canalele cu deduplicare, preferințe, confirmarea stărilor și oprire la consum. Retragerile se potrivesc pe GTIN/lot/interval și au sursă și istoric; pentru lot necunoscut se comunică incertitudinea. Probele folosesc telefoane reale, nu doar răspunsul API al furnizorului.

Livrabile:

- Notificări locale, Web Push, APNs sau FCM și email opțional.
- Rezumat zilnic, alerte urgente, preferințe și acces direct la produs.
- Sincronizare cu alertele producătorului și surse autorizate de rechemări.

Acceptare: Consumul oprește reamintirile pe toate dispozitivele; Revocarea permisiunii și lipsa rețelei nu sunt confundate cu livrarea unei notificări.

### WP12 — Bonuri fotografiate și bonuri digitale

Responsabil: BE2. Dependențe de acceptare: WP05, WP06, WP07, WP08, WP09. Reper: S25. Estimare: 22–36 zile-persoană.

Livrează OCR și interpretarea bonurilor, ecranul scurt de corecție, importul în bloc și anularea. QR-ul de bon are acces protejat și nu publică datele cumpărătorului. Include bon repetat, retur, discount, produse cântărite, articole nealimentare și bon fără GTIN/lot/termen.

Livrabile:

- Fotografiere sau partajare bon, OCR și potrivire de articole.
- Import grupat cu anulează și verificare numai pentru liniile incerte.
- Contract bon digital, QR securizat și API de import.

Acceptare: Același bon importat de două ori nu dublează produsele; Lotul și data sunt completate numai când există în sursă.

### WP13 — Integrări reale cu ERP și magazine

Responsabil: BE2. Dependențe de acceptare: WP02, WP03, WP11, WP12. Reper: S28. Estimare: 16–28 zile-persoană.

Alege partenerii în WP00 și închide mapările cu ei. Livrează cel puțin un conector ERP real și o integrare reală de bon digital de la magazin, cu teste de retry/replay/corecție. Contractul general permite alte integrări; nu se declară conectat un ERP sau retailer pentru care nu există probă.

Livrabile:

- Conector ERP pe formatul real al partenerului.
- Conector bon digital cu cel puțin un magazin pilot.
- Webhooks semnate, reluări, mapări și documentație de integrare.

Acceptare: Flux de producție asistat ERP către produs, lot și QR demonstrat cu partenerul; Un bon real autorizat adaugă cumpărăturile în gospodăria corectă.

### WP14 — Asistență de consum și cumpărături

Responsabil: BE1. Dependențe de acceptare: WP05, WP06, WP07, WP09, WP10, WP15. Reper: S29. Estimare: 12–20 zile-persoană.

Livrează lista de cumpărături comună, recumpărarea la alegere, sugestii pentru produsele disponibile, porții și alternative comparabile. Explică alegerea și sursele. Elimină din recomandări produsele retrase și incompatibilitățile cunoscute; nu deduce că un produs necunoscut este potrivit.

Livrabile:

- Listă de cumpărături și recumpărare facultativă.
- Sugestii de folosire a alimentelor disponibile și porții explicate.
- Alternative comparabile, filtrate după date și preferințe.

Acceptare: Recomandările nu folosesc alimente retrase sau incompatibile cunoscute; Datele incomplete și rețetele fără drept de reutilizare nu devin recomandări sigure.

### WP15 — Feedback căutare și analiză AI

Responsabil: BE1. Dependențe de acceptare: WP03, WP04, WP16. Reper: S24. Estimare: 20–32 zile-persoană.

Finalizează căutarea și cele șase joburi AI, moderarea, voturile, propunerile și citările. Separă explicațiile personale de răspunsurile publice și dovezile tehnice. Include apel real autorizat la furnizor și verificarea costurilor, plus regresie cu AI complet oprit.

Livrabile:

- Cele șase joburi AI ale dosarului, RAG și citări verificabile.
- Recenzii, întrebări, voturi, moderare și propuneri versionate.
- Explicații personale și OCR asistat, cu limite de cost și permisiuni.

Acceptare: Contextul public nu poate recupera sau deduce rețete private; AI indisponibil nu blochează salvarea, QR-ul, calculele sau alertele.

### WP16 — Documentație alimentară deschisă completă

Responsabil: BE1. Dependențe de acceptare: WP02, WP03. Reper: S18. Estimare: 18–28 zile-persoană.

Livrează editorul pentru toate secțiunile dosarului, calcule deterministe, versiuni, licențe, surse, rezultate, costuri și export. Reproduce scenariile PBT din proveniența lor, păstrând stadiul experimental. Adoptă profilurile inițiale cu specialiști, fără a impune parametrii pâinii altor categorii.

Livrabile:

- Editor al formulei, procesului, dovezilor și celor 24 de secțiuni.
- Costuri, furnizori, încercări, rezultate, comparații și export.
- Profil panificație și profiluri inițiale pentru alte categorii.

Acceptare: Publicarea materialelor are licență și drepturi explicite pe versiune; PBT, R1 și COST1–COST3 păstrează proveniența și stadiul real.

### WP17 — Evaluarea documentației și registrul siglei

Responsabil: BE1. Dependențe de acceptare: WP16, WP23. Reper: S25. Estimare: 20–32 zile-persoană.

Finalizează procedura și softwareul de evaluare, nu doar imaginea unei sigle. Definește emitentul, independența, conflictele, taxa dacă există, domeniul și contestațiile. Testează emiterea tranzacțională OB-24, reemiterea, revocarea și afișarea publică a limitelor. Plata nu cumpără decizia.

Livrabile:

- Standarde și profiluri versionate, evaluatori eligibili și conflicte de interese.
- Depunere înghețată, remedieri, decizie și emitere tranzacțională.
- Registru public, SVG, PDF, expirare, revocare și contestații.

Acceptare: OB-01–OB-23 sunt verificate înaintea deciziei OB-24 și emiterii; Nicio pagină QR, plată sau verificare GTIN nu acordă automat sigla.

### WP18 — Portabilitate și migrare completă

Responsabil: BE2. Dependențe de acceptare: WP02, WP03, WP05, WP12, WP16, WP17. Reper: S30. Estimare: 16–26 zile-persoană.

Livrează export/import în flux și pachete cu fișiere, dry-run, rapoarte și round-trip verificat. Adaptorul pentru furnizor folosește un export real autorizat. Separă transferul datelor de transferul drepturilor și al adreselor; exportul personal nu expune preferințele altor membri.

Livrabile:

- Import și export în flux pentru producători și consumatori.
- Adaptori vechi OpenBread, transfer v1 și format de furnizor real.
- Mapări de identități, documente, istoric, licențe și continuitate URL.

Acceptare: Exportul și reimportul reproduc datele, fișierele și istoricul în mediul țintă; Importul nu acordă automat roluri, mandat GS1, publicare sau siglă.

### WP19 — Domenii ale producătorilor și continuitate

Responsabil: OPS. Dependențe de acceptare: WP00, WP03, WP18, WP21. Reper: S32. Estimare: 10–18 zile-persoană.

Livrează activarea și retragerea domeniilor, certificatul TLS, rutarea și monitorizarea expirării. Probează două hosturi autorizate, dintre care cel puțin unul controlat de producător, plus mutarea efectivă a destinației fără ruperea identității. Documentează continuitatea URL la plecarea clientului.

Livrabile:

- Verificarea controlului DNS sau HTTP, TLS și registru de hosturi.
- Activare, dezactivare și monitorizare a domeniilor autorizate.
- Migrare la sau de la alt furnizor cu mapări și acord de continuitate.

Acceptare: Două domenii autorizate rezolvă aceleași identități fără confuzie între producători; Nicio destinație sau valoare Host arbitrară nu activează un domeniu.

### WP20 — Conținut public suport și experiență comercială

Responsabil: UX. Dependențe de acceptare: WP01, WP06, WP07, WP16, WP17, WP19, WP23. Reper: S33. Estimare: 8–14 zile-persoană.

Finalizează landingul pentru producători și intrarea simplă pentru consumatori, ghidurile, contactul și suportul. SEO folosește titluri și pagini utile despre alternativa independentă de găzduire/resolver GS1 Digital Link, produs/lot, QR și ERP; nu implică afiliere GS1. Conținutul descrie numai nivelul probat.

Livrabile:

- Landing, pagini produs, aplicație client, ajutor și contact funcțional.
- Documentație API, politici reale, exemple și mesaje despre limitele serviciului.
- SEO, accesibilitate și păstrarea imaginii alese de proprietar.

Acceptare: Pagina comercială și zona consumatorului au scopuri și acțiuni clare; Conținutul nu pretinde certificări, rezultate alimentare sau capabilități netestate.

### WP21 — Infrastructură și operare pe hosting

Responsabil: OPS. Dependențe de acceptare: WP00. Reper: S6. Estimare: 14–22 zile-persoană.

Livrează staging, release reproductibil, migrări, configurație privată, runner de joburi și backup restaurabil. Documentează operațiunile din cPanel fără shell/root. Evaluează accesul HTTPS de mentenanță și reduce suprafața lui; niciun fișier de instalare sau token universal nu rămâne activ necontrolat.

Livrabile:

- Staging izolat, livrare HTTPS, migrări și gestionarea secretelor.
- Cron sau serviciu de joburi autentificat, mail și cozi fiabile.
- Backup criptat, restaurare, jurnal și proceduri de operare.

Acceptare: Un job programat se execută fără shell sau endpoint de execuție public; Restaurarea completă este demonstrată într-un mediu separat.

### WP22 — Performanță observabilitate și capacitate

Responsabil: OPS. Dependențe de acceptare: WP03, WP05, WP09, WP11, WP19, WP21. Reper: S34. Estimare: 14–24 zile-persoană.

Măsoară traseele reale și stabilește limitele furnizorului. Optimizează SQL, cacheul, fișierele și cozile, apoi rulează încărcarea autorizată. Dacă planul shared e insuficient, migrarea de capacitate și retestarea sunt parte din livrare; declarațiile comerciale se bazează pe rezultatele obținute.

Livrabile:

- SLO, măsurători, cache sigur, indici și controlul costurilor.
- Test de capacitate și infrastructură adecvată traficului țintă.
- Monitorizare, incidente, invalidare și degradare controlată.

Acceptare: Ținta de un milion de scanări pe zi este probată pe infrastructura aleasă; Cache-ul nu ascunde retrageri și nu amestecă date personale.

### WP23 — Securitate confidențialitate și reguli de utilizare

Responsabil: SEC. Dependențe de acceptare: WP00, WP01, WP02, WP04, WP05, WP09. Reper: S15. Estimare: 18–30 zile-persoană.

Închide modelul de amenințări, politicile, temeiurile, DPIA dacă este necesară și revizia afirmațiilor până la introducerea datelor reale. Integrează controalele în toate echipele. Auditul final rămâne în WP24 și acoperă și modulele construite după săptămâna 15.

Livrabile:

- Model de amenințări, politici GDPR, retenție și analiză DPIA.
- Revizie a funcțiilor de sănătate, licențelor și schemei de evaluare.
- Controale pentru autorizare, acces extern, fișiere și furnizori.

Acceptare: Profilurile de sănătate și inventarul nu ajung la producători sau alți membri fără drept; Temeiurile, acordurile și limitele afirmațiilor sunt stabilite înainte de date reale.

### WP24 — Validare integrală și probe fizice

Responsabil: QA. Dependențe de acceptare: WP06, WP07, WP08, WP09, WP10, WP11, WP12, WP13, WP14, WP15, WP16, WP17, WP18, WP19, WP20, WP21, WP22, WP23. Reper: S36. Estimare: 22–36 zile-persoană.

Execută întreaga matrice pe buildurile candidat, inclusiv public/private, AI indisponibil, offline și revenire. Probează tiparul și POS cu persoana autorizată a magazinului; separă decodarea optică de utilizarea comercială. Remediază și repetă probele care au eșuat, fără a cosmetiza necunoscutele.

Livrabile:

- Matricea completă de acceptare și regresie.
- Probe dispozitive reale, accesibilitate, imprimare și POS.
- Revizie de securitate, teste utilizatori și exercițiu de incident.

Acceptare: Toate cazurile obligatorii au dovezi și niciun defect critic sau major deschis; Scanarea optică și recunoașterea articolului la POS sunt probate separat.

### WP25 — Distribuție Android și iOS

Responsabil: MOBILE. Dependențe de acceptare: WP07, WP20, WP23, WP24. Reper: S38. Estimare: 8–14 zile-persoană.

Pregătește conturile, certificatele și materialele devreme, apoi trimite buildurile validate. Rezolvă observațiile magazinelor, obține aprobarea și verifică descărcarea publică pe dispozitive reale. Include politici, ștergere cont, acces de recenzie, permisiuni și actualizarea versiunii instalate.

Livrabile:

- Google Play și App Store, conturi și certificate controlate de operator.
- Declarații de confidențialitate și sănătate, materiale și recenzare.
- Publicare și test instalare, actualizare, linkuri și ștergere cont.

Acceptare: Aplicațiile sunt aprobate și disponibile, nu doar trimise spre verificare; Instalarea nouă și actualizarea păstrează datele și accesul.

### WP26 — Lansare și stabilizare

Responsabil: LEAD. Dependențe de acceptare: WP18, WP19, WP21, WP22, WP24, WP25. Reper: S40. Estimare: 8–14 zile-persoană.

Lansează controlat web/API/aplicații cu responsabil de incident și revenire exersată. Rulează pilot real cu producători și minimum zece gospodării, urmărește 14 zile, rezolvă erorile și predă operarea. Anunțurile și contactarea partenerilor se fac de operator sau numai cu autorizarea lui.

Livrabile:

- Lansare controlată web, API și aplicații, cu plan de revenire.
- Pilot real de producător și gospodării, monitorizare și remedieri.
- 14 zile de stabilizare și predare a operării.

Acceptare: Nu există pierdere de date, funcții promise inaccesibile sau defecte critice; Responsabilul de operare poate restaura și publica o corecție fără autorul inițial.

### WP27 — Închiderea completă a planului

Responsabil: LEAD. Dependențe de acceptare: WP26. Reper: S40. Estimare: 3–5 zile-persoană.

Închide fiecare cerință prin dovadă și acceptare nominală. Predă codul, buildurile, contractele, accesurile delegabile, runbookurile, licențele și costurile recurente. Orice integrare obligatorie lipsă sau blocaj major menține planul deschis; o eliminare de scop ar necesita o decizie explicită a proprietarului.

Livrabile:

- Registru final al cerințelor, livrabilelor, probelor și dependențelor.
- Specificații, contracte, release, surse și accesuri predate.
- Decizie nominală de finalizare integrală.

Acceptare: Toate pachetele WP00–WP26 și cerințele R01–R56 sunt acceptate; Un simulator sau o dependență externă nerezolvată nu înlocuiește integrarea reală cerută.

## 14. Trasabilitatea cerințelor

Fiecare cerință are pachete responsabile și scenarii de acceptare în backlogul structurat. Tabelul rezumă aria; probele detaliate sunt în matrice. Starea inițială a extensiilor este „planificat”, inclusiv atunci când o parte a funcției există deja și trebuie retestată.

| ID | Cerință | Pachete responsabile |
|---|---|---|
| R01 | Păstrarea codului rutelor și contractelor existente | WP00, WP02, WP24 |
| R02 | Serviciul QR pentru orice categorie fără rețetă sau siglă | WP02, WP03, WP24 |
| R03 | Validarea identificatorilor și verificarea separată a mandatului | WP03, WP08, WP19 |
| R04 | Versiuni istorice și alerte curente fără rescriere retrospectivă | WP02, WP03, WP10, WP11 |
| R05 | Date alimentare structurate cu unități surse și precizie | WP02, WP03, WP10 |
| R06 | Compatibilitate cu importurile și API v1 | WP02, WP18, WP24 |
| R07 | Scanare EAN UPC QR nativ și GS1 Digital Link | WP08, WP06, WP07 |
| R08 | Loturi și date GS1 cu codificare și validare corecte | WP02, WP08, WP10 |
| R09 | Produse externe și mai multe resolvere | WP09, WP24 |
| R10 | Fotografie de dată lipsă și confirmare minimă | WP01, WP08, WP10 |
| R11 | Prima utilizare fără cont și acces persistent | WP04, WP06, WP07 |
| R12 | Recuperare și sincronizare între dispozitive fără pierderi | WP04, WP05, WP24 |
| R13 | Inventar comun și permisiuni între membrii gospodăriei | WP04, WP05, WP23 |
| R14 | Funcționare offline și independență de AI | WP05, WP06, WP07, WP15 |
| R15 | Buget minim de gesturi și înțelegere rapidă | WP01, WP24 |
| R16 | Alergeni declarați posibili și necunoscuți | WP02, WP10, WP23 |
| R17 | Carbohidrați porții și informare pentru persoane cu diabet | WP02, WP10, WP23 |
| R18 | Tipuri de termene deschidere congelare și condiții de păstrare | WP05, WP10 |
| R19 | Ambalaje distincte cantități consum anulează și prevenirea dublurilor | WP05, WP08 |
| R20 | Notificări locale push rezumat email și preferințe | WP11, WP21, WP25 |
| R21 | Retrageri la nivelul corect și lot necunoscut tratat explicit | WP09, WP10, WP11 |
| R22 | Nicio garanție medicală sau alimentară dedusă din lipsa datelor | WP10, WP14, WP23 |
| R23 | Bon digital și QR de bon cu contract public propriu | WP02, WP12, WP13 |
| R24 | OCR bon potrivire linii și excluderea produselor nealimentare | WP08, WP12 |
| R25 | Cel puțin o integrare reală cu magazin pilot | WP00, WP13, WP24 |
| R26 | ERP documentat cu automatizare și o integrare reală | WP03, WP13 |
| R27 | Listă de cumpărături și recumpărare opțională | WP05, WP14 |
| R28 | Sugestii de folosire și alternative verificate | WP10, WP14, WP15 |
| R29 | Accesibilitate voce și citire asistată | WP01, WP06, WP07, WP08, WP24 |
| R30 | Aplicații Android și iPhone distribuite efectiv | WP07, WP25 |
| R31 | Intrare web PWA cameră și actualitatea datelor | WP06, WP11, WP24 |
| R32 | Imaginea aleasă de proprietar și proveniența vizualurilor | WP20 |
| R33 | Documentație deschisă formule proces costuri și rezultate | WP16 |
| R34 | Evaluare independentă OB-01–OB-24 și registrul siglei | WP17, WP23 |
| R35 | Registrul afirmațiilor și aprobarea informațiilor publice | WP03, WP10, WP16, WP17 |
| R36 | Recenzii voturi întrebări moderare și propuneri istorice | WP15 |
| R37 | Căutare RAG cu acces controlat și citări verificabile | WP15 |
| R38 | Joburi AI complete cu cozi buget și lipsa serviciului tratată | WP15, WP21 |
| R39 | Export import și migrare integrală producător și consumator | WP18 |
| R40 | Domenii proprii TLS control verificat și continuitate | WP19, WP21 |
| R41 | PBT R1 scenarii cost și originale intacte cu stadiul real | WP00, WP16, WP18 |
| R42 | GDPR politici reale și declarații corecte în magazinele de aplicații | WP23, WP25 |
| R43 | Izolare producători gospodării dispozitive și furnizori externi | WP03, WP04, WP05, WP09, WP23 |
| R44 | Capacitate măsurată pentru milioane de scanări | WP21, WP22 |
| R45 | Operare cPanel fără root shell sau execuție publică arbitrară | WP21, WP26 |
| R46 | Backup restaurare monitorizare incidente și costuri | WP21, WP22, WP26 |
| R47 | Probe fizice de tipar scanare și integrare POS | WP24 |
| R48 | Documentație versiuni SDK și regresie a contractelor | WP02, WP13, WP18, WP24, WP27 |
| R49 | SEO ofertă comercială ajutor și suport funcțional | WP20 |
| R50 | Finalizare integrală fără funcții obligatorii amânate | WP27 |
| R51 | Interfață română simplă și localizare corectă | WP01, WP06, WP07 |
| R52 | Licențe explicite și proveniență a surselor externe | WP09, WP16, WP18, WP23 |
| R53 | Export ștergere consimțământ și retenție a datelor personale | WP04, WP05, WP18, WP23 |
| R54 | Gospodării cu minori și date de sănătate protejate | WP05, WP10, WP23 |
| R55 | Produse necunoscute fără cod și completare facultativă | WP05, WP08, WP09 |
| R56 | Informare la raft separată de adăugarea acasă | WP01, WP06, WP07, WP08 |

## 15. Verificare, lansare și definiția finalizării

### Dovezi cerute

Testele acoperă unități și reguli deterministe, contracte API, integrare DB, autorizare, import/export, clienți reali, offline/sincronizare, furnizori, accesibilitate, utilizare fără instruire, securitate, performanță și recuperare. Testele simulate sunt utile pentru dezvoltare, dar nu înlocuiesc probele reale indicate de matrice.

Fiecare probă înregistrează versiunea codului/buildului, migrațiile, mediul, data, datele de intrare anonimizate, rezultatul așteptat/obținut și dovada. Fotografiile de bon, datele medicale și secretele nu intră în rapoarte publice. Un eșec are responsabil, remediere și retestare; un scenariu neexecutat nu se marchează trecut.

Se păstrează o matrice de dispozitive reale Android/iPhone, inclusiv un Android accesibil ca preț, Safari/iOS și Chrome/Android. Se testează camera, lumină slabă, permisiuni refuzate, fundal, economie baterie, rețea intermitentă, actualizare și reinstalare/recuperare. Sistemele pot limita execuția în fundal și notificările; interfața comunică starea fără promisiuni false.

### Tipar și casa de marcat

Se probează separat:
1. conținutul și sintaxa QR;
2. decodarea optică pe ecran și tipar;
3. rezolvarea HTTPS și resursa deschisă;
4. citirea GS1 de echipamentul POS;
5. înregistrarea articolului și, dacă POS permite, utilizarea lotului/termenului în fluxul comercial.

Cerințele de dimensiune, contrast, quiet zone și amplasare se verifică pe ambalajul real, folosind [ghidul GS1 pentru 2D în retail](https://ref.gs1.org/guidelines/2d-in-retail/). Nu declarăm „funcționează în toate magazinele” după o probă pe telefon.

GTIN-ul demonstrativ 952 se folosește numai în medii de demonstrație/test autorizate. Proba comercială folosește identificatorii reali asupra cărora producătorul are drepturi și articolul configurat de magazin. Coexistența sau înlocuirea codului de bare depinde de pregătirea partenerului și regulile aplicabile; landingul nu promite că orice casă de marcat acceptă deja QR.

### Lansare și revenire

Stagingul trece matricea și restore-ul. Release-ul este reproductibil, cu manifest, checksum, migrații și verificarea configurației. Se face backup înaintea schimbării, se aplică migrările controlat, se verifică rutele persistente și se activează gradual funcțiile. Datele introduse în intervalul de lansare sunt reconciliate; rollbackul de cod nu șterge operațiuni noi sau alerte.

Pilotul final include cel puțin un producător non-pâine, PBT ca exemplu documentat corect, partenerii ERP/magazin și minimum zece gospodării. Stabilizarea durează minimum 14 zile în interiorul planului; o eroare critică sau pierdere de date cere remediere și o nouă perioadă relevantă de observație. Sunt definite suportul, orele de acoperire și escaladarea.

### Planul se închide numai când

- Toate WP00–WP27 sunt acceptate, iar R01–R56 au probe trecute. Fiecare test obligatoriu din matrice este executat și are dovadă.
- Serviciul A funcționează pe o categorie non-pâine fără rețetă, fără siglă și fără AI; produsul și lotul rămân corecte după actualizări.
- Web/PWA, Android și iPhone sunt utilizabile public, cu permisiuni, recuperare și actualizare probate. Aplicațiile sunt aprobate, nu doar trimise magazinelor.
- ERP-ul real, bonul digital real, sursele externe, importul de la furnizor și domeniul producătorului au probe reale în limitele contractelor declarate.
- Modulele B/C, feedbackul și cele șase joburi AI sunt complete. Statutul experimental al unui produs sau respingerea legitimă a unui dosar nu se falsifică pentru a marca softwareul finalizat.
- Nu există defecte critice/majore, încălcări de izolare, blocaje alimentare/juridice sau alerte ascunse de cache. Problemele minore rămase nu împiedică vreun flux ori cerință și au acceptare nominală.
- Țintele de capacitate și recuperare au fost probate pe infrastructura de livrare. Dacă nu au fost atinse, migrarea și retestarea rămân lucru deschis.
- Operatorul are codul, datele, cheile și conturile sub control, documentația, exporturile, runbookurile, inventarul de dependențe, bugetele recurente și persoanele de suport.
- Dependențele E01–E12 sunt închise prin dovezi și există raportul final de stabilizare și predare.

Nicio integrare obligatorie absentă nu este mutată într-o listă vagă de îmbunătățiri. Dacă un partener, un emitent sau un export real lipsește, se continuă lucrul independent, iar cerința dependentă rămâne deschisă, cu responsabil și termen revizuit. Numai o decizie explicită a proprietarului poate modifica aria; nu se schimbă definiția finalizării pentru a declara proiectul închis.

### Ce se predă operatorului

Repozitoriul și release-urile, schemele/migrările, buildurile semnate și procedura de semnare, OpenAPI/exemple/colecții de test, contractele receipt/sync/export, documentația de administrare, politicile aprobate, standardele/profilurile adoptate, inventarul licențelor, rapoartele de acceptare, manifestele PBT, planul de backup/restore, contactele și costurile serviciilor, cheile în canal sigur și instrucțiunile pentru rotația lor.

Costurile se bugetează distinct: dezvoltare și specialiști, hosting/capacitate/backup, domenii/TLS, conturi și CI pentru aplicații, email/push unde se aplică, OCR/AI/date licențiate, audit și suport. WP00/E12 aduc oferte și limite reale; acest document nu inventează prețuri ale furnizorilor și nu autorizează achiziții.

## 16. Actualizarea documentației pe parcurs

La fiecare pachet se actualizează punctual specificația și contractele afectate, exemplele API, migrarea și dovezile. Pagina comercială reflectă numai funcțiile livrate și nivelul de testare obținut. Istoria OpenBread, originalele și rapoartele vechi nu se rescriu global.

Sursele oficiale citate au fost consultate pentru planul din 24 octombrie 2026. Versiunile GS1, politicile magazinelor, matricea SDK/OS, licențele API și cerințele juridice se reverifică la pornirea pachetului relevant și înaintea lansării. Schimbarea lor produce o actualizare explicită a planului și testelor, păstrând istoricul.

Starea de la redactare: plan și matrice create; execuția extensiilor, acceptarea lor și data de lansare nu sunt confirmate prin acest document.

