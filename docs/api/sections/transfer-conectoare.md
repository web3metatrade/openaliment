## Configurarea unui conector

1. Creează un conector `catalog` pentru produse și loturi sau `retail_receipt` pentru bonuri digitale.
2. Adaugă o versiune de mapare `openaliment.mapping.v1`. Versiunea este imutabilă și identificată prin ID și SHA-256.
3. Trimite un eveniment cu `mapping_id`, `external_id`, `event_key`, `operation` și `payload` la `preview`. Rezultatul arată datele canonice, fără import.
4. Verifică rezultatul și trimite evenimentul la `ingest`, cu `Idempotency-Key`.
5. Pentru catalog, aprobă separat eticheta și publicarea lotului, folosind permisiunile și rolurile necesare.

Un conector catalog acceptă `product` și `batch`, cu mapări distincte. Produsul creează o ciornă; o versiune nouă adaugă o etichetă. Lotul indică `trade_external_id` importat prin același conector și `label_version_key` deja publicată. Un GTIN nou cere un articol extern nou. Publicarea și eliberarea lotului nu sunt efecte ale importului.

### Reguli de mapare

Sursele și destinațiile folosesc JSON Pointer: `~1` pentru slash, `~0` pentru tildă. O regulă conține exact `from` sau `value`, opțional `op` și `required`. Operații: `copy`, `text`, `decimal`, `integer`, `gtin`, `date_iso`, `date_dmy`, `boolean`.

Sunt acceptate maximum 100 de reguli pe obiect, 8 segmente de cale, 2 liste și 500 de rânduri pe listă, într-o cerere de maximum 1,1 MB. Numerele cu separatori ambigui sunt respinse. Maparea nu execută scripturi, SQL sau descărcări. Câmpurile nemapate nu se copiază automat, iar valorile absente rămân necunoscute.

Exemplu de mapare a bonului unui partener:

```json
{
  "schema_version": "openaliment.mapping.v1",
  "fields": {
    "/merchant": {"from": "/shop/title", "required": true},
    "/receipt_external_id": {"from": "/ticket/id"},
    "/issued_at": {"from": "/ticket/at"}
  },
  "arrays": [{
    "from": "/ticket/basketLines", "to": "/lines",
    "fields": {
      "/line_id": {"from": "/row", "op": "text"},
      "/name": {"from": "/text"},
      "/gtin": {"from": "/EAN", "op": "gtin"},
      "/quantity": {"from": "/pieces", "op": "integer"},
      "/unit": {"value": "pack"}
    }
  }]
}
```

GTIN-ul nu conține automat lotul, data de valabilitate sau valorile nutriționale. Trimite informațiile deținute de partener, cu proveniența lor; clientul verifică datele contradictorii pe ambalaj.

### Reluarea evenimentelor

`event_key` este sensibil la majuscule și are maximum 128 de caractere. Aceeași cheie cu aceleași date întoarce rezultatul inițial; alte date produc 409. Persistă cheia și versiunea mapării înainte de trimitere. Nu schimba maparea sau payload-ul unei reluări după timeout. Procesarea aceluiași conector este serializată și respectă [cotele de scriere](/api/docs/guides/limite).

## Bonul digital

Importul unui bon întoarce o adresă confidențială `/acasa/#bon=TOKEN`, valabilă 30 de zile. Magazinul o livrează numai cumpărătorului. Oricine deține legătura poate consulta lista până la expirare; nu o include în loguri publice, analytics sau conversații cu asistenți AI.

Previzualizarea trimite tokenul prin POST și nu cere cont. Fragmentul este eliminat din bara adresei la deschiderea aplicației. Clientul verifică lista și apasă „Adaugă acasă”. Două bucăți identice devin două ambalaje, cu deschidere și consum independente. Același bon nu dublează ambalajele la o nouă adăugare, inclusiv după consum sau export/import. Cantitățile cântărite rămân în g/ml.

Formatul `openaliment.receipt.v1` acceptă comerciantul, referința bonului, momentul cu fus orar și liniile alimentelor. Exclude emailul cumpărătorului, date de plată, prețuri și totaluri. Bonul digital OpenAliment nu este document fiscal sau dovadă de plată. Importul unei fotografii de bon și integrarea fiscală nu sunt disponibile prin acest contract. Expirarea legăturii blochează accesul; nu echivalează cu ștergerea fizică a înregistrării.

