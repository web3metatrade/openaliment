# OpenAliment — matricea completă de acceptare

Plan OPENALIMENT-FULL-2026-10-24 · 24 octombrie 2026 · 132 scenarii obligatorii

Această matrice definește probele de executat pentru [planul complet](../OPENALIMENT_IMPLEMENTATION_PLAN_2026-10-24.md). Starea inițială a fiecărui scenariu este **planificat**, nu trecut. Rapoartele de verificare existente rămân dovezi pentru versiunile istorice, fără a demonstra extensiile noi.

## Precizare autorizată pentru execuția din 24 octombrie

Proprietarul a autorizat simulatoare ERP și magazin, fără un partener fix. Probele contractelor și mapărilor se execută cu acești simulatori și se identifică explicit drept simulate. Această precizare prevalează asupra cerinței de partener real pentru implementarea WP13/E05/E06; probele fizice POS, livrarea emailului real și publicarea în magazine rămân distincte. Vezi [jurnalul execuției](../implementation/EXECUTION_2026-10-24.md).

## Reguli de execuție

QA coordonează probele împreună cu responsabilul pachetului. Probele alimentare/juridice necesită specialistul desemnat, cele comerciale partenerul real, iar cele de publicare aprobările reale ale magazinelor. Conținutul cazurilor se transformă în pași și fixture-uri versionate înaintea execuției; nu se schimbă rezultatul așteptat pentru a ascunde o eroare.

Fiecare execuție înregistrează ID, build/commit, migrări, mediu, dispozitiv/OS, data, evaluator, intrări anonimizate, rezultat așteptat/obținut, dovezi și defecte. Rezultatele permise sunt planificat, în execuție, trecut, eșuat sau blocat. „Blocat” și „neexecutat” nu înseamnă acceptat. Retestarea păstrează istoricul.

Toate cerințele R01–R56 și pachetele WP00–WP27 au scenarii asociate în [backlogul JSON](openaliment-backlog-2026-10-24.json). Dependențele externe au propriile dovezi de închidere. Testele de mai jos pot fi descompuse în mai multe exemple automate și manuale; numărul rândurilor nu este numărul total de aserțiuni.

### Ce înseamnă dovadă reală

- Integrare reală: sistemul partenerului autorizat schimbă datele în fluxul convenit; un mock nu îl înlocuiește.
- Telefon real: model, OS și versiune a aplicației înregistrate; emulatorul este probă suplimentară.
- POS real: echipament și configurație convenite cu magazinul; citirea optică și tranzacția comercială sunt rezultate diferite.
- Publicare reală: aplicație aprobată și instalabilă din magazinul public al operatorului.
- Restore real: date și fișiere reconstruite într-un mediu separat și verificate.
- Încărcare: infrastructură autorizată, profil, volum, latențe, erori, cost și limite înregistrate.
- Revizie: persoană eligibilă identificată și decizie motivată; aprobarea tehnică nu substituie specialistul alimentar sau juridic.

Datele de test sunt izolate. GTIN-ul 952 demonstrativ nu intră în registrul comercial și nu se folosește ca articol comercial real. Fotografii, bonuri, conturi și date sensibile sunt utilizate numai cu autorizare și minimizare. Rapoartele publice nu conțin secrete sau date personale.

## Scenarii

### Pregătirea execuției

| ID / pachet | Cerințe | Situație de probă | Rezultat obligatoriu | Dovadă |
|---|---|---|---|---|
| AT001 · WP00 | R01, R41, R46, R50 | Se închide inventarul inițial și se atribuie nominal resursele, bugetele și dependențele E01–E12. | Baseline-ul codului și arhivei este identificabil, copia este restaurabilă, iar calendarul cu resurse reale și responsabili este acceptat. | Inventar+backup+decizie nominală |

### Produs, lot, resolver și compatibilitate

