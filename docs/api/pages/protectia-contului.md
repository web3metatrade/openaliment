# Protecția contului și confirmări sensibile

Contul de producător și contul personal Acasă sunt separate. Cheile ERP și autorizările OAuth nu pot administra cheile de acces ale persoanei, recupera parola sau confirma ștergerea contului personal.

## Parolă și cheie de acces

După înrolarea unei chei WebAuthn, parola singură nu permite intrarea în spațiul producătorului. Browserul confirmă cheia prin `navigator.credentials.get`. Serverul verifică originea exactă, domeniul RP, provocarea de unică folosință, semnătura și verificarea persoanei pe autentificator. Nu trimite cheia privată serverului.

Înrolarea se face din Securitatea contului și cere parola actuală. Opțiunile `create/options` și `get/options` conțin octeți codificați base64url: convertește `challenge`, `user.id` și identificatorii credentialelor în `Uint8Array` înainte de apelul browserului. Răspunsul browserului se convertește înapoi în base64url fără padding pentru `confirm`. Nu reutiliza un challenge după o încercare nereușită.

Operațiile sensibile cer confirmare recentă, în ultimele 15 minute. Eroarea `mfa_required` conduce persoana la `/account/verify`; `reauthentication` cere o nouă confirmare. Cookie-ul și CSRF se pot schimba după confirmare. Recitește sesiunea înaintea următoarei scrieri.

Păstrează codurile de recuperare offline. Sunt afișate o singură dată, fiecare funcționează o singură dată, iar regenerarea le invalidează pe cele vechi. Pentru a elimina ultima cheie, înregistrează întâi o altă cheie verificată.

## Recuperarea parolei

`POST /api/v2/account/password/request` primește `email` și răspunde identic pentru un cont cunoscut și unul necunoscut. Recuperarea este disponibilă prin browserul aceleiași origini, fără cheie API. Mesajul este procesat asincron; acceptarea cererii nu confirmă livrarea în inbox.

Emailul conține un token în fragmentul URL. Elimină fragmentul din adresă înaintea altor cereri. `preview` verifică legătura fără să o consume; `reset` primește `token`, `password` și `confirmation`, consumă legătura și revocă sesiunile, cheile API și granturile producătorului. Tokenul expiră în 15 minute. Resetarea parolei păstrează cheile WebAuthn și cerința MFA; nu conectează automat persoana.

## Dispozitivele proprii

`GET /api/v2/account/security` listează metadatele sesiunilor și cheilor proprii, fără cookie-uri sau secrete. Revocarea unui ID de sesiune din alt cont primește 404. Revocarea sesiunii curente cere reconectare la cererea următoare.

## Contul personal Acasă

Ștergerea contului, transferul administrării familiei și părăsirea familiei folosesc `account/action/request`, apoi legătura primită prin email. Aceste operații cer cookie-ul personal și `X-Home-CSRF`; granturile OAuth nu le pot executa.

Confirmarea este legată de cont și dispozitivul care a solicitat-o. `preview` nu modifică date; `confirm` aplică o singură dată alegerea. Transferul poate desemna numai un editor existent al familiei. Înainte de ștergerea contului, transferă familiile cu alți membri. Ștergerea retrage accesurile, elimină identitatea de contact și spațiile personale fără alți membri; contribuțiile din familiile altora rămân în istoricul lor, cu o referință pseudonimă. Copiile deja descărcate pe alte dispozitive pot rămâne acolo.

Ștergerea datelor locale din browser este o alegere separată. Salvează întâi o copie dacă vrei să păstrezi alimentele și fotografiile.

## Voturi și documente

Un vot retras păstrează `mutation_version`. Trimite `expected_mutation_version` în corpul PUT sau în query la DELETE; un retry cu versiune veche primește 409 și nu reactivează votul. Recitește starea proprie înaintea unei alte modificări.

Documentul încărcat începe privat. Verifică `GET /api/v2/manage/documents/{id}/scan`; publicarea unui document nou cere `clean`, drepturi și aprobarea copiei. Un scaner neconfigurat, indisponibil sau o modificare a octeților nu echivalează cu o verificare reușită. Același conținut poate avea mai multe proveniențe, păstrând separarea între organizații.
