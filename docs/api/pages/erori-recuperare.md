# Erori, reîncercări și depanare

## Citește codul HTTP și corpul

API-ul nu întoarce succes HTTP 200 pentru orice rezultat. O eroare obișnuită are structura:

```json
{
  "error_code": "idempotency_conflict",
  "message": "Cheia a fost utilizată pentru altă cerere.",
  "request_id": "00000000000000000000000000000000"
}
```

Exemplul este sintetic. Folosește `error_code` și status pentru decizii programatice; `message` este pentru afișare și poate evolua. Păstrează request_id pentru suport, fără Authorization, parole, cookie-uri sau corpuri private în loguri.

OAuth token/revoke folosesc `error`, `error_description`, `request_id`. Resolverul GS1 are propriul răspuns `error_code`, `message`, fără request_id garantat. La negocierea unei relații absente, resolverul poate răspunde 404 cu un linkset de alternative. Acasă sync poate răspunde 409 cu rezultatul de sincronizare și conflicts, nu doar cu un mesaj de eroare.

## Ce faci pentru fiecare clasă

| HTTP | Interpretare | Acțiune |
| --- | --- | --- |
| 400 | Corp/adresă neinterpretabilă, parametri OAuth incorecți | Corectează formatul; nu relua identic în buclă |
| 401 | Credential expirat, revocat sau absent | Reautorizează; la OAuth încearcă un singur refresh coordonat dacă grantul permite |
| 403 | Scope, rol, apartenență, origine sau tip de sesiune greșit | Verifică grantul și rolul; un Bearer nu înlocuiește sesiunea ownerului |
| 404 | Inexistent, nepublicat sau inaccesibil | Verifică ID-ul și părintele; nu deduce existența într-o altă organizație |
| 409 | Stare/versiune concurentă sau cheie reutilizată cu alte date | Citește starea curentă și reconciliază; nu genera automat o operație nouă |
| 410 | Magic link/invitație expirată sau consumată | Cere o legătură nouă |
| 413 | Corp, fișier sau cotă depășită | Reduce dimensiunea sau folosește export NDJSON; cota se discută cu operatorul |
| 419 | CSRF nu corespunde cookie-ului | Citește din nou sesiunea și folosește csrf nou |
| 422 | Câmp sau condiție de domeniu invalidă | Corectează datele; verifică și relațiile între articol, etichetă, lot și rețetă |
| 429 | Buget de cereri depășit | Respectă Retry-After când este emis; altfel backoff cu jitter |
| 503 | Funcție neactivată sau serviciu indisponibil | Verifică manifestul; reîncearcă limitat dacă indisponibilitatea este temporară |

Un proxy/cPanel poate întoarce HTML la o eroare de transport sau dimensiune. Verifică Content-Type înainte de parsarea JSON. La 307 de la resolver nu transmite Authorization spre destinația redirecționată.

## Timeout la o scriere

Un timeout nu dovedește că serverul nu a executat operația. Persistă înainte de trimitere cheia, metoda, calea exactă și corpul. După timeout retrimite-le identic, cu aceeași organizație și același principal. Cheia este izolată pe organizație și credential/utilizator; schimbarea cheii API poate schimba spațiul de deduplicare.

Nu genera un UUID nou la fiecare retry. Nu modifica ordinea parametrilor din adresă sau payload-ul în timp ce pretinzi aceeași operație. Pentru o schimbare de intenție folosește o operație nouă după reconcilierea celei vechi. Rezultatele generale de idempotentă sunt păstrate 90 de zile, după care pot fi eliminate. Înainte de reluarea unei operații mai vechi, reconciliază resursele existente. Evenimentele conectorilor folosesc separat `event_key`.

Feedbackul folosește `submission_key`. Evenimentele partenerilor au și `event_key`, în plus față de Idempotency-Key. Acasă folosește `event_id` și expected_revision. Voturile PUT/DELETE sunt idempotente prin identitatea contului și propunerii.

La emiterea cheilor, retry-ul poate întoarce același ID cu `secret_available=false`; secretul nu poate fi recuperat. Revocă acea cheie și emite alta dacă ai pierdut prima livrare.

## Backoff și limite

Pentru o eroare temporară, limitează numărul încercărilor și timpul total. O politică de client poate folosi 1, 2, 4, 8 secunde cu jitter, respectând o valoare Retry-After mai mare. Aceste intervale sunt o recomandare de client, nu o garanție a serverului. La expirarea bugetului păstrează jobul pentru reconciliere și informează operatorul integrării.

RateLimit-Limit, RateLimit-Remaining și RateLimit-Reset descriu bugetul evaluat de limiterul care a emis antetele. O operație poate consuma mai multe bugete. Nu interpreta antetele ca o rezervare de capacitate și nu porni zeci de cereri concurente numai pentru că Remaining este mare.

## Cazuri frecvente

| Simptom | Verificare concretă |
| --- | --- |
| Cheia citește, dar nu publică | publish și owner/quality; write creează numai ciorne |
| Lotul nu poate fi creat | Eticheta trebuie să fie published și să aparțină aceluiași articol |
| QR returnează URL nativ | Verifică gtin_status și transportul; sintaxa GTIN validă nu confirmă mandatul |
| O alertă închisă lasă lotul on_hold | Este comportament intenționat; release este o decizie separată |
| 200 de la food, dar produsul este Aliment | source.kind=unavailable; codul poate fi valid fără date de la un producător |
| API merge din server, dar nu din browser extern | Originea aplicației trebuie înregistrată; cheile ERP nu se încorporează în frontend |
| Login întoarce 419 | Cookie și csrf trebuie să provină din aceeași sesiune; login rotește sesiunea |
| Aplicația Acasă nu vede altă familie | OAuth fixează familia aprobată; trebuie o nouă autorizare |
| AI întoarce ai_disabled | Nu bloca QR-ul sau afișarea produsului; AI nu este în fluxul de citire |