| ID / pachet | Cerințe | Situație de probă | Rezultat obligatoriu | Dovadă |
|---|---|---|---|---|
| AT002 · WP03 | R01, R02, R03 | Organizație verificată publică un iaurt fără rețetă, licență deschisă sau siglă. | Articolul, eticheta aprobată și lotul se publică; QR-ul deschide pagina corectă cu AI oprit. | API+UI+HTTPS real |
| AT003 · WP03 | R03 | GTIN cu cifră greșită; GTIN valid fără mandat; mandat aprobat sau contestat. | Validarea numerică și dreptul sunt decizii distincte; numai organizația autorizată administrează identificatorul. | Automat+revizie de mandat |
| AT004 · WP03 | R02, R08 | Se generează QR generic de produs și QR pentru un lot precis. | Codurile au destinații și identități distincte; pagina generică nu pretinde că știe lotul cumpărătorului. | Decodare+HTTPS |
| AT005 · WP02 | R04, R06 | Se publică versiunea V2 după un lot legat de V1. | Lotul vechi păstrează V1; produsul curent poate afișa V2; alertele curente rămân suprapuse. | Integrare DB+UI |
| AT006 · WP03 | R04, R35, R43 | Ciornă, fișier privat și rețetă privată coexistă cu o etichetă aprobată. | HTML, JSON, linksetul și documentele publice expun numai proiecțiile aprobate. | Contract+test acces negativ |
| AT007 · WP24 | R01, R06, R48 | Se rulează corpusul actual pentru rute publice, administrare și API v1 după migrări. | Rutele și tipurile existente rămân compatibile; orice mapare necesară este explicită și testată. | Regresie automată |
| AT008 · WP24 | R07, R08, R48 | Se execută matricea normativă a resolverului pentru profilul GTIN declarat. | Negocierea, linkseturile, decompresia și erorile respectă specificația declarată; raportul indică limitele. | Corpus GS1+HTTPS cPanel |
| AT009 · WP08 | R08 | Loturi cu litere mici/mari, separator, caractere permise și escape-uri trec prin URI și server. | Se păstrează lotul exact, fără dublă decodare, coliziuni sau ocolirea autorizării; formele nepermise se resping clar. | Corpus encoding+HTTPS |
| AT010 · WP02 | R05, R06 | Un import v1 cu alergeni și nutriție ca text este migrat și citit și prin v1. | Textul original se păstrează; propunerile structurate nu devin aprobate automat; necunoscut nu devine zero. | Round-trip+contract |
| AT011 · WP19 | R03, R40 | O organizație încearcă să revendice GTIN-ul sau hostul altei organizații. | Nu obține acces ori prioritate prin simpla cerere; există audit și rezolvare autorizată a conflictului. | Test izolare+revizie |

### Identitate personală, persistență și recuperare

| ID / pachet | Cerințe | Situație de probă | Rezultat obligatoriu | Dovadă |
|---|---|---|---|---|
| AT012 · WP04 | R11, R15 | Persoană nouă deschide webul și salvează primul produs. | Nu apare formular de cont, parolă sau instalare înainte de salvare; inventarul local este disponibil. | UI pe telefon real |
| AT013 · WP04 | R11, R12 | Aplicația și browserul sunt închise și redeschise pe dispozitiv autorizat. | Inventarul și autorizarea persistă conform politicii; utilizarea obișnuită nu cere login. | Android+iPhone+web real |
| AT014 · WP04 | R12 | Un vizitator cu inventar local se conectează la un cont cu inventar existent. | Unirea este idempotentă, fără pierderi; ambalajele distincte nu sunt colapsate ca simple duplicate. | Integrare+rețea întreruptă |
| AT015 · WP04 | R12, R43 | Se folosește magic link expirat, refolosit, preîncărcat de email sau destinat altui flux. | Linkul are utilizare unică și confirmare sigură; prefetchul nu autentifică un atacator și nu consumă inutil accesul. | Securitate+email real |
| AT016 · WP04 | R12, R29 | Passkey este creat, folosit, pierdut; dispozitivul nu suportă metoda. | Autentificarea și recuperarea au alternative accesibile; nu există blocaj permanent sau slăbirea contului. | Dispozitive reale+revizie |
| AT017 · WP04 | R12 | Se activează opțiunile Apple/Google și apoi se leagă/dezleagă o identitate. | Verificarea furnizorului este corectă; conturile nu se unesc numai pe baza unui email nevalidat. | Integrare reală+negativ |
| AT018 · WP04 | R12, R43 | Se revocă un dispozitiv pierdut și se încearcă refolosirea tokenului vechi. | Tokenul rotit/revocat nu mai permite acces; celelalte dispozitive autorizate continuă să funcționeze. | API+dispozitiv real |
| AT019 · WP04 | R11, R12 | Stocarea locală este ștearsă sau aplicația este reinstalată. | Datele sincronizate se recuperează; pierderea inventarului exclusiv local nu este ascunsă printr-o promisiune de backup. | Reinstalare reală |
| AT020 · WP04 | R43 | Un cont personal încearcă să intre în administrarea unui producător. | Nu primește organizație, rol sau acces doar pentru că este autentificat ca utilizator. | Test autorizare |
| AT021 · WP23 | R42, R53, R54 | Persoana activează, refuză și retrage sincronizarea preferințelor sensibile. | Datele locale și cloud au scopuri distincte; retragerea oprește prelucrările aplicabile și urmează politica aprobată. | Revizie juridică+API |
| AT022 · WP04 | R12, R43 | Se încearcă o schimbare sensibilă din sesiune veche sau de pe dispozitiv comun. | Se cere reautentificare adecvată; logoutul și lista dispozitivelor funcționează. | Securitate+UI |
| AT023 · WP04 | R53 | Persoana cere export și ștergerea contului prin aplicație și canalul web. | Cererea este realizabilă, verificată și executată conform retenției; nu șterge datele altor membri fără drept. | UI+API+revizie |

### Scanare și informații externe

