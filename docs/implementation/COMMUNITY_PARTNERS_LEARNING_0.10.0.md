# Contribuitori, parteneri și formare · 0.10.0

Implementarea planului din 29 octombrie 2026. Ținta de publicare este `dev.openaliment.ro`; instalarea pe o anumită instanță se confirmă prin manifestul acesteia și prin verificarea migrărilor. Raportul descrie codul și probele, fără să pretindă o publicare în producție.

## Fluxuri livrate

| Zonă | Comportament |
| --- | --- |
| Contribuitori | Profil propriu sau curatoriat, acord legat de revizie, prezentare și fotografie aprobate, căutare, export și retragere imediată. Profilurile curatoriate se confirmă prin email; retragerea fără cont se solicită contactului platformei. |
| Companie parteneră | Intrare cu CUI, ANAF v9, toate grupurile sursei, corectare înainte de confirmare, prezentare separată de identitatea fiscală, logo, email confirmat și telefon verificat de evaluator. |
| Reprezentare | Review independent și motivat. Un CUI existent intră în reconciliere explicită, fără rezervarea lui de o cerere nevalidată și fără transferul tenantului. |
| Persoana și firma | Progresul și calificarea rămân ale persoanei. Invitațiile acordă afiliere în program; schimbarea CUI-ului creează alt dosar. Nicio afiliere nu acordă automat acces la produsele sau rețetele firmei. |
| Cursuri | Cont personal fără organizație fictivă, 32 de lecții proprii, 16 module, patru specializări, resurse oficiale, exerciții publice și progres pe ediții imuabile. O specializare include 24 de lecții comune și două specifice. |
| Editor | Editare vizuală și JSON, import/export, validare înainte de salvare, drept separat de publicare, ediții cu hash și retragere pentru utilizări noi. Publicarea nu rescrie cursul persoanelor deja înscrise. |
| Examen | Bancă privată versionată, minimum 100 de întrebări, selecție aleatoare de 30, autosalvare, reluare fără prelungirea termenului, praguri globale/pe domeniu și toate întrebările critice corecte. |
| Probe | P01–P06, laborator limitat fără execuție SQL, rețea sau echipamente reale. Două probe comune și una specifică, verificări automate urmate de rubrică independentă; P04 necesită dovadă fizică revizuită. |
| Rezultate | Contestații, versiuni noi la reevaluare, rubrici și motive păstrate în audit, calificări și autorizații cu termen, suspendare, revocare și verificare publică prin QR web. |
| Publicare | Firma apare numai cu prezentare aprobată, situație fiscală activă și proaspătă, email verificat și persoană afiliată calificată. Condițiile se recalculează la citire, fără a aștepta cronul. |
| API | 109 operații noi, 410 în contractul agregat, DTO-uri explicite, OAuth personal separat de ERP/Acasă, revocare, CSRF, idempotency, exemple și documentație Markdown/Postman/AI. |
| Operare | Migrări explicite 012–015, 23 de tabele, worker cu tranșe și retry, bugete de scriere și imagini, retenție și backup criptat cu fișierele private ale programului. |

Lecțiile proprii folosesc CC BY 4.0. Standardele GS1 indicate prin referințe își păstrează drepturile. Banca reală pregătită separat conține 107 întrebări, inclusiv 12 critice; ea nu este în repository, în documentația publică sau în pachetul cPanel. Fixture-ul public are întrebări sintetice și este respins pe hosting.

## Compatibilitate și decizii de implementare

