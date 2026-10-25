# Verificări și evidență

## Actualizare 02

69 verificări domeniu de bază, 54 domeniu resolver/MariaDB, 117 HTTP resolver și 58 HTTP/regresie: 298 trecute. Cele 47 fișiere PHP ale aplicației au trecut verificarea sintactică înainte de publicare. Rapoartele resolver și transport HTTPS sunt în `verification/`; matricea actuală este în `GS1_IMPLEMENTATION.md`. Hostingul a confirmat PHP 8.4.27, MariaDB 10.6.28 și extensiile necesare prin verificarea furnizată de proprietar. Migrarea 002 este aditivă. Probele detaliate de producție și deploy sunt în `updates/PRODUCTION_RESOLVER_2026-10-23.md`.

## Evidența istorică a etapei 1

Următorul raport descrie momentul livrării inițiale; limitele resolverului și lipsa verificării hostingului de atunci sunt înlocuite de actualizarea 02 de mai sus.

Data: 23.10.2026. Mediu executat: Windows local, PHP 8.4.27 NTS, MariaDB 11.4.10, PDO MySQL, browserul integrat Chromium. Baza `openaliment_tests` este izolată; nu au fost executate modificări pe DB sau hostingul de producție.

## Probe executate

| Suită | Rezultat |
| --- | --- |
| Integritate handover și originale | 47/47 controale structurale |
| Domeniu PHP și integrare MariaDB | 69/69 |
| HTTP, API, autentificare și integrare ERP | 53/53 |
| Decodare QR independentă | 7/7 |
| Sintaxă PHP | Toate fișierele aplicației verificate |
| Flux UI și layout | Parcurs manual automatizat în browser; desktop 1440×960 și mobil 390×844 |

Cele 129 de verificări automate ale aplicației sunt distincte de cele 47 ale pachetului istoric. Cele 49 de scenarii AT și 16 QR din arhivă rămân specificația istorică, fără schimbarea artificială a stării lor în „trecut”. Nu s-a pretins executarea întregului backlog OpenBread.

Testele acoperă un iaurt și un suc fără rețetă/siglă, API idempotent, două organizații, acces și FK transversale respinse, roluri, scope-uri, CSRF, publicarea separată de eliberare, snapshot L1 păstrat după L2, alerte și rechemări, coduri de lot case-sensitive, encodare unică, GTIN invalid, drepturi neverificate, continuitatea unui URI după dispută, feedback negativ, protejarea sesizărilor, vot unic/retragere, AI dezactivat, surse private excluse, citări inventate respinse, ieșire AI doar draft, export și digest NDJSON, import privat idempotent și PBT fără rezultate fabricate.

În browser s-a creat un aliment din categoria apicole, fără GTIN și fără rețetă, o etichetă, un lot `UI/001+00`, apoi s-a publicat pagina și descărcat QR-ul SVG. Destinația a deschis exact lotul. SVG-ul descărcat efectiv a fost decodat folosind ZXing-C++ 3.1.1, independent de biblioteca Nayuki folosită la generare. Șase alte simboluri au verificat GTIN, lot, zerouri, slash, plus, procent și encodare literală. Acestea sunt probe digitale; nu sunt probe pe ambalaje.

La dimensiunile verificate nu s-a observat overflow orizontal pe pagina principală și pagina mobilă a produsului. Logul consolei pentru parcursul final nu a avut erori. Nu se declară conformitate WCAG formală pe baza acestui control; cititorul de ecran și browserele iOS/Android native necesită probe separate.

## Reproducere locală

- `tests/integration.php`, cu `OA_TEST_CONFIG` spre o configurație privată. Scriptul acceptă și resetează exclusiv DB numită `openaliment_tests`.
- Pornește previzualizarea PHP cu `tools/dev-router.php` și configurația către aceeași bază izolată; rulează `tests/test_http.py`. Fixture-urile generate conțin credențiale exclusiv de test și nu se publică.
- Rulează `tests/qr-matrices.cjs`, apoi `tests/test_qr.py` cu decodorul de test instalat separat. SVG-ul UI se verifică dacă există fișierul și URL-ul așteptat în `tests/runtime/`.
- Reverifică pachetul original cu Python în modul UTF-8: `python -X utf8 OpenBread_Codex_v1_2026-10-23/10_verificare/verify_package.py`.

Rapoartele detaliate fără credențiale sunt în `docs/verification/`. Rezultatele nu includ apeluri reale la OpenAI: transportul AI a fost substituit controlat pentru verificarea contractului și a ACL.

## Necesar înainte de lansare

Versiunea PHP/extensiile, DB efectivă, TLS, `.htaccess`, encoded slashes, WAF/CDN, backup/restore și capacitatea shared hosting nu au fost verificate pe domeniul real. Document root-ul `/home/apelero/openaliment.ro/public/` a fost comunicat explicit de proprietar, nu verificat prin acces la cPanel. Utilitarul protejat `hosting-check.php` este inclus pentru această probă.

Nu s-au testat tiparul pe material real, scanarea la POS, milioane de scanări, infrastructura GS1 completă ori probe alimentare. Resolverul complet, sigla, activarea domeniilor producătorilor și fluxul integral de evaluare nu sunt funcții declarate finalizate ale acestui release. Politicile și identitatea operatorului se configurează înaintea activării colectării publice de date.
