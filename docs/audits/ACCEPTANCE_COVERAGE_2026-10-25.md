# Acoperirea scenariilor de acceptare — audit 25.10.2026

Cod 0.5.5. [Audit principal](PRODUCTION_READINESS_AUDIT_2026-10-25.md) · [registru implementare](IMPLEMENTATION_COVERAGE_2026-10-25.md) · [date JSON](production-readiness-2026-10-25.json).

**Acesta este un registru de acoperire, nu un raport care declară scenariile trecute.** Toate scenariile au fost evaluate față de implementare; nu au fost reexecutate individual și semnate ca acceptare integrală. Cele 40 de suite locale și smoke-urile publice sunt dovezi pentru domeniul lor, fără a înlocui probele fizice, operaționale sau umane cerute.

„Acoperire automată parțială” indică o funcție prezentă și teste candidate în pachet; poate rămâne o probă de producție, expert sau dispozitiv. „Funcții/probe încă lipsă” poate combina software existent cu o operațiune neimplementată. „Neimplementat” privește operațiunea din caz, nu toate componentele pachetului.

R25/R26 și AT071/AT072 aplică autorizarea utilizatorului pentru ERP/magazin simulate. AT130 rămâne test fizic POS. AT132 aplică aceeași excepție fără a înlocui restul probelor. [Specificația inițială a celor 132 cazuri](../plans/OPENALIMENT_ACCEPTANCE_MATRIX_2026-10-24.md) și stările ei istorice rămân intacte.

## Cele 132 de scenarii ale planului extins

### Pregătirea execuției

| ID / pachet | Scenariu | Acoperire în audit | Lipsuri / limită de acceptare |
|---|---|---|---|
| AT001 · WP00 | Se închide inventarul inițial și se atribuie nominal resursele, bugetele și dependențele E01–E12. | Funcții/probe încă lipsă | Inventarul de surse/teste este disponibil. Lipsesc atribuirea nominală, bugetele asumate și copia restaurată care închid cazul. |

### Produs, lot, resolver și compatibilitate

| ID / pachet | Scenariu | Acoperire în audit | Lipsuri / limită de acceptare |
|---|---|---|---|
| AT002 · WP03 | Organizație verificată publică un iaurt fără rețetă, licență deschisă sau siglă. | Acoperire automată parțială | Creare/publicare fără rețetă și fără siglă probate local. Rămâne proba cu un producător non-pâine și date reale aprobate. |
| AT003 · WP03 | GTIN cu cifră greșită; GTIN valid fără mandat; mandat aprobat sau contestat. | Acoperire automată parțială | Checksum și mandat sunt separate; mandatul real nu poate fi fabricat în fixture. |
| AT004 · WP03 | Se generează QR generic de produs și QR pentru un lot precis. | Acoperire automată parțială | QR generic și QR de lot sunt generate și decodate în teste; proba fizică pe ambalaj este separată, nu o lipsă a generatorului. |
| AT005 · WP02 | Se publică versiunea V2 după un lot legat de V1. | Acoperire automată parțială | Snapshotul V1 al lotului este păstrat în testele de integrare. Se cere dovadă exactă pentru cazul V1/V2 și upgrade pe copia DB instalată. |
| AT006 · WP03 | Ciornă, fișier privat și rețetă privată coexistă cu o etichetă aprobată. | Acoperire automată parțială | Proiecția publică și refuzul datelor private sunt acoperite automat. Verificarea cu roluri și documente reale se face fără publicarea fixture-urilor. |
| AT007 · WP24 | Se rulează corpusul actual pentru rute publice, administrare și API v1 după migrări. | Acoperire automată parțială | Regresia locală completă trece. CI Linux/MariaDB 10.6 și upgrade de la instalația efectivă nu sunt demonstrate de rularea pe Windows/11.4. |
| AT008 · WP24 | Se execută matricea normativă a resolverului pentru profilul GTIN declarat. | Acoperire automată parțială | Profil GTIN 01 declarat, cu calificatori și EPC; nu toate cheile GS1 și nici certificat extern. |
| AT009 · WP08 | Loturi cu litere mici/mari, separator, caractere permise și escape-uri trec prin URI și server. | Acoperire automată parțială | URI și comparația exactă a loturilor au teste locale și raport HTTPS de transport. Proba normativă trebuie legată de vectorii exacți, fără a cere intrare vocală pentru acest caz. |
| AT010 · WP02 | Un import v1 cu alergeni și nutriție ca text este migrat și citit și prin v1. | Acoperire automată parțială | Compatibilitatea v1 și datele necunoscute sunt testate; trebuie atașat rezultatul pe corpusul legacy exact și migrarea instalației reale. |
| AT011 · WP19 | O organizație încearcă să revendice GTIN-ul sau hostul altei organizații. | Funcții/probe încă lipsă | Protecția GTIN există; activarea domeniului propriu încă lipsește. |

### Identitate personală, persistență și recuperare

| ID / pachet | Scenariu | Acoperire în audit | Lipsuri / limită de acceptare |
|---|---|---|---|
| AT012 · WP04 | Persoană nouă deschide webul și salvează primul produs. | Acoperire automată parțială | Salvare locală fără cont există și domeniul client este testat. Rămâne observația pe telefon; SMTP/passkeys nu sunt condiții ale primei salvări locale. |
| AT013 · WP04 | Aplicația și browserul sunt închise și redeschise pe dispozitiv autorizat. | Acoperire automată parțială | Persistența locală și sesiunea personală sunt implementate; închidere/redeschidere, restricții browser și durată reală de sesiune necesită probă pe dispozitiv. |
| AT014 · WP04 | Un vizitator cu inventar local se conectează la un cont cu inventar existent. | Acoperire automată parțială | Sync are teste de merge/versiuni și conflicte. Se cere proba cu inventar local existent și cont existent pe două dispozitive reale, fără pierderi. |
| AT015 · WP04 | Se folosește magic link expirat, refolosit, preîncărcat de email sau destinat altui flux. | Acoperire automată parțială | Preview separat de confirmarea single-use și expirarea sunt testate. Linkul trebuie primit efectiv și probat inclusiv prin scannerul furnizorului de email. |
| AT016 · WP04 | Passkey este creat, folosit, pierdut; dispozitivul nu suportă metoda. | Neimplementat | Passkeys nu sunt implementate; dacă sunt facultative se consemnează decizia de produs înainte de a marca neaplicabil. |
| AT017 · WP04 | Se activează opțiunile Apple/Google și apoi se leagă/dezleagă o identitate. | Neimplementat | Apple/Google login nu este implementat; inexistența conturilor nu înlocuiește implementarea. |
| AT018 · WP04 | Se revocă un dispozitiv pierdut și se încearcă refolosirea tokenului vechi. | Acoperire automată parțială | Revocarea dispozitivului este testată în domeniu/API. Rămâne proba de dispozitiv pierdut și token vechi; nu poate șterge copii deja descărcate. |
| AT019 · WP04 | Stocarea locală este ștearsă sau aplicația este reinstalată. | Funcții/probe încă lipsă | Sync/export permit recuperare numai pentru datele sincronizate/exportate. Se probează reinstalarea și se explică pierderea datelor exclusiv locale fără copie. |
| AT020 · WP04 | Un cont personal încearcă să intre în administrarea unui producător. | Acoperire automată parțială | Identitățile personale sunt separate de producători și există teste de autorizare. Rămâne înregistrarea explicită a cererilor negative pentru cazul de acceptare. |
| AT021 · WP23 | Persoana activează, refuză și retrage sincronizarea preferințelor sensibile. | Neimplementat | Preferințele sensibile nu se sincronizează acum. Activarea în viitor cere consimțământ și controale separate. |
| AT022 · WP04 | Se încearcă o schimbare sensibilă din sesiune veche sau de pe dispozitiv comun. | Funcții/probe încă lipsă | Mecanismul complet de confirmare recentă pentru operațiuni personale sensibile nu este livrat/probat; autentificarea persistentă singură nu îl înlocuiește. |
| AT023 · WP04 | Persoana cere export și ștergerea contului prin aplicație și canalul web. | Neimplementat | Export disponibil și contact manual; nu există ștergere self-service sau procedură de ștergere probată. |

