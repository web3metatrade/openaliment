# Evaluarea strategiei de integrare Open Food Facts

27 octombrie 2026. Evaluare arhitecturală a [planului de implementare](../plans/OPEN_FOOD_FACTS_IMPLEMENTATION_PLAN_2026-10-27.md), după redactarea acestuia și confruntarea cu structura OpenAliment 0.7.3. Aceasta este o analiză a strategiei; testele din plan rămân criterii pentru implementare, nu rezultate obținute.

Strategia este potrivită misiunii OpenAliment și poate fi implementată fără a face serviciul produs–lot–QR dependent de OFF. Separarea catalogului extern, păstrarea contractelor existente și activarea distinctă a distribuirii evită principalele conflicte structurale. Activarea publică rămâne condiționată de tratamentul licențelor, iar scrierea automată de garanțiile transportului OFF; acestea sunt limite ale modulului, nu condiții pentru funcționarea întregii platforme.

## Criteriile evaluării

Au fost comparate planul și implementarea existentă pentru: identitate și mandat GS1; versiuni de etichetă și istoric de lot; fluxurile A/B/C; scanare fără cont; autorizare și izolare între producători; sincronizarea Acasă și clienți vechi; import/export; concurență; operare cPanel; licențe; securitatea conexiunilor externe; posibilitatea de oprire și revenire.

Surse de implementare relevante: `ConsumerFood`, `FoodData`, `HomeInventory`, modulele JS Acasă și cloud, `Catalog`, `Portability`, `Integrations`, `Operations`, `Migrations`, controllerul de actualizări, registrul API și manifestul de release. Referințele externe și versiunile consultate sunt enumerate în plan.

## Constatări și soluții introduse în plan

| ID | Problemă identificată în analiza strategiei | Soluția din planul revizuit | Cum se confirmă în implementare |
| --- | --- | --- | --- |
| OAR01 | Introducerea datelor comunitare în FoodData ar atribui statutul de declarație a producătorului | Pachet de rezoluție separat și supliment extern; FoodData v1 își păstrează sensul | OFT02, OFT03, OFT18–OFT21 |
| OAR02 | Un profil alimentar v2 introdus direct în inventar poate fi respins sau trunchiat de clienți/sincronizare | Profil minimal v1, referință externă în câmpurile deja acceptate și cache separat; noul format complet este limitat la transferul explicit | OFT02, OFT24–OFT28 |
| OAR03 | Bugete individuale identice pe dev și producție ar putea depăși limita comună de IP | Alocare administrată între cele două medii, simulatoare implicite pe staging și cooldown | OFT15 și monitorizarea bugetului total |
| OAR04 | Un fetch extern în workerul comun ar putea întârzia emailul, scanerul și AI | Worker separat, lease scurt și I/O fără lock-ul workerului comun sau tranzacție DB deschisă | OFT13–OFT16, OFT34 |
| OAR05 | Un lookup procesat numai la cron ar face prima scanare prea lentă; accesul nelimitat la fetch ar permite abuz | Cache imediat, un singur fetch scurt admis la POST, apoi 202 și continuarea adăugării; coadă și GTIN-uri distincte limitate | OFT13–OFT16, OFT22, OFT23 |
| OAR06 | Rescrierea cu datele curente OFF ar altera formula istorică sau termenul lotului | Eticheta lotului și datele observate se păstrează; suplimentul este despre produs și nu confirmă formula lotului | OFT07–OFT10 |
| OAR07 | Referințe doar prin ID ar putea lega o revizie sau un conflict de alt produs/tenant | FK compuse pentru produs–revizie și binding–conflict, plus validarea articolului etichetei | OFT31, teste DB de substituire și concurență |
| OAR08 | Creșterea nelimitată a istoricului ar putea umple hostingul; ștergerea după vechime ar rupe referințele Acasă | Cote, oprirea admisiilor, arhivarea normalizată cu hash/index și citire probată; fără presupunerea că JSON-urile nu mai referă o revizie | OFT25, OFT34, OFT35 |
| OAR09 | Trimiterea codului complet în query ar amplifica expunerea loturilor/seriilor în loguri | Noua rezoluție prin POST, fără corpuri de scanare în logurile aplicației; OFF primește numai GTIN | OFT30 și verificarea logurilor reale pe hosting |
| OAR10 | Separarea tabelelor ar putea fi confundată cu eliminarea obligațiilor ODbL | Politică de reutilizare, atribuiri și export reutilizabil; gate distinct înainte de expunere publică | OFT36, verificarea juridică a formei de reutilizare |
| OAR11 | Reconstruirea întregului export la fiecare scanare ar introduce latență și costuri | Snapshot de bază plus pachete incrementale/manifest, cu revizia normalizată descărcabilă | OFT33, OFT36 și măsurători ale publicării |
| OAR12 | Un endpoint PATCH documentat nu dovedește suportul tuturor câmpurilor sau al scrierii condiționale | Matrice pe câmp și transport; fără promisiune de tranzacție distribuită; mod automat limitat la capabilități validate | OFF10, OFT43–OFT47 |
| OAR13 | Datele importate din OFF ar putea fi retrimise ca noutăți ale producătorului | Proveniență păstrată, comparație de amprente și filtrarea ecourilor | OFT32, OFT43 și probe pentru bucla read–draft–write |
| OAR14 | Revocarea nu poate anula garantat o cerere deja plecată | Oprire înaintea claim-ului, reconcilierea operațiilor în zbor și istoric explicit | OFT42, OFT44, OFT48 |
| OAR15 | Un 404 sau o retragere de fișă ar putea fi confundate cu rechemarea unui produs | Disponibilitate externă separată de starea lotului; suspendare publică cu audit, fără ștergerea istoricului | OFT10, OFT16 și cazul retragerii unei fișe cunoscute |
| OAR16 | Un worker nou sau configurația exemplu pot lipsi din pachetul cPanel, deși testele sursei trec | Includere explicită în manifestul release-ului și testarea artifactului pe staging | OFF07, OFF08, OFF12 |