| ID / pachet | Cerințe | Situație de probă | Rezultat obligatoriu | Dovadă |
|---|---|---|---|---|
| AT024 · WP08 | R07, R08 | Se scanează corpusul EAN/UPC și GS1 2D declarat pe telefoanele din matrice. | Identificatorii și semantica sunt corecte; formatele nesuportate sunt comunicate fără valori fabricate. | Dispozitive reale+corpus |
| AT025 · WP08 | R07, R19 | Același ambalaj este citit în mai multe cadre, inclusiv cu EAN și QR vizibile. | Nu se creează două ambalaje din aceeași scanare continuă; Anulează funcționează. | Video+evenimente |
| AT026 · WP08 | R19 | Persoana adaugă două ambalaje identice intenționat. | Sistemul permite două unități sau cantitatea corectă, cu date/deschideri ulterior distincte. | UI+sincronizare |
| AT027 · WP08 | R08, R18 | Codul include date GS1 valide/invalide și precizie specială permisă de standard. | Se aplică interpretarea standardului; o dată imposibilă sau incompletă nu devine termen exact inventat. | Corpus normativ |
| AT028 · WP09 | R09, R52 | Se scanează produse de pe două resolvere externe independente și din Open Food Facts. | Se identifică sursa și licența; produsul poate fi adăugat; lipsa nutriției sau lotului rămâne explicită. | HTTPS extern real+manifest |
| AT029 · WP09 | R09, R14 | Resolverul extern răspunde lent, returnează eroare sau nu are resursa cerută. | Salvarea personală nu se blochează; îmbogățirea se poate relua și nu inventează câmpuri. | Integrare cu fault injection |
| AT030 · WP09 | R05, R35, R52 | Două surse oferă ingrediente sau nutrienți diferiți pentru piețe/versiuni diferite. | Conflictele și proveniența se păstrează; nu se înlocuiește tăcut informația aprobată. | Contract+revizie de date |
| AT031 · WP23 | R43 | QR-ul extern indică localhost, rețea privată, redirect periculos sau DNS rebinding. | Fetchul este blocat și nu divulgă tokenuri, cookie-uri sau preferințe ale persoanei. | Test SSRF controlat |
| AT032 · WP08 | R10, R18 | Data lipsește din cod; fotografia conține două date sau este neclară. | Aplicația cere cel mult o confirmare contextuală; dacă nu există confirmare, termenul rămâne necunoscut. | Fotografii autorizate+UX |
| AT033 · WP08 | R29, R55 | Aliment fără cod este adăugat prin fotografie sau voce. | Se salvează privat fără tastare obligatorie; nu se publică și nu primește valori alimentare inventate. | Telefon real+permisiuni |
| AT034 · WP08 | R07, R26 | Se scanează un cod local de magazin sau cu greutate variabilă. | Se folosește maparea autorizată a comerciantului; codul nu este considerat automat GTIN global. | Date reale de partener |
| AT035 · WP06 | R15, R56 | QR-ul este deschis cu camera sistemului la raft și apoi din scannerul aplicației. | Consultarea paginii nu adaugă automat în inventar; modul explicit Adaugă permite salvarea directă. | Android+iPhone+web real |

### Inventar, gospodării și offline

| ID / pachet | Cerințe | Situație de probă | Rezultat obligatoriu | Dovadă |
|---|---|---|---|---|
| AT036 · WP05 | R13, R19 | Două ambalaje din același lot sunt deschise în zile diferite. | Au stări și termene după deschidere independente; identitatea lotului nu este duplicată fals. | Integrare+UI |
| AT037 · WP05 | R18, R19 | Am deschis, mutare în congelator, consum și Anulează sunt folosite succesiv. | Evenimentele și istoricul sunt corecte; anularea restabilește starea și programul de notificare. | Reguli+UI |
| AT038 · WP05 | R14, R19 | Aceleași operațiuni sunt repetate după timeout sau retry de rețea. | Operațiunea idempotentă se aplică o singură dată; cantitatea nu devine negativă. | Integrare concurentă |
| AT039 · WP05 | R13, R14 | Două telefoane modifică offline același ambalaj și apoi se reconectează. | Conflictele au rezolvare deterministă sau cerere simplă; niciun eveniment nu dispare fără trasabilitate. | Două dispozitive reale |
| AT040 · WP05 | R13, R43 | Invitația gospodăriei expiră, este revocată sau este reutilizată de altă persoană. | Accesul neautorizat este refuzat; rolul permis este aplicat la fiecare obiect. | Securitate+UI |
| AT041 · WP05 | R13, R54 | Un membru limitat/minor vede inventarul comun. | Nu primește implicit datele sensibile ale altor membri sau drepturi de administrare. | Matrice roluri+revizie |
| AT042 · WP05 | R19 | Se folosesc ambalaje, grame și mililitri, inclusiv cantitate parțială. | Unitățile nu sunt amestecate; persoana poate folosi fluxul simplu fără cântărire obligatorie. | Corpus unități+UX |
| AT043 · WP06 | R14, R31 | PWA se deschide offline după o alertă și apoi revine online. | Arată vechimea datelor; nu declară că nu există alerte actuale; reîmprospătarea corectează starea. | Service worker+telefon |
| AT044 · WP05 | R19 | Un aliment trece de termen fără acțiunea persoanei. | Nu este șters sau marcat consumat automat; apare în lista potrivită și poate fi corectat. | Timp simulat+UI |
| AT045 · WP05 | R13, R53 | Membrul părăsește gospodăria sau este eliminat, apoi exportă date. | Accesul comun se revocă; exportul respectă proprietatea și drepturile celorlalți. | API+verificare export |