### Scanare și informații externe

| ID / pachet | Scenariu | Acoperire în audit | Lipsuri / limită de acceptare |
|---|---|---|---|
| AT024 · WP08 | Se scanează corpusul EAN/UPC și GS1 2D declarat pe telefoanele din matrice. | Funcții/probe încă lipsă | Decodoare și corpus sintetic există; scanarea EAN/UPC/GS1 pe telefoanele din matrice, cu camera reală, nu este probată de testele de domeniu. |
| AT025 · WP08 | Același ambalaj este citit în mai multe cadre, inclusiv cu EAN și QR vizibile. | Acoperire automată parțială | Deduplicarea de scanare este implementată; rămâne proba cu EAN și QR în același cadru, focalizare variabilă și repetare video reală. |
| AT026 · WP08 | Persoana adaugă două ambalaje identice intenționat. | Acoperire automată parțială | Ambalajele au identități distincte și operații independente. Se probează în UI adăugarea intenționată repetată fără blocarea ei ca duplicat accidental. |
| AT027 · WP08 | Codul include date GS1 valide/invalide și precizie specială permisă de standard. | Acoperire automată parțială | Parsarea datelor GS1 are teste; vectorii de precizie specială și cod invalid trebuie atașați exact. Datele din cod nu rescriu eticheta aprobată. |
| AT028 · WP09 | Se scanează produse de pe două resolvere externe independente și din Open Food Facts. | Neimplementat | Nu există client extern de produse/resolvere și Open Food Facts; capabilitatea este false. Construire și probe pe două surse independente. |
| AT029 · WP09 | Resolverul extern răspunde lent, returnează eroare sau nu are resursa cerută. | Neimplementat | Lipsesc adaptorul extern și fallbackurile sale; timeoutul sau eroarea nu trebuie să inventeze date ori să împiedice salvarea manuală. |
| AT030 · WP09 | Două surse oferă ingrediente sau nutrienți diferiți pentru piețe/versiuni diferite. | Neimplementat | Nu există reconciliere implementată între surse externe pentru piață/versiune. Se păstrează proveniența și se cere confirmare când informațiile diferă. |
| AT031 · WP23 | QR-ul extern indică localhost, rețea privată, redirect periculos sau DNS rebinding. | Neimplementat | Nu există fetch extern acum; SSRF trebuie testat când se implementează WP09, înainte de activare. |
| AT032 · WP08 | Data lipsește din cod; fotografia conține două date sau este neclară. | Funcții/probe încă lipsă | OCR local de dată există și resursele sunt disponibile. Lipsesc corpusul real de ambalaje și măsurarea ambiguităților/timpului pe telefoane. |
| AT033 · WP08 | Aliment fără cod este adăugat prin fotografie sau voce. | Neimplementat | Adăugarea manuală există; fotografia generală și intrarea vocală din caz nu sunt implementate. |
| AT034 · WP08 | Se scanează un cod local de magazin sau cu greutate variabilă. | Neimplementat | Nu există normalizare completă pentru coduri locale și greutate variabilă pe contracte de magazin. Aceste coduri nu trebuie tratate ca GTIN universal. |
| AT035 · WP06 | QR-ul este deschis cu camera sistemului la raft și apoi din scannerul aplicației. | Acoperire automată parțială | Pagina QR și adăugarea explicită Acasă sunt separate în cod. Se probează camera sistemului/deep link și scannerul aplicației pe telefoane. |

### Inventar, gospodării și offline

| ID / pachet | Scenariu | Acoperire în audit | Lipsuri / limită de acceptare |
|---|---|---|---|
| AT036 · WP05 | Două ambalaje din același lot sunt deschise în zile diferite. | Acoperire automată parțială | Pachete distincte și momente separate de deschidere sunt implementate; se înregistrează cazul complet de UI/sync pe două exemplare din același lot. |
| AT037 · WP05 | Am deschis, mutare în congelator, consum și Anulează sunt folosite succesiv. | Acoperire automată parțială | Operațiile și undo au teste de domeniu. Rămâne proba secvenței în UI cu revenire după refresh și sincronizare. |
| AT038 · WP05 | Aceleași operațiuni sunt repetate după timeout sau retry de rețea. | Acoperire automată parțială | Event IDs, revizii și conflict 409 sunt testate; se leagă dovada pentru timeout/retry și același corp, fără operație dublată. |
| AT039 · WP05 | Două telefoane modifică offline același ambalaj și apoi se reconectează. | Acoperire automată parțială | Concurența și conflictul sunt testate cu principale simulate. Două telefoane offline și rezolvarea explicită în interfață necesită probă reală. |
| AT040 · WP05 | Invitația gospodăriei expiră, este revocată sau este reutilizată de altă persoană. | Acoperire automată parțială | Invitațiile single-use, expirarea și autorizarea sunt implementate; se atașează probele negative pentru identitatea greșită și revocare. |
| AT041 · WP05 | Un membru limitat/minor vede inventarul comun. | Neimplementat | Nu există profilul complet de membru limitat/minor și politica aferentă din caz. Rolurile actuale nu sunt dovadă de control parental. |
| AT042 · WP05 | Se folosesc ambalaje, grame și mililitri, inclusiv cantitate parțială. | Acoperire automată parțială | Cantități și unități sunt modelate; trebuie reconciliate pachete/g/ml, cantități parțiale și round-trip pe client/server, fără conversii de densitate inventate. |
| AT043 · WP06 | PWA se deschide offline după o alertă și apoi revine online. | Acoperire automată parțială | Offline are marcaje de lipsă a verificării curente. Se probează revenirea online pe dispozitiv și afișarea imediată a unei retrageri noi. |
| AT044 · WP05 | Un aliment trece de termen fără acțiunea persoanei. | Acoperire automată parțială | Trecerea datei nu consumă automat ambalajul. Se probează schimbarea zilei/fusului orar și păstrarea inventarului/istoricului. |
| AT045 · WP05 | Membrul părăsește gospodăria sau este eliminat, apoi exportă date. | Acoperire automată parțială | Retragerea accesului blochează citirile viitoare pe server. Exportul și datele deja descărcate, precum și părăsirea gospodăriei, necesită caz distinct. |

