# Înregistrare cu CUI și profil fiscal ANAF

Datele firmei sunt consultate prin serviciul public ANAF v9 și confirmate înainte de salvare. Consultarea ANAF nu acordă reprezentarea organizației, verificarea emailului, drepturi asupra GTIN sau o siglă. Datele nu sunt publicate automat pe etichete sau pe paginile QR.

## 1. Deschide sesiunea de înscriere

`GET /api/v2/identity/session` întoarce `csrf` și `registration_enabled`; păstrează cookie-ul `oa_session`. Înscrierile trebuie să fie active. Pentru această etapă nu există încă o cheie API: se folosește sesiunea anonimă proprie, inclusiv într-un cookie jar al aplicației native. Originile browser externe nu primesc acces implicit prin CORS.

```bash
curl --fail-with-body --cookie-jar cookies.txt \
  https://openaliment.ro/api/v2/identity/session
```

În exemplele următoare, `CSRF_DIN_SESIUNE`, `CUI_AL_FIRMEI` și identificatorii de verificare sunt valori de înlocuit. CUI-ul se trimite ca șir, cu prefix RO opțional. Nu trimite CNP, parole sau chei în URL. Nu crea conturi de probă pentru firme reale pe care nu le reprezinți; folosește mediul de test al integrării și fixture-urile locale pentru teste automate.

## 2. Consultă CUI-ul

```bash
curl --fail-with-body --cookie cookies.txt --cookie-jar cookies.txt \
  -X POST https://openaliment.ro/api/v2/identity/organization-lookups \
  -H 'Content-Type: application/json' -H 'X-CSRF-Token: CSRF_DIN_SESIUNE' \
  --data '{"cui":"CUI_AL_FIRMEI"}'
```

Răspunsul conține `id`, `cui`, `state`, `record`, `source`, `warnings`, `expires_at`, `expected_version_id` și `differences`. `record` păstrează toate câmpurile firmei primite de la ANAF, inclusiv câmpuri noi; poate fi null dacă `state=not_found`. Sursa include data de referință, momentul consultării, API v9, versiunea mapării și amprenta răspunsului. Un rezultat reutilizat din cache păstrează momentul real al consultării.

Datele generale, perioadele TVA, TVA la încasare, inactivitatea, plata defalcată TVA, sediul social și domiciliul fiscal rămân distincte. Un șir gol, null sau un câmp lipsă înseamnă „nefurnizat”. Un boolean false este o valoare reală, nu o lipsă. `statusRO_e_Factura=false` privește registrul indicat, nu afirmă lipsa obligațiilor e-Factura. Codurile poștale, CAEN și numărul de registru nu se completează sau corectează prin presupuneri.

## 3. Corectează CUI-ul sau confirmă firma

Pentru corectare, repetă POST-ul cu noul CUI și același cookie. Fiecare verificare nouă înlocuiește confirmările anterioare ale aceleiași sesiuni; folosește numai ultimul `id`. Un răspuns întârziat nu reactivează un rezultat înlocuit.

`GET /api/v2/identity/organization-lookups/{id}` recitește rezultatul fără cerere nouă la ANAF. Necesită aceeași sesiune. Un alt cookie primește 404; un rezultat înlocuit, consumat sau expirat primește 409.

La confirmare se trimit CUI-ul și referința serverului, împreună cu datele persoanei:

```json
{
  "cui": "CUI_AL_FIRMEI",
  "organization_lookup_id": "IDENTIFICATORUL_ULTIMEI_VERIFICARI",
  "display_name": "Numele persoanei",
  "email": "contact@example.invalid",
  "password": "PAROLA_ALEASA_DE_PERSOANA"
}
```

Corpul se trimite către `POST /api/v2/identity/registrations`, cu același cookie și `X-CSRF-Token`. Succesul este 201; răspunsul conține utilizatorul, organizația și noul CSRF. Reține noul cookie dacă sesiunea este regenerată. Datele fiscale se citesc exclusiv din observația verificată a serverului; `organization` nu poate înlocui denumirea firmei confirmate. Crearea utilizatorului, a organizației și a primei versiuni fiscale este atomică. Refolosirea confirmării nu creează o altă organizație și nu conectează într-un cont existent prin simpla cunoaștere a CUI-ului.