Soluțiile sunt cerințe în planul revizuit, nu remedieri runtime livrate prin această evaluare.

## Alinierea cu misiunea

| Obiectiv OpenAliment | Efectul integrării planificate | Evaluare |
| --- | --- | --- |
| Orice categorie alimentară | Catalogul extern extinde recunoașterea, fără profil de panificație obligatoriu | Compatibil |
| Produs și lot fără rețetă | Inbound și distribuirea OFF sunt opționale; niciun flux nu impune rețeta | Compatibil |
| Documentație deschisă voluntară | Datele externe își păstrează licența; materialele producătorului nu sunt licențiate automat | Compatibil, cu politica de reutilizare validată |
| Siglă bazată pe dosar și criterii | Scorurile OFF nu emit și nu înlocuiesc sigla OpenAliment | Compatibil |
| Găzduire independentă și GS1 | GTIN este puntea de asociere; resolverul, mandatul și URI-urile rămân ale mecanismului existent | Compatibil |
| Istoric și trasabilitate | Etichete și loturi imuabile, surse externe versionate, diferențe explicabile | Compatibil |
| UX simplu pentru consumator | Scanare fără cont nou, nume/fotografie automate, o singură confirmare, data cerută numai când lipsește | Beneficiu direct, de confirmat prin probe cu utilizatori |
| Acces pentru dezvoltatori și AI | Pachet nou documentat, sursă pe câmp și licențe mașinabile; fără acces implicit la date private | Compatibil |
| Portabilitate | Transferurile vechi rămân valide; datele externe au export reutilizabil și proveniență | Compatibil, cu verificarea round-trip |
| Operare ușoară pe cPanel | Cozi DB și cron PHP, importuri streaming, fără servicii permanente noi | Fezabil pentru pilotul limitat; volumul se măsoară |