### Reguli alimentare și ajutor pentru persoane cu diabet

| ID / pachet | Scenariu | Acoperire în audit | Lipsuri / limită de acceptare |
|---|---|---|---|
| AT046 · WP10 | Alergen declarat, posibile urme și lipsă de date sunt întâlnite separat. | Acoperire automată parțială | Contractele separă alergen declarat, urme și necunoscut. Se cer corpusul alimentar și revizia specialistului, fără garanția implicită fără alergeni. |
| AT047 · WP10 | Eticheta V2 schimbă alergeni, iar un lot vechi rămâne la V1. | Acoperire automată parțială | Versiunea etichetei este fixată per lot; se probează explicit schimbarea alergenilor V1/V2 și afișarea corectă pe lotul vechi. |
| AT048 · WP10 | Se compară două produse cu zahăr redus și carbohidrați diferiți. | Acoperire automată parțială | Calculul de carbohidrați este distinct de zaharuri. Se verifică date reale și mesajele cu specialist; nu se deduc doze sau adecvare medicală. |
| AT049 · WP10 | Nutriție per 100 g, per 100 ml, per porție și produs preparat. | Acoperire automată parțială | Contractul conține baze/unități/porții. Toate bazele 100 g/100 ml/porție/preparat trebuie probate cu date reale, fără conversie implicită între ele. |
| AT050 · WP10 | Date UE și date dintr-un regim cu altă definiție a carbohidraților. | Funcții/probe încă lipsă | Nu există dovadă de validare expert pentru jurisdicții cu definiții diferite ale carbohidraților; nu se presupune echivalența câmpurilor. |
| AT051 · WP10 | Termen de siguranță și termen de calitate în aceeași listă de produse. | Acoperire automată parțială | Regulile disting termen de siguranță și de calitate. Se probează textele/ordinea în UI și datele reale cu specialistul. |
| AT052 · WP10 | Instrucțiune după deschidere depășește data-limită a ambalajului. | Acoperire automată parțială | Calculul după deschidere trebuie limitat de data aplicabilă ambalajului. Cazul-limită se verifică în corpus și cu specialist, nu numai prin formulă. |
| AT053 · WP10 | Persoana congelează un produs cu termen cunoscut sau necunoscut. | Acoperire automată parțială | Congelarea și istoricul există. Se probează termen cunoscut/necunoscut și se exclude afirmația că simpla congelare validează siguranța sau o dată nouă. |
| AT054 · WP10 | Data are numai lună/an sau utilizatorul alege un memento personal. | Acoperire automată parțială | Precizia datei este modelată; mementoul personal nu trebuie prezentat ca termen al producătorului. Se cere proba lună/an și confirmare în UI. |
| AT055 · WP11 | Există retragere pentru un lot, iar persoana are GTIN cunoscut și lot necunoscut. | Funcții/probe încă lipsă | Citirea alertelor există; lot necunoscut și livrare proactivă nu sunt o acceptare închisă. |
| AT056 · WP14 | O sugestie ar folosi produs retras sau alergen incompatibil cunoscut. | Neimplementat | Nu există motorul de sugestii/alternative; filtrarea produselor retrase și alergenilor este condiție înainte de activarea lui. |
| AT057 · WP14 | Persoana folosește lista de cumpărături, porții și alternative. | Funcții/probe încă lipsă | Lista locală există; alternativele și sincronizarea nu sunt complete. |

### Notificări și retrageri

| ID / pachet | Scenariu | Acoperire în audit | Lipsuri / limită de acceptare |
|---|---|---|---|
| AT058 · WP11 | Permisiunea pentru notificări este cerută după o salvare relevantă și apoi refuzată. | Neimplementat | Nu există flux complet de permisiuni pentru notificări. Refuzul trebuie păstrat și nu trebuie să blocheze salvarea produsului. |
| AT059 · WP11 | Se programează memento local, APNs/FCM, Web Push și email facultativ. | Neimplementat | Notificările locale/APNs/FCM/Web Push/email nu sunt implementate ca serviciu; nu există probă de livrare reală pe fiecare canal declarat. |
| AT060 · WP11 | Ambalajul este consumat pe alt dispozitiv înaintea notificării. | Neimplementat | Nu există coadă de notificări care să anuleze programarea după consum pe alt dispozitiv. Se cere idempotency și verificare înainte de livrare. |
| AT061 · WP11 | Mai multe produse ajung la termen, cu ore liniștite și profil privat. | Neimplementat | Nu există digest de termene cu ore liniștite și preferințe de confidențialitate. Se probează și conținutul lockscreen. |
| AT062 · WP11 | O alertă este emisă, corectată, închisă sau retrasă justificat. | Funcții/probe încă lipsă | Crearea/închiderea alertelor există; întregul ciclu de corecție/retragere și propagarea prin notificări nu sunt închise. |
| AT063 · WP11 | Furnizorul push sau cronul este indisponibil temporar. | Neimplementat | Nu există serviciul de push/runner de notificări. Retry/backoff, deduplicare și observarea indisponibilității trebuie construite și probate. |
| AT064 · WP06 | Pe iPhone este folosit webul simplu și apoi PWA instalată eligibilă. | Funcții/probe încă lipsă | PWA există; comportamentul Safari web/instalare, permisiunile și limitările versiunilor iOS țintă nu au probă fizică. |
| AT065 · WP22 | Alertă aprobată în timp ce paginile sunt servite la încărcarea țintă. | Funcții/probe încă lipsă | Nu există raport de încărcare cu aprobare de alertă în timpul servirii paginilor. No-store singur nu demonstrează ținta de latență/prospețime. |

### Bonuri și integrări ERP/magazin

