# Prima pagină: pâinea pilot cu QR la casa de marcat

24.10.2026 · https://openaliment.ro/

La cererea proprietarului, ilustrația abstractă de ambalaj din primul ecran a fost înlocuită cu o scenă fotorealistă Pâine Bună Tehnic la casa de marcat. Imagine generată cu `image_gen`, apoi ajustată pentru pâine de secară fără semințe adăugate, în acord cu ingredientele R1. Este un vizual ilustrativ al pilotului, marcat DEMO pe ambalaj și în legenda paginii; nu o fotografie de fabricație sau dovadă a unui test POS.

Titlu: „De la codul de bare la QR-ul produsului tău.” Explicația alăturată menționează case de marcat compatibile, accesul de pe telefon, resolverul și integrarea ERP prin API. O precizare scurtă arată coexistența EAN/QR în tranziție și trimite către ghidul deja publicat. Nu se afirmă eliminarea universală a EAN sau suportul tuturor magazinelor în prezent. Referință: [GS1 2D Barcodes at Retail POS, 1.1.0](https://ref.gs1.org/guidelines/2d-in-retail/).

QR-ul din imagine este cel demonstrativ de lot, nu un model aleator: `https://openaliment.ro/01/09520000000011/10/DEMO-PBT-01`. Imaginea și acțiunile „Scanează exemplul”/„Încearcă QR-ul” conduc la acea adresă și la pagina corectă prin resolver. Nu s-au modificat datele, drepturile GTIN sau istoricul pilotului.

Sursa și prompturile: `docs/images/PBT_CHECKOUT_PROMPT_2026-10-24.md`; sursa PNG este păstrată în `output/imagegen/`. Fișierele publice JPEG au 768 și 1200 pixeli lățime, aproximativ 96/195 KB, cu `srcset`, `sizes`, dimensiuni explicite și prioritate ridicată. Fără librării noi pe hosting. CSS este versionat `?v=5`. Pe telefon imaginea apare imediat după titlu, integral în primul ecran la 390×844; desktop verificat la 1440×950. Fără depășire orizontală sau erori în consolă.

Actualizare HTTPS `fc85a0225df244d2a1693bd56d1f8472`, 5 fișiere: două imagini, `home.php`, `layout.php`, `app.css`. Baseline verificat înainte de editare, backup automat și verificare SHA-256 după upload. Nicio modificare DB/config. Păstrate metadatele comerciale, navigarea și restul secțiunilor.

Validare: sintaxă PHP, 16 verificări pentru homepage/vizual și decodarea independentă ZXing-C++ a QR-ului din cele două JPEG-uri descărcate de pe hosting, 32 verificări existente pentru paginile publice și SEO, plus verificare în browser a accesului la lot din noul vizual. Nu s-a executat o probă fizică POS.

Dovezi: `docs/verification/checkout-hero-production-report.json`, `docs/verification/landing-production-report.json`, capturi `docs/previews/production-checkout-home-desktop-2026-10-24.jpg` și `production-checkout-home-mobile-2026-10-24.jpg`.

Recuperare: jurnalul HTTPS de mai sus permite revenirea. Pachetul incremental `release/OpenAliment_homepage_checkout_2026-10-24.zip` conține fișierele publicate, testul, scriptul de optimizare, prompturile și raportul; cere platforma și demonstrația PBT existente. Fără credențiale, configurație reală sau conexiune de mentenanță.
