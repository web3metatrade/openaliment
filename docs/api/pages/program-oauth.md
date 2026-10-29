# OAuth pentru comunitate și învățare

Aplicațiile native și web pot lucra cu progresul, rezultatele proprii, profilurile de contribuitor și cererile firmelor reprezentate. Accesul se acordă de persoană, prin OAuth Authorization Code cu PKCE S256.

## Permisiuni

În browser, începe cu `GET /api/v2/identity/session`, păstrează cookie-ul și trimite câmpul `csrf` primit în antetul `X-CSRF-Token` la scrieri. Acest lucru este obligatoriu și pentru înregistrarea fără cont și previzualizarea/confirmarea legăturilor. După crearea contului sau rotirea sesiunii, recitește sesiunea pentru un CSRF nou. Nu trimite cookie-ul ori CSRF-ul în URL sau către alte domenii.

Pentru un cont personal folosește `POST /api/v2/program/registrations`, cu display_name, email, password și policy_accepted=true. Contul nu creează o firmă fictivă. Verificarea emailului se solicită separat, cu purpose=personal_email. Linkul de confirmare afișează solicitarea și se consumă numai după acceptarea explicită. Examenele cer emailul confirmat.

| Permisiune | Acces |
| --- | --- |
| `learning:read` | Cursuri, înscrieri, progres, rezultate și calificări proprii |
| `learning:write` | Înscriere, progres, exerciții și ciorne ale probelor practice |
| `partners:read` | Cereri proprii, afilieri și exportul companiei reprezentate |
| `partners:write` | Verificarea companiei, prezentare, contacte, trimiterea ori retragerea cererii |
| `contributors:read` | Profilurile private proprii de contribuitor |
| `contributors:write` | Editare, acord, trimitere și retragere a propriilor profiluri |
| `assets:read` | Imaginile proprii din program, inclusiv dovezile private |
| `assets:write` | Încărcarea și retragerea imaginilor proprii din program |

Aceste permisiuni formează contextul OAuth `program`. Nu le combina în aceeași autorizare cu permisiuni de producător sau Acasă. Aplicația înregistrează permisiunile dorite, iar persoana aprobă exact accesul solicitat. Tokenurile nu primesc roluri administrative, chiar dacă persoana care autorizează este administrator.

## Înregistrarea aplicației

Contul personal poate crea un client prin `POST /api/v2/program/me/applications`, în sesiunea sa web, cu CSRF și Idempotency-Key:

```json
{"name":"Aplicația mea de învățare","redirect_uris":["https://aplicatie.example/callback"],"scopes":["learning:read","learning:write"]}
```

Adresele de revenire sunt validate și comparate exact. Clienții publici nu primesc un secret care să fie ascuns într-un APK. Folosește PKCE S256, `state` și verificarea emitentului, conform ghidului OAuth al platformei.

Autorizarea se face pentru ID-ul contului propriu. Nu este necesară o organizație de producător. Tokenurile de acces sunt scurte; refresh tokenurile sunt rotite. O aplicație poate avea autorizări separate pentru program, producător și Acasă, dacă utilizatorul le acordă separat.

## Operații care cer persoana în browser

Începutul examenului, salvarea răspunsurilor, predarea și contestația cer sesiunea personală. API-ul refuză Bearer pentru aceste operații. Verificarea emailului, invitațiile și alegerea vizibilității numelui se confirmă tot în sesiunea proprie. Un token ERP sau o aplicație cu `learning:write` nu susține examenul și nu aprobă o companie.

Rolurile de editor, evaluator și verificator sunt delegate separat de administrator. Niciuna dintre permisiunile de mai sus nu înlocuiește un rol și nici nu permite publicarea unei bănci de întrebări printr-un token delegat.

## Revocare și izolare

`GET /api/v2/program/me/grants` arată autorizările programului. `DELETE /api/v2/program/me/grants/{id}` le revocă imediat. Dezactivarea unui client propriu revocă autorizările lui. Revocarea standard OAuth rămâne disponibilă prin `/oauth/revoke`.

La fiecare cerere se verifică starea contului, clientului, autorizării, tokenului și permisiunii. Un ID al altui cursant primește 404. O afiliere la o companie nu acordă acces la răspunsurile colegilor și nu acordă drepturi asupra produselor companiei.

Nu trimite tokenuri în query string, loguri sau capturi. Pentru imagini private, folosește operația de conținut autentificată; URL-ul web al imaginii nu o face automat publică.

## Bugete pentru scrieri

Scrierile personale noi sunt ponderate cu dimensiunea UTF-8 a cererii și a răspunsului păstrat pentru retry, rotunjită în KiB. Plafoanele inițiale sunt 2 MiB/cont/zi și 20 MiB/zi pentru program. O cerere care depășește bugetul răspunde cu `429`, iar tranzacția se anulează. Retry-ul cu aceeași cheie și același conținut nu consumă buget încă o dată. Antetele de limitare ale acestor scrieri indică unități ponderate, iar `Retry-After` precizează când poți reîncerca.

Rolurile editoriale și administrative verificate sunt exceptate de acest buget de conținut; controalele de acces și limitele generale rămân active. Plafoanele pot fi ajustate de operator prin `security_limits.program_write_user_kib_day` și `security_limits.program_write_kib_day`, în configurația privată.

Pentru probe practice: maximum 20 de probe noi pe zi și 60 într-un an per cont, 20 de salvări și 20 de predări pe oră, document JSON de maximum 100 KB. Contorul global inițial permite 2.000 de probe noi pe zi. Aceste limite protejează stocarea și nu modifică termenul sau baremul unui examen.
