# Contribuitori și parteneri OpenAliment

Programul are două registre publice: persoane care contribuie la proiect și companii autorizate pentru anumite specializări. Un profil de contribuitor nu acordă acces administrativ. Calificarea unei persoane nu autorizează automat firma, iar autorizația OpenAliment nu reprezintă acreditare GS1.

## Acces și medii

Selectează mediul explicit. Pentru verificările de integrare folosește `https://dev.openaliment.ro`; datele de probă nu se trimit în producție. Operațiile personale acceptă sesiunea proprie sau OAuth în contextul `program`, cu permisiuni dedicate. Cheile ERP cu `read` sau `write` nu acordă acces la acest program.

În sesiunea web, fiecare cerere de modificare cere `X-CSRF-Token` și aceeași origine. Modificările scurte cer `Idempotency-Key`. Păstrează aceeași cheie și același conținut la retry. Interogările ANAF, trimiterea emailurilor și procesarea imaginilor au reguli specifice, explicate în referința fiecărei operații.

## Profilul contribuitorului

1. Creează o ciornă prin `POST /api/v2/program/me/contributors`, cu nume sau pseudonim, contribuție, categorii și legături HTTPS opționale.
2. Salvează modificările prin `PUT /api/v2/program/me/contributors/{id}`, folosind `expected_revision` citită anterior.
3. Trimite revizia prin `/submit`, cu `consent=true`. Administratorul verifică contribuția și acordul pentru aceeași revizie.
4. Registrul public întoarce numai versiunea aprobată. Ciorna nouă nu înlocuiește prezentarea publicată.
5. `/withdraw` retrage imediat publicarea. Nu există înscriere automată prin numărul de commituri sau preluare automată a numelui din GitHub.

Un curator poate pregăti un profil fără cont asociat. `purpose=contributor_consent` trimite persoanei o legătură pentru examinarea și acceptarea exactei revizii. Deschiderea legăturii nu confirmă acordul; confirmarea se face prin POST. Emailul folosit pentru această dovadă rămâne privat.

## Identitatea companiei prin ANAF

`POST /api/v2/program/me/partners/company-checks` primește CUI ca șir, cu prefixul RO opțional. Răspunsul include toate grupurile furnizate de ANAF v9, avertizările și proveniența. Verificarea este legată de cont și expiră după 20 de minute.

```json
{"cui":"RO900000001"}
```

CUI-ul de mai sus este sintetic și ilustrează formatul. Folosește în mediul online o companie reală pe care o reprezinți. Dacă utilizatorul corectează CUI-ul, lansează o verificare nouă și elimină din formular ID-ul verificării anterioare. Rezultatul vechi devine inutilizabil, inclusiv dacă răspunde mai târziu o cerere concurentă.

Confirmarea firmei se face prin `POST /api/v2/program/me/partners/applications`, cu `company_check_id`, `cui` și `company_confirmed=true`. Datele fiscale și răspunsul sursei sunt păstrate în versiuni. Nu trimite un nume de firmă editabil pentru a suprascrie denumirea ANAF.

ANAF confirmă informația fiscală pentru data consultată. Nu confirmă mandatul persoanei și nu acordă drepturi GS1. O companie inactivă poate avea o cerere privată, dar nu poate obține publicarea ca partener activ.

## Contactele, descrierea și logo-ul

Compania propune descrierea, serviciile, regiunile, limbile, website-ul și logo-ul. Prezentarea se aprobă separat. `expected_revision` protejează fiecare modificare de suprascriere concurentă.

ANAF v9 nu furnizează email. Adresa se introduce prin `/applications/{id}/contacts`, cu `kind=email` și `public_consent=true`, apoi se verifică prin `POST /api/v2/program/me/verifications`:

```json
{"purpose":"partner_contact","object_id":"11111111111111111111111111111111"}
```

`object_id` este ID-ul contactului din răspunsul real, nu ID-ul cererii sau CUI-ul. Legătura expiră în 30 de minute. Telefonul opțional necesită verificarea înregistrată de un evaluator independent; simpla introducere nu îl face verificat. Un contact schimbat invalidează dovada veche și nu este înlocuit în tăcere în prezentarea aprobată.

Imaginile folosesc `POST /api/v2/program/assets`: PNG/JPEG/WebP de maximum 2 MiB și 4 megapixeli, cu drepturi confirmate. Serverul decodează, reencodează în PNG și elimină metadatele. SVG și HTML încărcate de utilizator sunt refuzate. Dovezile practice rămân private; un logo este public doar dacă este referit de un profil aprobat și eligibil. Retragerea imaginii oprește accesul la ea.

## Publicarea ca partener

Cererea se trimite prin `/applications/{id}/submit`. Un evaluator independent verifică reprezentarea și prezentarea. Cursul și verificările firmei pot continua în paralel.

Publicarea automată cere simultan: profil aprobat și activ, situație fiscală activă și suficient de recentă, email al companiei verificat și aprobat pentru publicare, persoană afiliată cu o calificare valabilă și autorizație activă pentru specializarea afișată.

Prospețimea observației fiscale este de maximum 30 de zile. Workerul începe reverificarea după 27 de zile și aplică retry cu backoff la indisponibilitate.

Aceste condiții se verifică și la citirea publică. Retragerea contactului, expirarea calificării sau încheierea afilierii nu așteaptă cronul pentru a opri afișarea unei autorizații care nu mai îndeplinește condițiile. JSON-ul de verificare arată emitentul și starea curentă, inclusiv pentru o autorizație suspendată sau revocată.

## Schimbarea companiei și invitații

Nu se înlocuiește CUI-ul unui profil existent. Creează o verificare și o cerere pentru noua companie, indicând `previous_profile_id` atunci când este cazul. După verificarea noului mandat, afilierea veche poate fi încheiată explicit. Progresul personal rămâne în cont; produsele, GTIN-urile, documentele și mandatele vechii firme nu sunt transferate.

Un CUI deja prezent nu acordă acces noului solicitant. Reviewerul verifică mandatul și confirmă explicit accept_existing_company=true înainte de a asocia cererea cu profilul existent. Tenantul și datele producătorului rămân la organizația inițială. După încheierea mandatului, fostul reprezentant își poate citi istoricul propriu, dar nu mai poate modifica profilul, contactele sau retrage firma.

O invitație cu `purpose=partner_invite` asociază un coleg cu programul firmei, după acceptarea din contul adresei invitate. Nu acordă membership în spațiul producătorului și nu transmite rezultatele private ale examenelor colegilor.

Catalogul partenerilor acceptă q pentru prefixul numelui, specialization pentru specializarea activă și region pentru regiunea declarată exact. Lista contribuitorilor acceptă q și category. Paginarea păstrează filtrele.

## Portabilitate, contestații și erori

Exportul cererii include datele proprii și istoricul deciziilor, fără rezultate ale altor persoane sau secrete. Contestația se înregistrează separat, cu motiv. Ea nu reactivează automat profilul.

`404` pentru registrul public înseamnă că profilul nu este disponibil public; nu se divulgă motivul privat al refuzului. `409` cere recitirea reviziei ori repetarea verificării CUI. `429` cere respectarea `Retry-After`; `503` indică indisponibilitatea temporară a unei componente, fără aprobare implicită.

Surse: [ANAF v9](https://static.anaf.ro/static/10/Anaf/Informatii_R/Servicii_web/doc_WS_V9.txt), [GS1 URI Syntax 1.7.0](https://ref.gs1.org/standards/digital-link/uri-syntax/1.7.0/), [GS1 Resolver 1.2.1](https://ref.gs1.org/standards/resolver/1.2.1/).