## Export JSON și NDJSON

Formatul JSON `openaliment.transfer.v1` include data exportului, domeniul sursă, identitatea declarată a organizației, colecțiile și `data_sha256`. Include produsele, articolele, versiunile de etichetă și rețetă, loturile, evenimentele, alertele, documentele, proveniența, propunerile, mapările URL, feedbackul publicat și auditul. Conectoarele, mapările și asocierile externe sunt colecții opționale.

Exportul este privat și poate conține rețete private. Exclude utilizatori, parole, tokenuri API, tokenuri de feedback, contacte private, reclamații brute și legături confidențiale de bon. Documentele sunt transferate cu octeții originali; verifică prezența fișierelor pentru o migrare completă.

`data_sha256` este SHA-256 al JSON-ului emis fără whitespace, cu Unicode și slashuri neescapate, în ordinea colecțiilor păstrată de exportator. Nu reordona datele înainte de verificare. Amprentele fișierelor sunt calculate pe octeții originali. Digestul verifică integritatea datelor, nu identitatea unei autorități.

Exportul JSON are un plafon de 10.000 de rânduri pe colecție și aproximativ 8 MB de documente și istoric. Pentru volume mari folosește `GET /api/v2/manage/export?format=ndjson`, cu scope `export`. Formatul este `openaliment.transfer.stream.v1`:

- Prima linie declară schema și sursa.
- Liniile de date conțin `table` și `row`.
- Ultima linie conține `end=true`, `records` și SHA-256 al tuturor octeților liniilor anterioare, inclusiv separatorii LF.

Lipsa liniei terminale sau digestul incorect indică un export incomplet. NDJSON include și registrul importurilor și proveniența pilotului Pâine Bună Tehnic. Păstrează exportul într-un spațiu privat.

## Import și reaprobarea datelor

Importatorul HTTP acceptă JSON v1, maximum 24 MB. NDJSON este un format de export pentru prelucrare în flux și migrare asistată; nu este acceptat ca cerere de import JSON.

`dry_run=true` verifică forma, hashul și numărul colecțiilor. Aplicarea cu `dry_run=false` cere idempotentă și verifică tranzacțional referințele, identitățile, relațiile, snapshoturile și fișierele. Cotele organizației se aplică și importurilor. Dry-run nu rezervă spațiu și nu înlocuiește verificările de aplicare.

Importul creează ID-uri locale și returnează `id_map`. Etichetele și destinațiile resolverului intră în ciornă; loturile și rețetele rămân private, documentele necesită confirmarea drepturilor, iar GTIN-urile necesită reverificare. Alertele, istoricul și sursa completă se păstrează în proveniența privată. Reluarea aceleiași surse și aceluiași hash întoarce aceeași mapare. O sursă modificată nu suprascrie automat obiectele existente.

După migrare, emite chei noi și reconciliază evenimentele partenerului cu versiunile deja importate. Colecțiile opționale absente din exporturile mai vechi nu invalidează pachetul. Publicările, siglele și mandatele sursei nu devin aprobări locale.

### Compatibilitate OpenBread

Importul pilot recunoaște `openbread.product_seed.v0.1`, `openbread.recipe.general.v0.1` și reprezentarea R1. Păstrează originalele, extrasele, recuperările BT01 și proveniența MOPAN. Rețeta R1 este experimentală, iar COST3 conține estimări. Fișierul deteriorat rămâne în carantină. Importul nu creează rezultate de fabricație, termen de valabilitate sau GTIN comercial pentru pâine.

### Migrare de la alt furnizor

GS1 Digital Link standardizează identificatorii și rezolvarea legăturilor; nu impune un export universal al datelor comerciale sau rețetelor. Pentru un alt furnizor, inclusiv servicii GS1, stabilește formatul exportului disponibil, documentele, dicționarul câmpurilor și dreptul organizației de a le transfera. Câmpurile nesuportate se păstrează în proveniență prin adaptorul convenit.

Un linkset public poate inventaria destinațiile; nu dovedește titularul și nu înlocuiește exportul privat. Migrarea datelor nu mută licența GS1 și nu schimbă domeniul tipărit în QR. Continuitatea adreselor cere controlul sau acordul operatorului domeniului sursă. Mapările importate rămân neactive până la verificare. Găzduirea pe domenii proprii ale producătorilor nu este disponibilă prin API-ul public actual.