| ID / pachet | Scenariu | Acoperire în audit | Lipsuri / limită de acceptare |
|---|---|---|---|
| AT066 · WP12 | Se fotografiază un bon cu alimente, nealimentare, discounturi, retur și cantitate cântărită. | Neimplementat | OCR pentru data ambalajului nu este OCR de bon. Lipsesc detecția liniilor, retur/discount, nealimentare și cantități cântărite din fotografie. |
| AT067 · WP12 | Bonul nu are GTIN, lot sau termen. | Neimplementat | Bonul digital permite câmpuri absente; interpretarea unei fotografii de bon fără GTIN/lot/termen nu este construită. |
| AT068 · WP12 | Același bon este importat din fotografie, QR și retry API. | Funcții/probe încă lipsă | Bon digital idempotent testat; traseul fotografie bon nu există încă. |
| AT069 · WP12 | Persoana confirmă un bon cu multe potriviri clare și două neclare. | Neimplementat | Lipsește UI/corpusul de confirmare a potrivirilor OCR de bon. Nu se cere confirmare pentru fiecare linie clară și nu se aprobă în tăcere cele ambigue. |
| AT070 · WP12 | Linkul de bon este ghicit, distribuit, expirat sau refolosit de persoană neautorizată. | Acoperire automată parțială | Tokenul de bon este capabilitate bearer: cine primește linkul îl poate citi până la expirare. Nu este legat exclusiv de cumpărător. |
| AT071 · WP13 | Magazinul pilot emite bon digital real și persoana îl importă în gospodăria sa. | Acoperire automată parțială | Cerința de magazin real este înlocuită, la instrucțiunea utilizatorului, cu simulator generic; POS fizic nu este astfel probat. |
| AT072 · WP13 | ERP-ul pilot creează articol, versiune de etichetă, lot și resurse QR. | Acoperire automată parțială | Cerința de ERP real este înlocuită, la instrucțiunea utilizatorului, cu simulator generic și contract/mapare. |
| AT073 · WP13 | Webhookul este repetat, modificat, întârziat sau semnat greșit. | Funcții/probe încă lipsă | Transportul actual este Bearer HTTPS + chei idempotente, fără contract de webhook HMAC și semnătură verificată. |
| AT074 · WP13 | Un integrator nou folosește numai OpenAPI, exemplele și instrucțiunile livrate. | Acoperire automată parțială | OpenAPI, SDK și exemplu executabil sunt livrate/testate. Se cere proba unui integrator nou, fără explicații private suplimentare. |
| AT075 · WP23 | Bonurile sursă sunt păstrate, exportate și șterse conform politicii. | Funcții/probe încă lipsă | Tokenul de bon expiră; eliminarea fizică și retenția bonurilor/payloadurilor sursă nu au proces complet implementat și probat. |

### Documentație deschisă și pilotul PBT

| ID / pachet | Scenariu | Acoperire în audit | Lipsuri / limită de acceptare |
|---|---|---|---|
| AT076 · WP16 | Se completează formula, procesul, furnizorii, controlul, ambalarea și probele unui dosar. | Neimplementat | Există rețetă JSON și documente; editorul/registrele pentru formula, proces, furnizori, control, ambalaj și probe ale dosarului complet lipsesc. |
| AT077 · WP16 | Se scalează formula și se calculează bilanț, randament, pierderi și scenarii de cost. | Neimplementat | Nu există motorul complet de scalare/bilanț/randament/pierderi/cost din dosarul tehnic; estimările importate nu sunt acel motor. |
| AT078 · WP16 | Se importă și se compară PBT R1, COST1/COST2/COST3 și cercetarea. | Acoperire automată parțială | Import/cercetare păstrate în aplicație; editorul comparativ și calculele complete de cost nu sunt livrate. |
| AT079 · WP16 | Fișier BT01 deteriorat și material recuperat sunt prezentate ca surse. | Acoperire automată parțială | Originalele deteriorate/recuperate au proveniență și clasificare. Se cere proba vizuală de prezentare a stării; BT01 nu devine rezultat validat. |
| AT080 · WP16 | Se documentează pâine, lactat, băutură și alt aliment folosind profilurile inițiale. | Neimplementat | Profilul bread este separat și categoria este generică; editorul și profilurile adoptate pentru lactat/băutură/alte alimente nu sunt complete. |
| AT081 · WP16 | Dosarul este publicat parțial, cu licențe diferite și drepturi neconfirmate la unele fișiere. | Funcții/probe încă lipsă | Licența explicită și private/public există pentru rețete/documente. Publicarea parțială a dosarului extins și drepturile mixte necesită implementarea WP16. |
| AT082 · WP16 | Un rezultat, o fotografie și o afirmație de impact sunt modificate după publicare. | Neimplementat | Nu există registrul complet de afirmații/măsurători și revizii ale dosarului. Nu se rescrie o probă publicată fără proveniență și corecție. |
| AT083 · WP18 | Dosarul PBT este exportat și importat într-un mediu gol. | Funcții/probe încă lipsă | Round-trip de bază poate fi testat; dosarul extins este încă neimplementat, deci cazul complet nu este închis. |

### Evaluare, acreditare și contestație

| ID / pachet | Scenariu | Acoperire în audit | Lipsuri / limită de acceptare |
|---|---|---|---|
| AT084 · WP17 | Emitentul, standardul și evaluatorii eligibili sunt configurați cu roluri reale. | Neimplementat | Nu există rolurile și fluxul de emitent/evaluatori. Standardul din arhivă este propus, fără adoptare și mandat nominal. |
| AT085 · WP17 | Autorul sau un evaluator cu conflict încearcă să aprobe dosarul. | Neimplementat | Conflictul de interese și independența evaluării nu au flux implementat; autorul nu trebuie să-și acorde singur decizia. |
| AT086 · WP17 | O cerință OB-01–OB-23 eșuează ori are problemă blocantă. | Neimplementat | Motorul de evaluare pe dovezi OB-01–OB-23 și blocaje nu există. Un procent de completare nu poate înlocui cerințele obligatorii. |
| AT087 · WP17 | Se încearcă marcarea integrală a unei cerințe OB drept neaplicabilă. | Neimplementat | Nu există evaluarea/excepțiile la nivel de câmp. Nu se poate marca întreaga cerință obligatorie drept neaplicabilă pentru a emite sigla. |
| AT088 · WP17 | Dosarul eligibil este aprobat de două cereri concurente; una eșuează în tranzacție. | Neimplementat | Nu există tranzacția atomică OB-24/decizie/credential. Se cer concurență și rollback fără aprobare parțială ori dependență circulară. |
| AT089 · WP17 | Publicul deschide sigla SVG, PDF și pagina de verificare. | Neimplementat | Nu există emitere SVG/PDF și pagină de verificare a siglei cu standard/versiune/domeniu/limite și stare curentă. |
| AT090 · WP17 | Acreditarea este suspendată, revocată, expirată sau înlocuită. | Neimplementat | Registrul și ciclul suspendare/revocare/expirare/înlocuire nu sunt implementate; afișarea unei imagini nu ar fi verificare de stare. |
| AT091 · WP17 | O versiune nouă, un producător nou sau un import copiază un dosar acreditat. | Neimplementat | Nu există credentiale/evaluări de transferat. Viitorul import/fork nu trebuie să copieze autoritatea sau o siglă valabilă pentru alt dosar. |
| AT092 · WP17 | Este depusă contestație la decizia evaluatorului. | Neimplementat | Nu există fluxul de contestație și al doilea evaluator eligibil; procedura și protecția datelor private trebuie implementate. |
| AT093 · WP17 | Se încearcă emiterea pentru PBT fără drepturi sau rezultate suficiente, inclusiv după plată. | Neimplementat | Lipsa funcției de emitere previne acordarea automată acum; nu demonstrează motorul viitor de eligibilitate PBT și probe/drepturi. |

### Comunitate, căutare și AI