- Rutele noi sunt grupate sub `/api/v2/.../program/`. Matricea exactă, schemele și permisiunile sunt în [inventarul generat](../api/PROGRAM_ACTION_MATRIX.md). Nu sunt modificate adresele GS1 Digital Link sau contractele de identificare a produselor/loturilor.
- Adaptoarele HTTP/web folosesc aceleași cazuri de utilizare, cu clase de domeniu separate. S-a păstrat MVC-ul existent și nu s-au adăugat frameworkuri sau servere runtime noi. GD este folosit pentru reencodarea imaginilor; lipsa sa oprește uploadul sigur, fără a afecta QR sau lectura.
- Activarea standalone instalează dependențele de cont, OAuth și securitate. Nu condiționează programul de activarea Open Food Facts sau de o rețetă publicată.
- O ediție de teorie promovată poate fi reutilizată pentru alte specializări ale aceleiași ediții de curs; lecțiile și probele specifice rămân obligatorii. Calificarea personală, autorizarea companiei, evaluarea documentației alimentare și acreditarea GS1 sunt distincte.
- Prospețimea fiscală este de maximum 30 de zile, cu reverificare de la ziua 27 și backoff la indisponibilitate. Nu există o săptămână suplimentară de afișare ca autorizat fără o observație validă.
- Importul personal reia numai poziții de lectură pentru conținut identic. Nu acordă promovări, calificări sau mandate dintr-un fișier. Firma nouă și reprezentarea ei trec prin verificările curente.
- Bugetul comun al imaginilor este 30 MiB/cont și 2 GiB global. Scrierile personale au plafoane ponderate după cerere și răspuns, inclusiv recipisele de retry. Aceste limite sunt documentate în ghidul OAuth; rolurile editoriale verificate au tratament separat.
- La restaurare, o copie veche nu reactivează acorduri, calificări, roluri delegate, contacte sau profiluri publice. Rezultatele, edițiile și amprentele rămân disponibile pentru reconciliere.

## Probe de acceptare

Suita completă existentă, cu reexecutarea probelor afectate de corecții, a trecut local pe PHP 8.4/MariaDB 11.4. Rezumatul sanitizat este în [raportul de verificare](../verification/community-partners-learning-0.10.0.json). Testele automate folosesc exclusiv baza `openaliment_tests`, firme sintetice și transport local de email.

- Toate cele 410 operații au cel puțin un succes real HTTP validat față de schemă. Cele 109 operații noi sunt probate prin teste funcționale, cu refuzuri pentru CSRF lipsă, acces ERP, alt cont, rol revocat și revizie expirată.
- Browser: CUI inițial și corectat, toate câmpurile ANAF, înregistrare în contextul partenerilor, logo, confirmare email, review, 26 de lecții, examen cu autosalvare și reload, trei probe, calificare și publicare, contribuție și retragere, schimbare de firmă și păstrarea istoricului.
- Editor: ciornă vizuală fără înlocuirea ediției publicate, import/export al băncii sintetice și blocarea salvării când cheia de corectare introdusă nu este JSON valid.
- Celelalte specializări au probe de domeniu complete, inclusiv refuzul promovării P04 fără confirmarea dovezii fizice. O imagine sintetică folosită în test verifică mecanismul software, nu o tipărire sau un POS real.
- Layout verificat la 390, 768, 1200, 1280 și 1440 px. Formularele CUI și predarea examenului au cale HTML fără JavaScript; schimbarea unui CUI neconfirmat este refuzată pe server.
- Backupul este creat, verificat, decriptat și restaurat într-o destinație izolată. Proba include efectiv imagine de program, profiluri publice și calificări înainte de copie și verifică retragerea lor la restaurare.

Testele nu reprezintă acreditare GS1, audit extern, validare juridică a unui mandat real sau certificare fizică a unei imprimante/POS. Firma WEB3 METATRADE, folosită în probele ANAF anterioare, este inactivă în observația consultată și nu trebuie publicată ca partener activ pe baza acestor probe.

## Operare și verificare online

[Instrucțiunile de operare](../operations/PARTNER_PROGRAM.md) descriu instalarea, publicarea privată a băncii, rolurile, cronul și restaurarea. Promovarea în `main` și producție este un flux separat. Un merge în `dev` nu confirmă singur instalarea pe hosting sau funcționarea cronului.
