# Activarea mecanismului API pe producție

Migrarea aditivă `005_api` a fost aplicată la 24 octombrie 2026, prin butonul „Activează accesul pentru aplicații” din sesiunea administratorului pe `https://openaliment.ro/admin/updates`. Panoul a confirmat „Accesul API și autorizarea aplicațiilor sunt pregătite”. La revenirea pe pagină, starea rămâne activă, iar butonul de instalare nu mai apare.

Verificarea HTTPS după activare a încheiat toate cele 21 de probe fără eșecuri, cu `pending_schema=false`. Manifestul declară `authentication.oauth.available=true`, `capabilities.consumer_oauth=true` și 206 operații documentate. Catalogul public nou răspunde 200. Lookup-ul demonstrativ PBT, linksetul GS1, documentația, schemele și regulile de refuz al accesului anonim continuă să treacă probele.

Pagina HTML `/manage/developers` este accesibilă în sesiunea autentificată și afișează formularul aplicațiilor fără eroare. Încercarea de a deschide direct lista JSON privată a cheilor a fost blocată de clientul browserului (`ERR_BLOCKED_BY_CLIENT`), astfel încât nu este pretinsă o probă reușită a acelei citiri pe producție. Nu au fost create chei API, aplicații, autorizări OAuth sau produse reale pentru test. Fluxurile complete de emitere, rotație, revocare și OAuth au fost executate anterior pe instanța locală izolată; activarea nu reprezintă o certificare externă OAuth/OIDC.

Migrarea creează tabelele API și indexul informațiilor publice aprobate; nu rescrie versiunile istorice ale produselor, loturilor ori rețetelor. Configurațiile private și originalele OpenBread nu sunt modificate. Activarea a folosit sesiunea administrativă, fără parole transmise în chat și fără SQL prin puntea de mentenanță.

Dovezi: [raportul public de verificare](api-activation-2026-10-24.json), `tests/runtime/reports/api-production-smoke.json` și captura `tests/runtime/screenshots/api-activation-complete.jpg`. Instrucțiunile de instalare rămân aplicabile instanțelor noi; starea confirmată aici se referă numai la `openaliment.ro`.
