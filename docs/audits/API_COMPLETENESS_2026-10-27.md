# Audit de acoperire API — 27 octombrie 2026

## Rezultat și domeniu

Versiunea locală verificată include **294 de operații API**, fiecare cu răspuns de succes executat prin HTTP și validat după contract. Modulul Open Food Facts are **36 de operații**: 8 publice, 18 pentru organizația producătorului și 10 administrative. Sunt mapate explicit toate cele **15 acțiuni din formularele OFF**, citirile paginilor și operațiile suplimentare pentru aplicații.

Aceasta este o confirmare a acoperirii funcționalităților implementate și a scenariilor executate. Nu înseamnă că orice combinație posibilă de date, ERP, echipament POS sau serviciu extern a fost testată. Trimiterea automată către OFF este o capabilitate oprită, declarată ca atare, nu un transport prezentat drept finalizat.

Probe reproductibile: [rezultatele sanitizate](../verification/api-completeness-2026-10-27.json), [matricea acțiunilor](../api/external-action-coverage.json) și [referința completă OFF](../api/pages/surse-externe.md).

## Diferențele dintre medii la verificare

| Mediu | Operații API | Operații OFF | Stare |
| --- | ---: | ---: | --- |
| Producție | 258 | 0 | Integrarea OFF nu este instalată/activă |
| Dev online | 293 | 35 | Documentația corespunde versiunii instalate |
| Cod local auditat | 294 | 36 | Include corecția de acoperire; încă nepublicată |

Au fost citite exclusiv contractele, manifestele și documentația publică online. Toate cele 35 de pagini Markdown OFF instalate pe dev au fost comparate cu conținutul canonic al commitului existent: **35/35 coincid**. Auditul nu a publicat fișiere, nu a schimbat cronul și nu a rulat mutații în producție.

## Lipsa găsită și corecția

Pagina `/manage/sources` putea lista toate articolele urmărite de organizație prin `ExternalSources::list($p)`. API-ul avea doar lista pentru un articol, obligând o integrare să enumere mai întâi toate articolele și să facă cereri separate.

S-a adăugat:

```text
GET /api/v2/manage/sources/openfoodfacts/bindings
operationId: externalOrganizationBindings
scope: read
```

Endpointul reutilizează funcția de domeniu existentă, verifică organizația autentificată și oferă `limit`/`cursor`, inclusiv asocierile deconectate. Nu admite un `tenant_id` ales de client. Rutele existente se păstrează; nu necesită migrare SQL, schimbare de permisiuni sau reconectarea utilizatorilor.

Au fost regenerate OpenAPI, catalogul operațiilor, Postman, referința, ghidurile și indexurile folosite de documentația pentru AI. Ghidul OFF listează fiecare operație și scope-ul său. Descrierile stărilor și codurilor joburilor externe au fost desprinse de descrierile despre loturi/OAuth. Numărătorile istorice din README-ul portalului au fost corectate; rapoartele istorice nu au fost rescrise.

## Acoperirea funcțională OFF

| Suprafață | Acțiuni acoperite |
| --- | --- |
| Acasă și aplicații publice | Rezolvare locală a scanării, căutare GTIN, urmărirea căutării, surse/licențe, revizii paginate, observații, imagini validate și export public |
| Surse ale organizației | Lista generală și pe articol, asociere/deconectare, comparație, creare ciornă, lista diferențelor și decizii |
| Contribuția producătorului | Conexiune/deconectare, acorduri versionate, revocare, previzualizare, pregătirea pachetului, istoric și descărcare autorizată |
| Administrare | Stare/cote, activare/pauză/oprire, verificarea formatului, suspendarea observațiilor, reîncercări, verificare/import subset, export și procesare limitată |
| Accesul partenerilor | Chei cu scopes, idempotentă, OAuth cu PKCE și consimțământ, revocarea cheii și a grantului OAuth |

Instalarea migrărilor rămâne în fluxul administrativ `/admin/updates`; configurarea cronului și a cotelor private aparține operatorului. Acestea nu sunt drepturi delegate cheilor ERP. Camera, OCR, inventarul fără cont și preferințele personale pot rămâne locale; nu se inventează un endpoint care să oblige încărcarea lor în cloud. Aceste delimitări sunt documentate în ghid și în matrice.

## Verificări executate

Suita completă a trecut în mediul izolat: **65 de suite, 14.659 de verificări, zero suite eșuate**, inclusiv browserul Edge instalat. Testele folosesc exclusiv baza `openaliment_tests`, server loopback, transporturi simulate și fixtures sintetice.

- **294/294 operații** au un succes HTTP validat după schemă, fără operații lipsă.
- Noua verificare statică execută **191 de controale** asupra formularelor, registrului runtime, autorizării, paginării, OpenAPI, referinței Markdown și Postman.
- Proba HTTP OFF are **23 de verificări funcționale** și **79 de răspunsuri validate**. Include chei Bearer fără cookie, scope insuficient, acces anonim refuzat, paginare, izolarea organizațiilor, idempotentă, revocarea cheii și pachet blocat după revocarea acordului.
- Fluxul OAuth OFF înregistrează un client cu scopes explicite, obține consimțământ, verifică `state` și issuer, schimbă codul prin PKCE, accesează resursele, refuză depășirea scope-ului și blochează accesul după revocarea grantului.
- Portalul este verificat prin HTML, Markdown, index JSON, corpus AI și Postman. Exemplele și răspunsurile sunt validate; testele verifică și compatibilitatea v1, SDK-urile, GS1 și scanarea independentă a SVG-ului QR.
- Probele din browser acoperă fluxurile Acasă, cont, marketplace, prima vizită și OFF; suita include și backup/restaurare.

În testele locale nu s-au făcut scrieri remote către OFF, trimiteri SMTP reale sau modificări de producție. Primul start al suitei a întâlnit restricția Windows `is_readable` din sandbox; un start ulterior a constatat absența Chromium-ului Playwright. Verificarea finală a folosit permisiunile locale necesare și Edge instalat, fără modificarea testelor pentru a ocoli verificările de acces.

## Prevenirea regresiilor și publicare

`tests/test_external_api_coverage.py` rulează în suita rapidă și în CI. O nouă rută externă sau acțiune de formular necesită actualizarea matricei și a documentației. `tests/test_api_coverage.py` cere în continuare un succes HTTP pentru fiecare operație din contractul agregat. Generatorul `tools/build/artifacts.py --check` detectează contractele/documentația rămase în urmă.

Corecția se integrează prin fluxul normal de PR în `dev`, se verifică în CI și se instalează din pachetul commitului respectiv. Înainte de promovare se verifică online noua operație și numărul 294 în contract și index. Publicarea în producție rămâne un pas separat; auditul local nu trebuie confundat cu instalarea sa online.