### Reguli alimentare și ajutor pentru persoane cu diabet

| ID / pachet | Cerințe | Situație de probă | Rezultat obligatoriu | Dovadă |
|---|---|---|---|---|
| AT046 · WP10 | R05, R16, R22 | Alergen declarat, posibile urme și lipsă de date sunt întâlnite separat. | Cele trei stări au mesaje distincte; lipsa nu produce afirmație de absență sau siguranță. | Corpus expert+UI |
| AT047 · WP10 | R16, R35 | Eticheta V2 schimbă alergeni, iar un lot vechi rămâne la V1. | Se păstrează informația aplicabilă lotului și se suprapun eventualele alerte de corecție. | Integrare DB+expert |
| AT048 · WP10 | R17, R22 | Se compară două produse cu zahăr redus și carbohidrați diferiți. | Prezentarea folosește carbohidrații și porția; nu emite verdict medical ori doză de insulină. | Revizie nutriție+reguli |
| AT049 · WP10 | R05, R17 | Nutriție per 100 g, per 100 ml, per porție și produs preparat. | Calculul se face numai cu baza și conversia valide; datele incompatibile rămân separate. | Corpus numeric+expert |
| AT050 · WP10 | R05, R17 | Date UE și date dintr-un regim cu altă definiție a carbohidraților. | Definiția și sursa sunt păstrate; nu se scad fibre sau polioli fără o regulă justificată. | Revizie de schemă+expert |
| AT051 · WP10 | R18, R22 | Termen de siguranță și termen de calitate în aceeași listă de produse. | Mesajele și prioritățile diferă corect; niciun mesaj nu garantează lanțul frigorific. | Corpus EFSA+UX |
| AT052 · WP10 | R18 | Instrucțiune după deschidere depășește data-limită a ambalajului. | Aplicația nu prelungește data de siguranță și explică informația aplicabilă. | Reguli deterministe+expert |
| AT053 · WP10 | R18 | Persoana congelează un produs cu termen cunoscut sau necunoscut. | Evenimentul se păstrează; nu apare o nouă valabilitate fără instrucțiune și condiții adecvate. | Reguli+UI |
| AT054 · WP10 | R10, R18 | Data are numai lună/an sau utilizatorul alege un memento personal. | Precizia se păstrează; mementoul nu este prezentat ca termen verificat. | Contract+UI |
| AT055 · WP11 | R21, R22 | Există retragere pentru un lot, iar persoana are GTIN cunoscut și lot necunoscut. | Primește avertizarea de posibilă afectare și instrucțiunea de verificare, fără verdict fals. | Reguli+mesaj expert |
| AT056 · WP14 | R22, R28 | O sugestie ar folosi produs retras sau alergen incompatibil cunoscut. | Sugestia este exclusă sau blocată adecvat; datele insuficiente nu sunt ascunse. | Corpus recomandări+expert |
| AT057 · WP14 | R27, R28 | Persoana folosește lista de cumpărături, porții și alternative. | Acțiunile sunt facultative, sursele/licențele sunt clare, comparațiile folosesc aceeași bază validă. | UI+revizie surse |

### Notificări și retrageri

| ID / pachet | Cerințe | Situație de probă | Rezultat obligatoriu | Dovadă |
|---|---|---|---|---|
| AT058 · WP11 | R20, R29 | Permisiunea pentru notificări este cerută după o salvare relevantă și apoi refuzată. | Beneficiul este explicat simplu; refuzul nu blochează inventarul și nu provoacă solicitări repetate agresive. | Android+iPhone+web real |
| AT059 · WP11 | R20 | Se programează memento local, APNs/FCM, Web Push și email facultativ. | Canalele sunt testate cu mesaje reale; stările de trimitere și limitele platformelor sunt înregistrate distinct. | Livrare pe dispozitiv+email |
| AT060 · WP11 | R19, R20 | Ambalajul este consumat pe alt dispozitiv înaintea notificării. | Programarea este anulată/reconciliată; nu se mai notifică acel consum ca produs rămas. | Două dispozitive+coadă |
| AT061 · WP11 | R20, R54 | Mai multe produse ajung la termen, cu ore liniștite și profil privat. | Sumarul evită spamul; ecranul blocat nu dezvăluie alergii/condiții medicale fără opțiunea persoanei. | Telefon real+revizie |
| AT062 · WP11 | R04, R21 | O alertă este emisă, corectată, închisă sau retrasă justificat. | Istoricul rămâne; starea curentă se propagă fără a rescrie snapshotul lotului. | UI+API+coadă |
| AT063 · WP11 | R20, R21 | Furnizorul push sau cronul este indisponibil temporar. | Există retry limitat, deduplicare, monitorizare și stare reală; nu se pretinde livrare confirmată. | Fault injection+monitor |
| AT064 · WP06 | R20, R31 | Pe iPhone este folosit webul simplu și apoi PWA instalată eligibilă. | Opțiunile de notificare reflectă suportul real; nu se promite push în condiții nesuportate. | Safari/iPhone real |
| AT065 · WP22 | R21, R44 | Alertă aprobată în timp ce paginile sunt servite la încărcarea țintă. | Noile citiri arată alerta; cacheul nu o ascunde și cozile ating pragul temporal măsurat. | Încărcare autorizată+tracing |

