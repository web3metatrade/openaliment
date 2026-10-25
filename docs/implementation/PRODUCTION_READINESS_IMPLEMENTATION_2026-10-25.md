# Implementarea auditului de producție

25.10.2026 · bază 0.5.5 · release 0.6.0 publicat pe openaliment.ro, migrare aditivă 007 activată după acordul explicit al utilizatorului. Planul integral nu este declarat închis.

Sursa sarcinilor este [auditul](../audits/PRODUCTION_READINESS_AUDIT_2026-10-25.md). Cererea utilizatorului este implementarea lui, păstrând compatibilitatea PHP MVC/cPanel, API-urile, QR-urile și originalele. Rapoartele auditului rămân istorice și nu sunt rescrise ca probe ale noului cod.

Clarificare ulterioară: înainte de înființarea ONG-ului, OpenAliment este un proiect în formare și nu operează comercial. Nu se inventează o entitate sau un mandat juridic. Cron Jobs există în cPanel. Executabilul PHP 8.4 pentru OpenAliment trebuie probat separat de executabilele 8.2/8.3 folosite de celelalte aplicații ale contului. Destinația externă de backup nu este încă indicată.

## Modificări publicate

- Migrare aditivă 007 pentru sesiuni individuale, passkeys/recuperare, confirmări sensibile, voturi versionate, criterii de recenzie, bloburi/proveniențe, scanare și operare.
- WebAuthn verificat cu biblioteca lbuchs/WebAuthn 2.2.0, inclusă local, MIT; UV, origine exactă, challenge single-use, cont și sesiune, coduri de recuperare hash și păstrarea MFA la resetul parolei.
- Sesiuni PHP în storage privat; listare/revocare individuală; recuperare prin email cu răspuns uniform și mesaje criptate în coada privată.
- Vot retras păstrat cu mutation_version, refuz de retry vechi, proiecția legacy votes menținută. Note și agregate pe criterii distincte, necunoscutul rămâne null.
- Stocare de documente deduplicată în interiorul organizației, proveniențe separate și verificare înainte de publicare. Adaptor ClamAV local, fără root, fără exec și fără upload la un serviciu public. Lipsa scanerului nu produce rezultat clean.
- Confirmări prin email pentru transferul familiei, părăsire și ștergere; revocare, eliminarea emailului/spațiilor personale și coadă pentru fotografii, cu păstrarea datelor altor membri și registru de ștergere.
- Worker unic, limitat, cu heartbeat și diagnostic privat pentru cPanel; backup ZIP AES-256, restaurare într-o destinație goală și reconcilierea registrului actual de ștergere; monitor HTTPS independent, fără trimitere automată de mesaje.
- Import NDJSON cu digest validat înainte de scriere, maximum 24 MB/10.000 rânduri pe colecție; licențe păstrate ca metadate private și proveniențe multiple exportate/importate. Importul PBT în schema nouă păstrează R1 experimentală și carantina BT01.
- ZIP autonom al documentației publice licențiate, cu README, snapshot exact, manifest și licențe distincte; cache limitat la 200 arhive/256 MiB/24 ore. Materialele private și fără licență sunt excluse.
- Portal API 2.3.0: 229 operații și 18 ghiduri. Disponibilitatea funcțiilor care cer 007 este declarată în manifest, separat de existența rutei.
- Formulare de producător adaptate rolului, panouri de securitate cu spațiere uniformă și verificare desktop/mobil; PWA v7 păstrează baza IndexedDB existentă.

Migrarea 007 este activă. Înrolarea MFA se face separat de fiecare utilizator; utilizatorul existent nu primește o cheie inventată. Mutarea sesiunilor PHP private cere o reconectare a producătorilor; cookie-ul Acasă și inventarul local rămân independente. Administratorul s-a reconectat înaintea activării.

## Închidere

Suita finală: **47 suite, 12.799 verificări contorizate, zero eșecuri**, în MariaDB local și Edge cu profiluri temporare. Toate cele **229 operații documentate** au un succes HTTP real validat după schema publicată. Sunt incluse 49 verificări de domeniu pentru 007, 31 pentru noile fluxuri HTTP, 27 de browser Acasă și 9 de browser cu `navigator.credentials` și autentificator virtual. Înrolarea/semnăturile WebAuthn sunt verificate criptografic; nu reprezintă o probă pe telefon sau cheie fizică.

