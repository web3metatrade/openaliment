# Raportarea vulnerabilităților

Raportează confidențial problemele de securitate prin funcția GitHub Private Vulnerability Reporting, când este activată pe repository. Dacă aceasta nu este disponibilă, folosește contactul operatorului din pagina publică de confidențialitate a instanței afectate. Nu publica parole, tokenuri, date personale sau instrucțiuni de exploatare într-un issue public.

Include versiunea codului, componenta, impactul și pași minimali de reproducere cu date sintetice. Nu testa accesul la organizațiile sau inventarele altor utilizatori și nu executa scanări de volum asupra hostingului comun.

Remedierile sunt pregătite pe versiunea activă de dezvoltare. O listă de versiuni suportate și termene de răspuns se publică atunci când există un proces operațional stabilit; acest document nu promite timpi inexistenți.

Dezvoltarea și CI nu folosesc configurația DB, SMTP sau cheia de mentenanță a producției. Cheia HTTPS permite actualizarea codului și se tratează ca acces administrativ. Fișierele private rămân în afara `public/` și a pachetelor distribuite.

API: cheile organizațiilor și tokenurile OAuth sunt opace, păstrate numai ca hashuri și revocabile. Tokenurile nu se transmit în URL. Aplicațiile publice folosesc Authorization Code cu PKCE S256 și consimțământ explicit, redirect URI exact, refresh rotit și detectarea reutilizării. Cheile ERP nu se includ într-un APK sau JavaScript public. Nu se acordă administrarea platformei prin chei sau granturi terțe. Vezi [ghidul API](docs/api/DEVELOPER_GUIDE.md) pentru limite și retragerea accesului.

Cotele API limitează frecvența, dimensiunea cererilor, paginarea, cheile/aplicațiile și stocarea per organizație. Ele nu înlocuiesc protecțiile de volum oferite de hosting/CDN. Antetele proxy sunt ignorate implicit; `trusted_proxy_cidrs` trebuie configurat numai pentru proxy-uri controlate și verificate.