OFF completează acoperirea catalogului și permite o contribuție voluntară la ecosistemul deschis. Valoarea proprie a OpenAliment rămâne administrarea de către producători, loturile, informațiile istorice, integrarea ERP/echipamente, documentația tehnică și resolverul GS1. Nu se creează un catalog extern care acordă implicit autoritate asupra tuturor GTIN-urilor întâlnite.

## Verificarea scenariilor operaționale

### OFF este indisponibil o zi

Resolverul și paginile aprobate nu apelează OFF. Acasă folosește inventarul și cache-ul, iar pentru un produs necunoscut permite adăugarea și confirmarea datei. Circuitul oprește reîncercările excesive. Emailul, documentele și AI folosesc workerul existent. Dependența afectează recunoașterea sau prospețimea externă, nu funcționarea serviciului A.

### Intră multe produse noi într-un interval scurt

Cache-ul servește codurile cunoscute. Deduplicarea reunește cererile identice, iar bugetul, cota de GTIN-uri noi și coada finită limitează admisiile. Cererile care nu primesc capacitate continuă ca alimente necunoscute. Seedul și importul din exporturi rezolvă creșterea acoperirii; nu se promite recunoaștere instantanee pentru orice cod nou în afara capacității sursei.

Acesta este compromisul operațional principal: nu toate primele scanări vor putea beneficia imediat de OFF. Explicarea lui în stările API și continuarea fluxului evită blocarea utilizatorului, fără a ascunde lipsa informațiilor.

### Se modifică ingredientele unui produs care are loturi vechi

Se înregistrează revizia externă și diferența. Loturile vechi păstrează versiunea etichetei. Interfața nu amestecă ingredientele vechi cu alergenii sau nutriția unei formule curente. Acceptarea unei diferențe de către producător creează o ciornă pentru o versiune nouă; nu actualizează retrospectiv loturile.

### O familie folosește simultan clienți vechi și noi

Itemul sincronizat păstrează profilul v1. Referința externă folosește câmpurile deja acceptate, iar suplimentele sunt separate de amprenta itemului. Un client vechi vede o informație minimală și date alimentare necunoscute; nu interpretează fișa externă drept declarație a producătorului. Testarea amprentelor, importului și a actualizării service worker rămâne obligatorie înainte de activare.

Pentru un produs aprobat cu supliment extern, suplimentul nu intră în istoricul etichetei și nu trebuie propagat ca o modificare personală a itemului. Dispozitivele îl pot reîncărca separat. Această delimitare evită conflicte artificiale la fiecare refresh de sursă.

### Producătorul și comunitatea modifică același câmp

Comparația în trei versiuni identifică diferența. Fără suport demonstrat pentru scriere condițională, un simplu read–PATCH poate avea o cursă; planul nu îl tratează ca sigur pentru înlocuiri automate. Exportul pentru producător și decizia manuală rămân căi complete de distribuire. Activarea unei automatizări mai largi cere garanții reale ale transportului, nu dezactivarea protecțiilor locale.

### Acordul este retras în timpul unei transmiteri

Joburile care nu au plecat sunt oprite. Pentru operația deja expediată se păstrează starea, se citește rezultatul remote și se consemnează confirmarea sau incertitudinea. Revocarea nu promite anularea retroactivă a unei distribuiri publice. Această regulă trebuie să fie aceeași în UI, API și acord.

### Spațiul sau backupul devin insuficiente

Se opresc admisiile și fișierele externe noi; serviciile produs/lot/QR rămân active. Arhiva include reviziile necesare referințelor, iar restaurarea se verifică în mediu izolat. Nu se folosește o curățare agresivă pentru a masca lipsa capacității. Cota trebuie să includă artifactele și arhivele, nu doar imaginile curente.

## De ce structura nu este mai simplă sau mai complexă