| ID / pachet | Scenariu | Acoperire în audit | Lipsuri / limită de acceptare |
|---|---|---|---|
| AT094 · WP15 | Recenzie negativă, întrebare și sesizare sunt asociate produsului și lotului cunoscut. | Acoperire automată parțială | Recenzii, întrebări și sesizări cu produs/lot și moderare sunt implementate. Se cere probă nominală de flux și păstrarea criticii legitime. |
| AT095 · WP15 | Un utilizator votează repetat și încearcă importul de identități/voturi privilegiate. | Acoperire automată parțială | Unicitate și autorizare există. AT-049 original cere suplimentar withdrawn/mutation_version și note pe criterii: acestea lipsesc și retry vechi poate reactiva votul retras. |
| AT096 · WP15 | Feedbackul conduce la o propunere de schimbare a etichetei sau rețetei. | Acoperire automată parțială | Propunerea și decizia au istoric separat; nu modifică direct eticheta/lotul. Dosarul tehnic extins trebuie să aplice aceeași regulă. |
| AT097 · WP15 | Căutarea și public_qa întâlnesc rețetă privată și documente ale altui tenant. | Neimplementat | Nu există căutare/RAG/public_qa. Corpusul privat și izolarea pe fragmente/citări trebuie probate înainte de activare, nu deduse din feedback_triage. |
| AT098 · WP15 | Permisiunea sau versiunea documentului se schimbă în timpul jobului. | Acoperire automată parțială | Reverificare ACL/hash după răspuns există pentru feedback_triage; nu există RAG/public_qa. |
| AT099 · WP15 | Se execută document_map, dossier_gap_check, feedback_triage, feedback_digest, experiment_proposal, public_qa. | Neimplementat | Doar feedback_triage există; nu există probă de apel real sau acceptarea tuturor celor șase joburi. |
| AT100 · WP15 | AI este dezactivat, fără buget sau indisponibil în timpul scanării și folosirii inventarului. | Acoperire automată parțială | QR și inventarul sunt independente de AI și testate în acest scop; exercițiul complet cu serviciu căzut, buget epuizat și telefoane reale rămâne. |
| AT101 · WP15 | Jobul expiră, se reia, depășește bugetul sau primește prompt injection dintr-un document. | Funcții/probe încă lipsă | Lease/retry/cap de joburi există pentru triere. Buget monetar, toate joburile și corpusul complet de injection/cost nu sunt implementate/probate. |
| AT102 · WP15 | Asistentul personal răspunde unei întrebări despre porție, alergen sau folosirea alimentelor. | Neimplementat | Calculul determinist de porție nu este asistent personal AI. Lipsesc fluxul și validarea expert/red-team pentru întrebările din caz. |

### Portabilitate și domenii

| ID / pachet | Scenariu | Acoperire în audit | Lipsuri / limită de acceptare |
|---|---|---|---|
| AT103 · WP18 | Se importă pachete OpenBread vechi, v1 curent și flux NDJSON mare. | Funcții/probe încă lipsă | JSON import și NDJSON export există; NDJSON import/reluare mare lipsește. |
| AT104 · WP18 | Exportul complet al producătorului este reimportat într-un mediu gol. | Acoperire automată parțială | Round-trip JSON de bază este testat; echivalența tuturor colecțiilor/docurilor/licențelor dosarului complet nu poate fi semnată înainte de WP16. |
| AT105 · WP18 | Se exportă inventarul personal și datele gospodăriei permise. | Funcții/probe încă lipsă | Exportul nu ține locul ștergerii datelor personale și regulilor de proprietate comună. |
| AT106 · WP18 | Pachetul importat include tokenuri, roluri, mandat GTIN sau siglă pretinsă. | Acoperire automată parțială | Importul nu transferă chei, roluri sau mandat; datele intră în stări private/ciornă. Se înregistrează separat corpusul ostil și decizia de drepturi. |
| AT107 · WP18 | Se primește un export real autorizat de la furnizor, inclusiv serviciul GS1 vizat dacă este disponibil. | Funcții/probe încă lipsă | Nu există export real autorizat de la furnizorul vizat; linksetul public nu este export privat de migrare. |
| AT108 · WP19 | Se activează domeniu de producător prin DNS/HTTP și TLS. | Neimplementat | Nu există activare de domeniu producător prin challenge DNS/HTTP, TLS și rutare tenant; este necesar un domeniu real controlat. |
| AT109 · WP19 | Cererea folosește Host fals, certificat expirat sau domeniu cu control retras. | Neimplementat | Allowlistul pentru hosturi proprii, revalidarea controlului și tratarea certificatului expirat trebuie construite odată cu WP19 și testate negativ. |
| AT110 · WP19 | Clientul mută datele și destinația păstrând QR-uri deja imprimate. | Neimplementat | Mutarea datelor nu mută automat domeniul openaliment.ro din QR. Continuitatea cere controlul domeniului producătorului sau acord de redirecționare păstrat. |

### Infrastructură, securitate și capacitate

| ID / pachet | Scenariu | Acoperire în audit | Lipsuri / limită de acceptare |
|---|---|---|---|
| AT111 · WP21 | Se instalează și actualizează release-ul pe staging cPanel fără root/shell. | Funcții/probe încă lipsă | Deploy-uri pe cPanel existente nu sunt probă de staging și rollback/restore complet. |
| AT112 · WP21 | Cronul/runnerul programat execută cozi, iar cereri neautorizate încearcă execuții arbitrare. | Funcții/probe încă lipsă | Worker CLI implementat; lipsă dovadă de cron cPanel și heartbeat operațional. |
| AT113 · WP23 | Se execută matricea de acces pentru tenant, gospodărie, fișiere și administrare. | Acoperire automată parțială | Matricea de autorizare are teste locale; revizia independentă pe roluri/tenant/gospodării/fișiere și raportul de acceptare nu sunt închise. |
| AT114 · WP23 | Se încarcă documente ostile, ZIP bomb, path traversal, SVG activ și document cu XXE. | Acoperire automată parțială | Protecții structurale/MIME/limite testate; nu există motor AV/CDR și nici pretenție de scanare completă. |
| AT115 · WP22 | Se rulează profilul susținut și rafala de încărcare convenite. | Funcții/probe încă lipsă | Nu există raport load pentru 250 RPS susținut/1000 RPS rafală. Necesită mediu și limite convenite cu hostingul, fără stresarea producției. |
| AT116 · WP22 | Sub încărcare se schimbă destinația resolverului și se aprobă o alertă. | Funcții/probe încă lipsă | Versiunile și no-store există; sub încărcare, propagarea alertei și schimbarea destinației trebuie măsurate și reconciliate. |
| AT117 · WP21 | Se restaurează DB, storage și configurația într-un mediu separat. | Funcții/probe încă lipsă | Nu există restore complet cronometrat demonstrat. Backupul trebuie să refacă DB, storage, configurație, QR, alerte și revocări recente. |
| AT118 · WP26 | Se simulează incident DB, AI, email, push sau serviciu extern și se revine. | Funcții/probe încă lipsă | Nu există exercițiu operațional complet cu responsabil și runbook executat. Căderea AI, DB, email, push și surselor are efecte diferite. |
| AT119 · WP23 | Se revizuiesc temeiurile, retenția, furnizorii și necesitatea DPIA. | Funcții/probe încă lipsă | ONG în curs de înființare și contact clarificate; responsabilitatea reală și documentele/fluxurile GDPR nu sunt astfel acceptate. |
| AT120 · WP22 | Se verifică pragurile de cost, metricile și alertele operaționale. | Funcții/probe încă lipsă | Nu există probă de alertă operațională primită și buget urmărit; lipsesc capacitate/cost observate și monitorizarea cozilor/backupului. |
| AT121 · WP21 | Se verifică mecanismul HTTPS de mentenanță, tokenurile și fișierele de setup. | Acoperire automată parțială | Probe negative ale bridge-ului există; închiderea accesului temporar, revizia privilegiilor și rotația nu sunt probate în acest audit. |