### Bonuri și integrări ERP/magazin

| ID / pachet | Cerințe | Situație de probă | Rezultat obligatoriu | Dovadă |
|---|---|---|---|---|
| AT066 · WP12 | R23, R24 | Se fotografiază un bon cu alimente, nealimentare, discounturi, retur și cantitate cântărită. | Importul separă liniile corect și cere atenție numai pentru potrivirile ambigue. | Bonuri reale anonimizate+UI |
| AT067 · WP12 | R24 | Bonul nu are GTIN, lot sau termen. | Datele lipsă nu sunt deduse drept certe; numele/SKU au sursă și grad de potrivire. | Corpus bonuri+revizie |
| AT068 · WP12 | R19, R23 | Același bon este importat din fotografie, QR și retry API. | Idempotenta previne dublarea; eventualele corectări sunt evenimente distincte și trasabile. | Integrare+concurență |
| AT069 · WP12 | R15, R24 | Persoana confirmă un bon cu multe potriviri clare și două neclare. | Confirmă coșul o singură dată; numai cele două linii cer clarificare; anularea revine corect. | UX fără instruire |
| AT070 · WP12 | R23, R43 | Linkul de bon este ghicit, distribuit, expirat sau refolosit de persoană neautorizată. | Nu expune coșul sau datele de plată; emitentul și destinatarul sunt autorizați. | Securitate+API |
| AT071 · WP13 | R23, R25 | Magazinul pilot emite bon digital real și persoana îl importă în gospodăria sa. | Articolele/cantitățile se potrivesc sistemului partener; datele personale inutile sunt eliminate. | Sistem real de retailer |
| AT072 · WP13 | R26 | ERP-ul pilot creează articol, versiune de etichetă, lot și resurse QR. | Fluxul real ajunge la pagina aprobată, cu audit, scopeuri și tratamentul explicit al aprobării. | ERP real+HTTPS |
| AT073 · WP13 | R26, R43 | Webhookul este repetat, modificat, întârziat sau semnat greșit. | Semnătura/replay sunt verificate; retry și reconcilierea nu dublează și nu modifică neautorizat date. | Integrare+negativ |
| AT074 · WP13 | R26, R48 | Un integrator nou folosește numai OpenAPI, exemplele și instrucțiunile livrate. | Poate executa fluxul documentat; erorile, rate limitul, idempotenta și versiunile sunt coerente. | Probă de integrare independentă |
| AT075 · WP23 | R42, R53 | Bonurile sursă sunt păstrate, exportate și șterse conform politicii. | Nu apar informații de plată inutile în inventar sau rapoarte; retenția este aplicată efectiv. | Revizie+verificare storage |

### Documentație deschisă și pilotul PBT

| ID / pachet | Cerințe | Situație de probă | Rezultat obligatoriu | Dovadă |
|---|---|---|---|---|
| AT076 · WP16 | R33, R35 | Se completează formula, procesul, furnizorii, controlul, ambalarea și probele unui dosar. | Editorul acoperă toate cele 24 de secțiuni; referințele și stadiile se păstrează. | UI+export dosar |
| AT077 · WP16 | R33 | Se scalează formula și se calculează bilanț, randament, pierderi și scenarii de cost. | Calculele deterministe păstrează unități/rotunjiri și separă estimarea de valoarea observată. | Corpus numeric+expert |
| AT078 · WP16 | R33, R41 | Se importă și se compară PBT R1, COST1/COST2/COST3 și cercetarea. | Originalele și hashurile sunt intacte; COST3 rămâne estimare, R1 experimentală; nu apar rezultate inventate. | Manifest+round-trip |
| AT079 · WP16 | R41 | Fișier BT01 deteriorat și material recuperat sunt prezentate ca surse. | Avertizările și proveniența sunt vizibile; recuperarea nu este promovată automat la probă validată. | Revizie manuală+hash |
| AT080 · WP16 | R02, R33 | Se documentează pâine, lactat, băutură și alt aliment folosind profilurile inițiale. | Parametrii pâinii nu sunt impuși tuturor; criteriile adoptate și limitele profilului sunt explicite. | Expert+UI |
| AT081 · WP16 | R33, R52 | Dosarul este publicat parțial, cu licențe diferite și drepturi neconfirmate la unele fișiere. | Numai componentele permise sunt publice; licența este explicită pe versiune, fără extindere implicită. | ACL+revizie de licență |
| AT082 · WP16 | R33, R35 | Un rezultat, o fotografie și o afirmație de impact sunt modificate după publicare. | Se creează versiune/propunere nouă, cu dovadă și aprobare; istoricul rămâne accesibil conform drepturilor. | UI+audit |
| AT083 · WP18 | R39, R41 | Dosarul PBT este exportat și importat într-un mediu gol. | Versiunile, proveniența, fișierele și stadiile se reconstruiesc fără publicare sau siglă automată. | Round-trip comparat |