Copierea directă în `products`/`product_info_versions` ar economisi tabele, dar ar amesteca autoritatea, licențele și publicarea. A folosi tabelul de conectori ERP pentru catalogul public ar asocia impropriu date globale unei organizații. Un JSON mare în configurație nu ar rezolva concurența, istoricul și căutarea după GTIN.

Cele nouă tabele inbound acoperă identitatea sursei, identitatea produsului, reviziile, media, coada, bugetele, operațiile de fișier și cele două relații private cu producătorul. Cele trei outbound separă conexiunea, acordul și intenția de distribuire. Nutriția, taxonomiile și proveniența nu sunt desfăcute în sute de tabele de atribute. Auditul, identitatea, rolurile și catalogul propriu sunt reutilizate.

Nu se introduce un framework de pluginuri executabile pe hosting, un broker de mesaje sau o instanță Product Opener doar pentru pilot. Un adaptor cu capabilități declarate permite ulterior alte surse fără a face acceptabile URL-uri sau mapări executabile arbitrare. Separarea în clase rămâne limitată la responsabilitățile din plan.

## Condiții de activare și responsabilități

| Condiție | Cine o închide | Ce permite și ce rămâne funcțional înainte |
| --- | --- | --- |
| Contracte, FK, profile și regresii | Responsabil tehnic și QA | Permite activarea codului inbound pe staging; platforma existentă continuă cu feature flag oprit |
| Licențe, atribuiri și forma exportului derivat | Responsabilul proiectului, cu clarificare OFF/juridică unde este necesar | Permite publicarea catalogului extern; nu condiționează paginile proprii sau dezvoltarea locală |
| Cotă cPanel, bugete între medii și cron | Administratorul hostingului și responsabilul tehnic | Permite pilotul cu volum limitat; scanarea locală și datele proprii nu depind de workerul extern |
| Utilizare API identificată și transport citire probat | Responsabilul integrării, cu autorizare pentru contactul extern | Permite apeluri reale controlate; simulatorul acoperă dezvoltarea și CI |
| Organizație, mandat și drepturi de distribuire | Owner al producătorului și administratorul integrării | Permite conexiunea outbound; lectura OFF rămâne independentă |
| Capabilități de scriere, confirmare și concurență | Echipa integrării împreună cu OFF | Permite automatizarea exact în domeniul validat; altfel se folosește distribuirea prin pachete/import confirmat |
| Backup, rollback și artifact identic staging/producție | Proprietarul și responsabilul operațional | Permite promovarea pe producție conform fluxului GitHub existent |

Înființarea ONG-ului și operarea comercială rămân guvernate de politica actuală a proiectului. Integrarea nu activează plăți, contracte comerciale, parteneriate declarate sau consimțăminte în numele utilizatorilor.

## Decizia tehnică și limitele confirmării

Planul revizuit este recomandat pentru implementare. Nu conține o schimbare incompatibilă obligatorie pentru catalogul propriu, resolver sau sincronizarea existentă. Condițiile externe sunt izolate la activarea catalogului/distribuirii, iar oprirea acestor funcții nu este proiectată să oprească serviciile principale.

Această concluzie confirmă coerența strategiei, nu corectitudinea unui cod încă nescris și nici o certificare GS1 sau o avizare juridică. Cele 48 de scenarii, măsurătorile pe cPanel și pilotul real sunt probele necesare pentru trecerea de la strategie la funcție disponibilă. Nu ar fi justificată o promisiune de zero conflicte, sincronizare universală în timp real ori volum nelimitat pe shared hosting.

Prima implementare trebuie să închidă OFF01–OFF09 complet: contracte, catalog, operare, UX, portabilitate și documentație. Distribuirea OFF10–OFF12 rămâne definită în același plan, cu condiții precise de activare. Această ordine aduce beneficiul pentru consumator fără a lega lansarea sa de un acces de scriere care nu este încă stabilit.
