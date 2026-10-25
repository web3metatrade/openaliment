# OpenAliment — soluții de îmbunătățire a securității

24.10.2026 · bază verificată: release 0.5.3 și migrarea 006 active. **Propunere de implementare; măsurile noi de mai jos nu sunt declarate instalate.**

Obiectivul este întărirea accesului, a publicării și a operării, cu păstrarea QR-urilor persistente, a API-urilor documentate și a folosirii simple a aplicației Acasă. Acest document completează [schimbările deja publicate](../implementation/SECURITY_HARDENING_0.5.3.md) și [procedura operațională](SECURITY_OPERATIONS.md). Nu înlocuiește analiza UI/UX și nu adaugă o nouă listă de vulnerabilități demonstrate.

## Ce păstrăm din implementarea existentă

Autorizarea pe organizație și scopes, refuzul Bearer pe administrarea HTML, parolele întărite, sesiunea producătorului cu limite, CSRF, cheile revocabile, OAuth cu PKCE și refresh rotit, datele publice aprobate, stocarea privată a fișierelor, antetele HTTPS și separarea AI/QR sunt deja implementate. Nu le prezentăm din nou ca funcții care lipsesc.

Reverificarea surselor a confirmat și că:

- `ApiKeys::recent()` cere deja autentificare recentă pentru emitere/rotație, cu o fereastră de 15 minute; extindem această politică la alte operațiuni sensibile.
- Acasă are deja dispozitive revocabile, linkuri de conectare de unică folosință și sesiune persistentă. Adăugăm confirmări numai unde este necesar accesul sensibil.
- Documentele au deja validare MIME, limite și aprobare de publicare. Adăugăm starea verificării conținutului.
- Jurnalele și curățarea limitată există. Completăm executarea programată și semnalele de operare.
- AI construiește deja context din feedback public moderat și versiuni publicate, fără interogarea rețetelor private. Păstrăm această separare și o testăm explicit la orice extindere.

## Ordinea recomandată

| Ordine | Soluție | Rezultat pentru utilizator / operator | Unde se realizează |
|---:|---|---|---|
| 1 | Autentificare suplimentară pentru acces privilegiat și confirmarea acțiunilor sensibile | Administratorul și proprietarul organizației pot proteja publicarea și accesul la cont | Aplicație + o implementare WebAuthn verificată |
| 2 | Recuperare sigură și administrarea sesiunilor | Recuperarea accesului și retragerea unui singur dispozitiv, fără intervenții manuale în DB | Aplicație + SMTP existent |
| 3 | Backup automat independent, cu restaurare probată | Recuperare verificabilă a produselor, loturilor, alertelor și fișierelor | Hosting + destinație de backup controlată de operator |
| 4 | Ferestre limitate pentru mentenanța HTTPS și privilegii separate pentru migrări | Actualizări controlate, cu păstrarea canalului de publicare solicitat | Aplicație + cPanel |
| 5 | Carantină și scanare înainte de publicarea documentelor | Numai copia verificată poate fi publicată | Aplicație + scaner disponibil pe hosting sau serviciu privat ales |
| 6 | Alerte de securitate și mentenanță programată | Operatorul află despre evenimente importante și joburi oprite | Aplicație + cPanel Cron Jobs + SMTP |
| 7 | Protecție la nivelul hostingului / proxy și limitare adaptată scanărilor | Traficul abuziv este oprit înainte să consume PHP și DB | Hosting / serviciu de protecție configurat de operator |
| 8 | Politică suplimentară pentru chei ERP și destinații resolver | Fiecare integrare și schimbare de destinație are control explicit | Aplicație |
| 9 | Protecția emailului și administrarea datelor personale | Conectare prin email fiabilă și control mai complet asupra datelor | Aplicație + DNS/cPanel |
| 10 | Verificări de securitate obligatorii la fiecare release | Îmbunătățirile rămân valabile când contribuie alți dezvoltatori | Teste locale + GitHub CI, după publicarea repository-ului |

