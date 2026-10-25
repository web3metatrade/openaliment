# Prima vizită — modificări în 0.7.2

Implementarea urmărește găsirea rapidă a unei acțiuni și recuperarea simplă după o greșeală. Nu modifică nucleul GS1, modelul de permisiuni, contractele API sau schema bazei de date.

## Parcursuri

- Antetul oferă direct „Alimentele mele”. Pe ecrane compacte, celelalte destinații sunt într-un meniu nativ care poate fi închis și cu Escape. Fără JavaScript, meniul rămâne utilizabil.
- Primul ecran mobil arată beneficiul și butonul „Încearcă exemplul” înaintea fotografiei. Legăturile care promit același exemplu conduc direct la lotul demonstrativ.
- Pagina pâinii afișează întâi informațiile pentru consumator și valorile necunoscute. Identificatorii, descărcarea QR și proba POS sunt într-o secțiune tehnică extensibilă. Datele experimentale nu devin rezultate validate.
- În Acasă, un cod invalid rămâne în dialog, cu eroare lângă câmp și focus pentru corectare. Un cod valid închide dialogul după adăugare.
- Cardul fără dată oferă completare directă. Panoul de dată modifică numai termenul, păstrând denumirea, cantitatea și locul; tipul datei rămâne ales explicit. Editarea completă, precizia lună/an, OCR și anularea rămân disponibile.
- Preferințele au o etichetă vizibilă. Antetul dialogurilor rămâne accesibil la derulare.
- Un cont de producător gol prioritizează primul produs. Conturile fără drept de editare primesc o stare informativă; nu primesc un formular nou de creare. Funcțiile avansate și ieșirea din cont sunt grupate separat, cu aceleași drepturi.
- Catalogul gol oferă exemplul marcat demonstrativ și Acasă. Marketplace-ul diferențiază lipsa ofertelor de o căutare fără rezultate, permite resetarea și separă solicitarea de publicarea unei integrări.
- Contactul explică deschiderea emailului și permite copierea adresei. Nu trimite mesaje automat.
- Căutarea API arată scopul, metoda, calea și versiunea. Potrivirile din titlu au prioritate, iar rezultatele v1 rămân disponibile pentru compatibilitate.

## Compatibilitate și actualizare

PWA v9 folosește aceeași bază IndexedDB. Instalează resursele și pagina Acasă înainte de activare; un răspuns necorespunzător pentru pagină oprește instalarea noii versiuni. Activarea păstrează resursele OCR în cache-ul media și elimină cache-urile vechi ale aplicației. Nu șterge inventarul, fotografiile, preferințele sau istoricul personal.

Nu este necesară o migrare SQL. Actualizarea obișnuită păstrează configurațiile private și stocarea. Publicarea pe `dev`, instalarea pe staging și promovarea în producție rămân acțiuni distincte, conform [fluxului de colaborare](../operations/COLLABORATION.md).

## Verificări

`tests/first-visit-browser.cjs` verifică navigarea la 320, 390, 768 și 1440 px, exemplul prin resolver, adăugarea fără cont, corectarea codului invalid, completarea datei fără modificarea altor câmpuri, căutarea API, revenirea dintr-o căutare goală și crearea unui produs din categoria lactate. Este inclus în `python tools/qa/run.py --suite browser --reset-test-db`.

Proba de actualizare PWA folosește un worker vechi minimal, servit numai de routerul CLI de test, și apoi workerul real nou. Verifică schimbarea cache-ului, păstrarea inventarului și deschiderea offline fără închiderea paginii. Aceasta probează ciclul de cache, nu fiecare octet al aplicației istorice. Servirea reală evită limitarea [Playwright pentru interceptarea scripturilor worker actualizate](https://playwright.dev/docs/service-workers#known-limitations).

Suitele existente continuă să verifice separarea organizațiilor, accesurile, istoricul, contractele, interpretarea GS1, QR-urile, exportul/importul și funcționarea fără AI. Datele de test, configurațiile și capturile rămân în directoarele excluse din Git. Probele responsive nu reprezintă un studiu cu participanți, eye tracking, verificare de dispozitiv fizic sau acceptare POS.
