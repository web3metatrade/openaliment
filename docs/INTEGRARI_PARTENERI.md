# Conectoare ERP și magazin

Contracte: `openaliment.mapping.v1`, `openaliment.receipt.v1`, API `/api/v2/manage/connectors`. API-ul v1 rămâne compatibil. Migrări aditive: `003_integrations.sql`; activare din Administrare → Actualizări. Nicio rețetă sau siglă nu este cerută pentru aceste fluxuri.

## Fluxul unei conexiuni

1. Proprietarul creează un token cu `read`/`write`. `publish` se acordă separat numai responsabilului publicării. Tokenul identifică organizația; ID-uri de organizație primite în payload nu conferă acces.
2. Creează conectorul în [spațiul producătorului](https://openaliment.ro/manage/connectors), cu tipul `catalog` sau `retail_receipt`.
3. Adaugă o versiune a mapării. Aceasta este imuabilă și are SHA-256. Fiecare eveniment indică explicit `mapping_id`; schimbarea mapării nu schimbă interpretarea evenimentelor vechi.
4. Rulează `preview` cu un payload al partenerului. Rezultatul arată datele canonice și nu importă nimic.
5. Rulează `ingest`. Un produs nou creează produs, articol și etichetă în ciornă. O versiune nouă adaugă etichetă; nu rescrie eticheta veche. Un lot se leagă de `label_version_key` explicit și deja publicat. Importul nu publică și nu eliberează lotul.
6. Verificarea dreptului asupra GTIN-ului, publicarea etichetei și publicarea lotului folosesc fluxurile existente. Validarea cifrei de control nu acordă drepturi de administrare.

Un conector catalog poate primi atât `product`, cât și `batch`, cu mapări distincte. Referința externă a lotului `trade_external_id` trebuie să fi fost importată prin același conector. Un GTIN nou necesită un articol extern nou. Un număr numeric JSON nu este acceptat drept GTIN: zerourile inițiale trebuie păstrate prin text.

## Mapare declarativă

Destinațiile și sursele folosesc JSON Pointer (inclusiv `~1` pentru slash și `~0` pentru tildă). Regula conține exact `from` sau `value`, o operație și opțional `required`. Operații: `copy`, `text`, `decimal`, `integer`, `gtin`, `date_iso`, `date_dmy`, `boolean`. Datele au format explicit; numerele cu separatori ambigui se resping. Nu există expresii executabile, SQL, scripturi sau downloaduri configurabile. Maximum 100 reguli pe obiect, 8 segmente de cale, 2 liste și 500 de rânduri pe listă; cerere de maximum 1,1 MB.

Exemplu de mapare pentru un bon:

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

Lotul, data și valorile nutriționale nu se deduc din GTIN. Câmpurile absente rămân necunoscute. Nu se copiază automat alte câmpuri din payload. Un agent AI poate propune o mapare într-un proces ulterior; publicarea unei astfel de propuneri nu este implementată și nu se execută automat.

## Livrare, autorizare și reluare

API-ul privat folosește `Authorization: Bearer` exclusiv prin HTTPS. Modificările cer `Idempotency-Key` de 8–128 caractere ASCII. La nivel de conector, `event_key` este unic, sensibil la majuscule, de maximum 128 caractere. Aceeași cheie cu aceleași date întoarce rezultatul inițial; date diferite primesc 409. Tranzacția include asocierile externe, datele, evenimentul și rezultatul. Nu se creează dubluri după un timeout. Procesarea unui conector este serializată; limita curentă este 120 cereri/minut/principal, nu un flux bulk nelimitat.

Agentul [partner-agent.py](../tools/partner-agent.py) poate rula în infrastructura partenerului, independent de shared hosting. Acceptă un eveniment JSON, verifică implicit fără import și importă numai cu `--apply`. Citește tokenul din variabila privată `OA_API_TOKEN`, refuză redirecturile pentru a nu transmite tokenul altui host, reîncearcă timeout/429/5xx cu același corp și aceeași cheie. Pentru 4xx definitive, evenimentul se corectează și se emite o versiune nouă. Ieșirea poate conține o legătură privată de bon; se păstrează într-un fișier privat, fără log public. Agentul nu citește singur baza ERP: adaptorul partenerului produce evenimentele în formatul său, iar maparea le normalizează.

## Bon digital și experiența clientului

Importul unui bon returnează o adresă `/acasa/#bon=TOKEN`. Tokenul aleatoriu de 256 biți este o capabilitate confidențială, valabilă 30 de zile. Fragmentul nu ajunge în URL-ul HTTP sau în Referer și este șters din bara adresei la deschiderea aplicației. Previzualizarea folosește POST cu tokenul în corp, fără cache și fără cookie de producător. Nu se cere cont. Oricine primește legătura poate vedea lista cumpărăturilor până la expirare; magazinul trebuie să o livreze numai cumpărătorului. Tokenul nu se înscrie în loguri sau URL-uri de analiză.

Bonul nu conține emailul cumpărătorului, date de plată, prețuri sau totaluri. Schema acceptă numai comerciantul, referința bonului, momentul cu fus orar și liniile alimentelor. Nu există endpoint public care să enumere bonurile. Hashul tokenului se păstrează în tabelul bonurilor; rezultatul idempotent păstrează legătura în istoricul privat al integrării pentru a permite retrimiterea exactă. Nu este o dovadă fiscală sau de plată. În această versiune expirarea blochează accesul, dar eliminarea fizică după retenția legală/configurată necesită implementarea jobului de retenție.

Clientul vede lista, apasă „Adaugă acasă”, iar ambalajele se salvează local. Două bucăți identice devin două ambalaje, cu deschidere și consum independente. Bonul repetat nu dublează ambalajele, inclusiv după consum sau export/import. Cantitățile cântărite rămân în g/ml. Datele contradictorii dintre bon și producător cer confirmare pe ambalaj. Un GTIN absent permite totuși salvarea denumirii; o dată absentă nu devine o expirare inventată. Importul fotografiilor de bon și integrarea fiscală efectivă sunt cerințe distincte, încă neîncheiate.

## Dovezi și limite

`tests/integrations.php` simulează două formate diferite de ERP și magazin în `openaliment_tests`; `tests/receipt-domain.mjs` verifică adăugarea și deduplicarea ambalajelor. Rapoarte: `docs/verification/partner-simulator-report.json` și `receipt-client-report.json`. Aceste probe nu reprezintă conectare la un ERP comercial sau tranzacție fizică la POS. Conectorul este generic, fără dependență de numele unui furnizor. Conturile și contractele unui partener real se configurează după alegerea lui.

## Portabilitatea configurării

Exportul producătorului include opțional conectoarele, toate versiunile mapărilor și asocierile cu articolele externe. Importul remapează identificatorii și păstrează etichetele private pentru reverificare; pachetele vechi fără aceste colecții rămân acceptate. Nu exportă tokenuri API, legături confidențiale de bon sau rezultatele brute ale evenimentelor integrării. După migrare, partenerul primește credențiale noi și reconciliază coada cu versiunile și loturile deja importate înainte de reluare; o versiune sau un lot existent nu poate fi rescris printr-o cheie nouă.
