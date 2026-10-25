## Conectare și alegerea familiei

Identitatea Acasă este separată de contul producătorului. Inventarul local, scanarea și paginile QR funcționează fără conectare. Sincronizarea se activează la alegerea persoanei; disponibilitatea ei este indicată de [sesiunea Acasă](/api/docs/reference/homeGetApiV2HomeSession) și de manifest.

În browser, legătura de conectare pe email este valabilă 15 minute și se folosește o singură dată. Deschiderea ei cere confirmare înainte de conectare. Cookie-ul personal este HttpOnly, Secure, SameSite=Lax, valabil 90 de zile și prelungit la utilizare. El nu acordă acces la administrarea producătorilor.

O invitație de familie este valabilă 7 zile și se folosește o singură dată. Persoana se conectează cu propriul email și confirmă alăturarea; adăugarea alimentelor sale locale este o alegere separată. Proprietarul poate redenumi familia și retrage accesul unui membru. Dispozitivele se pot deconecta individual. Retragerea accesului oprește cererile viitoare, dar nu șterge copii deja descărcate sau exportate.

Pentru o aplicație terță folosește [OAuth cu PKCE](/api/docs/guides/oauth). Familia aprobată este fixată în grant. Schimbarea familiei în interfața OpenAliment nu extinde automat grantul aplicației.

## Revizii, coadă și conflicte

Fiecare ambalaj are `item_id` propriu și o revizie de server. Sincronizarea primește maximum 50 de operații și 50 de evenimente de istoric într-o cerere atomică. Trimite `event_id` stabil și `expected_revision`; după timeout retrimite același eveniment, cu același conținut.

La modificări concurente serverul răspunde 409 cu `conflicts[]` și variantele curente. Afișează variantele persoanei și păstrează alegerea explicită. Ceasul dispozitivului nu decide automat ce variantă se păstrează.

Cursorul de modificări este numeric și separat pentru fiecare familie. `GET /api/v2/home/changes?after=0&limit=50` returnează modificările autorizate; pagina conține cel mult 100. Persistă coada, cursorul și familia împreună. Exportul personal nu este un înlocuitor al cozii de sincronizare.

## Fotografii și limite

| Resursă | Limită |
| --- | --- |
| Fotografie privată | JPEG, maximum 1 MB și 6000 × 6000 pixeli |
| Fotografii într-o familie | 5.000 de fișiere, maximum 32 MB în total |
| Inventar | 2.000 de ambalaje |
| Istoric | 50.000 de evenimente |

Fotografiile se trimit numai când persoana alege sincronizarea. Preferințele personale pentru alergeni și lista de cumpărături rămân locale; `shopping_sync=false`. Exportul personal exclude cookie-uri, tokenuri, CSRF și invitații.

## Date curente și funcționare fără conexiune

Informațiile de siguranță salvate pe dispozitiv nu înlocuiesc verificarea curentă. Reîncarcă alertele publice și afișează momentul ultimei verificări. Fără conexiune, păstrează explicit starea neverificată. Rețetele private nu fac parte din inventarul personal.

Inventarul local rămâne utilizabil dacă sincronizarea sau emailul sunt indisponibile. Serviciul Acasă este disponibil în browser; nu distribuie aplicații native prin App Store sau Google Play. Notificările de fundal, transferul proprietății familiei și ștergerea integrală automată a contului nu sunt operații disponibile prin API. Cererile privind datele personale se adresează operatorului prin [pagina de confidențialitate](/confidentialitate).

Contracte: [OpenAPI Acasă](/api/home-openapi.json), [schema sincronizării](/api/schemas/home-sync.schema.json), [modele de date](/api/docs/guides/modele-date).