### Evaluare, acreditare și contestație

| ID / pachet | Cerințe | Situație de probă | Rezultat obligatoriu | Dovadă |
|---|---|---|---|---|
| AT084 · WP17 | R34, R35 | Emitentul, standardul și evaluatorii eligibili sunt configurați cu roluri reale. | Procedura și independența sunt aprobate; configurarea de test nu produce acreditare reală. | Revizie nominală+audit |
| AT085 · WP17 | R34 | Autorul sau un evaluator cu conflict încearcă să aprobe dosarul. | Decizia este blocată sau rerutată conform procedurii; conflictul este înregistrat. | Test roluri+revizie |
| AT086 · WP17 | R34 | O cerință OB-01–OB-23 eșuează ori are problemă blocantă. | Emiterea este blocată; completările și noua revizie păstrează istoricul. | Integrare DB+UI |
| AT087 · WP17 | R34 | Se încearcă marcarea integrală a unei cerințe OB drept neaplicabilă. | Standardul v0.1 nu permite ocolirea cerinței; numai excepțiile de câmp permise au motiv și aprobare. | Corpus de standard |
| AT088 · WP17 | R34 | Dosarul eligibil este aprobat de două cereri concurente; una eșuează în tranzacție. | Decizia, OB-24 și acreditarea sunt atomice, fără duplicat și fără dependență circulară de certificat. | Concurență+rollback DB |
| AT089 · WP17 | R34, R35 | Publicul deschide sigla SVG, PDF și pagina de verificare. | Vede emitentul, standardul, versiunea și aria evaluate, limitele și starea curentă. | HTTPS+revizie conținut |
| AT090 · WP17 | R34 | Acreditarea este suspendată, revocată, expirată sau înlocuită. | Registrul și verificarea afișează starea actuală și istoricul; badge-ul nu maschează revocarea. | Timp simulat+HTTPS |
| AT091 · WP17 | R34 | O versiune nouă, un producător nou sau un import copiază un dosar acreditat. | Nu primește automat acreditarea veche; relația și necesitatea reevaluării sunt explicite. | Import+reguli |
| AT092 · WP17 | R34, R42 | Este depusă contestație la decizia evaluatorului. | Alt evaluator eligibil gestionează revizia; motivarea este publicabilă cu datele private protejate. | Flux integral+revizie |
| AT093 · WP17 | R34, R35 | Se încearcă emiterea pentru PBT fără drepturi sau rezultate suficiente, inclusiv după plată. | Nu se fabrică eligibilitate; plata, verificarea GTIN și găzduirea nu acordă sigla. | Revizie de dosar+negativ |

### Comunitate, căutare și AI

| ID / pachet | Cerințe | Situație de probă | Rezultat obligatoriu | Dovadă |
|---|---|---|---|---|
| AT094 · WP15 | R36 | Recenzie negativă, întrebare și sesizare sunt asociate produsului și lotului cunoscut. | Moderarea și auditul păstrează semnalul; scanarea nu acordă fals achiziție verificată. | UI+audit |
| AT095 · WP15 | R36 | Un utilizator votează repetat și încearcă importul de identități/voturi privilegiate. | Unicitatea și autorizarea sunt respectate; importul nu creează autoritate de vot. | Integrare+negativ |
| AT096 · WP15 | R35, R36 | Feedbackul conduce la o propunere de schimbare a etichetei sau rețetei. | Propunerea are versiuni și decizie; datele aprobate nu sunt rescrise direct de recenzie sau AI. | Flux integral+audit |
| AT097 · WP15 | R37, R43 | Căutarea și public_qa întâlnesc rețetă privată și documente ale altui tenant. | Nici conținutul, nici existența sensibilă nu se divulgă prin fragmente, citări sau răspunsuri. | Corpus adversarial+ACL |
| AT098 · WP15 | R37 | Permisiunea sau versiunea documentului se schimbă în timpul jobului. | ACL/hashul sunt reverificate; ieșirea neautorizată sau cu citări invalide nu se salvează public. | Integrare cu concurență |
| AT099 · WP15 | R38 | Se execută document_map, dossier_gap_check, feedback_triage, feedback_digest, experiment_proposal, public_qa. | Fiecare job respectă schema, sursele, drepturile și fluxul de aprobare; există apel real autorizat documentat. | Provider real+corpus |
| AT100 · WP15 | R14, R38 | AI este dezactivat, fără buget sau indisponibil în timpul scanării și folosirii inventarului. | QR, datele aprobate, inventarul și regulile esențiale funcționează; nu apar fapte generate de avarie. | Fault injection+E2E |
| AT101 · WP15 | R38 | Jobul expiră, se reia, depășește bugetul sau primește prompt injection dintr-un document. | Retry/lease/buget sunt aplicate; instrucțiunile din surse nu acordă acces ori puteri de modificare. | Securitate+coadă |
| AT102 · WP15 | R17, R22, R28 | Asistentul personal răspunde unei întrebări despre porție, alergen sau folosirea alimentelor. | Explică numai informațiile accesibile și sursele; nu inventează termen, siguranță, diagnostic sau doză medicală. | Expert+red team |