Soluțiile 1–2 se implementează împreună, astfel încât activarea protecției suplimentare să aibă și o cale sigură de recuperare. Soluția 3 se poate configura în paralel, înainte de următoarele migrări.

## 1. Acces privilegiat și operațiuni sensibile

### Implementare

- Passkey/WebAuthn cu verificarea utilizatorului pentru administratori; disponibil și proprietarilor/responsabililor organizațiilor. Se utilizează o bibliotecă întreținută și verificată, cu dependențe evaluate, nu criptografie proprie.
- Challenge de unică folosință, scurt, legat de cont și sesiune; verificarea pe server a RP ID, originii și indicatorului de user verification. O simplă atingere de confirmare a prezenței nu se tratează automat drept al doilea factor. [OWASP MFA](https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html).
- Dacă este necesară o alternativă TOTP, secretul se păstrează criptat cu o cheie privată configurată în afara `public/`; codurile folosite nu se acceptă din nou. Alternativa nu ocolește politica de recuperare.
- `Auth::admin()` și toate API-urile administrative verifică starea autentificării complete. Parola validă singură nu acordă privilegiile care cer MFA.
- Un serviciu comun de confirmare recentă se aplică în browser la schimbarea factorilor, emiterea/rotația cheilor, schimbarea destinației implicite, exporturile sensibile și deciziile administrative. Pentru schimbări importante se leagă confirmarea de operația și datele aprobate, cu protecție la reluare. [OWASP Transaction Authorization](https://cheatsheetseries.owasp.org/cheatsheets/Transaction_Authorization_Cheat_Sheet.html).
- Automatizările ERP continuă să folosească scopes, roluri și aprobările configurate; nu primesc un formular MFA la fiecare cerere. O organizație poate separa cheia care creează ciorne de cheia/contul care aprobă publicarea.

### Folosire și verificare

Clientul care scanează QR nu primește o cerere de autentificare. Acasă își păstrează conectarea persistentă. Acțiunile obișnuite nu cer repetarea confirmării recente.

Teste: acces HTML și API admin refuzat fără factorul cerut; challenge expirat/reutilizat refuzat; origine/RP greșit refuzat; modificarea payloadului după confirmare refuzată; imposibilitatea ocolirii prin API v1/v2. Activarea pentru administrator se face etapizat, după înrolarea și verificarea metodei de recuperare.

Ținte: `app/Core/Auth.php`, `Session.php`, `ApiKeys.php`, controlerele de cont/admin, un modul comun pentru autentificare suplimentară și migrări aditive pentru credentiale/challenge-uri.

## 2. Recuperare de cont și sesiuni individuale

- Recuperare a parolei prin email cu răspuns public uniform, token aleator stocat ca hash, expirare scurtă și utilizare unică. Resetarea parolei nu dezactivează automat MFA.
- Coduri de recuperare MFA afișate o singură dată, stocate ca hashuri și consumate atomic. Schimbarea factorului cere o metodă de recuperare autorizată și trimite notificare de securitate.
- Registru al sesiunilor producătorului: denumire simplă a dispozitivului, ultima folosire și retragere individuală. Se păstrează și comanda existentă de retragere a tuturor accesurilor. Nu se impune o adresă IP fixă utilizatorilor mobili.
- Sesiunile PHP se stochează într-un director privat dedicat aplicației, cu permisiuni restrictive și curățare limitată. Izolarea de alte aplicații care rulează sub același utilizator de hosting necesită și măsuri ale furnizorului; permisiunile directorului singure nu asigură această separare.
- Pentru Acasă, confirmare nouă prin email la schimbarea identității ori operațiuni sensibile asupra contului; inventarul și scanarea rămân utilizabile. O eventuală rotație periodică a tokenului dispozitivului include compatibilitate scurtă pentru cererile paralele și retry, pentru a evita deconectări accidentale.

Teste: token expirat și reluare, două confirmări concurente, invalidarea sesiunilor după resetare, păstrarea MFA, retragerea unui singur dispozitiv și revenirea dintr-o întrerupere de rețea.

Ținte: `Accounts.php`, `HomeIdentity.php`, `Session.php`, `Mailer.php`, `AccountSecurityController.php`, contracte API aditive și migrări de acces.

## 3. Backup independent și restaurare

- Backup automat pentru DB, documentele private și configurația necesară restaurării, criptat și păstrat separat de contul care rulează aplicația. Cheia de decriptare și accesul de ștergere a copiilor nu se expun aplicației publice.
- Politică de retenție și obiective de pierdere maximă de date/timp de recuperare stabilite cu operatorul și furnizorul. Alegerea unei politici în panou nu înlocuiește verificarea restaurării.
- Copie înainte de migrări; manifest care permite verificarea coerenței DB–fișiere. Backupul codului din bridge rămâne util, dar nu este backupul bazei de date.
- Restaurare de probă într-un mediu separat, cu verificarea loturilor istorice, alertelor curente, hashurilor documentelor și imposibilității accesului între organizații.
- Procedură de revenire a codului și procedură separată pentru date. O migrare aditivă nu se inversează prin ștergerea automată a tabelelor când se revine la versiunea anterioară de cod.

Dependență: o destinație de backup și capabilitățile reale ale furnizorului. Acest document nu presupune că sunt configurate și nu transferă date către un furnizor terț.

## 4. Mentenanță HTTPS și privilegii pe hosting

- Canalul HTTPS de actualizare se păstrează, cu cheie distinctă pentru o fereastră de release, expirare scurtă și închidere după verificarea publicării. Generatorul are deja implicit șapte zile; rotația endpointului instalat se coordonează prin cPanel.
- Se păstrează allowlistul de fișiere, baseline-ul, hashurile, backupul și rollbackul existente. Endpointul nu primește SQL arbitrar, acces la configurația privată sau dreptul de a-și modifica propria politică.
- Cont DB curent pentru operațiuni normale, cu privilegii minime; cont separat pentru migrări dacă hostingul permite. Migratorul folosește explicit conexiunea dedicată, fără a transforma conexiunea globală de runtime într-una cu privilegii DDL.
- Verificarea permisiunilor fișierelor private și a izolării de celelalte site-uri din cont. Limitarea PHP la entry pointurile necesare și a execuției în directoarele de resurse se păstrează.
- HSTS poate crește treptat după o perioadă verificată de funcționare HTTPS. Extinderea la subdomenii și preload se evaluează separat, după inventarierea gazdelor.

Acceptare: cheia expirată/dezactivată nu poate actualiza; configurația privată rămâne inaccesibilă bridge-ului; contul DB de runtime nu poate executa DDL; migrările autorizate și rollbackul codului funcționează fără shell pe hosting.

## 5. Fișiere: carantină, scanare, publicare

- Stare de verificare separată de drepturile/licența documentului: `pending`, `clean`, `rejected`, `error`, plus starea explicită a fișierelor existente neverificate. Hashul, motorul/versiunea și momentul scanării leagă rezultatul de conținutul exact.
- Uploadurile și importurile noi rămân private până la încheierea verificării. Publicarea verifică și starea fișierului în domeniu, pentru HTML și API. O întrerupere a scanerului păstrează documentul în așteptare și nu blochează pagina produsului/lotului.
- Adaptor pentru scanerul oferit de hosting sau un serviciu privat autorizat de operator. Nu se presupune instalare cu root și nu se trimit automat rețete/documente private către servicii publice de analiză.
- Pentru tipuri potrivite, se poate produce o copie publicabilă reconstruită; originalul și proveniența se păstrează separat. Verificarea tipului, limitelor și stocarea în afara webrootului rămân necesare. [OWASP File Upload](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html).
- Migrarea nu etichetează fișierele existente drept verificate și nu retrage în bloc documentele deja publice. Ele intră într-un proces controlat de verificare; un fișier respins poate fi retras cu istoric și mesaj adecvat, păstrând originalul privat.
- Originalele OpenBread/Pâine Bună Tehnic nu sunt rescrise sau „reparate” automat. Cotele de dimensiune, pixeli, număr de fișiere și operațiuni se aplică și importurilor, în mod echivalent cu uploadul.

Teste: rezultat de scanare pentru alt hash, fișier schimbat după scanare, timeout, MIME fals, publicare înainte de verificare, import concurent și păstrarea istoricului originalelor.

Ținte: `Documents.php`, `FilePolicy.php`, `Portability.php`, `HomePhotos.php`, worker, adaptor de scanare și migrare aditivă pentru starea verificării.

## 6. Alerte utile și mentenanță automată

- Coadă limitată de notificări pentru conectare nouă, factor schimbat, recuperare de cont, cheie emisă/rotită, export sensibil și schimbarea destinației implicite. Mesajele au link către propriul cont și nu conțin secrete sau documente private.
- Agregarea evenimentelor repetate și bugete pentru a preveni inundarea emailului. Lipsa SMTP nu blochează QR-ul sau operațiunea deja autorizată.
- Alertă de stare pentru jurnal plin, job de curățare nerulat, erori repetate ale scanerului și backup nereușit. Un panou administrativ afișează ultima execuție și rezultatul, fără versiuni/secrete publice.
- Cron privat în cPanel pentru worker și verificarea execuției. Dacă furnizorul nu oferă cron PHP CLI, se stabilește un mecanism privat autentificat și limitat; workerul CLI nu se expune direct la un URL public.
- Copie controlată a jurnalelor de securitate în afara hostingului, cu retenție și acces restrictive. Nu se jurnalizează parole, tokenuri, cookie-uri sau payloaduri alimentare private. [OWASP Logging](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html).
- Pseudonimul de rețea din jurnal poate folosi o cheie privată rotită/versionată, astfel încât să nu fie calculabil doar din data zilei și IP. Minimizarea și retenția rămân necesare; pseudonimizarea nu se declară anonimizare.

Teste: deduplicare, plafon de notificări, redactare, SMTP indisponibil, ultimul job prea vechi, jurnal la limită și lipsa dependenței QR de toate aceste servicii.

## 7. Protecție pentru trafic mare și abuz

- Protecție la nivelul hostingului sau al unui proxy administrat, cu reguli separate pentru autentificare, upload, API și resolver. Scanarea publică nu primește CAPTCHA și API-ul nu este condiționat de executarea JavaScriptului într-un browser.
- Limitele existente se păstrează și se calibrează cu probe într-un mediu dedicat. Multe persoane pot folosi aceeași rețea/NAT; limitarea numai pe IP nu devine regula unică de control.
- La introducerea unui proxy se configurează numai sursele de încredere reale, pentru ca adresele din antetele forward să nu poată fi inventate de client.
- Cache la nivel de resurse statice imediat; cache pentru date publice numai după testarea politicii de invalidare. Paginile private, sesiunile, dispozitivele, fotografiile și exporturile nu intră în cache public. Alertele și retragerile nu se servesc din copii învechite sub pretextul disponibilității.
- Limite de durată/memorie și răspunsuri previzibile pentru operațiuni costisitoare, cu păstrarea procesării AI, scanării și trimiterii emailului în afara traseului QR.

Acceptare: trafic legitim agregat și val de cereri simulate, fără teste masive pe producție; actualizarea unei alerte este vizibilă conform contractului stabilit; GET/HEAD/OPTIONS și negocierea resolverului continuă să funcționeze.

## 8. Chei ERP și destinații resolver

- Politici opționale pe cheie ERP: CIDR-uri permise pentru partenerii cu IP stabil, durată aleasă de organizație și bugete separate. Restricția IP nu se aplică implicit aplicațiilor mobile sau granturilor personale.
- Fiecare integrare are cheia proprie, cu nume, scopes minime, ultima folosire și revocare independentă. Aceste metadate și revocarea există deja; îmbunătățirea este aplicarea noilor politici în același punct de autorizare pentru toate rutele.
- Pentru destinații noi, HTTPS implicit; excepțiile HTTP existente se păstrează printr-o politică explicită de migrare, fără înlocuire globală a adreselor istorice.
- La schimbarea domeniului destinației implicite: afișarea clară a gazdei, confirmare recentă, audit și notificare. Resursele externe legitime rămân posibile; verificarea de proprietate se cere pentru domeniile pe care producătorul le revendică drept proprii, nu pentru orice link către un terț.
- Dacă se adaugă preluarea automată de date externe sau verificarea legăturilor, se folosește un client dedicat cu limite de răspuns, timeout, verificarea adreselor DNS și a fiecărui redirect. Redirecționarea actuală a browserului nu este ea însăși un fetch pe server.

Teste: aceeași politică pe v1/v2, proxy nefiabil, IP mobil schimbat, cheie revocată, destinație HTTP nouă/istorică, schimbare de host și păstrarea adresei QR/istoricului lotului.

## 9. Email și controlul datelor personale

- Verificarea SPF/DKIM în cPanel și introducerea DMARC în mod observare, apoi aplicarea unei politici mai stricte după verificarea tuturor expeditorilor legitimi. Nu se modifică DNS pe baza presupunerii că aceste înregistrări lipsesc.
- Proces de retragere și ștergere a contului Acasă, a accesurilor și a fotografiilor asociate, cu confirmare recentă și reguli pentru familii comune. Transferul sau închiderea spațiului de familie se tratează explicit, pentru a nu șterge datele altor membri.
- Separarea retenției datelor personale de istoricul public al produselor, loturilor, alertelor și documentelor. Copiile exportate pe dispozitive terțe nu pot fi retrase retroactiv.
- Pagina contului explică efectul revocării, deconectării și ștergerii. Conectarea prin magic link și inventarul local rămân simple; controalele suplimentare se aplică modificării accesului și eliminării datelor.

Acceptare: verificare DNS/livrare separată de simpla autentificare SMTP; token de confirmare single-use; ștergere izolată pe cont și păstrarea drepturilor celorlalți membri; reguli documentate pentru backupuri și retenție.

## 10. Verificare continuă, inclusiv AI

- Extinderea testelor negative pe familii de operații: două organizații și două familii distincte, roluri diferite, scopes diferite și resurse private/publice. Acestea completează testele deja existente, nu le înlocuiesc prin simple verificări ale codului HTTP.
- Pentru fiecare regulă nouă de acces, probe pe HTML, API v1/v2 și comenzile de domeniu. Teste pentru confirmări reluate, refresh reutilizat după cleanup, concurență și rollback.
- Pentru AI, date sintetice private cu markeri unici: proba verifică faptul că markerii nu intră în contextul transmis furnizorului, nu doar că lipsesc din răspuns. Feedbackul ostil este tratat drept conținut, iar AI nu primește autoritatea de a publica, exporta sau modifica aprobările. [OWASP Prompt Injection Prevention](https://cheatsheetseries.owasp.org/cheatsheets/LLM_Prompt_Injection_Prevention_Cheat_Sheet.html).
- Înainte de primul push public: scanarea secretelor în arbori și istoric, verificarea artefactelor de release și o analiză statică PHP/JS cu reguli relevante. Inventarul OSV și scanarea țintită existente rămân în CI.
- După publicarea pe GitHub: verificări obligatorii pentru schimbări, permisiuni minime ale workflowurilor și proces privat de raportare. Configurarea CI în fișiere nu se confundă cu un job executat cu succes în repository-ul real.
- Cerințele aplicabile se mapează la [OWASP ASVS](https://asvs.owasp.org/), cu dovezi și limitări. Folosirea standardului ca listă de verificare nu se prezintă drept certificare.

## Închiderea implementării

Fiecare soluție se închide cu cod/configurație aplicată, teste relevante, dovadă de operare și compatibilitate verificată. Elementele dependente de furnizor — scaner, backup, cron, protecție de trafic și privilegii DB — sunt marcate separat până la verificarea reală. Aplicația rămâne PHP MVC cu dependențe minime; migrațiile sunt aditive, iar pachetele nu includ secrete.

În această verificare au fost citite sursele și documentația oficială; nu s-au schimbat credentiale, sesiuni, DNS, privilegii sau servicii din producție și nu au fost trimise notificări.