### Accesibilitate, conținut și distribuție finală

| ID / pachet | Scenariu | Acoperire în audit | Lipsuri / limită de acceptare |
|---|---|---|---|
| AT122 · WP01 | Minimum 20 de persoane reprezentative folosesc aplicația fără instruire. | Funcții/probe încă lipsă | Nu există studiu cu minimum 20 persoane și măsurare 3 secunde. |
| AT123 · WP24 | Se folosesc cititor de ecran, tastatură, text mărit, contrast și orientări diferite. | Funcții/probe încă lipsă | Analiza responsive nu probează cititorul de ecran, tastatura, zoomul, contrastul și orientarea pe dispozitive. Se cere audit de accesibilitate al fluxurilor. |
| AT124 · WP07 | Pe Android accesibil ca preț și iPhone se scanează în lumină diferită, offline și în fundal. | Funcții/probe încă lipsă | Nu există builduri native și matrice de dispozitive Android/iPhone în condiții reale. Testele domeniului web nu înlocuiesc această probă. |
| AT125 · WP20 | Se verifică landingul, imaginea aleasă, exemplul PBT, ghidurile și mesajele comerciale. | Acoperire automată parțială | Revizie editorială 0.5.4/0.5.5 și probe HTTPS există; layout și dispozitive reale încă neînchise. |
| AT126 · WP20 | Se verifică SEO, sitemap, canonical, metadate, date structurate și suportul de contact. | Funcții/probe încă lipsă | Există SEO tehnic și contact public; sitemap/canonical/date structurate și contactul primit efectiv necesită raport complet, inclusiv paginile private excluse. |
| AT127 · WP25 | Aplicațiile sunt trimise și evaluate în conturile operatorului Apple/Google. | Neimplementat | Nu există conturi/builduri trimise la Apple/Google sau aprobări ale magazinelor. Declarațiile trebuie să corespundă datelor reale. |
| AT128 · WP25 | O persoană instalează versiunile publice și actualizează o versiune cu inventar existent. | Neimplementat | Nu există aplicații native disponibile în magazine; instalarea publică și upgradeul cu inventar păstrat nu pot fi probate încă. |
| AT129 · WP24 | QR real este imprimat la dimensiunile și pe ambalajele convenite. | Funcții/probe încă lipsă | SVG și decode digital nu reprezintă probă tipografică. |
| AT130 · WP24 | POS-ul partenerului citește QR demo în test și articol comercial autorizat în proba convenită. | Funcții/probe încă lipsă | Simulatorul de magazin nu demonstrează citirea unui QR la POS fizic. |
| AT131 · WP26 | Pilotul cu producător non-pâine, parteneri și minimum zece gospodării rulează 14 zile. | Funcții/probe încă lipsă | Nu există jurnal de pilot real 14 zile cu producător non-pâine și minimum zece gospodării, metrici și incidente reconciliate. |
| AT132 · WP27 | Se face predarea și auditul final R01–R56/WP00–WP27/E01–E12. | Funcții/probe încă lipsă | Se aplică derogarea explicită pentru simulatoare ERP/magazin; restul probelor fizice și externe rămân obligatorii. |

## Cele 49 de scenarii originale OpenBread

Identificatorii cu cratimă provin din [specificația originală](../../OpenBread_Codex_v1_2026-10-23/08_codex/acceptance_tests.json). Starea originală `not_executed` nu este schimbată prin acest audit. Criteriile de dosar/siglă și RAG nu se înlocuiesc cu testele etichetei ori ale singurului job AI existent.