### Portabilitate și domenii

| ID / pachet | Cerințe | Situație de probă | Rezultat obligatoriu | Dovadă |
|---|---|---|---|---|
| AT103 · WP18 | R06, R39 | Se importă pachete OpenBread vechi, v1 curent și flux NDJSON mare. | Dry-run și importul produc mapări/rapoarte; reluarea este sigură și memoria rămâne în buget. | Corpus+încărcare de import |
| AT104 · WP18 | R39, R52 | Exportul complet al producătorului este reimportat într-un mediu gol. | Datele, versiunile, documentele, licențele și referințele sunt echivalente, cu diferențe explicate. | Round-trip+manifest |
| AT105 · WP18 | R39, R53 | Se exportă inventarul personal și datele gospodăriei permise. | Pachetul este reutilizabil și nu divulgă date sensibile ale altor membri; ștergerea urmează politica. | Round-trip+ACL |
| AT106 · WP18 | R03, R39, R43 | Pachetul importat include tokenuri, roluri, mandat GTIN sau siglă pretinsă. | Nu importă privilegii/secrete; autoritatea și drepturile se verifică separat. | Import ostil+revizie |
| AT107 · WP18 | R39, R48 | Se primește un export real autorizat de la furnizor, inclusiv serviciul GS1 vizat dacă este disponibil. | Adaptorul mapează câmpurile și pierderile explicit; nu se declară migrare GS1 fără export și probă reală. | Fișier real+reconciliere |
| AT108 · WP19 | R40 | Se activează domeniu de producător prin DNS/HTTP și TLS. | Controlul și certificatul sunt probate; identitatea și tenantul sunt corecte pe hosturile autorizate. | DNS+HTTPS real |
| AT109 · WP19 | R40, R43 | Cererea folosește Host fals, certificat expirat sau domeniu cu control retras. | Hostul neautorizat nu este servit ca tenant; se alertează și se aplică procedura sigură. | Securitate+TLS |
| AT110 · WP19 | R01, R40 | Clientul mută datele și destinația păstrând QR-uri deja imprimate. | Proba distinge domeniul propriu de openaliment.ro; continuitatea se asigură prin control DNS sau acordul aplicabil. | Migrare reală+HTTPS |

### Infrastructură, securitate și capacitate

| ID / pachet | Cerințe | Situație de probă | Rezultat obligatoriu | Dovadă |
|---|---|---|---|---|
| AT111 · WP21 | R45, R46 | Se instalează și actualizează release-ul pe staging cPanel fără root/shell. | Configurația rămâne în afara publicului; migrarea și revenirea sunt repetabile, fără secrete în pachet. | Staging real+manifest |
| AT112 · WP21 | R38, R45 | Cronul/runnerul programat execută cozi, iar cereri neautorizate încearcă execuții arbitrare. | Joburile fixe rulează în limite și sunt monitorizate; nu există shell/exec/upload public arbitrar. | Hosting real+negativ |
| AT113 · WP23 | R42, R43, R54 | Se execută matricea de acces pentru tenant, gospodărie, fișiere și administrare. | Accesul orizontal/vertical și datele sensibile sunt protejate; nu rămân defecte critice sau majore. | Audit securitate |
| AT114 · WP23 | R43 | Se încarcă documente ostile, ZIP bomb, path traversal, SVG activ și document cu XXE. | Fișierele sunt respinse/carantinate fără execuție, ieșire din storage sau epuizare necontrolată. | Corpus de securitate |
| AT115 · WP22 | R44 | Se rulează profilul susținut și rafala de încărcare convenite. | Capacitatea, latența, erorile și costurile ating țintele sau se livrează capacitate nouă și retestare. | Raport load autorizat |
| AT116 · WP22 | R04, R21, R44 | Sub încărcare se schimbă destinația resolverului și se aprobă o alertă. | Istoricul rămâne, noile citiri sunt corecte, cacheul nu servește stare de siguranță veche. | Load+tracing+UI |
| AT117 · WP21 | R46 | Se restaurează DB, storage și configurația într-un mediu separat. | Se ating RPO/RTO, hashurile și mapările QR sunt corecte; alertele recente sunt reconciliate. | Restore real cronometrat |
| AT118 · WP26 | R45, R46 | Se simulează incident DB, AI, email, push sau serviciu extern și se revine. | Runbookul este executabil de responsabilul desemnat; monitorizarea și degradarea sunt corecte. | Exercițiu operațional |
| AT119 · WP23 | R42, R53, R54 | Se revizuiesc temeiurile, retenția, furnizorii și necesitatea DPIA. | Documentele și implementarea coincid; orice DPIA necesară și acțiunile sale sunt închise înaintea datelor reale. | Revizie nominală juridică |
| AT120 · WP22 | R44, R46 | Se verifică pragurile de cost, metricile și alertele operaționale. | Responsabilul primește alerta reală; lipsa unui furnizor nu produce cheltuieli sau retry necontrolat. | Monitor real+buget |
| AT121 · WP21 | R43, R45 | Se verifică mecanismul HTTPS de mentenanță, tokenurile și fișierele de setup. | Numai operații autorizate și căi permise; accesul temporar inutil este eliminat; secretele expuse sunt rotite. | Revizie+probe negative |

