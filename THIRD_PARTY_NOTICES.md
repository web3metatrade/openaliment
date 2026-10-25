# Componente terțe și proveniență

Fișierele terțe își păstrează licențele. Nu sunt reformate și nu se modifică pentru schimbări de organizare. Versiunile, sursele și hashurile verificate sunt păstrate în [docs/DEPENDENCIES.json](docs/DEPENDENCIES.json).

| Componentă | Locație | Licență și notificări |
| --- | --- | --- |
| PHPMailer 7.1.1 | `app/Vendor/PHPMailer/` | LGPL-2.1-only; `LICENSE.txt` și notificările din surse. |
| lbuchs/WebAuthn 2.2.0 | `app/Vendor/WebAuthn/` | MIT; `LICENSE` și notificările originale. Verificare server WebAuthn, fără modificări în biblioteca inclusă. |
| Bootstrap 5.3.8 | `public/assets/vendor/bootstrap.min.css` | MIT; `BOOTSTRAP-LICENSE.txt`. |
| Nayuki QR Code generator | `public/assets/vendor/qrcodegen.js` | MIT; notificarea din sursă și proveniența snapshotului. |
| ZXing Browser 0.2.1 și core ZXing | `public/assets/vendor/zxing-browser.min.js` | MIT pentru stratul browser, Apache-2.0 pentru core; `ZXING-BROWSER-LICENSE.txt` și `THIRD-PARTY-NOTICES.txt`. |
| Tesseract.js/core 7.0.0 și datele eng 1.0.0 | `public/assets/ocr/` | Apache-2.0 pentru motor/date; pachetul de date are și notificare MIT. `APACHE-LICENSE.txt` și manifestul dependențelor. |
| Resurse GS1 TDT, scheme/linkset și registrul media IANA | `contracts/gs1/` | Sursele și notificările sunt în `contracts/gs1/NOTICE.json`; proveniența fiecărei resurse trebuie păstrată. |

Playwright, sharp și pachetele Python din manifestele de dezvoltare se instalează separat pentru dezvoltare și CI. Nu sunt necesare pe hosting și nu sunt incluse ca runtime-uri în pachetul cPanel.

Documentele PBT și imaginile sunt tratate separat în [LICENSING.md](LICENSING.md). Această listă nu atribuie platformei drepturi asupra mărcilor sau conținutului altor titulari.

Standardele GS1 au propriile drepturi GS1 AISBL și nu sunt relicențiate MIT. Catalogul și copiile private sunt descrise în docs/standards/GS1.md; distribuția publică a PDF-urilor folosește adresele oficiale GS1.