| ID / titlu | Pachete actuale | Acoperire | Constatare |
|---|---|---|---|
| AT-001 · Separarea producătorilor la citire | WP03, WP15, WP23 | Parțial / necesită probe | Izolarea la citire are teste pe tenant/API; se atașează cererile exacte ale cazului, inclusiv fișierele și indicii de existență. |
| AT-002 · Separarea producătorilor la modificare | WP03, WP15, WP23 | Parțial / necesită probe | Mutațiile derivă tenantul din principal și au teste negative; matricea completă pe roluri/resurse trebuie semnată pe buildul final. |
| AT-003 · Descărcare și range request fără acces | WP03, WP15, WP23 | Parțial / necesită probe | Downloadul verifică proiecția publică sau accesul tenantului; cererile Range/HEAD neautorizate trebuie probate explicit, fără divulgare de octeți. |
| AT-004 · Izolarea căutării și RAG | WP03, WP15, WP23 | Neimplementat | Căutarea și RAG nu sunt implementate; izolarea lor nu poate fi dedusă din autorizarea downloadului. |
| AT-005 · Revocarea rolului înainte de job | WP03, WP15, WP23 | Parțial / necesită probe | Trierii i se verifică accesul/contextul la execuție; cazul complet pe toate joburile documentare rămâne până la implementarea lor. |
| AT-006 · Mutări de stare protejate și CSRF | WP03, WP15, WP23 | Parțial / necesită probe | CSRF, metodele și rolurile sunt testate pentru fluxurile existente; deciziile/sigla viitoare au nevoie de aceeași matrice. |
| AT-007 · Înghețarea salvează versiune reproductibilă | WP02, WP16 | Parțial / necesită probe | Snapshoturile de etichetă/rețetă există; înghețarea dosarului structurat complet și manifestul reproductibil lipsesc. |
| AT-008 · Versiune frozen/published imuabilă | WP02, WP16 | Parțial / necesită probe | Nu există ciclul complet draft/frozen/published al dosarului. Imuabilitatea unui snapshot nu înlocuiește evaluarea întregii versiuni. |
| AT-009 · Concurență la editare și înghețare | WP02, WP16 | Parțial / necesită probe | Controlul concurenței există pentru unele resurse, nu editorul/înghețarea dosarului complet. Se cere expected_version și conflict pe acel flux. |
| AT-010 · R2 nu rescrie lotul R1 | WP02, WP16 | Parțial / necesită probe | Lotul fixează eticheta și opțional rețeta; istoricul este testat. Editorul și documentele complete R1/R2 nu sunt încă livrate. |
| AT-011 · Corecție justificată fără distrugerea istoricului | WP02, WP16 | Parțial / necesită probe | Alerte și versiuni noi păstrează istoricul; corectura justificată a întregului dosar și manifestelor se implementează în WP16. |
| AT-012 · Dry-run fără efecte operative | WP16, WP18 | Parțial / necesită probe | Dry-run există pentru importurile actuale; se probează absența scrierilor operative și a autorității importate pe corpusul exact. |
| AT-013 · Hash corupt și fișier lipsă | WP16, WP18 | Parțial / necesită probe | Hashuri și referințe sunt verificate; se atașează dovada fișierului lipsă/corupt, fără import parțial publicabil. |
| AT-014 · Arhivă și conținut executabil ostil | WP16, WP18 | Parțial / necesită probe | Sunt testate limite/tipuri/căi și import ostil; AV/CDR nu este implementat, iar corpusul complet de arhive trebuie delimitat explicit. |
| AT-015 · Reimport identic și reluare după întrerupere | WP16, WP18 | Parțial / necesită probe | Importul identic este deduplicat la nivel de pachet. Deduplicarea bloburilor tenant+SHA cu source_id/proveniențe multiple și reluarea NDJSON mare nu sunt implementate. |
| AT-016 · Nouă revizie, istoric COST1 și seed R1 | WP16, WP18 | Parțial / necesită probe | R1 și COST sunt păstrate cu proveniență; editorul și comparațiile structurate de revizii/cost nu sunt complete. |
| AT-017 · Cerințe obligatorii și dovezi, nu procent cosmetic | WP17 | Neimplementat | Motorul de eligibilitate pe cerințe și dovezi nu există; niciun procent de completare nu acordă sigla. |
| AT-018 · R1 fără lot fizic și plan îndeplinit | WP17 | Neimplementat | PBT este experimental și nu primește sigla acum; fluxul viitor trebuie să ceară lot/probe reale și toate criteriile aplicabile. |
| AT-019 · Excepții numai la câmp în OB-STD-0.1 și drepturi clarificate | WP17 | Neimplementat | Evaluarea, excepțiile limitate la câmp și drepturile aferente nu sunt implementate. |
| AT-020 · Reproducere independentă, conflict și fork | WP17 | Neimplementat | Nu există flux de reproducere independentă, conflict și evaluare fork; licența nu transferă acreditarea. |
| AT-021 · Ciclul de stare și verificarea publică | WP17 | Neimplementat | Nu există registrul de sigle, ciclul de stări și verificarea publică. |
| AT-022 · Rute native canonice și identificatori necunoscuți | WP03, WP24 | Parțial / necesită probe | Rutele p/t/b și p/id/v/version există; ruta credentialului nu există deoarece modulul siglei nu este implementat. Necunoscutul nu este alt produs. |
| AT-023 · GTIN cu zero inițial și drept neconfirmat | WP03, WP24 | Parțial / necesită probe | Validare GTIN/zerouri și blocarea dreptului neconfirmat există; dovada reală de mandat se verifică separat de fixture. |
| AT-024 · Lot case-sensitive și apartenență comercială | WP03, WP24 | Parțial / necesită probe | Lotul are comparație exactă și apartenență la articol/tenant; testele domeniu și HTTP acoperă tema, cu probă fizică separată. |
| AT-025 · Date de durabilitate manipulate în URL | WP03, WP24 | Parțial / necesită probe | Parametrii de dată nu suprascriu eticheta aprobată; se probează explicit contradicția și avertizarea în UI. |
| AT-026 · Codare URL, dublă decodare și parametrizare ostilă | WP03, WP24 | Parțial / necesită probe | Codificare și o singură decodare sunt testate local/HTTPS în profilul declarat; nu toate caracterele unui lot arbitrar devin GS1 valide. |
| AT-027 · QR vechi după migrare și date publice limitate | WP03, WP24 | Parțial / necesită probe | Persistența obiectelor este implementată; migrarea/restaurarea reală și continuitatea domeniului încă nu sunt probate complet. |
| AT-028 · Retragere vizibilă după vizită anterioară | WP06, WP11, WP24 | Parțial / necesită probe | No-store și alerte curente există; revenirea după vizită veche pe dispozitiv și propagarea sub load rămân probe distincte. |
| AT-029 · Offline nu afirmă statut actual sigur | WP06, WP11, WP24 | Parțial / necesită probe | Offline marchează imposibilitatea verificării curente; se probează pe telefon fără a prezenta absența datelor drept siguranță. |
| AT-030 · Autentificare și date private excluse din cache | WP06, WP11, WP24 | Parțial / necesită probe | Cacheul este limitat și datele private au politici separate; se cere verificare browser a session/photo/export și upgrade service worker. |
| AT-031 · Update aplicație și accesibilitate mobilă | WP06, WP11, WP24 | Parțial / necesită probe | Web/PWA există; updateul cu inventar real și accesibilitatea pe Safari/Android/cititor de ecran nu sunt închise. |
| AT-032 · Recenzie negativă legitimă | WP15 | Parțial / necesită probe | Nota 1/5 și critica legitimă pot intra în moderare; politica/contestațiile și proba de păstrare a semnalului necesită acceptare. |
| AT-033 · QR nu dovedește cumpărarea și demo nu afectează scorurile | WP15 | Parțial / necesită probe | Demo este separat, scanarea nu este achiziție verificată; testele nu demonstrează cumpărare reală sau scor comercial. |
| AT-034 · API indisponibil și limită de buget | WP15 | Parțial / necesită probe | Feedbackul se salvează independent de AI; retry/cap de joburi există. Bugetul monetar complet și starea operațională nu sunt probate. |
| AT-035 · Rezultat incomplet, refuz sau schemă invalidă | WP15 | Parțial / necesită probe | Schema strictă, refuzul și rezultate invalide sunt tratate pentru feedback_triage; proba provider real și restul joburilor lipsesc. |
| AT-036 · Surse inventate, private sau din altă versiune | WP15 | Parțial / necesită probe | Citările sunt validate numai față de contextul furnizat trierei; nu există RAG documentar și corpusul său complet de versiuni private. |
| AT-037 · Prompt injection în recenzie și document | WP15 | Parțial / necesită probe | AI nu primește puteri de publicare și conținutul este tratat ca date; corpusul adversarial trebuie extins la viitoarele documente/RAG. |
| AT-038 · Reclamație de siguranță și observație de mucegai | WP15 | Parțial / necesită probe | Semnale locale de calitate intră în canal restricționat, fără termen nou automat. Escaladarea primită efectiv și intervenția umană trebuie probate. |
| AT-039 · Duplicare de trimitere și worker concurent | WP15 | Parțial / necesită probe | Feedback idempotent și lease worker există; concurența/retry se probează cu evidența providerului pentru a nu pretinde cost extern exact din mock. |
| AT-040 · Semnal comunitar produce experiment, nu rescriere | WP15 | Parțial / necesită probe | Propunerile rămân separate de date aprobate; jobul experiment_proposal și schema completă ipoteză/variabilă/criterii nu sunt livrate. |
| AT-041 · Export public fără date sensibile sau drepturi lipsă | WP18, WP21, WP23 | Parțial / necesită probe | Exportul privat nu este exportul public cerut. Arhiva publică autonomă, lizibilă/licențiată și filtrată pe drepturi a dosarului complet necesită WP16/WP18. |
| AT-042 · Drepturi asupra datelor și eliminare din RAG | WP18, WP21, WP23 | Neimplementat | RAG și fluxul complet de ștergere/retenție nu există. Contactul manual nu este dovadă că procedura a fost executată. |
| AT-043 · Restaurare completă cu integritatea legăturilor | WP18, WP21, WP23 | Parțial / necesită probe | Lipsește restaurarea completă cronometrată DB/storage/configurație cu verificarea QR-urilor și hashurilor. |
| AT-044 · Backup vechi nu reactivează siglă revocată | WP18, WP21, WP23 | Neimplementat | Registrul siglei nu există; reconcilierea revocărilor după restore trebuie proiectată și probată înainte de emitere. |
| AT-045 · Portabilitate a dosarului deschis și închiderea organizației | WP18, WP21, WP23 | Parțial / necesită probe | Portabilitate JSON există; închiderea organizației, arhiva lizibilă completă și păstrarea URL-urilor/retention nu sunt integral livrate. |
| AT-046 · OB-24 trecut atomic fără credential preexistent | WP17 | Neimplementat | Nu există decizia atomică OB-24 fără credential preexistent; este o cerință de implementare, nu test trecut. |
| AT-047 · Emiterea concurentă și rollbackul nu lasă aprobare parțială | WP17 | Neimplementat | Nu există emiterea concurentă a credentialului/rollback. Se cere unicitate și tranzacție pe viitorul modul. |
| AT-048 · ACL reevaluat după răspunsul AI și înainte de publicare | WP03, WP15, WP23 | Parțial / necesită probe | ACL/hash sunt reverificate după feedback_triage și rezultatul este draft. Restul joburilor și publicarea lor nu sunt implementate. |
| AT-049 · Vot verificat între tenanturi, idempotentă, retragere și agregat fără schimbare de rețetă | WP15 | Parțial / necesită probe | Community::vote folosește INSERT IGNORE/DELETE: lipsesc withdrawn, mutation_version și refuzul retry-ului vechi. Feedbackul are o singură notă, fără criterii/agregate separate din scenariu. |

