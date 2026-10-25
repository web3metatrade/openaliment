# Prima actualizare prin conexiunea HTTPS

Data: 23.10.2026. Domeniu: `https://openaliment.ro`. Fișierul de conectare a fost încărcat de proprietar în document root. Cererile de administrare au folosit cheia locală privată prin antet HTTPS, fără cheia sau parola contului în documente ori comenzi.

## Conexiune și actualizare

- Protocol confirmat pe hosting: `openaliment.deploy.v1`, PHP 8.4.27, tokenizer disponibil, directoarele `app/` și `public/` accesibile pentru scriere.
- Fișier actualizat: `app/Controllers/PublicController.php`.
- Copia citită de pe hosting corespundea exact fișierului din release-ul inițial. Modificarea adaugă redirecționarea contului deja autentificat din GET/HEAD `/login` și `/register` către `/manage`.
- ID actualizare: `d13445ec9cbb499daeed622284ea5350`.
- Stare raportată și verificată: `applied`, la `2026-10-23T19:33:30+00:00` (22:33:30 Europe/Bucharest).
- SHA-256 anterior: `d54b318b5edde02827ae8a9992ab835b3dfb64a8a7302c5117ccbc48429dfbb5`.
- SHA-256 activ, recitit după upload: `2f365a3b6465e3347d441fe539745ba44aae388f18f1df25570e90c00da4f5b6`.
- Jurnalul și copia anterioară sunt păstrate în spațiul privat de mentenanță. Nu există migrare SQL pentru această corecție.

## Probe pe domeniul real

1. În sesiunea deja autentificată, linkul din pagina principală către `/login` a deschis direct `/manage`, cu organizația Mopan Suceava și butonul „Ieși din cont”.
2. Accesarea `/register` în aceeași sesiune a ajuns tot la `/manage`.
3. Cereri HTTPS separate, fără cookie-uri, către `/login` și `/register` au primit HTTP 200 și formularele cu câmpul de parolă.
4. Controalele de domeniu nu au creat produse, loturi sau decizii de verificare.

Captură locală a rezultatului: `docs/previews/production-auth-redirect.jpg`. Raportul HTTP local pentru corecție rămâne `docs/verification/auth-redirect-http-report.json` (58/58); raportul conexiunii este `docs/verification/deploy-bridge-report.json` (65 trecute, o probă de symlink omisă deoarece contul Windows nu poate crea astfel de legături). TLS și transferul autentificat au fost confirmate acum și pe hosting.

Revenirea poate fi cerută pentru ID-ul de mai sus folosind clientul de administrare. Nu s-a executat o revenire pe producție după verificarea unei corecții funcționale; mecanismul de restaurare a fost exercitat în suita locală izolată.
