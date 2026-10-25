# Vizual pentru prima pagină: Pâine Bună Tehnic la casă

Generat și ajustat cu instrumentul integrat `image_gen`, prin skill-ul `imagegen`. Scenă fotorealistă ilustrativă, nu fotografie a unui produs comercial fabricat sau a unei instalări POS validate.

QR de referință: `public/assets/demo/pbt-lot-qr.svg`, randat în PNG pentru instrument. Destinație: `https://openaliment.ro/01/09520000000011/10/DEMO-PBT-01`. Textul etichetei marchează DEMO. Nu se adaugă claims alimentare, licențe sau sigle GS1.

Fișierele finale sunt salvate în proiect:

- `C:/Users/MOPAN/Downloads/OPENALIMENT/output/imagegen/pbt-checkout-source.png` — sursa integrală 1536×1024;
- `C:/Users/MOPAN/Downloads/OPENALIMENT/public/assets/pbt-checkout-768.jpg` — 768×512, 95.668 octeți;
- `C:/Users/MOPAN/Downloads/OPENALIMENT/public/assets/pbt-checkout-1200.jpg` — 1200×800, 195.395 octeți.

Variantele web provin din redimensionare și compresie JPEG cu Sharp, calitate 85 și subeșantionare 4:4:4, fără modificarea conținutului. Script: `tools/build-checkout-assets.cjs`, cu biblioteca Sharp din runtime-ul local, nu o dependență a hostingului. QR-ul a fost decodat independent în sursa finală și în ambele JPEG-uri servite de producție.

## Prompt inițial

Use case: product-mockup.
Asset type: photorealistic editorial marketing photograph for the homepage of OpenAliment, an independent Romanian GS1 Digital Link platform.
Primary request: a realistic close-up of the pilot rye bread "Pâine Bună Tehnic" in a transparent retail bread bag being scanned at a modern supermarket checkout, with the QR on its packaging as the unmistakable focal point.
Input image 1 is a supporting insert: the exact real demonstration QR code. Reproduce its complete black and white module pattern faithfully on the package label, including the four-module white quiet zone. Do not invent another QR.
Composition: landscape 3:2 photograph, close-up, bread package centered and filling most of the image; crop-safe subject for a 1.15:1 webpage frame. Dense rectangular dark rye tin loaf visible through a lightly crinkled transparent food bag, pale natural kraft paper band around the middle. The band carries a flat white rectangular label facing almost directly toward the camera. The large, crisp QR from reference occupies the lower half of this label. It should be large enough to scan and have no glare, wrinkles, or fingers over it.
Exact text above QR on label, in clean dark green typography: "Pâine Bună Tehnic". Small exact text below: "DEMO · OpenAliment". No invented food claims, shelf life, nutritional data, awards, organic seals or GS1 logos.
Scene: stainless checkout counter and dark optical 2D scan window just behind the package, with a cashier's single natural hand gently holding the package from the side away from the label. A discreet short soft red scanner light near the lower corner of the pack suggests the scan in progress without crossing or obscuring the QR. Softly blurred checkout terminal and grocery store in the background, no legible screen data, no faces, no retailer logos.
Style: believable commercial food photography, real plastic texture, subtle paper fibers, dense rye bread crust, natural imperfections, soft daylight and neutral warm store lighting. Restrained cream, kraft and deep green details that fit OpenAliment, crisp focus on the QR label and bread, shallow depth of field behind.
No arrows, infographic overlays, floating interface cards, oversized text, captions, frame, watermark or linear barcode. This is a demonstration of a future-ready QR checkout, not a claim all shops currently support it.

## Ajustare

Use case: precise-object-edit.
Edit the supplied photograph with only one change: the bread visible inside the transparent bag must be a dense dark RYE TIN LOAF with a natural rough rye crust, with NO added sunflower seeds, NO sesame seeds, NO oats, and NO other added seeds. The actual pilot recipe has rye flour and rye grist but no added seeds. Remove the visible pale seeds from the loaf surfaces and replace them with believable plain dark rye crust, retaining the loaf's rectangular tin-baked shape, position and size.
Preserve everything else exactly: the entire flat white label, its typography "Pâine Bună Tehnic" and "DEMO · OpenAliment", the complete real QR pattern including its white quiet zone, all wrapping and kraft band, checkout, scanner, hand, lighting, framing and camera angle. The QR currently decodes correctly to https://openaliment.ro/01/09520000000011/10/DEMO-PBT-01 and MUST remain completely unchanged, not regenerated or reinterpreted. Retain photorealistic food packaging textures and shallow depth of field.
