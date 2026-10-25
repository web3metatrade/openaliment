# Email SMTP pe openaliment.ro

Biblioteca PHPMailer este livrată în proiect; hostingul nu are nevoie de Composer. Nu se folosește funcția PHP mail() ca alternativă tăcută la SMTP.

1. Creează contul de email în cPanel și deschide configurarea clientului de email pentru acel cont.
2. Copiază [config/mail.local.example.php](../config/mail.local.example.php) în **config/mail.local.php**, în afara public/. Acesta este un fișier separat de configurația bazei de date; nu o înlocuiește.
3. Completează hostul exact indicat de cPanel, utilizatorul complet și parola contului, adresa expeditorului, portul și criptarea. Pentru SMTPS folosește port 465/encryption ssl; pentru STARTTLS, 587/tls, potrivit setărilor gazdei. Setează enabled la true după completare.
4. Păstrează fișierul privat, cu permisiuni restrictive. Nu trimite parola în chat, nu include fișierul în release și nu îl muta în public/.

Verificarea certificatului TLS rămâne activă. Dacă gazda indică un alt nume de server, folosește numele valid din configurația sa, fără dezactivarea verificării. DNS-ul pentru expeditor, SPF/DKIM și livrarea efectivă se verifică după configurarea contului real.

Transportul de test scrie numai în storage privat, exclusiv cu environment=test și baza openaliment_tests. Nu este o dovadă de livrare SMTP și nu poate fi activat în producție. Configurația SMTP este încărcată numai când este necesară; o problemă a emailului nu blochează paginile QR.