Contractul vechi, cu `organization` și fără verificare ANAF, rămâne acceptat pentru clienții existenți și organizațiile fără profil românesc. Acest traseu nu acordă profil fiscal sau identitate verificate. Interfața nouă de firmă românească cere verificarea CUI-ului. Organizațiile străine și inițiativele fără CUI românesc folosesc înregistrarea prin administrator.

## 4. Citește și actualizează profilul propriu

- `GET /api/v2/manage/organization/legal-profile`, scope `read`: profilul propriu sau null, cu proveniența.
- `GET /api/v2/manage/organization/legal-profile/history`, scope `read`: versiuni imuabile, cu `limit` 1–100 și cursor ID exclusiv. Ordinea ID-urilor nu este cronologică.
- `POST /api/v2/manage/organization/legal-profile/checks`, owner cu `write`: primește CUI, întoarce observația și diferențele, fără aplicare. Pentru profilul existent, CUI-ul trebuie să fie același. Nu necesită Idempotency-Key.
- `POST /api/v2/manage/organization/legal-profile/confirm`, owner cu `write`: primește `organization_lookup_id` și `expected_version_id` din verificare, null pentru primul profil. Necesită Idempotency-Key. Un conflict produce 409; citește profilul și reverifică înainte de altă confirmare.
- `GET /api/v2/admin/organizations/{id}/legal-profile`: numai sesiune de administrator, pentru consultarea probelor fiscale. Cheile ERP nu oferă acces administrativ.

Pentru confirmare cu cookie se cere `current_password` dacă autentificarea are peste 15 minute. Confirmarea prin Bearer folosește dreptul owner cu scope write verificat la fiecare cerere, fără trimiterea parolei în integrarea ERP. Un editor cu write nu poate confirma profilul fiscal. Actualizările creează versiuni și păstrează informațiile aplicabile etichetelor și loturilor vechi.

Un profil importat are `origin=imported` și `directly_verified=false`. Semnăturile de integritate ale pachetului păstrează proveniența, dar nu dovedesc că sursa a fost consultată direct de această instalare. Reverifică ANAF înainte de a-l prezenta ca observație directă. Importul nu transferă mandate, aprobări de identitate, chei sau apartenențe.

## 5. Limite și erori

Integrarea interoghează un singur CUI, respectând limita ANAF de o cerere pe secundă. Cache-ul pentru o firmă găsită este de 6 ore, numai pentru aceeași zi de referință, iar rezultatele negăsite se reutilizează 5 minute. Confirmarea expiră în 20 de minute și nu se folosește după schimbarea zilei în Europe/Bucharest.

Limitele inițiale sunt 6 verificări/15 minute/sesiune, 20/15 minute/IP și 300 de cereri externe/oră/instalare. Dev și producția coordonează intervalul de cel puțin 1,1 secunde într-un fișier privat comun al contului cPanel. Alte aplicații de pe același IP trebuie să își coordoneze propriul trafic. Cererile nu țin o tranzacție SQL sau sesiunea blocată pe durata apelului ANAF. Nu există import automat al tuturor firmelor și nici dependență ANAF pentru resolver.

| HTTP sau rezultat | Semnificație și remediu |
| --- | --- |
| 200, `state=not_found` | CUI negăsit; verifică cifrele. Nu crea profil cu valori presupuse. |
| 403 | Înscrieri oprite, sesiune/rol/scope insuficient sau origine interzisă. |
| 404 | Verificarea nu există în sesiunea proprie sau organizația administrativă nu există. |
| 409 | Verificare înlocuită, expirată, consumată, alt CUI ori versiune schimbată. Reconsultă și reconfirmă. |
| 419 | Cookie/CSRF necorespunzător. Recitește sesiunea și păstrează ambele valori împreună. |
| 422 | CUI sau corp incorect; corectează înainte de retry. |
| 429 | Așteaptă `Retry-After`; fără polling continuu. |
| 503 | Activare lipsă, ANAF indisponibil sau răspuns neconfirmabil. Păstrează datele introduse; nu interpreta ca firmă inexistentă. |

Nu trimite parole, cookie-uri sau răspunsuri private complete în tichete de suport. Folosește `X-Request-ID`. Accesul public la acest ghid și la OpenAPI/Markdown nu necesită crawling al paginilor utilizatorilor.