### Accesibilitate, conținut și distribuție finală

| ID / pachet | Cerințe | Situație de probă | Rezultat obligatoriu | Dovadă |
|---|---|---|---|---|
| AT122 · WP01 | R15, R29, R51 | Minimum 20 de persoane reprezentative folosesc aplicația fără instruire. | Minimum 90% recunosc acțiunea următoare în 3 s și finalizează fluxurile de bază fără ajutor. | Studiu UX+raport anonim |
| AT123 · WP24 | R29, R51 | Se folosesc cititor de ecran, tastatură, text mărit, contrast și orientări diferite. | Fluxurile esențiale sunt utilizabile, etichetele sunt în română, mesajele nu depind numai de culoare. | Audit a11y+dispozitive |
| AT124 · WP07 | R07, R14, R29, R30 | Pe Android accesibil ca preț și iPhone se scanează în lumină diferită, offline și în fundal. | Funcțiile declarate se probează; limitele de focalizare/permisiuni/baterie au răspuns util și fără pierderi. | Matrice dispozitive reale |
| AT125 · WP20 | R32, R49 | Se verifică landingul, imaginea aleasă, exemplul PBT, ghidurile și mesajele comerciale. | Imaginea/proveniența sunt păstrate; demo-ul este distinct; afirmațiile corespund implementării probate. | Revizie conținut+UI |
| AT126 · WP20 | R48, R49 | Se verifică SEO, sitemap, canonical, metadate, date structurate și suportul de contact. | Paginile utile sunt indexabile corect; privatele nu intră în sitemap; contactul ajunge la operator. | Audit tehnic+contact test |
| AT127 · WP25 | R30, R42 | Aplicațiile sunt trimise și evaluate în conturile operatorului Apple/Google. | Declarațiile și politicile corespund funcțiilor și datelor reale; observațiile magazinelor sunt rezolvate. | Aprobări reale ale magazinelor |
| AT128 · WP25 | R12, R30 | O persoană instalează versiunile publice și actualizează o versiune cu inventar existent. | Aplicațiile sunt disponibile efectiv în magazine; upgradeul păstrează datele și drepturile. | Instalare publică reală |
| AT129 · WP24 | R07, R08, R47 | QR real este imprimat la dimensiunile și pe ambalajele convenite. | Decodarea, conținutul, quiet zone și pagina HTTPS sunt corecte; fotografia ilustrativă nu este proba. | Tipar fizic+scanare |
| AT130 · WP24 | R03, R47 | POS-ul partenerului citește QR demo în test și articol comercial autorizat în proba convenită. | Se distinge decodarea de înregistrarea articolului; nu se afirmă compatibilitate cu toate magazinele. | POS real+confirmare partener |
| AT131 · WP26 | R02, R14, R46 | Pilotul cu producător non-pâine, parteneri și minimum zece gospodării rulează 14 zile. | Fluxurile sunt folosite real, incidentele remediate, datele reconciliate și suportul funcțional. | Jurnal de stabilizare |
| AT132 · WP27 | R48, R50 | Se face predarea și auditul final R01–R56/WP00–WP27/E01–E12. | Toate probele obligatorii și accesurile sunt predate; nicio integrare cerută nu este înlocuită cu simulator. | Proces-verbal+registru dovezi |

## Praguri de închidere

Toate cele 132 scenarii sunt executate și trecute, cu remedierea defectelor critice/majore și a oricărui blocaj de confidențialitate sau siguranță. Acceptarea nominală a detaliilor minore nu poate elimina o funcție cerută ori înlocui o probă reală.

Țintele UX, capacitate, latență, propagarea alertelor, RPO/RTO și stabilizarea sunt definite în planul principal. Dacă se schimbă un prag printr-o decizie explicită, se versionează planul și matricea înaintea acceptării; nu se rescriu rapoartele vechi.

Închiderea WP27 reconciliază acest registru, cele 56 de cerințe, 28 de pachete și 12 dependențe externe. Orice lipsă rămâne vizibilă până la rezolvare sau la o schimbare de scop autorizată explicit.

