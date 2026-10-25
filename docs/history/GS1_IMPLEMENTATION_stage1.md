# GS1 Digital Link — nivel implementat și conformitate

Verificare oficială: 23.10.2026. Au fost deschise registrul GS1 și capitolele de sintaxă și conformitate. Edițiile confirmate sunt aceleași cu cele citate în handover: [URI Syntax 1.7.0](https://ref.gs1.org/standards/digital-link/uri-syntax/1.7.0/) și [GS1-Conformant Resolver 1.2.1](https://ref.gs1.org/standards/resolver/1.2.1/), ratificate în august 2026. Linkurile fără versiune rămân punctele de verificare pentru actualizări: [sintaxă](https://ref.gs1.org/standards/digital-link/uri-syntax/), [resolver](https://ref.gs1.org/standards/resolver/).

## Decizie

Implementarea inițială este **pagină de destinație pentru un subset de URI Digital Link**, cod intern `digital_link_landing`. Nu este declarată GS1-Conformant Resolver. Standardul resolverului, §1.1, distinge explicit aceste utilizări și nu impune ca orice adresă Digital Link să fie conectată la un resolver.

OpenAliment oferă găzduire independentă. Nu alocă GTIN-uri, nu transferă licențe GS1 și nu pretinde afiliere, acreditare sau aprobare GS1. Organizația justifică identitatea și mandatul; administratorul păstrează dovada, actorul, data și istoricul deciziei. Cifra de control nu dovedește titularul.

## Profil inițial

- AI (01): GTIN-8/12/13/14 la intrare, validat și normalizat la 14 cifre în URI. Nu se utilizează GTIN arbitrar pe produse reale.
- AI (10): 1–20 caractere din X character set GS1, comparare exactă. Generatorul are separat un profil de transport conservator `[A-Za-z0-9_-]{1,20}` până la probe pe infrastructură.
- Codificare RFC3986 cu `rawurlencode` pe valoarea lotului; segmentarea se face înaintea unei singure decodări. `+` nu devine spațiu. Lotul literal `A%2FB` este diferit de `A/B`.
- `/01/{gtin14}` identifică articolul; `/01/{gtin14}/10/{lot}` identifică lotul. Nu se deduce lotul din momentul scanării.
- Datele `11`, `15`, `17` din query nu rescriu datele oficiale; o nepotrivire este semnalată în pagina lotului. Generatorul inițial nu adaugă atribute de dată.
- Identificatorii suplimentari, CPV (22), seria (21), combinațiile și URI comprimate nu sunt generați sau declarați suport complet.

URI pe `openaliment.ro` nu este „canonical GS1 URI” în sensul §4.12; acea formă folosește `id.gs1.org`. Este o adresă persistentă pe domeniul serviciului.

## Matrice resolver 1.2.1, §5

| Funcție | Prima implementare | Condiția pentru declarație completă |
| --- | --- | --- |
| GET/HEAD | Pagini produs/lot; testare HTTP locală | Proba completă HTTP/TLS pe domeniu |
| OPTIONS/CORS | Nu se declară interfață resolver | Implementare și teste normative |
| HTTPS | Configurare cPanel și redirect pregătite | TLS și proxy verificate pe hosting |
| Toți calificatorii cheilor declarate | Numai GTIN și lot în profilul inițial | CPV, lot, serie și combinațiile permise |
| Validare și 400/404 | Subsetul declarat este validat; necunoscut nu devine produs aproximat | Suita GS1 completă, inclusiv AI asociați |
| Compresie/decompresie EPC | Neimplementată | Implementare și corpus oficial |
| Destinații multiple/tipuri de link | Neimplementate | Registru versionat și selecție normativă |
| `linkType=linkset`/Accept linkset | `406`, fără fals răspuns de conformitate | Linkset RFC9264 validat cu schema GS1 |
| Moștenire și granularitate GTIN | Neimplementate ca resolver | Teste pentru ierarhie și default-uri |
| Negociere limbă/media/context | Neimplementată | Matrice de selecție și fallback |
| Păstrarea query-ului la redirect | Nu există redirect arbitrar de resolver | Propagare conformă și teste de securitate |
| `/.well-known/gs1resolver` | Nu este publicat | Descriptor validat și declarație exactă |
| Namespace-uri GS1/defaultLink | Neimplementate ca resolver | Relații și descriptor validate |

Adăugarea unui simplu redirect sau a unui linkset JSON izolat nu ar închide această matrice. Conformitatea se declară numai după implementarea cerințelor aplicabile și testarea ediției fixate.

## Transport cPanel și tipar

[Apache AllowEncodedSlashes](https://httpd.apache.org/docs/2.4/mod/core.html#allowencodedslashes) poate refuza `%2F` înaintea PHP. Nu se încearcă dublă codificare pentru a ocoli gazda și nu se schimbă valoarea lotului. În acea situație se generează `/b/{id}`. Activarea `gs1_transport_verified` cere probe prin DNS/TLS/CDN/WAF/Apache/PHP, inclusiv majuscule, zerouri și fiecare caracter activat în profil.

QR-ul SVG folosește biblioteca Nayuki MIT locală, corecție MEDIUM sau creșterea automată permisă de bibliotecă, negru pe alb și margine de patru module. Dimensiunea fizică, materialul, contrastul și citirea la POS se verifică separat. Un SVG decodabil pe ecran nu reprezintă verificare de tipar sau certificare GS1.

## Migrare

Transferul păstrează GTIN-ul ca identificator declarat și dovezile istorice, fără transfer automat de autoritate. La intrarea de la GS1/alt furnizor se obține exportul disponibil și mandatul; un resolver public nu oferă prin standard acces la baza privată a furnizorului. Mapper-ele se definesc pe formatul efectiv primit. Pentru URL-uri tipărite pe domeniul vechi este necesară continuitatea acelui domeniu/serviciu și configurarea explicită a destinației. Domeniul controlat de producător reduce dependența de furnizor, dar necesită verificare, TLS și rutare înainte de activare.