Restaurarea are 15 verificări pe un set mic complet: fișier absent refuzat, AES, cheie greșită, hashuri, schemă/rânduri, accesuri revocate, lot cu etichetă istorică și reaplicarea unei ștergeri ulterioare backupului. Durata setului mic nu este o probă RTO/RPO pentru baza reală. Un test anterior pe fixture-uri acumulate a durat prea mult și a fost oprit; nu este declarat test de volum reușit. Configurațiile de producție nu sunt citite în testul de backup.

Biblioteca nouă lbuchs/WebAuthn 2.2.0 este inclusă fără modificări, MIT, cu hashuri în manifestul dependențelor. Interogarea OSV din ziua verificării nu a returnat advisories pentru această versiune; aceasta nu înlocuiește revizia sau monitorizarea viitoare.

[Dovezile sanitizate](../verification/readiness-0.6.0/summary.json) păstrează rapoartele și hashurile runtime. Logurile brute, tokenurile, configurațiile și cheile sintetice rămân în spațiile ignorate. Cele 47 suite au rulat local; matricea CI MariaDB 10.6/11.4 este configurată, dar nu este declarată executată pe GitHub.

## Publicare și pași operaționali rămași

Comparația inițială HTTPS: 123 fișiere diferite, fără conflicte față de baseline-ul publicat 0.5.5. Utilizatorul a aprobat explicit: **„Da, publică 0.6.0 și activează 007”**. Cele 123 de fișiere au fost publicate în 15 operații confirmate, cu verificarea hashurilor și păstrarea copiilor pentru revenire. Interfața administrativă autentificată a activat `install_readiness`, instalând și cele zece resurse operaționale cu manifest fix și hash verificat.

La transfer, un șablon care începea cu HTML (`app/Views/recipe.php`) a fost refuzat de verificarea tagului PHP. A fost adăugat doar `<?php ?>` înaintea conținutului, fără schimbarea textului sau comportamentului paginii; sintaxa și echivalența tokenurilor utile au fost verificate. Publicarea a continuat recunoscând hashurile fișierelor deja aplicate. Verificarea ulterioară a repository-ului a trecut 403 verificări; această etapă suplimentară nu este inclusă în cele 12.799 de verificări ale suitei complete precedente.

Confirmarea administrativă și capabilitățile publice din manifest arată 007 activă. [Probele HTTPS după publicare](../verification/readiness-0.6.0/production-smoke.json) au trecut **23/23**: pagini și linkset pentru QR de produs și lot, HEAD/OPTIONS, contract și documentație API, manifest, Acasă, recuperare și refuzul accesului neautentificat la resurse private. Cele [cinci probe de disponibilitate și TLS](../verification/readiness-0.6.0/availability-after.json) au trecut separat. Probele nu au creat produse, nu au modificat loturi și nu au trimis emailuri. [Confirmarea vizuală](../verification/readiness-0.6.0/migration-007-confirmed.jpg) și [jurnalul sanitizat de publicare](../verification/readiness-0.6.0/publication.json) sunt păstrate cu raportul.

Verificarea automată respinsese inițial comanda de pregătire HTTPS, interpretând scriptul ca putând publica release-ul și cerând acord explicit pentru 0.6.0. Ramura de inspectare a fost apoi separată și probată offline cu zero apeluri `deploy`; inspectarea a fost acceptată. Publicarea efectivă a fost executată după acordul explicit citat mai sus.

Procedura concretă este în [runbook](../operations/PRODUCTION_RUNBOOK.md). Înainte de operare comercială sunt necesare: organizarea ONG-ului și responsabilitățile reale, executabilul PHP CLI și cronul probate în cPanel, cheia de backup păstrată separat și destinația externă verificată, restaurare pe infrastructura țintă, motorul antivirus configurat, confirmarea livrării SMTP și înrolarea administratorilor. Lipsa motorului blochează publicarea documentelor noi; nu produce o verificare fictivă.

Modulele extinse de dosar complet/evaluare-siglă, RAG/AI suplimentar, notificări, surse externe, domenii de producător și aplicații native rămân în ordinea auditului. Acest release nu le marchează disponibile și nu închide întregul plan WP00–WP27. Capacitatea de milioane de scanări, telefonul real, POS-ul și disponibilitatea lunară cer probe separate, înaintea asumării lor contractuale.
