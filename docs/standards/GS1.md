# Referința GS1 a proiectului

Verificat la 25 octombrie 2026. Catalogul fixat este în [contracts/gs1/standards-catalog.json](../../contracts/gs1/standards-catalog.json). El păstrează versiunile, sursele HTTPS, dimensiunile și SHA-256 ale PDF-urilor oficiale, fără modificarea documentelor.

| Document | Ediție | Utilizare |
| --- | --- | --- |
| GS1 Digital Link URI Syntax | 1.7.0 | Adresa HTTPS, GTIN, lot, serie și atributele aplicabile. |
| GS1-Conformant Resolver | 1.2.1 | Moștenire, selecție, negociere, linkset și transport. |
| GS1 Digital Link URI: Compression | 1.0.0 | Șiruri binare EPC exprimate în URI. |
| GS1 General Specifications | 26.0.0 | Definițiile AI, simboluri, dimensiuni și cerințe de imprimare. |

Copiile originale sunt păstrate local în `storage/private/standards/gs1/`, împreună cu `provenance.json`; cache-ul descărcării este în `.tools/standards/gs1/`. Ambele directoare sunt ignorate de Git. Nu se introduc în codul MIT sau în release-urile publice originale asupra cărora nu a fost stabilit un drept de redistribuire. Metadatele și explicațiile proprii sunt publice; pagina `/standarde/gs1` trimite la PDF-urile oficiale GS1. În caz de diferențe, PDF-ul oficial prevalează asupra prezentărilor HTML.

## Copii private reproductibile

```text
python tools/standards/fetch.py
```

Comanda păstrează copiile în `storage/private/standards/gs1`, verifică TLS, sursa exactă GS1, dimensiunea și SHA-256, refuzând redirecturi sau schimbarea neanunțată a ediției. Fișierele nu sunt accesibile prin document root. `--only uri-syntax` limitează descărcarea la documentul indicat. Actualizarea ediției cere verificare manuală și altă intrare/proveniență, fără înlocuirea retroactivă a fișierului vechi.

Pe cPanel se folosește `/admin/standards`: fiecare ediție se descarcă separat printr-o acțiune autentificată, cu CSRF, într-o destinație privată fixă. Administratorul vede dacă hashul copiei locale corespunde. O descărcare nereușită sau modificată nu înlocuiește copia existentă.

Pentru testele offline ale endpointului de păstrare, pregătește PDF-ul oficial înaintea suitei:

```text
python tools/standards/fetch.py --only uri-syntax --directory tests/runtime/reference-gs1
```

Setează `OA_GS1_REFERENCE_DIR=tests/runtime/reference-gs1`. Suita folosește numai copia cu hashul corect și nu descarcă documente din rețea. CI are etapa explicită de pregătire a acestei resurse publice, înaintea testelor. Alternativ, mediul de dezvoltare poate folosi copiile originale din `.tools/standards/gs1`.

## Integrarea în implementare

- [Profilul resolverului și probele](../GS1_IMPLEMENTATION.md).
- [Marketplace și tipărire în linie](../api/pages/marketplace.md).
- API public: `/api/v2/public/standards/gs1`; API administrativ: `/api/v2/admin/standards/gs1` și `POST /api/v2/admin/standards/gs1/copies`.
- URI-ul din QR folosește domeniul HTTPS complet, AI 01 pentru GTIN, 10 pentru lot, 21 pentru serie, 11 pentru producție, 15 pentru best before sau 17 pentru expiry, conform datelor aprobate.
- Testele disting corectitudinea sintaxei, decodarea QR software, funcțiile resolverului și proba fizică de imprimare/POS. Una nu înlocuiește celelalte.

© GS1 AISBL. [Notificarea GS1](https://ref.gs1.org/gs1/standards-disclaimer/) și condițiile sale rămân aplicabile. Copierea privată pentru referință nu reprezintă revendicarea drepturilor GS1 sau o certificare a platformei.