## Cele 16 cazuri QR originale

Sursa: [cazurile QR](../../OpenBread_Codex_v1_2026-10-23/08_codex/qr_acceptance_cases.json). Profilul conservator și limita M1 sunt istorice; implementarea ulterioară este documentată în [nivelul resolverului](../GS1_IMPLEMENTATION.md).

| ID / titlu | Acoperire | Constatare |
|---|---|---|
| QR-01 · Lot fără GTIN, identificare proprie | Acoperire automată parțială | Vezi tests/resolver.php, tests/test_resolver_http.py și raportul HTTPS istoric de transport; acceptarea fizică nu se deduce. |
| QR-02 · GTIN normalizat păstrează zeroul inițial | Acoperire automată parțială | Vezi tests/resolver.php, tests/test_resolver_http.py și raportul HTTPS istoric de transport; acceptarea fizică nu se deduce. |
| QR-03 · GTIN invalid, neverificat sau substituent | Acoperire automată parțială | Vezi tests/resolver.php, tests/test_resolver_http.py și raportul HTTPS istoric de transport; acceptarea fizică nu se deduce. |
| QR-04 · Sensibilitate la majuscule în lot | Acoperire automată parțială | Vezi tests/resolver.php, tests/test_resolver_http.py și raportul HTTPS istoric de transport; acceptarea fizică nu se deduce. |
| QR-05 · Zerouri finale și inițiale ale lotului | Acoperire automată parțială | Vezi tests/resolver.php, tests/test_resolver_http.py și raportul HTTPS istoric de transport; acceptarea fizică nu se deduce. |
| QR-06 · Articol necunoscut | Acoperire automată parțială | Vezi tests/resolver.php, tests/test_resolver_http.py și raportul HTTPS istoric de transport; acceptarea fizică nu se deduce. |
| QR-07 · Produs cunoscut, lot necunoscut | Acoperire automată parțială | Vezi tests/resolver.php, tests/test_resolver_http.py și raportul HTTPS istoric de transport; acceptarea fizică nu se deduce. |
| QR-08 · Manipularea datei 15 în query | Acoperire automată parțială | Vezi tests/resolver.php, tests/test_resolver_http.py și raportul HTTPS istoric de transport; acceptarea fizică nu se deduce. |
| QR-09 · Substituția semantică 15/17 și query duplicat | Acoperire automată parțială | Vezi tests/resolver.php, tests/test_resolver_http.py și raportul HTTPS istoric de transport; acceptarea fizică nu se deduce. |
| QR-10 · Lot cu slash pe Apache shared hosting | Acoperire automată parțială | Vezi tests/resolver.php, tests/test_resolver_http.py și raportul HTTPS istoric de transport; acceptarea fizică nu se deduce. |
| QR-11 · Decodare repetată și escape invalid | Acoperire automată parțială | Vezi tests/resolver.php, tests/test_resolver_http.py și raportul HTTPS istoric de transport; acceptarea fizică nu se deduce. |
| QR-12 · Lot istoric după modificarea rețetei | Acoperire automată parțială | Vezi tests/resolver.php, tests/test_resolver_http.py și raportul HTTPS istoric de transport; acceptarea fizică nu se deduce. |
| QR-13 · Persistența URL-ului tipărit | Parțial / necesită probe | Persistența funcțională există; continuitatea prin restore/migrare și tiparul se probează separat. |
| QR-14 · Cache, rechemare și funcționare offline | Parțial / necesită probe | No-store și offline testate în domeniul software; dispozitive reale și alerte sub încărcare rămân. |
| QR-15 · Blocarea tiparului cu substituenți și verificarea dimensiunii | Parțial / necesită probe | Placeholder-ele/demo sunt separate; dimensiunile și citirea trebuie probate pe ambalaj real. |
| QR-16 · Limita revendicării de conformitate resolver | Acoperire automată parțială | Profilul ulterior GS1 resolver 1.2.1 înlocuiește limitarea istorică M1; testele locale/HTTPS nu sunt certificat sau probă POS. |

## Cum se închide un scenariu

Se adaugă un rezultat real cu ID-ul registrului sursă, build/commit sau manifest, data/mediu, fixture/drepturi, pași, așteptat/obținut și dovadă minimizată. Se leagă exact testul automat ori proba umană/fizică și se notează responsabilul. Un criteriu mixt se închide numai când toate părțile obligatorii sunt probate. O modificare autorizată de scop se consemnează înainte de acceptare, păstrând specificația și dovezile istorice.
