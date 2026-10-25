"""Maintained, source-aligned API reference metadata. No production data is read.

The route registry owns paths/permissions. This module owns explanations, concrete
response projections and synthetic examples. Unknown operations fail the build.
"""
import copy
import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
STAMP = '2026-10-24 09:00:00'
ISO = '2026-10-24T09:00:00+00:00'
BASE = 'https://openaliment.ro'
ID = {'type': 'string', 'pattern': '^[a-f0-9]{32}$', 'description': 'ID intern opac, 32 caractere hexazecimale mici; nu este GTIN.'}
SHA = {'type': 'string', 'pattern': '^[a-f0-9]{64}$', 'description': 'Amprentă SHA-256 hexazecimală a conținutului indicat.'}
S = lambda n=2000: {'type': 'string', 'maxLength': n}
B = {'type': 'boolean'}
N = {'type': 'integer', 'minimum': 0}
DATE = {'type': 'string', 'description': 'Dată și oră UTC în format SQL YYYY-MM-DD HH:mm:ss. Adaugă explicit UTC la conversia într-un fus local.', 'example': STAMP}
TIME = {'type': 'string', 'format': 'date-time', 'example': ISO}


def obj(properties, required=None, extra=False):
    return {'type': 'object', 'properties': copy.deepcopy(properties), 'required': list(properties) if required is None else list(required), 'additionalProperties': extra}


def arr(items):
    return {'type': 'array', 'items': copy.deepcopy(items)}


def nullable(value):
    return {'anyOf': [copy.deepcopy(value), {'type': 'null'}]}


def ref(name):
    return {'$ref': '#/components/schemas/' + name}


FIELDS = {
    'name': 'Denumirea resursei. La citirea publică provine exclusiv din eticheta aprobată.',
    'category': 'Cheia categoriei alimentare; de exemplu lactate sau bread. Nu impune un profil tehnic de panificație.',
    'id': 'ID intern al resursei; păstrează-l pentru cererile următoare.',
    'tenant_id': 'Organizația proprietară. Este stabilită de autentificare, nu poate fi aleasă în payload.',
    'product_id': 'ID-ul produsului din aceeași organizație.',
    'trade_item_id': 'ID-ul articolului comercial (ambalare/variantă); diferă de produs și de GTIN.',
    'product_info_version_id': 'ID-ul versiunii aprobate a etichetei aplicabile. Lotul păstrează această legătură chiar dacă apare E2.',
    'recipe_version_id': 'Rețetă opțională a aceluiași produs. null este valid pentru serviciul QR independent.',
    'batch_id': 'ID-ul lotului, când este cunoscut. Nu deduce lotul dintr-un QR generic de produs.',
    'version_key': 'Versiune aleasă de producător, unică în articol/produs. O versiune închisă nu se rescrie.',
    'lot_code': 'Codul exact al lotului, sensibil la majuscule; fără spații la capete. În URL codifică separat segmentul o singură dată.',
    'gtin': 'GTIN ca șir de 8, 12, 13 sau 14 cifre, cu cifră de control validă. Omiterea permite un URL nativ. Nu acordă drepturi GS1.',
    'gtin14': 'GTIN normalizat la 14 cifre, păstrând zero-urile inițiale. null în proiecții publice fără verificarea administrării.',
    'gtin_original': 'Șirul GTIN introdus inițial; separat de forma normalizată.',
    'gtin_status': 'Starea verificării dreptului de administrare: unverified, verified, disputed sau withdrawn.',
    'identifier_status': 'Starea dreptului de administrare GTIN; verificarea sintaxei nu echivalează cu verified.',
    'claim_evidence': 'Referințe private la dovezile mandatului asupra GTIN; administratorul le examinează separat.',
    'created_at': 'Momentul creării în UTC; format SQL, fără offset în șir.',
    'approved_at': 'Momentul aprobării în UTC; null înainte de aprobare.',
    'approved_by': 'ID-ul persoanei care a aprobat versiunea; numai în contextul autorizat.',
    'verified_at': 'Momentul primei verificări în UTC. Verifică și starea curentă, nu numai prezența datei.',
    'verified_by': 'ID-ul administratorului care a înregistrat decizia.',
    'current_info_id': 'ID-ul etichetei publicate curente; loturile pot continua să folosească o versiune mai veche.',
    'snapshot': 'Copie imutabilă a datelor acestei versiuni, decodată ca JSON în API v2.',
    'snapshot_json': 'Compatibilitate v1: JSON serializat într-un șir. Parsează explicit șirul; nu este obiectul snapshot din v2.',
    'snapshot_sha256': 'Amprenta exactă a snapshotului stocat. Nu o recalcula dintr-un JSON reformatat de client.',
    'visibility': 'private sau public; publicarea necesită o operație explicită și drepturi potrivite.',
    'state': 'Starea resursei. Publicarea lotului și eliberarea sa sunt operații independente.',
    'rights_status': 'Starea declarației privind drepturile; cleared permite publicarea, unconfirmed nu.',
    'license_id': 'Licență aleasă explicit; null la simpla găzduire fără acordarea unei licențe deschise.',
    'technical_status': 'Stadiul real al documentației, de exemplu experimental. Nu reprezintă un rezultat validat sau o siglă.',
    'source_key': 'Referință de proveniență/import; null pentru înregistrări create direct.',
    'ingredients': 'Lista ingredientelor publicabile, ca text aprobat de producător.',
    'allergens': 'Declarația textuală privind alergenii. Absența datelor nu înseamnă lipsa alergenilor.',
    'net_quantity': 'Cantitatea netă de pe etichetă, inclusiv unitatea, de exemplu 200 g.',
    'operator': 'Numele și adresa operatorului alimentar pentru eticheta publicabilă.',
    'description': 'Descriere publicabilă opțională a alimentului.',
    'storage': 'Condițiile declarate de păstrare.',
    'after_opening': 'Instrucțiunile după deschidere; textul nu este transformat automat într-un termen validat.',
    'origin': 'Originea declarată pe etichetă.',
    'nutrition': 'Declarația nutrițională textuală. Pentru calcule folosește food_data cu baza și unitățile explicite.',
    'usage': 'Instrucțiuni de utilizare publicabile.',
    'document_ids': 'Maximum 20 documente ale aceluiași produs; toate trebuie aprobate pentru publicarea etichetei.',
    'food_data': 'Date alimentare structurate și declarații explicite; unknown nu înseamnă zero sau absent.',
    'production_date': 'Data producerii, YYYY-MM-DD, sau null.',
    'best_before': 'A se consuma de preferință înainte de, YYYY-MM-DD. Exclusiv cu use_by.',
    'use_by': 'A se consuma până la, YYYY-MM-DD. Exclusiv cu best_before; nu trebuie să preceadă producția.',
    'public_notes': 'Observații publicabile aplicabile exclusiv lotului.',
    'message': 'Textul alertei sau al rezultatului, destinat persoanei care îl citește.',
    'resolution': 'Motivul documentat al închiderii alertei. Închiderea nu eliberează automat lotul.',
    'resolved_at': 'Momentul închiderii în UTC; null indică o alertă activă.',
    'resolved_by': 'Persoana care a înregistrat închiderea alertei.',
    'created_by': 'Persoana care a creat înregistrarea.',
    'title': 'Titlul documentului, propunerii sau destinației.',
    'body': 'Explicația propunerii; nu modifică automat informațiile aprobate.',
    'public': 'Publicarea directă a propunerii cere și publish, plus rol owner/quality. Implicit false.',
    'reason': 'Motivul atribuit persoanei care ia decizia; rămâne în istoric.',
    'decision': 'Justificarea deciziei. Propunerea și istoricul ei sunt separate de eticheta publicată.',
    'kind': 'Tipul resursei/feedbackului conform enumului declarat.',
    'rating': 'Notă între 1 și 5 sau null; nu este un rezultat al evaluării documentației.',
    'original_text': 'Feedbackul original, disponibil numai producătorului autorizat.',
    'public_text': 'Copia revizuită și aprobată pentru afișare publică; poate fi null.',
    'status': 'Starea moderării: pending, published, restricted sau rejected.',
    'consent': 'true confirmă informarea pentru feedback; false nu este acceptat.',
    'submission_key': 'Cheie stabilă de deduplicare a trimiterii. Refolosește aceeași valoare și același payload după timeout.',
    'status_token': 'Secret returnat numai la prima trimitere; permite citirea stării prin X-Status-Token. Nu-l publica.',
    'text': 'Textul feedbackului; unele sesizări de siguranță sunt reclasificate quality și rămân restricționate.',
    'consent_version': 'Versiunea informării acceptate la trimitere.',
    'mime': 'Tip media identificat din conținutul fișierului pe server.',
    'size_bytes': 'Dimensiunea fișierului în octeți.',
    'provenance': 'Proveniența documentului/importului; câmpurile specifice sursei sunt păstrate.',
    'event_type': 'Tipul evenimentului imuabil al lotului, de exemplu published, released, alert sau alert_resolved.',
    'payload': 'Datele specifice evenimentului sau sistemului partener, conform operației.',
    'actor_id': 'Persoana căreia îi este atribuit evenimentul.',
    'action': 'Numele operației în audit; de exemplu label.published.',
    'object_id': 'ID-ul resursei la care se referă auditul.',
    'details': 'Detaliile deciziei/auditului, păstrate în istoric.',
    'result': 'Rezultatul jobului/importului decodat din JSON; poate fi null înainte de finalizare.',
    'job_type': 'Tipul jobului AI; valoarea acceptată este feedback_triage.',
    'output_audience': 'Audiența rezultatului AI. Jobul nu primește drepturi suplimentare față de contextul autorizat.',
    'attempts': 'Numărul încercărilor de procesare executate de worker.',
    'available_at': 'Momentul UTC de la care workerul poate prelua jobul.',
    'lease_until': 'Termenul UTC al rezervării interne a jobului; nu este un token de acces.',
    'source_sha256': 'Amprenta datelor analizate pentru a identifica schimbarea sursei.',
    'error_code': 'Cod stabil pentru tratarea programatică; textul message poate evolua.',
    'package_sha256': 'Amprenta pachetului importat.',
    'days': 'Valabilitatea cheii în zile: 1–365, implicit 90 la emitere.',
    'scopes': 'Permisiuni cerute. Rolul și apartenența curente limitează suplimentar fiecare cerere.',
    'current_password': 'Parola actuală a contului, numai din browserul propriu. Obligatorie pentru schimbarea parolei/retragerea accesurilor; la emiterea cheilor, dacă autentificarea este mai veche de 15 minute. Nu se jurnalizează.',
    'new_password': 'Parola nouă, minimum 12 și maximum 200 de octeți pe un host Argon2id; maximum 72 de octeți pe un host numai bcrypt. Diacriticele ocupă mai mulți octeți. Nu se trunchiază.',
    'confirmation': 'Repetarea exactă a parolei noi; trebuie să coincidă, inclusiv spații și majuscule.',
    'access_revoked': 'true confirmă revocarea celorlalte sesiuni și a cheilor/granturilor emise de cont. Sesiunea curentă este rotită; folosește noul CSRF.',
    'token': 'Secret opac. Se transmite numai în canalul prevăzut; niciodată în query sau către AI.',
    'expires_at': 'Termenul de expirare în UTC, format SQL.',
    'revoked_at': 'Momentul revocării în UTC sau null; revocarea se aplică cererii următoare.',
    'last_used_at': 'Ultima folosire în UTC, actualizată cel mult o dată la 15 minute.',
    'prefix': 'Prefixul neconfidențial folosit pentru identificarea unei chei în interfață.',
    'rotated_from': 'ID-ul cheii înlocuite, fără secretul acesteia.',
    'revocation_reason': 'Motivul revocării sau null.',
    'href': 'Destinație HTTP(S) publică, fără credențiale; nu poate crea o buclă spre resolver sau administrare.',
    'types': 'Relații GS1/IANA. gs1:pip este normalizat la URI-ul vocabularului GS1.',
    'qualifiers': 'Calificatori GS1 pentru această destinație: 22, 10, 21 sau 235, în combinațiile permise.',
    'is_default': 'Destinație implicită la nivelul calificatorilor. Nu se combină cu metadate opționale de limbă/format/context.',
    'link_key': 'Identitatea stabilă a destinației în istoric; omite la prima versiune.',
    'expected_version': 'Versiunea anterioară cunoscută, obligatorie când actualizezi un link_key existent.',
    'version_no': 'Numărul versiunii destinației; fiecare schimbare adaugă o înregistrare.',
    'is_current': '1 pentru versiunea curentă a destinației, 0 pentru istoric.',
    'scope_sha256': 'Amprenta setului ordonat de calificatori GS1.',
    'target': 'Destinația și metadatele sale aprobabile.',
    'hreflang': 'Limbi BCP 47 ale destinației, de exemplu ro sau en-GB.',
    'context': 'Contexte comerciale publicate pentru selecția destinației.',
    'type': 'Tipul media IANA al destinației.',
    'rights_confirmed': 'true declară drepturile asupra materialului înainte de publicare.',
    'license_confirmed': 'true confirmă alegerea explicită a licenței deschise pentru rețetă.',
    'public_projection_confirmed': 'true confirmă că informațiile selectate sunt aprobate pentru publicare.',
    'dry_run': 'Implicit true: validează și raportează fără import. false cere idempotentă și rol de aprobare.',
    'package': 'Pachet de transfer verificat cu amprentă, relații și versiune recunoscută; datele intră private.',
    'mapping_id': 'Versiunea imutabilă a mapării aparținând conectorului.',
    'mapping': 'Mapare declarativă JSON Pointer; fără expresii executabile, SQL sau scripturi.',
    'event_key': 'Identificator stabil al evenimentului partener; același ID cu alte date produce conflict.',
    'external_id': 'Identitatea articolului/evenimentului în sistemul partener, în spațiul conectorului.',
    'operation': 'product, batch sau receipt; trebuie să corespundă tipului conectorului.',
    'email': 'Adresa persoanei. Nu este un câmp public de produs.',
    'display_name': 'Numele persoanei sau organizației, în contextul specific.',
    'password': 'Parola contului propriu; fluxul este pentru browserul de încredere. Aplicațiile terțe folosesc OAuth.',
    'organization': 'Denumirea organizației create la înscriere.',
    'organization_id': 'Organizația selectată dintre apartenențele utilizatorului.',
    'redirect_uris': 'Callback-uri înregistrate exact, fără wildcard, fragment sau credențiale.',
    'client_id': 'Identificatorul public al aplicației OAuth; nu există client_secret în acest flux.',
    'grant_type': 'authorization_code sau refresh_token; nu este acceptat fluxul password.',
    'redirect_uri': 'Callback identic cu cel înregistrat și folosit la autorizare.',
    'code': 'Codul scanat în endpointurile alimentare; la OAuth, codul de autorizare unic primit la callback.',
    'code_verifier': 'Secret PKCE aleator de 43–128 caractere, păstrat privat până la schimbul codului.',
    'refresh_token': 'Secret de reîmprospătare cu rotație. Reutilizarea unei valori consumate revocă grantul.',
    'scope': 'Permisiuni separate prin spații. Nu combina producător și home:* într-un singur grant.',
    'evidence': 'Referințe la dovezi examinate de administrator privind identitatea, reprezentarea sau mandatul GS1.',
    'device_name': 'Denumire scurtă pentru recunoașterea dispozitivului în lista proprie.',
    'household_id': 'Familia autorizată explicit; un grant nu se extinde automat la alte familii.',
    'operations': 'Maximum 50 mutații locale cu event_id stabil și expected_revision pentru controlul concurenței.',
    'history': 'Maximum 50 evenimente de istoric imuabil; fără preferințe de sănătate sau lista de cumpărături.',
    'document': 'Fișier binar în multipart/form-data, maximum 15.000.000 octeți; MIME verificat pe server.',
}

# Field semantics are maintained explicitly; avoid merely repeating field names.
FIELDS.update({
    '10':'AI (10): codul lotului, codificat o singură dată în segmentul URL.',
    '21':'AI (21): numărul de serie al instanței de produs.',
    '22':'AI (22): varianta produsului pentru consumator.',
    '235':'AI (235): extensia identificatorului administrată de o terță parte.',
    'accepted':'Operațiile confirmate de server; persistă revizia și secvența returnate.',
    'access_token':'Credential OAuth opac, cu durată scurtă. Trimite-l numai în Authorization: Bearer și păstrează-l privat.',
    'active':'Starea activării contului/aplicației. În listările SQL este 0/1; rezultatul suspendării folosește boolean.',
    'added_at':'Momentul adăugării ambalajului în inventar, ISO 8601 cu fus orar.',
    'after':'Valoarea obiectului după evenimentul de istoric; null pentru ștergere.',
    'after_opening_text':'Instrucțiunea textuală aprobată privind păstrarea după deschidere.',
    'ai_required':'false indică faptul că verificarea sau citirea nu necesită un serviciu AI.',
    'alert_scope':'unknown: alertele nu sunt confirmate; product_only: cod generic; batch: verificare pentru lotul identificat.',
    'alerts':'Alertele active la momentul citirii, peste informațiile istorice ale etichetei.',
    'alerts_checked_at':'Momentul ISO al verificării alertelor; null înseamnă că verificarea curentă nu este disponibilă.',
    'allergen_text':'Declarația textuală de alergeni de pe eticheta aprobată; nu este dedusă din rețete private.',
    'amount':'Cantitatea porției sau mărimea exprimată în unitatea declarată, fără conversie implicită g/ml.',
    'arrays':'Mapări de colecții: fiecare element sursă se transformă folosind regulile declarate, maximum două colecții.',
    'op':'Transformare declarativă: copy, text, decimal, integer, gtin, date_iso, date_dmy sau boolean. Nu execută scripturi.',
    'required':'true: valoarea sursă este obligatorie pentru această regulă de mapare; lipsa ei oprește transformarea.',
    'atomic':'true: operațiile noi din lotul de sincronizare sunt aplicate împreună sau deloc la conflict.',
    'audit_events':'Evenimentele istorice de audit exportabile, cu acțiune, obiect, detalii și dată.',
    'auth_type':'Tipul credentialului verificat: api_key sau oauth; nu indică rol de administrator.',
    'authenticated':'Este identificat un principal valid în domeniul de identitate al acestui endpoint.',
    'authorization_url':'Adresă relativă pentru continuarea consimțământului OAuth după conectarea personală.',
    'available':'Funcția este activată și instalată; diferă de starea de conectare a dispozitivului.',
    'basis':'Baza declarației nutriționale: per_100_g, per_100_ml sau per_portion; nu amesteca baze diferite.',
    'batch':'Lotul public identificat, cu starea curentă și datele sale istorice.',
    'batch_events':'Istoricul imuabil al publicărilor, eliberărilor și alertelor loturilor.',
    'batch_state':'Starea lotului recunoscut; null când profilul nu identifică un lot publicat.',
    'batches':'Loturile și legăturile lor la versiunile istorice; rețeta este opțională.',
    'before':'Snapshotul anterior evenimentului de istoric; null la prima adăugare.',
    'canonical':'Rezultatul transformării mapării în formatul OpenAliment, fără efectuarea importului.',
    'carbohydrate_g':'Carbohidrați în grame pentru baza și prepararea declarate; absența valorii nu înseamnă zero.',
    'changes':'Schimbări de inventar și istoric ordonate după secvența serverului, inclusiv tombstones.',
    'checked_at':'Momentul verificării în format ISO 8601; nu este o promisiune privind starea viitoare.',
    'client_type':'public: aplicația nu poate păstra un secret OAuth comun și folosește PKCE.',
    'cloud_sync':'Disponibilitatea sincronizării opționale; inventarul local poate funcționa fără ea.',
    'codes':'Codurile scanate în ordinea cererii; maximum 20, fiecare cu rezultat sau eroare proprie.',
    'conditions':'Condițiile declarate de producător pentru fereastra de utilizare după deschidere.',
    'conflicts':'Operații cu revizie concurentă; citește current și solicită reconciliere înainte de retrimitere.',
    'connected':'Confirmarea conectării acestui dispozitiv după consumarea magic link-ului.',
    'connector_id':'ID-ul conectorului din aceeași organizație; ID-urile externe sunt izolate pe conector.',
    'consumed_at':'Momentul ISO la care ambalajul a fost marcat consumat, sau null.',
    'contact':'Contactul public al operatorului platformei pentru această informare.',
    'contains':'Codurile alergenilor declarați ca prezenți. O listă goală cu status=unknown nu este o declarație de absență.',
    'content_base64':'Octeții documentului codificați base64 în export; null dacă fișierul nu este disponibil.',
    'cost':'Stadiul scenariului de cost importat; COST3 este o estimare, nu rezultat economic validat.',
    'counts':'Numărul înregistrărilor per resursă, nu obiectele complete.',
    'csrf':'Tokenul sesiunii web, păstrat în memorie și trimis în antetul CSRF potrivit domeniului de identitate.',
    'current':'Snapshotul/revizia curentă de pe server folosită pentru rezolvarea conflictului.',
    'cursor':'Cursor de continuare; colecțiile producătorului folosesc ID, iar Acasă o secvență numerică.',
    'data':'Conținutul structurat al proiecției, snapshotului de lot sau pachetului, conform schemei operației.',
    'date_ocr':'Resursele OCR locale sunt disponibile; nu garantează citirea corectă a oricărei fotografii.',
    'dates':'Date GS1 recunoscute în cod, cu tip și precizie păstrate.',
    'deleted':'Tombstone: resursa a fost eliminată din inventarul sincronizat, fără ștergerea istoricului imuabil.',
    'destination_url':'Destinația propusă pentru migrarea unei adrese; activarea sa cere verificările aplicabile.',
    'details_json':'JSON serializat al detaliilor istorice; parsează explicit șirul.',
    'devices':'Dispozitivele proprii active, fără secretul cookie-ului; un grant OAuth vede numai dispozitivul aplicației.',
    'digital_link':'true când URL-ul generat folosește sintaxa GS1 eligibilă; false pentru URL nativ persistent.',
    'digital_receipts':'Disponibilitatea funcției de bonuri digitale pe această instalare.',
    'documents':'Documente autorizate în contextul cererii; proiecția publică include numai materiale aprobate.',
    'download_url':'Adresa de descărcare. Documentele private cer în continuare autentificare și apartenență.',
    'email_verified':'0/1: contactul contului a fost verificat separat; necesar participării prin vot.',
    'enabled':'Funcția este permisă prin configurația instalării, fără expunerea credentialelor sale.',
    'energy_kcal':'Energie în kilocalorii pentru baza nutrițională declarată.',
    'energy_kj':'Energie în kilojouli pentru baza nutrițională declarată.',
    'error':'Eroare individuală de lookup sau cod protocolar OAuth, conform formei răspunsului.',
    'error_description':'Explicația erorii OAuth pentru afișare; folosește error pentru tratare programatică.',
    'event_id':'ID aleator stabil al operației locale. Retrimite-l cu aceleași date după timeout.',
    'examples':'Valori ilustrative ale vocabularului, fără a reprezenta destinații înregistrate automat.',
    'expected_revision':'Revizia serverului pe care o modifici; 0 la prima creare. O diferență produce conflict, nu suprascriere.',
    'expires_in':'Secunde rămase până la expirarea access tokenului; maximum 900 în acest flux.',
    'expires_in_days':'Valabilitatea linkului-capability în zile, de la emitere.',
    'expiry':'Termenul declarat/observat, cu tip, precizie și proveniență. null nu înseamnă termen nelimitat.',
    'exported_at':'Momentul exportului, ISO 8601, cu fus orar.',
    'external_sources':'false: platforma nu interoghează automat baze externe pentru produse necunoscute.',
    'fat_g':'Grăsimi în grame pentru baza și prepararea declarate.',
    'feedback':'Copiile moderate exportabile ale feedbackului; originalele private și secretele de urmărire sunt excluse.',
    'feedback_id':'ID-ul feedbackului asociat propunerii sau jobului de analiză.',
    'fibre_g':'Fibre în grame pentru baza și prepararea declarate.',
    'fields':'Reguli declarative sau nume de câmpuri acceptate, conform schemei și vocabularului operației.',
    'formula':'Formula tehnică potrivit profilului de categorie ales; poate rămâne privată.',
    'from':'JSON Pointer către câmpul sursă din payload; exclusiv cu value în aceeași regulă.',
    'has_more':'Există o pagină următoare; continuă cu cursorul corespunzător și aceleași filtre.',
    'history_accepted':'ID-urile evenimentelor de istoric confirmate; pot include reluări deja acceptate.',
    'hosting_requires_open_license':'false: simpla găzduire nu obligă la publicarea sub licență deschisă.',
    'hours':'Fereastră declarată după deschidere, în ore, valabilă numai cu conditions respectate.',
    'household_name':'Denumirea familiei selectate în această sesiune sau autorizare.',
    'identity':'Interpretarea sintactică a codului, fără verificarea mandatului de administrare.',
    'identity_notice':'Informare curentă despre mandat contestat/retras, păstrând accesul la istoric și alerte.',
    'identity_status':'Starea verificării identității organizației, independentă de GTIN și siglă.',
    'imported':'Ambalajul provine dintr-un import local; nu este dovadă de verificare a produsului.',
    'imports':'Istoricul pachetelor importate, cu proveniență și amprente; conținut privat în export.',
    'integration_bindings':'Asocierile între external_id și ID-urile interne, izolate pe conector.',
    'integration_connectors':'Conectorii organizației, fără chei sau credentiale de partener.',
    'integration_mappings':'Versiunile imutabile ale mapărilor declarative, cu amprente.',
    'is_admin':'0/1 în profilul contului web; Bearer nu acordă administrarea platformei.',
    'issued_at':'Momentul emiterii bonului, ISO 8601, cu fus orar.',
    'item':'Snapshotul ambalajului din inventar; null pentru tombstone.',
    'item_id':'ID-ul ambalajului personal, distinct de trade_item_id al producătorului.',
    'items':'Elementele colecției, în forma și ordinea declarate de operație.',
    'job_types':'Tipurile de analiză acceptate de această versiune, fără execuție AI în cererea HTTP.',
    'jurisdiction':'Regimul declarației nutriționale: EU, US sau other; păstrează-l la afișare și calcule.',
    'jurisdictions':'Valorile acceptate pentru jurisdicția declarației nutriționale.',
    'label':'Informațiile publicabile ale versiunii de etichetă aplicabile, distincte de rețetă.',
    'label_version_key':'Versiunea exactă deja publicată în articolul asociat conectorului, nu eticheta implicită presupusă.',
    'limit':'Mărimea paginii aplicată de server; nu numărul total al înregistrărilor.',
    'line_id':'Identitatea stabilă a liniei bonului în același bon.',
    'lines':'Liniile bonului. quantity poate reprezenta mai multe ambalaje fizice; nu dubla importul prin reluare.',
    'local_object_id':'ID-ul intern asociat adresei importate; nu conferă acces la altă organizație.',
    'local_only':'Categorii de date personale care rămân numai pe dispozitiv în această versiune.',
    'location':'Locul ales de persoană: home, fridge, freezer sau pantry.',
    'magic_link_ready':'Transportul de email pentru legătura personală este configurat; nu confirmă livrarea unui mesaj anume.',
    'mapping_json':'Maparea istorică păstrată ca șir JSON în transfer sau compatibilitate.',
    'may_contain':'Codurile alergenilor declarați ca posibile urme; distincte de contains.',
    'merchant':'Denumirea comerciantului din bonul digital confidențial.',
    'method':'Metoda de obținere a documentului, de exemplu upload sau import.',
    'namespace':'Spațiul de nume al relațiilor GS1 din vocabular.',
    'native':'Referință la o pagină OpenAliment t/p/b și ID-ul ei, dacă adresa este locală.',
    'native_url':'Adresa OpenAliment persistentă de produs sau lot; disponibilă și fără GTIN verificat.',
    'next_cursor':'ID-ul exclusiv pentru pagina următoare; null la final. Nu este un offset numeric.',
    'next_external_id':'Cursorul exclusiv al asocierilor de partener; trimite-l în external_id, nu cursor.',
    'nominal_input_mass_g':'Masa nominală a intrărilor rețetei pilot R1, în grame; nu randament măsurat.',
    'notices':'Informări despre limite, diferențe între cod și lot, simulare sau starea produsului.',
    'nutrition_bases':'Bazele nutriționale acceptate pentru normalizarea cantităților.',
    'nutrition_text':'Declarația nutrițională textuală aprobată; pentru calcule folosește food_data.',
    'gs1_qualifiers':'Calificatorii observați în URI, decodificați o singură dată: 22 CPV, 10 lot, 21 serie sau 235 TPX. Nu reprezintă o aprobare ori dovada achiziției.',
    'observed_dates':'Toate datele recunoscute în cod, cu tip și precizie, inclusiv când diferă de lot.',
    'opened_at':'Momentul ISO la care ambalajul a fost marcat deschis, sau null.',
    'operator_text':'Textul public al informării de confidențialitate configurat de operator.',
    'optional_sync':'Categorii de date care pot fi sincronizate numai după conectarea voluntară.',
    'original_name':'Numele original al fișierului încărcat, păstrat ca proveniență; nu este cale de acces pe server.',
    'originals':'Numărul documentelor originale din pachetul pilot, inclusiv proveniența lor.',
    'payload_json':'Pachetul sau evenimentul original serializat; conținut privat de proveniență.',
    'pending_feedback':'Numărul sesizărilor aflate în așteptarea moderării organizației.',
    'photo_id':'ID-ul fotografiei private a familiei; metadatele nu publică fotografia.',
    'polyols_g':'Polioli în grame pentru baza și prepararea declarate.',
    'portion':'Cantitatea și unitatea porției declarate; necesară interpretării valorilor per_portion.',
    'precision':'Precizia termenului: day, month sau year. Nu inventa o zi când este cunoscută numai luna.',
    'preparation':'as_sold pentru alimentul vândut, as_prepared pentru forma preparată descrisă.',
    'previous_revoked':'true confirmă revocarea imediată a cheii înlocuite la rotație.',
    'previous_id':'ID-ul cheii înlocuite; nu include secretul anterior.',
    'process':'Procesul tehnologic al versiunii, cu stadiul real al încercărilor și rezultatelor.',
    'producer':'Denumirea publică a organizației producătoare; în filtrul producer se trimite ID-ul, nu această denumire.',
    'product_info_versions':'Versiunile istorice imutabile ale informațiilor de etichetă.',
    'products':'Produsele organizației, cu categoria și proveniența lor.',
    'profile':'Proiecția alimentară păstrată cu ambalajul personal; nu include rețete private.',
    'profile_url':'Pagina publică a profilului și limitelor implementării resolverului.',
    'protein_g':'Proteine în grame pentru baza și prepararea declarate.',
    'provenance_json':'Proveniența documentului ca șir JSON în transfer sau v1.',
    'public_context_only':'true: analiza AI folosește numai contextul public moderat, fără rețete private.',
    'public_url':'Pagina publică asociată produsului/lotului; null când nu există date publicate.',
    'publication':'Starea de publicare rezultată sau cerința de aprobare separată; importul nu publică automat.',
    'qr_depends_on_ai':'false: citirea și rezolvarea QR nu necesită AI.',
    'qualifiers_json':'Calificatorii GS1 serializați în istoric/transfer; v2 îi decodează ca qualifiers.',
    'quantity':'Cantitatea personală sau de pe bon în unitatea declarată; pack cere număr întreg în bon.',
    'quarantine':'Identitatea materialului deteriorat păstrat în carantină; nu îl prezenta drept document validat.',
    'raw':'Textul original al datei observate, păstrat separat de valoarea normalizată.',
    'realm':'Domeniul identității și autorizării; contul producătorului și Acasă sunt separate.',
    'receipt':'Proveniența ambalajului din bon: bon, linie și indexul unității fizice.',
    'receipt_external_id':'Identitatea bonului în sistemul comerciantului/conectorului.',
    'receipt_id':'ID-ul intern al bonului confidențial, distinct de tokenul-capability.',
    'receipt_url':'Legătură confidențială cu token în fragment. Permite preview până la expirare; nu o publica.',
    'recipe_identity':'Referința istorică a rețetei pilot, pbt:R1.',
    'recipe_versions':'Documentația tehnică versionată, cu vizibilitate, licență și stadiu experimental păstrate.',
    'recorded':'true: decizia a fost atribuită administratorului și consemnată în istoric.',
    'recovered':'Numărul materialelor recuperate, distincte de originalele intacte.',
    'redirect_uris_json':'Lista callback-urilor ca șir JSON în listarea aplicațiilor; parsează explicit.',
    'refresh_expires_in':'Secunde rămase până la expirarea refresh tokenului; nu depășește durata grantului.',
    'registration_enabled':'Înscrierile de conturi/organizații sunt permise de operator în această instalare.',
    'removed':'Confirmarea eliminării editorului; poate fi true și dacă persoana nu mai era membru.',
    'request_id':'ID de corelare al cererii pentru suport; nu este credential sau ID de produs.',
    'request_sha256':'Amprenta corpului evenimentului folosită pentru detectarea reluărilor cu alte date.',
    'resolver_level':'Nivelul implementării declarat de platformă; verifică profilul și probele, fără a deduce o certificare externă.',
    'resolver_links':'Istoricul destinațiilor resolverului, fără transfer automat al dreptului GS1.',
    'resources':'Consum și limite pe tip de resursă, în organizația autentificată.',
    'result_json':'Rezultatul istoric serializat; v2 îl decodează ca result unde este documentat.',
    'revision':'Revizia locală a snapshotului de inventar; distinctă de server_revision și versiunea etichetei.',
    'revoked':'true: credentialul, dispozitivul sau grantul nu mai poate autoriza o cerere nouă.',
    'rights_verified':'false la interpretarea sintactică; parserul nu confirmă mandatul organizației asupra codului.',
    'role':'Rolul curent în organizație/familie. Un scope nu poate depăși drepturile rolului.',
    'salt_g':'Sare în grame pentru baza și prepararea declarate.',
    'saturates_g':'Acizi grași saturați în grame pentru baza și prepararea declarate.',
    'schema_version':'Identifică familia și versiunea formei JSON; verifică valoarea înainte de import sau sincronizare.',
    'scopes_json':'Permisiunile autorizării/aplicației ca șir JSON în listare; nu reprezintă secrete.',
    'secret_available':'true numai la prima livrare a cheii. La reluare este false și token lipsește.',
    'seq':'Secvența monotonă atribuită de server în familia respectivă; folosită pentru sincronizare, nu timpul dispozitivului.',
    'server_revision':'Revizia autoritară a obiectului pe server, folosită ca expected_revision la următoarea modificare.',
    'session':'Principalul personal al familiei selectate, sau null dacă dispozitivul nu este conectat.',
    'sha256':'Amprenta SHA-256 a documentului, mapării sau datelor indicate; păstrează octeții și regulile de calcul.',
    'shopping_sync':'false: lista de cumpărături rămâne locală în această versiune.',
    'simulation':'true indică explicit exemplu/simulare, fără validare comercială, nutrițională sau POS implicită.',
    'source':'Proveniența informației și momentul verificării, ori sursa declarată a datei observate.',
    'source_base_url':'Originea furnizorului de date exportate, folosită pentru proveniență și migrarea adreselor.',
    'source_url':'Adresa istorică din pachetul importat; nu devine automat o redirecționare activă.',
    'storage_bytes':'Spațiul de documente în octeți, cu used și limit.',
    'storage_text':'Condițiile de păstrare din eticheta publicată.',
    'sugars_g':'Zaharuri în grame pentru baza și prepararea declarate; distincte de carbohidrații totali.',
    'sync_scope':'Categoriile de date admise în sincronizarea opțională.',
    'target_json':'Destinația și metadatele resolverului ca șir JSON în istoric/transfer.',
    'to':'JSON Pointer al colecției destinație în forma canonică a mapării.',
    'token_endpoint_auth_method':'none pentru aplicații publice PKCE; nu se emite client_secret.',
    'token_type':'Bearer: trimite access_token în antetul Authorization, nu în query.',
    'trade_external_id':'Referința articolului din sistemul partener, rezolvată numai prin asocierile acestui conector.',
    'trade_items':'Articolele comerciale ale produselor; identificatorii necesită reverificare după import.',
    'trade_name':'Denumirea internă a articolului comercial importat, distinctă de numele public de pe etichetă.',
    'undoes':'ID-ul evenimentului anterior anulat explicit de evenimentul undo.',
    'undone_at':'Momentul ISO al anulării din istoricul local, sau null.',
    'unit':'Unitatea cantității: pack, g sau ml; nu converti masa în volum fără informații suplimentare.',
    'unit_index':'Index de la zero al ambalajului fizic provenit din aceeași linie de bon.',
    'updated_at':'Momentul actualizării snapshotului, ISO 8601; conflictele se rezolvă prin revizii, nu prin acest ceas.',
    'url':'Adresa asociată resursei. Drepturile de acces sunt cele ale destinației, nu ale simplului link.',
    'url_mappings':'Asocierile adreselor istorice importate, păstrate pentru migrare și verificare.',
    'used':'Volumul deja ocupat/consumat în unitatea resursei, nu limita disponibilă.',
    'user':'Profilul contului producătorului din sesiunea proprie, fără parola sau hashul acesteia.',
    'valid':'Validarea descrisă de operație a reușit; nu echivalează cu verificare GS1, siglă sau validare medicală.',
    'value':'Valoare literală de mapare sau valoarea normalizată a datei, conform structurii. Maparea folosește value exclusiv cu from.',
    'values':'Valorile nutriționale explicite, în unitățile numelor de câmp și pentru baza declarată; câmpurile lipsă rămân necunoscute.',
    'verification':'Starea reverificării după import; drepturile și aprobările nu se transferă automat.',
    'version':'Versiunea sursei publicate sau a informării; distinctă de ID-ul intern.',
    'vocabulary_url':'Adresa vocabularului oficial al relațiilor GS1.',
    'voted':'true după PUT de susținere, false după DELETE; un cont are cel mult un vot pe propunere.',
    'votes':'Numărul curent al susținerilor propunerii, fără identitatea votanților.',
    'writes':'false la validare/preview: operația nu creează și nu publică date de domeniu.',
})
for code,label in {'gluten':'cereale care conțin gluten','crustaceans':'crustacee','eggs':'ouă','fish':'pește','peanuts':'arahide','soy':'soia','milk':'lapte','nuts':'fructe cu coajă lemnoasă','celery':'țelină','mustard':'muștar','sesame':'susan','sulphites':'dioxid de sulf și sulfiți','lupin':'lupin','molluscs':'moluște'}.items():
    FIELDS[code]='Eticheta română pentru codul standard de alergen: '+label+'.'
FIELDS.update({
    'access_token_seconds':'Durata maximă a access tokenului, în secunde.',
    'agent_use':'Regulile de integrare pentru agenți AI, cu acces explicit la contracte și delegare pentru date private.',
    'anchor':'Identificatorul GS1 Digital Link pentru care se publică setul de relații.',
    'api_keys':'Mecanismul cheilor ERP, cu scopes, locul emiterii și posibilitatea revocării.',
    'authentication':'Mecanismele și disponibilitatea autentificării pe această instalare.',
    'authorization_endpoint':'Adresa pentru consimțământul OAuth deschisă în browserul sistemului.',
    'badge_assessment_api':'Disponibilitatea API-ului de evaluare pentru siglă; false în versiunea curentă.',
    'base_url':'Originea HTTPS a instalării, fără credentiale sau parametri.',
    'batch_recipe_optional':'true: lotul și serviciul QR nu cer rețetă înregistrată.',
    'capabilities':'Funcțiile efectiv declarate de instalare; verifică valorile înainte de utilizare.',
    'catalog_per_minute':'Bugetul implicit al căutării/catalogului, per IP și minut.',
    'change_proposals':'Propunerile istorice de schimbare, distincte de versiunile aprobate.',
    'code_challenge_methods_supported':'Metode PKCE acceptate; numai S256.',
    'compatibility':'Garanțiile de compatibilitate și separare a modulelor declarate de instalare.',
    'consumer_api':'Disponibilitatea proiecțiilor alimentare pentru aplicațiile consumatorilor.',
    'consumer_oauth':'Disponibilitatea autorizării delegate pentru o familie Acasă.',
    'contexts':'Valorile de context prezente în destinațiile curente publicate.',
    'crawling_required':'false: contractele pot fi citite explicit fără crawling HTML.',
    'custom_domains_api':'Disponibilitatea administrării domeniilor proprii prin API; false în această versiune.',
    'documentation':'Adresa portalului public de documentație.',
    'documentation_index':'Indexul JSON al paginilor și versiunilor Markdown.',
    'extensionProfile':'Pagina profilului implementat, cu identificatori, funcții și limite testate.',
    'external_product_sources':'Disponibilitatea interogării bazelor externe pentru produse necunoscute; false.',
    'feedback_per_ip_per_hour':'Numărul maxim implicit de trimiteri de feedback per IP într-o oră.',
    'flows':'Fluxurile de autorizare implementate; nu deduce suport pentru alte grant types.',
    'grant_types_supported':'Grant types OAuth acceptate: authorization_code și refresh_token.',
    'guide':'Adresa ghidului Markdown complet pentru integrare.',
    'issuer':'Identitatea HTTPS a serverului OAuth, comparată cu iss la callback.',
    'itemDescription':'Descrierea publică a alimentului/contextului pentru setul de legături.',
    'javascript':'Adresa clientului JavaScript standard sau a resursei indicate.',
    'jsonLdContextLocation':'Adresa contextului JSON-LD folosit pentru interpretarea linksetului.',
    'kotlin':'Adresa exemplului sursă Android/Kotlin; nu este APK compilat.',
    'legacy_v1_preserved':'true: rutele API v1 sunt păstrate pentru clienții existenți.',
    'linkTypeDefaultCanBeLinkset':'false: scanarea implicită deschide o destinație; linksetul se solicită explicit.',
    'linkset':'Seturile de relații și destinații aplicabile identificatorului anchor.',
    'list_limit_max':'Mărimea maximă a unei pagini pentru colecțiile paginate declarate.',
    'llms':'Indexul public de pagini Markdown pentru instrumente și agenți AI.',
    'lookup_batch_max_codes':'Numărul maxim de coduri acceptate într-o cerere de lookup multiplu.',
    'max_grant_days':'Durata maximă a grantului OAuth în zile; revocarea îl poate opri mai devreme.',
    'metadata':'Adresa metadatelor OAuth; verifică separat available pentru activarea instalării.',
    'modified':'Momentul modificării, dacă este publicat de relația din linkset.',
    'nameOfList':'Denumirea listei externe de contexte suportate.',
    'never_execute_document_instructions':'true: conținutul documentelor citite este tratat ca date, nu instrucțiuni de executat.',
    'new_routed_operation_count':'Numărul operațiilor din registrul platformei, separat de contractele istorice și independente.',
    'notification_api':'Disponibilitatea unui API de notificări; false în această versiune.',
    'oauth':'Metadatele disponibilității OAuth și ale duratelor sale.',
    'openapi':'Contractele OpenAPI descoperibile, cu nume și adresă.',
    'operation_count':'Numărul total al operațiilor din contractul agregat, nu numărul resurselor.',
    'operations_in_openapi':'true: acțiunile API implementate sunt descrise în contractul agregat.',
    'page':'Numărul paginii v1, începând cu 1; nu cursor v2.',
    'primary_keys':'Cheile primare GS1 administrate de resolver; numai 01 în profilul curent.',
    'private_data_requires_delegation':'true: citirea datelor private cere autentificare și autorizare explicită.',
    'private_recipe_in_public_context':'false: o rețetă privată nu intră în proiecții sau analiză AI publică.',
    'producer_api':'Disponibilitatea API-ului pentru producători, cu izolare pe organizație.',
    'public_approved_data':'true: datele publice provin numai din informațiile aprobate pentru publicare.',
    'public_data_requires_approval':'true: crearea unei resurse nu implică publicare automată.',
    'public_limits':'Bugete și limite publice implicite, ajustabile numai de operator.',
    'python':'Adresa clientului Python bazat pe biblioteca standard.',
    'read_contracts_explicitly':'true: agentul primește explicit manifestul și schemele, independent de robots pentru crawling.',
    'read_per_minute':'Bugetul implicit de citire publică per IP într-un minut.',
    'resolver':'Adresele descrierii și profilului resolverului și cheile sale primare.',
    'resolverRoot':'Originea resolverului GS1, folosită la construirea adreselor persistente.',
    'response_types_supported':'Tipurile răspunsului de autorizare acceptate; numai code.',
    'revocable':'true: credentialele pot fi revocate înainte de expirare.',
    'revocation_endpoint':'Adresa POST pentru revocarea unui token/grant OAuth.',
    'schema':'Adresa unei scheme sau prefixul schema.org din contextul JSON-LD, potrivit formei declarate.',
    'schemas':'Schemele JSON descoperibile, cu adresă și amprentă a fișierului.',
    'scheme':'Mecanismul de transmitere a credentialului; bearer folosește antetul Authorization.',
    'scopes_supported':'Permisiunile acceptate de serverul OAuth; rolul și consimțământul limitează suplimentar accesul.',
    'sdk':'Clienții minimali descărcabili, fără credentiale incluse.',
    'supportedContextValuesExternal':'Liste externe ale valorilor context disponibile pentru selecția destinațiilor.',
    'supportedLinkType':'Prefixele și spațiile de nume ale relațiilor suportate.',
    'supportedPrimaryKeys':'Cheile primare GS1 recunoscute de profilul resolverului.',
    'token_endpoint':'Adresa POST pentru schimbul codului PKCE și rotația refresh tokenului.',
    'token_endpoint_auth_methods_supported':'none pentru aplicațiile publice; nu se folosește client_secret.',
    'unknown_is_not_zero':'true: absența informației nu se transformă în zero sau declarație de siguranță.',
})
for term in ['@container','@context','@id','@protected','@type','dcterms','gs1','gs1:value','owl','rdf','rdfs','skos','title*','xsd']:
    FIELDS[term]='Termen sau prefix din contextul JSON-LD publicat pentru linkset; interpretarea urmează contextul de la /resolver/linkset-context.'


def enrich(spec, routes, output):
    schemas = spec['components']['schemas']
    registry = {(r['method'].lower(), r['path']): r for r in routes}
    samples = {}

    def add(name, schema, example=None):
        schemas[name] = copy.deepcopy(schema)
        if example is not None:
            samples[name] = copy.deepcopy(example)
        return ref(name)

    from readiness_reference import install
    readiness_metadata = install(add, FIELDS, obj, arr, nullable, ref, S, B, N, ID, DATE)
    from marketplace_reference import install as install_marketplace
    marketplace_metadata=install_marketplace(add,FIELDS,obj,arr,nullable,ref,S,B,N,ID,DATE)

    def local_schema(filename, pointer=''):
        root = json.loads((output / filename).read_text(encoding='utf-8'))
        def walk(node):
            if isinstance(node, list):
                return [walk(x) for x in node]
            if not isinstance(node, dict):
                return node
            if '$ref' in node and not node['$ref'].startswith('#/components/'):
                file, _, fragment = node['$ref'].partition('#')
                target = root if not file else json.loads((output / Path(file).name).read_text(encoding='utf-8'))
                for key in fragment.lstrip('/').split('/') if fragment else []:
                    target = target[key]
                return walk(target)
            return {k: walk(v) for k, v in node.items() if k not in ['$schema', '$id', '$defs']}
        target = root
        for key in pointer.split('/') if pointer else []:
            target = target[key]
        return walk(target)

    def annotate(schema, prefix=''):
        if not isinstance(schema, dict):
            return
        for key, value in schema.get('properties', {}).items():
            value.setdefault('description', FIELDS.get(key, 'Valoarea câmpului ' + key + ', conform structurii și limitelor declarate.'))
            if value.get('format')=='date-time':value['description']='Moment ISO 8601 cu fus orar explicit; păstrează offset-ul sau convertește explicit la UTC.'
            if key=='allergens' and value.get('type')=='object':value['description']='Declarația structurată a alergenilor: status, prezență și posibile urme. unknown nu înseamnă absență.'
            if key=='nutrition' and ('oneOf' in value or value.get('type')=='object'):value['description']='Declarația nutrițională structurată, cu status, bază, preparare, jurisdicție și valori explicite.'
            if key=='after_opening' and ('anyOf' in value or value.get('type')=='object'):value['description']='Fereastra de utilizare declarată după deschidere, în ore și cu condiții; null înseamnă necunoscut.'
            if key=='type' and value.get('enum')==['add','open','consume','edit','undo']:value['description']='Acțiunea din istoricul inventarului: adăugare, deschidere, consum, editare sau anulare.'
            if key=='state' and value.get('enum')==['available','consumed']:value['description']='Starea ambalajului personal: disponibil sau marcat consumat; distinctă de starea lotului producătorului.'
            if key=='kind' and value.get('enum')==['use_by','best_before','reminder']:value['description']='Tipul datei: termen de consum, durabilitate minimală sau simplu memento. reminder nu este termen declarat.'
            if key=='kind' and value.get('const') in ['put','delete']:value['description']='Tipul mutației de inventar: put salvează snapshotul, delete creează tombstone.'
            if key=='value' and value.get('pattern','').startswith('^[0-9]{4}'):value['description']='Data normalizată în forma YYYY, YYYY-MM sau YYYY-MM-DD, potrivit preciziei; nu completa artificial ziua.'
            annotate(value, prefix + key + '.')
        for keyword in ['oneOf', 'anyOf', 'allOf']:
            for branch in schema.get(keyword, []):
                annotate(branch, prefix)
        if 'items' in schema:
            annotate(schema['items'], prefix + '[]')
        if isinstance(schema.get('additionalProperties'),dict):
            annotate(schema['additionalProperties'],prefix+'*')

    # Preserve the request contracts: these are also used by runtime validation.
    for schema in schemas.values():
        annotate(schema)
    label = copy.deepcopy(schemas['Label'])
    label.pop('title', None)
    label['properties'].pop('version_key')
    label['required'].remove('version_key')
    add('LabelSnapshot', label)
    add('BatchSnapshot', obj({k: copy.deepcopy(schemas['Batch']['properties'][k]) for k in ['production_date', 'best_before', 'use_by', 'public_notes']}))
    add('FoodData', local_schema('food-data.schema.json'))
    add('FoodDate', local_schema('food-date.schema.json'))
    add('RecipeSnapshot', obj({'formula': {'type': ['array', 'object'], 'description': 'Formula tehnică, structurată potrivit profilului de categorie ales.'}, 'process': {'type': ['array', 'object'], 'description': 'Procesul tehnologic al acestei versiuni; cerințele de panificație sunt opționale pentru alte categorii.'}}, extra=True))
    provenance = obj({'method': S(100), 'original_name': S(180), 'status': S(100), 'source': S()}, [], True)
    data_object = {'type': ['object', 'array'], 'description': 'Date specifice tipului de eveniment sau provenienței; un obiect PHP gol poate fi serializat ca [].', 'additionalProperties': True}
    # SQL-derived metadata mirrors SELECT *; response projections are explicit below.
    tables = {}
    for migration in sorted((ROOT / 'database/migrations').glob('*.sql')):
        for match in re.finditer(r'CREATE TABLE IF NOT EXISTS (\w+)\s*\((.*?)\) ENGINE=', migration.read_text(encoding='utf-8'), re.S):
            fields = {}
            for column in re.split(r',\s*(?![^()]*\))', match[2]):
                m = re.match(r'\s*(\w+)\s+(CHAR|VARCHAR|LONGTEXT|MEDIUMTEXT|TEXT|DATETIME|TINYINT|BIGINT|INT)\b(?:\((\d+)\))?(.*)', column, re.S | re.I)
                if not m:
                    continue
                name, kind, length, tail = m.groups()
                field = copy.deepcopy(ID if kind == 'CHAR' and length == '32' else SHA if name.endswith('sha256') or name == 'sha256' else DATE if kind == 'DATETIME' else N if kind in ['TINYINT', 'BIGINT', 'INT'] else S(int(length) if length else 24000000))
                field['description'] = FIELDS.get(name, 'Câmpul ' + name + ' al resursei autorizate.')
                if 'NOT NULL' not in tail and 'PRIMARY KEY' not in tail:
                    field = nullable(field)
                fields[name] = field
            tables[match[1]] = fields

    decoded = {'snapshot_json': 'snapshot', 'result_json': 'result', 'details_json': 'details', 'payload_json': 'payload', 'provenance_json': 'provenance', 'target_json': 'target', 'qualifiers_json': 'qualifiers', 'mapping_json': 'mapping'}
    removed = {'storage_key', 'contact_token_hash', 'request_key', 'request_hash', 'lease_token'}
    resource_tables = {'products': 'products', 'trade-items': 'trade_items', 'labels': 'product_info_versions', 'batches': 'batches', 'recipes': 'recipe_versions', 'documents': 'documents', 'feedback': 'feedback', 'proposals': 'change_proposals', 'imports': 'imports', 'audit-events': 'audit_events', 'ai-jobs': 'ai_jobs', 'resolver-links': 'resolver_links', 'events': 'batch_events', 'alerts': 'alerts'}
    names = {}

    def table_schema(table, columns=None, raw=False):
        fields = copy.deepcopy(tables[table])
        if columns:
            fields = {k: fields[k] for k in columns}
        if not raw:
            for name in removed:
                fields.pop(name, None)
            for old, new in decoded.items():
                if old in fields:
                    fields.pop(old)
                    value = ref('LabelSnapshot' if table == 'product_info_versions' else 'BatchSnapshot' if table == 'batches' else 'RecipeSnapshot') if new == 'snapshot' else provenance if new == 'provenance' else data_object
                    fields[new] = nullable(value) if new == 'result' and table == 'ai_jobs' else copy.deepcopy(value)
        else:
            fields.pop('storage_key', None)
        return obj(fields)

    for resource, table in resource_tables.items():
        name = 'Producer' + ''.join(x.title() for x in resource.split('-'))
        value = table_schema(table)
        if table == 'imports':
            value['properties'].pop('payload', None)
            value['required'].remove('payload')
        names[resource] = name
        add(name, value)
    summaries = {
        'imports': ['id', 'source_key', 'package_sha256', 'result_json', 'created_at'],
        'ai-jobs': ['id', 'feedback_id', 'job_type', 'output_audience', 'state', 'attempts', 'available_at', 'source_sha256', 'error_code', 'created_at'],
        'documents': ['id', 'product_id', 'title', 'sha256', 'mime', 'size_bytes', 'visibility', 'rights_status', 'license_id', 'provenance_json', 'created_at'],
    }
    for resource, columns in summaries.items():
        add(names[resource] + 'Summary', table_schema(resource_tables[resource], columns))
    add('AuditDecision', table_schema('audit_events', ['id', 'action', 'actor_id', 'details_json', 'created_at']))
    add('AiResultSummary', table_schema('ai_jobs', ['id', 'job_type', 'state', 'result_json', 'source_sha256', 'error_code', 'created_at']))
    for resource in ['products', 'trade-items', 'labels', 'batches', 'recipes', 'documents']:
        add('Legacy' + names[resource], table_schema(resource_tables[resource], raw=True))

    def collection(item, cursor=True):
        return obj({'items': item if item.get('type') == 'array' else arr(item), **({'next_cursor': nullable(ID), 'has_more': B, 'limit': {'type': 'integer', 'minimum': 1, 'maximum': 100}} if cursor else {})})

    def page(name, cursor=True):
        target = name + ('Page' if cursor else 'List')
        if target not in schemas:
            add(target, collection(ref(name), cursor))
        return ref(target)

    add('IdResult', obj({'id': ID}))
    for name, fields in {
        'TradeCreated': {'id': ID, 'gtin14': nullable({'type': 'string', 'pattern': '^[0-9]{14}$'}), 'gtin_status': {'const': 'unverified'}},
        'LabelCreated': {'id': ID, 'state': {'const': 'draft'}},
        'LabelPublished': {'id': ID, 'state': {'const': 'published'}},
        'BatchCreated': {'id': ID, 'recipe_version_id': nullable(ID), 'visibility': {'const': 'private'}},
        'BatchPublished': {'id': ID, 'visibility': {'const': 'public'}},
        'BatchReleased': {'id': ID, 'state': {'const': 'released'}},
        'ResolverDrafted': {'id': ID, 'link_key': ID, 'version_no': {'type': 'integer', 'minimum': 1}, 'state': {'const': 'draft'}},
        'ResolverPublished': {'id': ID, 'state': {'const': 'published'}},
        'ResolverWithdrawn': {'id': ID, 'state': {'const': 'withdrawn'}},
        'FeedbackModerated': {'id': ID, 'status': {'type': 'string', 'enum': ['published', 'restricted', 'rejected']}},
        'DocumentUploaded': {'id': ID, 'visibility': {'const': 'private'}, 'scan': {'enum':['pending','legacy_unverified']}},
        'AiQueued': {'id': ID, 'state': S(24)},
        'Revoked': {'revoked': {'const': True}},
        'RevokedId': {'id': ID, 'revoked': {'const': True}},
        'VoteResult': {'proposal_id': ID, 'voted': B, 'mutation_version': nullable(N), 'state': {'enum':['active','withdrawn']}},
        'AdminDecision': {'id': ID, 'recorded': {'const': True}},
        'QrResult': {'kind': {'enum': ['item', 'batch']}, 'url': {'type': 'string', 'format': 'uri'}, 'native_url': {'type': 'string', 'format': 'uri'}, 'digital_link': B, 'reason': nullable(S()), 'resolver_level': S(80)},
    }.items():
        add(name, obj(fields, [k for k in fields if k not in ({'state'} if name=='VoteResult' else set())]))
    add('FeedbackSubmitted', obj({'id': ID, 'status': S(24), 'status_token': {**S(64), 'description': FIELDS['status_token']}}, ['id', 'status']))
    add('PublicAlert', obj({'id': ID, 'message': S(3000), 'created_at': DATE, 'lot_code': S(120)}, ['id', 'message', 'created_at']))
    add('PublicDocument', obj({'id': ID, 'product_id': ID, 'title': S(180), 'sha256': SHA, 'mime': S(100), 'size_bytes': N, 'license_id': nullable(S(80)), 'created_at': DATE, 'download_url': {'type': 'string', 'format': 'uri'}}))
    add('PublicDocumentReference', obj({k: schemas['PublicDocument']['properties'][k] for k in ['id', 'title', 'sha256', 'mime', 'license_id']}))
    projection = obj({'product_id': ID, 'trade_item_id': ID, 'producer': S(180), 'gtin14': nullable(S(14)), 'identifier_status': S(24), 'identity_notice': nullable(S()), 'product_info_version_id': ID, 'version_key': S(60), 'approved_at': DATE, 'sha256': SHA, 'label': ref('LabelSnapshot'), 'documents': arr(ref('PublicDocumentReference')), 'alerts': arr(ref('PublicAlert'))})
    add('PublicProduct', projection)
    add('PublicBatch', obj(dict(projection['properties'], batch=obj({'id': ID, 'lot_code': S(120), 'state': S(24), 'data': ref('BatchSnapshot')}))))
    add('PublicLabel', table_schema('product_info_versions', ['id', 'product_id', 'trade_item_id', 'version_key', 'snapshot_json', 'snapshot_sha256', 'approved_at']))
    add('PublicRecipe', table_schema('recipe_versions', ['id', 'product_id', 'version_key', 'snapshot_json', 'snapshot_sha256', 'license_id', 'technical_status', 'created_at']))
    add('PublicRecipeSummary', table_schema('recipe_versions', ['id', 'version_key', 'license_id', 'technical_status', 'snapshot_sha256']))
    add('LegacyPublicRecipe', table_schema('recipe_versions', ['id', 'product_id', 'version_key', 'snapshot_json', 'snapshot_sha256', 'license_id', 'technical_status'], raw=True))
    proposal = table_schema('change_proposals', ['id', 'product_id', 'title', 'body', 'state', 'decision', 'created_at'])
    proposal['properties']['votes'] = N
    proposal['required'].append('votes')
    add('PublicProposal', proposal)
    add('PublicFeedback', table_schema('feedback', ['id', 'kind', 'rating', 'public_text', 'created_at', 'batch_id', 'product_info_version_id']))
    add('CatalogItem', obj({'id': ID, 'product_id': ID, 'gtin14': nullable(S(14)), 'identifier_status': S(24), 'product_info_version_id': ID, 'version_key': S(60), 'producer': S(180), 'name': S(180), 'category': S(80), 'net_quantity': S(100), 'url': {'type': 'string', 'format': 'uri'}}))
    unknown_food = {'schema_version': 'openaliment.food.v1', 'allergens': {'status': 'unknown', 'contains': [], 'may_contain': []}, 'nutrition': {'status': 'unknown', 'basis': None, 'preparation': None, 'jurisdiction': None, 'values': None, 'portion': None}, 'after_opening': None}
    source = obj({'kind': {'enum': ['unavailable', 'demonstration', 'producer_approved']}, 'checked_at': TIME, 'version_id': ID, 'version': S(60), 'sha256': SHA, 'approved_at': DATE}, ['kind', 'checked_at'])
    consumer = obj({'schema_version': {'const': 'openaliment.consumer-product.v1'}, 'name': S(180), **{k: nullable(S(5000)) for k in ['category', 'producer', 'ingredients', 'allergen_text', 'storage_text', 'after_opening_text', 'nutrition_text', 'net_quantity', 'public_url', 'lot_code']}, **{k: nullable(ID) for k in ['product_id', 'trade_item_id', 'batch_id']}, 'gtin14': nullable(S(14)), 'batch_state': nullable(S(24)), 'food_data': ref('FoodData'), 'expiry': nullable(ref('FoodDate')), 'observed_dates': arr(ref('FoodDate')), 'alerts': arr(ref('PublicAlert')), 'alerts_checked_at': nullable(TIME), 'alert_scope': {'enum': ['unknown', 'product_only', 'batch']}, 'source': source, 'simulation': B, 'notices': arr(S())})
    consumer['required'].remove('batch_state')
    demo = {k: None for k in ['category', 'producer', 'product_id', 'trade_item_id', 'batch_id', 'lot_code', 'ingredients', 'allergen_text', 'storage_text', 'after_opening_text', 'nutrition_text', 'net_quantity', 'expiry', 'alerts_checked_at']}
    demo.update(schema_version='openaliment.consumer-product.v1', name='Pâine Bună Tehnic', category='bread', gtin14='09520000000011', food_data=unknown_food, observed_dates=[], alerts=[], alert_scope='unknown', public_url=BASE + '/demo/paine-buna-tehnic', source={'kind': 'demonstration', 'checked_at': ISO, 'version': 'DEMO-E1'}, simulation=True, notices=['Exemplu demonstrativ. Datele alimentare și termenul nu sunt validate.'])
    consumer['properties']['gs1_qualifiers']=nullable(obj({ai:{'type':'string','minLength':1,'maxLength':28 if ai=='235' else 20} for ai in ['22','10','21','235']},[]))
    demo['gs1_qualifiers']=None
    add('ConsumerProduct', consumer, demo)
    identity = obj({'kind': {'enum': ['gtin', 'digital_link', 'native']}, 'gtin14': nullable(S(14)), 'lot_code': nullable(S(120)), 'native': nullable(obj({'type': {'enum': ['t', 'p', 'b']}, 'id': ID})), 'dates': arr(ref('FoodDate'))})
    identity['properties']['qualifiers']=consumer['properties']['gs1_qualifiers']
    add('ParsedIdentifier', obj({'valid': {'const': True}, 'identity': identity, 'rights_verified': {'const': False}}), {'valid': True, 'identity': {'kind': 'digital_link', 'gtin14': '09520000000011', 'lot_code': 'DEMO-PBT-01', 'native': None, 'dates': []}, 'rights_verified': False})
    add('FoodLookupsResult', obj({'items': arr({'oneOf': [obj({'code': S(4096), 'data': ref('ConsumerProduct')}), obj({'code': S(4096), 'error': obj({'error_code': S(80), 'message': S()})})]})}), {'items': [{'code': '9520000000011', 'data': demo}]})

    # Account/session responses are distinct from producer and consumer resources.
    user = obj({'id': ID, 'email': {'type': 'string', 'format': 'email'}, 'display_name': S(120), 'is_admin': N, 'email_verified': N})
    add('ProducerSessionResult', {'oneOf': [obj({'authenticated': B, 'user': nullable(user), 'csrf': S(64), 'registration_enabled': B}), obj({'authenticated': {'const': True}, 'realm': {'const': 'producer'}, 'organization_id': ID, 'role': S(24), 'scopes': arr(S(30))})]})
    add('ProducerLoginResult', obj({'authenticated': B, 'mfa_required': B, 'verification_url': nullable(S(200)), 'user': user, 'csrf': S(64)}))
    add('AccountSecurityResult', obj({'authenticated': {'const': True}, 'user': user, 'csrf': S(64), 'access_revoked': {'const': True}}))
    add('ProducerRegistrationResult', obj({'authenticated': {'const': True}, 'user_id': ID, 'organization_id': ID, 'csrf': S(64)}))
    add('ProducerLogoutResult', obj({'authenticated': {'const': False}}))
    add('OrganizationSelectionResult', obj({'organization_id': ID}))
    organization = obj({'id': ID, 'display_name': S(180), 'role': S(24)})
    add('OrganizationMembership', organization)
    add('TokenIdentity', obj({'organization_id': ID, 'user_id': ID, 'role': S(24), 'scopes': arr(S(30)), 'credential_id': ID, 'auth_type': {'enum': ['api_key', 'oauth']}}))
    add('ApiKeyMetadata', obj({k: v for k, v in dict(tables['api_tokens'], prefix=nullable(S(20)), last_used_at=nullable(DATE), rotated_from=nullable(ID), revocation_reason=nullable(S())).items() if k not in ['tenant_id', 'user_id', 'token_hash', 'scopes']} | {'scopes': arr(S(30))}))
    key_result = obj({'id': ID, 'token': S(100), 'expires_at': DATE, 'scopes': arr(S(30)), 'secret_available': B, 'previous_revoked': B, 'previous_id': ID}, ['id', 'expires_at', 'scopes', 'secret_available'])
    add('KeyIssuance', key_result)
    grant = obj({'id': ID, 'client_id': ID, 'name': S(120), 'tenant_id': nullable(ID), 'scopes_json': S(), 'created_at': DATE, 'expires_at': DATE, 'revoked_at': nullable(DATE)})
    add('ProducerGrant', grant)
    add('OAuthClientMetadata', obj({k: v for k, v in tables['api_clients'].items() if k in ['id', 'name', 'redirect_uris_json', 'scopes_json', 'active', 'created_at']}))
    add('OAuthClientCreated', obj({'id': ID, 'client_id': ID, 'client_type': {'const': 'public'}, 'token_endpoint_auth_method': {'const': 'none'}, 'redirect_uris': arr(S(2048)), 'scopes': arr(S(30))}))
    add('OAuthClientSuspended', obj({'id': ID, 'active': {'const': False}}))
    add('OAuthError', obj({'error': S(80), 'error_description': S(), 'request_id': S(64)}))
    add('OrganizationContext', obj({'organization': obj({'id': ID, 'display_name': S(180), 'identity_status': S(24)}), 'role': S(24)}))
    add('Dashboard', obj({'counts': obj({k: N for k in ['products', 'trade_items', 'batches', 'pending_feedback']})}))
    add('ProducerQuotas', obj({'resources': obj({k: obj({'used': N, 'limit': N}) for k in ['products', 'trade_items', 'product_info_versions', 'batches', 'recipe_versions', 'documents', 'change_proposals', 'integration_connectors', 'integration_mappings', 'storage_bytes']})}))
    add('AiCapabilities', obj({'enabled': B, 'job_types': arr(S(40)), 'qr_depends_on_ai': {'const': False}, 'public_context_only': {'const': True}}))
    add('LabelValidation', obj({'valid': {'const': True}, 'snapshot': ref('LabelSnapshot'), 'writes': {'const': False}}))
    add('LabelPreview', obj({'id': ID, 'state': S(24), 'snapshot': ref('LabelSnapshot'), 'snapshot_sha256': SHA, 'writes': {'const': False}}))

    add('Connector', table_schema('integration_connectors', ['id', 'name', 'kind', 'created_at']))
    add('ConnectorCreated', obj({'id': ID, 'kind': {'enum': ['catalog', 'retail_receipt']}}))
    add('ConnectorMapping', table_schema('integration_mappings', ['id', 'version_key', 'sha256', 'mapping_json', 'created_at']))
    add('MappingCreated', obj({'id': ID, 'sha256': SHA, 'version_key': S(60)}))
    add('ConnectorEvent', table_schema('integration_events', ['id', 'mapping_id', 'event_key', 'request_sha256', 'result_json', 'created_at']))
    binding = obj({'external_id': S(120), 'product_id': ID, 'trade_item_id': ID})
    add('ConnectorBindings', obj({'items': arr(binding), 'next_external_id': nullable(S(120)), 'has_more': B, 'limit': {'type': 'integer', 'minimum': 1, 'maximum': 100}}))
    canonical_product = obj({'name': S(180), 'category': S(80), 'trade_name': S(180), 'gtin': nullable(S(14)), 'claim_evidence': nullable(S(5000)), 'label': ref('Label')})
    canonical_batch = obj(dict(schemas['BatchSnapshot']['properties'], trade_external_id=S(120), label_version_key=S(60), lot_code=S(120)))
    # Receipt validation output has no generated id yet.
    canonical_receipt = copy.deepcopy(schemas['Receipt'])
    canonical_receipt['properties'].pop('id')
    canonical_receipt['required'].remove('id')
    add('ConnectorPreview', obj({'schema_version': {'const': 'openaliment.integration-preview.v1'}, 'operation': {'enum': ['product', 'batch', 'receipt']}, 'mapping_sha256': SHA, 'canonical': {'oneOf': [canonical_product, canonical_batch, canonical_receipt]}, 'writes': {'const': False}, 'publication': {'const': 'requires_separate_approval'}}))
    result_fields = {'operation': {'enum': ['product', 'batch', 'receipt']}, 'mapping_id': ID, 'mapping_sha256': SHA, 'event_key': S(128)}
    add('ConnectorIngested', {'oneOf': [obj(dict(result_fields, product_id=ID, trade_item_id=ID, label_id=ID, state={'const': 'draft'})), obj(dict(result_fields, **schemas['BatchCreated']['properties'])), obj(dict(result_fields, id=ID, receipt_url=S(2048), expires_in_days={'const': 30}, simulation=B))]})
    counts = obj({k: N for k in ['products', 'trade_items', 'product_info_versions', 'recipe_versions', 'batches', 'batch_events', 'alerts', 'documents', 'change_proposals', 'url_mappings', 'imports', 'resolver_links', 'integration_connectors', 'integration_mappings', 'integration_bindings', 'feedback', 'audit_events']}, [])
    for key,field in counts['properties'].items():field['description']='Numărul înregistrărilor din colecția '+key+' din pachetul analizat, înaintea atribuirii ID-urilor locale.'
    add('ImportReport', obj({'schema_version': S(80), 'counts': counts, 'publication': {'const': 'private'}, 'verification': {'const': 'requires_reverification'},'id_map':{'type':'object','additionalProperties':copy.deepcopy(ID)},'history_preserved_in_import':{'const':True}},['schema_version','counts','publication','verification']))
    add('PilotImportReport', obj({'name': S(180), 'visibility': {'const': 'private'}, 'technical_status': {'const': 'experimental'}, 'recipe_identity': {'const': 'pbt:R1'}, 'nominal_input_mass_g': {'const': 3790}, 'originals': {'const': 18}, 'recovered': {'const': 7}, 'quarantine': {'const': 'PBT-SRC-06'}, 'cost': {'const': 'COST3 estimated'}, 'manifest_sha256': SHA, 'product_id': ID, 'recipe_id': ID}, ['name', 'visibility', 'technical_status', 'recipe_identity', 'nominal_input_mass_g', 'originals', 'recovered', 'quarantine', 'cost', 'manifest_sha256']))
    # Export rows intentionally retain raw JSON strings, provenance and approved history.
    export_tables = ['products', 'trade_items', 'product_info_versions', 'recipe_versions', 'batches', 'batch_events', 'alerts', 'documents', 'change_proposals', 'url_mappings', 'imports', 'resolver_links', 'integration_connectors', 'integration_mappings', 'integration_bindings']
    export_data = {}
    for table in export_tables:
        fields = table_schema(table, raw=True)
        if table == 'documents':
            fields['properties']['content_base64'] = nullable(S(20000000))
            fields['properties']['download_url'] = {'type': 'string', 'format': 'uri'}
            fields['properties']['sources'] = arr(obj({'source_sha256':SHA,'details_json':S(50000),'created_at':DATE}))
            fields['required'] += ['content_base64', 'download_url']
        export_data[table] = arr(fields)
    export_data['feedback'] = arr(table_schema('feedback', ['id', 'product_id', 'batch_id', 'product_info_version_id', 'kind', 'rating', 'public_text', 'created_at'], raw=True))
    export_data['audit_events'] = arr(table_schema('audit_events', ['id', 'action', 'object_id', 'details_json', 'created_at'], raw=True))
    add('ProducerTransfer', obj({'schema_version': {'const': 'openaliment.transfer.v1'}, 'exported_at': TIME, 'source_base_url': {'type': 'string', 'format': 'uri'}, 'organization': obj({'id': ID, 'display_name': S(180)}), 'data': obj(export_data, [k for k in export_data if k not in ['resolver_links', 'integration_connectors', 'integration_mappings', 'integration_bindings']]), 'data_sha256': SHA}))

    # Acasă has a separate identity realm, cookies, CSRF and numeric sync cursor.
    add('HomeChanges', local_schema('home-sync.schema.json', '$defs/Page'))
    add('HomeSyncResult', local_schema('home-sync.schema.json', '$defs/Response'))
    hp = obj({'account_id': ID, 'device_id': ID, 'household_id': ID, 'household_name': S(120), 'email': {'type': 'string', 'format': 'email'}, 'role': S(24), 'csrf': nullable(S(64)), 'scopes': arr(S(30)), 'grant_id': ID}, ['account_id', 'device_id', 'household_id', 'household_name', 'email', 'role', 'csrf'])
    add('HomeSession', {'oneOf': [obj({'authenticated': {'const': False}, 'available': {'const': False}, 'magic_link_ready': {'const': False}}), obj({'authenticated': B, 'available': {'const': True}, 'magic_link_ready': B, 'session': nullable(hp), 'devices': arr(obj({'id': ID, 'name': S(120), 'last_seen_at': DATE, 'created_at': DATE}, ['id', 'name']))})]})
    add('HomeLoginRequested', obj({'message': S()}))
    add('HomeLoginPreview', obj({'email': {'type': 'string', 'format': 'email'}, 'expires_at': DATE}))
    add('HomeConnected', obj({'connected': {'const': True}, 'device_id': ID, 'account_id': ID, 'household_id': ID, 'email': {'type': 'string', 'format': 'email'}, 'authorization_url': S(2048)}, ['connected', 'device_id', 'account_id', 'household_id', 'email']))
    add('HomeMember', obj({'account_id': ID, 'role': {'enum': ['owner', 'editor']}, 'email': {'type': 'string', 'format': 'email'}}))
    add('HomeHousehold', obj({'id': ID, 'name': S(120), 'role': {'enum': ['owner', 'editor']}}))
    add('HomeGrant', obj({'id': ID, 'name': S(120), 'household_id': ID, 'scopes_json': S(), 'expires_at': DATE, 'revoked_at': nullable(DATE), 'created_at': DATE}))
    add('HomeInvitation', obj({'url': S(2048), 'expires_in_days': {'const': 7}}))
    add('HomeInvitationPreview', obj({'name': S(120)}))
    add('HomeSelected', obj({'household_id': ID}))
    add('HomeRenamed', obj({'name': S(120)}))
    add('HomeRemoved', obj({'removed': {'const': True}}))
    add('HomePhotoStored', obj({'id': ID, 'sha256': SHA}))

    # Small public vocabularies are value contracts, not generic records.
    vocabs = {
        'licenses': obj({'items': arr(S(80)), 'hosting_requires_open_license': {'const': False}}),
        'allergens': obj({'schema_version': {'const': 'openaliment.vocabularies.v1'}, 'items': obj({key: S(100) for key in ['gluten','crustaceans','eggs','fish','peanuts','soy','milk','nuts','celery','mustard','sesame','sulphites','lupin','molluscs']})}),
        'nutrition': obj({'fields': arr(S(40)), 'preparation': arr(S(40)), 'jurisdictions': arr(S(20))}),
        'units': obj({'items': arr(S(20)), 'nutrition_bases': arr(S(40))}),
        'link-types': obj({'namespace': {'type': 'string', 'format': 'uri'}, 'profile_url': {'type': 'string', 'format': 'uri'}, 'vocabulary_url': {'type': 'string', 'format': 'uri'}, 'examples': arr(S(80))}),
        'privacy': obj({'version': S(80), 'url': {'type': 'string', 'format': 'uri'}, 'operator': nullable(S()), 'contact': nullable(S()), 'operator_text': nullable(S()), 'local_only': arr(S(80)), 'optional_sync': arr(S(80))}),
    }
    for key, value in vocabs.items():
        add('Vocabulary' + key.title().replace('-', ''), value)
    add('ConsumerCapabilities', obj({'schema_version': {'const': 'openaliment.capabilities.v1'}, 'date_ocr': B, 'cloud_sync': B, 'sync_scope': arr(S(40)), 'shopping_sync': {'const': False}, 'digital_receipts': B, 'external_sources': {'const': False}}))
    add('ServiceStatus', obj({'status': {'enum': ['operational', 'unavailable']}, 'checked_at': TIME, 'ai_required': {'const': False}}))
    for kind, columns in {'Organizations': ['id', 'display_name', 'identity_status', 'created_at'], 'Users': ['id', 'email', 'display_name', 'email_verified', 'active', 'created_at'], 'GtinClaims': ['id', 'tenant_id', 'name', 'gtin14', 'gtin_status', 'claim_evidence', 'verified_by', 'verified_at']}.items():
        add('Admin' + kind, table_schema({'Organizations': 'organizations', 'Users': 'users', 'GtinClaims': 'trade_items'}[kind], columns))

    actions = {
        'product.create': ('Creează un produs alimentar', 'IdResult', 'Creează produsul intern în organizația autentificată. Categoria este generică. Nu publică o etichetă, nu cere rețetă și nu acordă sigla de documentație deschisă.'),
        'trade.create': ('Creează un articol comercial al produsului', 'TradeCreated', 'Separă ambalarea/articolul comercial de produs. GTIN este opțional și normalizat la 14 cifre, dar rămâne unverified până la decizia administratorului. Fără GTIN verificat, QR folosește URL-ul nativ.'),
        'label.create': ('Creează o versiune de etichetă în ciornă', 'LabelCreated', 'Salvează un snapshot imutabil al informațiilor publicabile. Pentru corecții creează o versiune nouă, de exemplu E2. Nu schimbă eticheta curentă până la publicare și nu modifică loturile vechi.'),
        'label.publish': ('Publică versiunea de etichetă', 'LabelPublished', 'Trece versiunea draft în published și o face eticheta curentă a articolului. Documentele selectate trebuie publicate anterior cu drepturi confirmate. Repetarea cu o cheie nouă după publicare produce conflict; reîncercarea folosește cheia inițială.'),
        'batch.create': ('Creează un lot fără rețetă obligatorie', 'BatchCreated', 'Leagă lotul de o etichetă published a aceluiași articol. recipe_version_id poate lipsi sau poate fi null; dacă există, trebuie să aparțină aceluiași produs și poate rămâne privată. Lotul începe planned și private.'),
        'batch.publish': ('Publică pagina lotului', 'BatchPublished', 'Face lotul vizibil public folosind versiunea istorică deja aprobată. Nu îl eliberează pentru consum și nu publică rețeta sa privată.'),
        'batch.release': ('Eliberează lotul', 'BatchReleased', 'Trece planned, in_production sau on_hold în released numai cu etichetă aprobată și fără alerte active. Publicarea paginii și eliberarea lotului sunt independente.'),
        'alert.create': ('Înregistrează o alertă curentă a lotului', 'IdResult', 'Adaugă alerta în istoric și trece lotul în on_hold sau recalled. Mesajul activ apare peste eticheta istorică la fiecare citire publică. Nu rescrie snapshotul și nu depinde de AI.'),
        'alert.resolve': ('Închide o alertă cu motiv documentat', 'IdResult', 'Păstrează alerta și motivul în istoric, setând resolved_at. Nu eliberează automat lotul și nu șterge evenimentele anterioare. După clarificare se poate solicita separat release.'),
        'recipe.create': ('Adaugă o versiune opțională de rețetă', 'IdResult', 'Implicit creează documentație privată. Publicarea directă cere write și publish, rol owner/quality, licență deschisă acceptată și toate cele trei confirmări. Serviciul produs/lot/QR funcționează fără această operație.'),
        'proposal.create': ('Înregistrează o propunere de schimbare', 'IdResult', 'Păstrează propunerea și justificarea separat de datele aprobate. public=true cere și publish plus owner/quality. Nicio propunere sau recenzie nu modifică direct etichete ori loturi.'),
        'proposal.decide': ('Înregistrează decizia asupra propunerii', 'IdResult', 'Actualizează starea și justificarea propunerii, cu un eveniment separat de audit. accepted sau tested nu publică automat o etichetă și nu acordă o siglă.'),
        'document.publish': ('Aprobă publicarea unui document', 'IdResult', 'Cere confirmarea drepturilor și a copiei publicabile. license_id este opțional pentru găzduire; nu se acordă automat o licență deschisă. Materialele deteriorate aflate în carantină sunt respinse.'),
        'feedback.moderate': ('Moderează copia publicabilă a feedbackului', 'FeedbackModerated', 'Păstrează originalul privat și motivul deciziei în audit. published cere public_text; quality nu poate fi publicat prin acest flux. AI poate procesa numai copia moderată și contextul public permis.'),
        'resolver.draft': ('Creează o versiune de destinație Digital Link', 'ResolverDrafted', 'Necesită un GTIN cu drept de administrare verified. Prima versiune generează link_key; o schimbare folosește același link_key și expected_version. Destinația rămâne draft până la aprobarea explicită.'),
        'resolver.publish': ('Publică versiunea destinației resolverului', 'ResolverPublished', 'Publică numai cea mai nouă ciornă și păstrează versiunile anterioare. Verifică destinația implicită la nivelul calificatorilor și existența unei destinații de bază. Confirmarea nu acordă drepturi GS1.'),
        'resolver.withdraw': ('Retrage destinația curentă cu istoric', 'ResolverWithdrawn', 'Adaugă o versiune withdrawn, cu motiv, și păstrează versiunile precedente. Nu permite eliminarea ultimei destinații implicite necesare legăturilor publicate.'),
    }
    resource_labels = {'products': 'produsele', 'trade-items': 'articolele comerciale', 'labels': 'versiunile de etichetă', 'batches': 'loturile', 'recipes': 'rețetele', 'documents': 'documentele', 'feedback': 'feedbackul', 'proposals': 'propunerile', 'imports': 'importurile', 'audit-events': 'evenimentele de audit', 'ai-jobs': 'joburile AI', 'resolver-links': 'destinațiile resolverului', 'events': 'evenimentele lotului', 'alerts': 'alertele lotului'}
    mutations = {r['path'].replace('/api/v2/', '/api/v1/'): r['handler'].split(':', 1)[1] for r in routes if r['handler'].startswith('mutation:')}

    def named(name):
        return ref(name)

    def classify(path, method, op):
        r = registry.get((method, path))
        h = r['handler'] if r else ''
        marketplace=marketplace_metadata(path,method,h)
        if marketplace:return marketplace
        readiness = readiness_metadata(path, method, h)
        if readiness:
            return readiness
        if path.startswith('/api/v1/') and method == 'post' and path in mutations:
            h = 'mutation:' + mutations[path]
        if h.startswith('mutation:'):
            return actions[h.split(':', 1)[1]]
        if h in ['manage.list', 'manage.get']:
            resource = path.split('/')[4]
            schema = names[resource] + ('Summary' if h.endswith('list') and resource in summaries else '')
            if h.endswith('list'):
                return ('Listează ' + resource_labels[resource] + ' ale organizației', page(schema), 'Aplică filtrele exacte declarate și cursorul exclusiv id > cursor, în ordine lexicografică a ID-ului. ID-urile sunt aleatoare, deci ordinea nu este cronologică. Nu include datele altor organizații.')
            return ('Citește ' + resource_labels[resource] + ' după ID', named(schema), 'Returnează numai resursa organizației autentificate. Un ID din altă organizație primește 404, identic cu un ID inexistent. Snapshoturile v2 sunt decodate; metadatele private nu se expun public.')
        if h == 'manage.children':
            parent, child = path.split('/')[4::2]
            schema = 'AiResultSummary' if child == 'ai-results' else 'AuditDecision' if child == 'decisions' else names[child] + ('Summary' if child in summaries else '')
            return ('Listează ' + ('deciziile' if child == 'decisions' else 'rezultatele AI' if child == 'ai-results' else resource_labels[child]) + ' pentru resursa selectată', page(schema), 'Verifică mai întâi proprietatea resursei părinte, apoi întoarce numai înregistrările asociate, cu paginare prin ID. Nu amestecă alte produse, articole sau organizații. Deciziile sunt evenimente de audit atribuite obiectului.')
        if h in ['public.get', 'public.children']:
            parts = path.split('/')
            resource = parts[4]
            if h == 'public.get':
                name = {'products': 'PublicProduct', 'trade-items': 'PublicProduct', 'batches': 'PublicBatch', 'labels': 'PublicLabel', 'recipe-versions': 'PublicRecipe', 'documents': 'PublicDocument', 'proposals': 'PublicProposal'}[resource]
                return ('Citește ' + {'products': 'produsul publicat', 'trade-items': 'articolul comercial publicat', 'batches': 'lotul publicat și alertele curente', 'labels': 'versiunea istorică publicată a etichetei', 'recipe-versions': 'rețeta cu publicare și licență aprobate', 'documents': 'metadatele documentului public', 'proposals': 'propunerea publică și numărul voturilor'}[resource], name, 'Expune exclusiv proiecția aprobată. Inexistent și nepublicat primesc 404. ' + ('Pentru un produs cu mai multe articole se alege primul după created_at și id; folosește lista de articole pentru selecție explicită.' if resource == 'products' else 'Lotul păstrează eticheta istorică, iar alertele active se citesc la momentul cererii.' if resource == 'batches' else 'Găzduirea și verificarea GTIN nu acordă o licență deschisă sau o siglă.'))
            child = parts[6]
            if child == 'trade-items':
                return ('Listează articolele publicate ale produsului', page('CatalogItem'), 'Catalogul aplică filtrul produsului și include numai etichete publicate; folosește next_cursor pentru continuare.')
            name = {'recipe-versions': 'PublicRecipeSummary', 'proposals': 'PublicProposal', 'alerts': 'PublicAlert', 'documents': 'PublicDocument'}[child]
            result = page(name, resource == 'products')
            if child == 'alerts':
                result = obj({'items': arr(ref(name)), 'checked_at': TIME})
            return ('Listează ' + {'recipe-versions': 'rețetele publice asociate', 'proposals': 'propunerile publice ale produsului', 'alerts': 'alertele active ale lotului', 'documents': 'documentele aprobate ale etichetei istorice'}[child], result, 'Sunt incluse numai înregistrările publicabile. O listă goală de rețete nu indică inexistența unei rețete private. ' + ('Această listă folosește cursor.' if resource == 'products' else 'Această listă este limitată de resursa părinte și nu folosește cursor.'))
        if h == 'public.catalog':
            return ('Caută articolele alimentare publicate', page('CatalogItem'), 'q caută un prefix literal în numele etichetei aprobate; %, _ și ! sunt tratate ca text. category, producer și gtin sunt filtre exacte și se combină prin AND. gtin nu caută identificatori neverificați. Rezultatele sunt ordonate după ID, nu relevanță sau dată.')
        if h == 'public.feedback':
            return ('Listează feedbackul public moderat al articolului', page('PublicFeedback'), 'Implicit selectează eticheta curentă. label_id poate alege o versiune istorică publicată. batch_id trebuie să aparțină articolului și aceleiași versiuni. Nu întoarce original_text sau sesizări quality.')
        if h in ['public.download', 'manage.download']:
            return ('Descarcă documentul ' + ('public' if h.startswith('public') else 'autorizat al organizației'), None, 'Răspuns binar cu Content-Type al fișierului și Content-Disposition: attachment. Metadatele și octeții sunt rute distincte. Descărcarea publică cere visibility=public și rights_status=cleared; fișierul absent primește 404.')
        if h == 'public.vocabulary':
            key = path.rsplit('/', 1)[-1]
            return ('Citește ' + {'privacy': 'informarea privind confidențialitatea', 'licenses': 'licențele acceptate', 'allergens': 'codurile alergenilor', 'nutrition': 'câmpurile și bazele nutriționale', 'units': 'unitățile acceptate', 'link-types': 'vocabularul tipurilor de legături'}[key], 'Vocabulary' + key.title().replace('-', ''), 'Dicționar public pentru validarea și afișarea valorilor din aplicații. Valorile sunt declarații ale contractului, nu recomandări medicale sau o evaluare a produsului.')
        if h in ['public.lookup', 'public.identifier']:
            return ('Caută mai multe coduri alimentare' if h == 'public.lookup' else 'Validează și interpretează identificatorul alimentar', 'FoodLookupsResult' if h == 'public.lookup' else 'ParsedIdentifier', 'Maximum 20 coduri; fiecare rezultat conține data sau error, în ordinea cererii. Fiecare cod consumă o unitate suplimentară din cota publică.' if h == 'public.lookup' else 'parse și validate folosesc în prezent același parser: verifică sintaxa, cifra de control și datele GS1 recunoscute. rights_verified rămâne false. Nu interoghează registrul GS1 și nu confirmă mandatul organizației.')
        simple = {
            'community.feedback': ('Trimite feedback pentru produs sau lot', 'FeedbackSubmitted', 'Necesită consent=true și submission_key stabil. Prima trimitere întoarce status_token, iar reluarea identică numai ID și status. quality rămâne restricționat; feedbackul nu modifică datele aprobate.'),
            'community.votes': ('Listează voturile proprii', page_vote(), 'Necesită scope community și contact verificat. Lista conține ID-urile propunerilor votate, cu cursor.'),
            'community.vote': ('Susține propunerea' if method == 'put' else 'Retrage votul propriu', 'VoteResult', 'Un singur vot per cont și propunere publică. Citește starea și trimite expected_mutation_version în corpul PUT sau query DELETE. Un retry cu versiune veche primește 409; retragerea păstrează tombstone. Compatibilitatea fără versiune permite numai prima activare, repetarea stării curente și prima retragere. Necesită contact verificat și scope community.'),
            'manage.dashboard': ('Citește indicatorii organizației', 'Dashboard', 'Numără produsele, articolele, loturile și feedbackul pending din organizația autentificată.'),
            'manage.organization': ('Citește organizația și rolul curent', 'OrganizationContext', 'Apartenența și rolul curent sunt verificate la fiecare cerere, inclusiv după emiterea unui token.'),
            'manage.quotas': ('Citește cotele și consumul organizației', 'ProducerQuotas', 'used și limit sunt numere pentru fiecare resursă; storage_bytes este în octeți. Limitele se configurează de operator și nu se pot mări prin API.'),
            'manage.preview': ('Previzualizează eticheta fără publicare', 'LabelPreview', 'Returnează snapshotul autorizat, starea și amprenta; writes=false. Nu acordă acces public documentelor și nu schimbă current_info_id.'),
            'manage.validate': ('Validează eticheta fără salvare', 'LabelValidation', 'Verifică structura și regulile câmpurilor, apoi returnează forma normalizată. Nu verifică publicarea documentelor la acest pas și nu creează o versiune.'),
            'manage.upload': ('Încarcă un document privat', 'DocumentUploaded', 'Trimite multipart/form-data cu title și document, maximum 15.000.000 octeți. PDF, text, JSON, JPEG, PNG și WebP sunt identificate din conținut. Fișierul începe privat, fără licență deschisă implicită.'),
            'manage.export': ('Exportă datele organizației cu istoric', 'ProducerTransfer', 'format=json este implicit; ndjson transmite antet, rânduri ordonate și înregistrare finală digest. Include documentele autorizate și istoricul; nu include chei API, parole sau mandat transferabil automat. Nu publica exportul privat.'),
            'manage.import': ('Verifică sau importă ' + ('pilotul Pâine Bună Tehnic' if path.endswith('import-pbt') else 'un pachet de transfer'), 'PilotImportReport' if path.endswith('import-pbt') else 'ImportReport', 'dry_run=true este implicit și nu cere Idempotency-Key. Aplicarea false cere import, rol owner/quality și cheie stabilă. Datele revin private și necesită reverificare; rețetele experimentale rămân experimentale. Pentru un export NDJSON folosește import-stream, cu limita documentată de 24 MB.'),
            'keys.list': ('Listează cheile fără secrete', page('ApiKeyMetadata'), 'Sesiunea ownerului este obligatorie. Sunt disponibile metadatele, prefixul, scopes și starea revocării, niciodată token_hash sau secretul.'),
            'keys.create': ('Emite o cheie API revocabilă', 'KeyIssuance', 'Owner, sesiune proprie și CSRF. Autentificarea mai veche de 15 minute cere current_password. Secretul este returnat numai la prima execuție; reluarea întoarce secret_available=false. Dacă se pierde răspunsul inițial, revocă cheia și emite alta.'),
            'keys.rotate': ('Rotește cheia și revocă imediat cheia veche', 'KeyIssuance', 'Păstrează scopes ale cheii înlocuite. Nu există perioadă de suprapunere. Secretul nou apare numai la prima execuție; reîncercarea nu îl recuperează. Coordonarea transferului rămâne responsabilitatea integrării.'),
            'keys.revoke': ('Revocă o cheie a organizației', 'RevokedId', 'Exclusiv owner prin sesiune și CSRF. Revocarea oprește cheia la următoarea cerere; nu șterge istoricul operațiilor sale.'),
            'token.inspect': ('Inspectează tokenul Bearer folosit', 'TokenIdentity', 'Cere un Bearer valid. Returnează identitatea, organizația și scopes, fără secret. O sesiune web fără Bearer primește 422 bearer_required.'),
            'token.revoke': ('Revocă propriul token Bearer', 'Revoked', 'Pentru OAuth revocă întregul grant; pentru o cheie ERP revocă acea cheie. Nu cere scope de scriere. După succes, același token nu mai poate confirma revocarea printr-o a doua cerere autenticată.'),
            'developer.suspend': ('Suspendă aplicația OAuth proprie', 'OAuthClientSuspended', 'Necesită sesiunea proprietarului aplicației și CSRF. active=false invalidează toate granturile aplicației; nu suspendă alte aplicații.'),
            'identity.session': ('Citește sesiunea producătorului și CSRF', 'ProducerSessionResult', 'Fără Bearer întoarce starea browserului și csrf chiar înainte de login. Cu Bearer întoarce identitatea limitată și scopes, fără cookie sau CSRF. Datele nu se cache-uiesc și nu sunt un profil public.'),
            'identity.login': ('Conectează contul în browserul propriu', 'ProducerLoginResult', 'Obține mai întâi csrf prin GET identity/session și păstrează cookie-ul aceleiași sesiuni. Login rotește sesiunea; folosește csrf nou din răspuns. Aplicațiile terților folosesc OAuth, nu colectează parola producătorului.'),
            'identity.password': ('Schimbă parola și retrage accesurile vechi', 'AccountSecurityResult', 'Exclusiv sesiunea proprie și CSRF, cu parola actuală și confirmarea parolei noi. Rotește sesiunea curentă și revocă toate cheile API și granturile producer emise de cont în toate organizațiile sale. Celelalte sesiuni sunt invalidate. Nu deconectează contul personal Acasă. Parolele noi folosesc Argon2id unde este disponibil; fallback bcrypt limitează la 72 de octeți.'),
            'identity.access-revoke': ('Retrage celelalte accesuri ale contului', 'AccountSecurityResult', 'Exclusiv sesiunea proprie, CSRF și confirmarea parolei actuale. Păstrează parola, dar rotește verificatorul ei, invalidează celelalte sesiuni și revocă cheile și granturile producer emise de cont. Actualizează CSRF din răspuns. Un cont legacy cu parolă bcrypt de peste 72 de octeți trebuie întâi să aleagă o parolă nouă.'),
            'identity.logout': ('Închide sesiunea web curentă', 'ProducerLogoutResult', 'Necesită sesiune proprie și CSRF. Expiră cookie-ul oa_session; nu revocă automat cheile ERP sau granturile OAuth.'),
            'identity.register': ('Înregistrează contul și organizația', 'ProducerRegistrationResult', 'Disponibil numai dacă înscrierile sunt active. Necesită browser propriu, cookie și CSRF. Creează owner, fără identitate sau contact verificate automat. Returnează 201 și csrf pentru noua sesiune.'),
            'identity.organizations': ('Listează organizațiile contului propriu', page('OrganizationMembership', False), 'Lista apartenențelor contului autentificat, nu catalogul tuturor producătorilor. Nu este paginată.'),
            'identity.select': ('Selectează organizația activă a sesiunii', 'OrganizationSelectionResult', 'Acceptă numai o apartenență existentă. Schimbarea sesiunii nu schimbă organizația fixată într-o cheie ERP sau grant OAuth.'),
        }
        if h in simple:
            return simple[h]
        if h == 'manage.qr' or path.startswith('/api/v1/manage/qr/'):
            return ('Obține adresa QR de ' + ('lot' if '/batch/' in path else 'produs'), 'QrResult', 'Returnează URL, nu o imagine QR. Publică întâi eticheta/lotul. digital_link=true cere drept GTIN verified și transport verificat; altfel url este native_url. Codul de produs nu identifică un lot. Generează imaginea QR local din URL-ul exact.')
        if h == 'manage.connector':
            kind = path.rsplit('/', 1)[-1]
            return ('Citește ' + ('asocierile de ID-uri externe' if kind == 'bindings' else 'evenimentele conectorului' if kind == 'events' else 'conectorul organizației'), 'ConnectorBindings' if kind == 'bindings' else page('ConnectorEvent') if kind == 'events' else 'Connector', 'Conectorul trebuie să aparțină organizației. bindings folosește next_external_id ca parametru external_id, nu cursor ID. Evenimentele ascund receipt_url din rezultate; bonul confidențial nu se publică în jurnal.')
        if h == 'manage.ai':
            return ('Programează analiza feedbackului' if method == 'post' else 'Citește disponibilitatea AI', 'AiQueued' if method == 'post' else 'AiCapabilities', 'Analiza este procesată asincron; răspunsul 202 confirmă înregistrarea jobului, nu finalizarea analizei. Același feedback poate întoarce jobul existent. AI dezactivat produce 503 ai_disabled; paginile QR și citirile publice continuă să funcționeze.' if method == 'post' else 'Expune disponibilitatea și tipurile de job, fără chei sau modele private. qr_depends_on_ai=false și public_context_only=true sunt limite funcționale, nu o autorizare de publicare.')
        if h == 'developer.apps':
            return ('Înregistrează o aplicație OAuth publică' if method == 'post' else 'Listează aplicațiile OAuth proprii', 'OAuthClientCreated' if method == 'post' else page('OAuthClientMetadata', False), 'Sesiunea proprie este obligatorie. Nu se emite client_secret; se folosește Authorization Code + PKCE S256. Callback-urile sunt înregistrate exact. Lista păstrează redirect_uris_json și scopes_json ca șiruri JSON pentru compatibilitate.')
        if h == 'identity.grants':
            return ('Retrage autorizarea unei aplicații' if method == 'delete' else 'Listează autorizările proprii', 'RevokedId' if method == 'delete' else page('ProducerGrant', False), 'Exclusiv sesiunea utilizatorului. Nu permite revocarea granturilor altcuiva. Revocarea invalidează tokenurile și refresh-ul la cererea următoare; lista are maximum 100 înregistrări și nu folosește cursor.')
        if h == 'admin.list':
            name = {'organizations': 'Organizations', 'users': 'Users', 'gtin-claims': 'GtinClaims'}[path.rsplit('/', 1)[-1]]
            return ('Listează ' + {'Organizations': 'organizațiile pentru verificare', 'Users': 'conturile pentru verificarea contactului', 'GtinClaims': 'revendicările GTIN'}[name], page('Admin' + name), 'Exclusiv sesiunea administratorului platformei. Cheile ERP și granturile OAuth nu acordă acest acces. Paginarea este prin ID; dovezile sunt private.')
        if h.startswith('admin:'):
            return ('Înregistrează verificarea ' + {'organization.verify': 'identității organizației', 'contact.verify': 'contactului utilizatorului', 'gtin.review': 'mandatului GTIN'}[h.split(':', 1)[1]], 'AdminDecision', 'Administratorul consemnează probele examinate în evidence. Organizația și contactul sunt marcate verified; numai decizia GTIN folosește state. Verificarea GTIN nu acordă o siglă, licență sau drept de a modifica alte organizații.')
        return None

    def page_vote():
        if 'OwnVote' not in schemas:
            add('OwnVote', obj({'id': ID, 'active': N, 'mutation_version': N, 'updated_at': DATE, 'created_at': DATE}, ['id']))
        return page('OwnVote')

    # Remaining families have legacy/independent controllers, not ApiRoutes.
    def special(path, method, op):
        readiness = readiness_metadata(path, method, '')
        if readiness:
            return readiness
        if path.startswith('/api/v1/'):
            suffix = path[len('/api/v1/'):]
            if method == 'get' and suffix == 'products':
                return ('Listează produsele publicate prin API v1', obj({'items': arr(obj({'id': ID, 'trade_item_id': ID, 'name': S(180), 'category': S(80), 'url': S(2048)})), 'page': {'type': 'integer'}, 'limit': {'type': 'integer'}}), 'Compatibilitate: paginare numerică page/limit, nu cursor. Un produs cu mai multe articole poate apărea pe mai multe rânduri. Pentru integrări noi folosește catalogul v2.')
            if method == 'get' and suffix == 'manage/products':
                return ('Listează produsele organizației prin API v1', collection(obj({k: tables['products'][k] for k in ['id', 'name', 'category', 'created_at']}), False), 'Compatibilitate: maximum 100 produse, fără cursor. Pentru parcurgere completă folosește GET /api/v2/manage/products.')
            if suffix == 'manage/export':
                return ('Exportă organizația prin API v1', 'ProducerTransfer', 'Același pachet privat ca în v2; format=ndjson este disponibil. Nu include chei API sau transfer automat de mandat/publicare.')
            if '/resolver-links' in suffix and method == 'get':
                return ('Citește istoricul destinațiilor prin API v1', collection(table_schema('resolver_links', raw=True), False), 'Compatibilitate: maximum 1.000 versiuni, ordonate după created_at descrescător; qualifiers_json și target_json rămân șiruri JSON. V2 le decodează și folosește cursor.')
            match = re.fullmatch(r'manage/(products|trade-items|labels|batches|recipes|documents)/\{id\}', suffix)
            if match:
                return ('Citește resursa privată prin API v1: ' + match[1], 'Legacy' + names[match[1]], 'Snapshoturile și proveniența rămân șiruri *_json. Se păstrează compatibilitatea cu clienții existenți; folosește v2 pentru obiecte JSON decodate.')
            if re.fullmatch(r'(products|trade-items|batches)/\{id\}', suffix):
                return ('Citește ' + ('lotul publicat' if suffix.startswith('batches') else 'produsul publicat') + ' prin API v1', 'PublicBatch' if suffix.startswith('batches') else 'PublicProduct', 'Aceeași proiecție publică aprobată ca în v2, cu etichetă istorică și alerte curente pentru lot.')
            if suffix == 'recipe-versions/{id}':
                return ('Citește rețeta publică prin API v1', 'LegacyPublicRecipe', 'Numai rețete publice cu drepturi confirmate și licență explicită; snapshot_json rămâne șir serializat.')
            if suffix == 'feedback/{id}/status':
                return ('Citește starea feedbackului cu secretul propriu', obj({'status': S(24)}), 'Trimite secretul primit la prima trimitere în X-Status-Token. Tokenul incorect și ID-ul necunoscut primesc același 404. Nu returnează textul original.')
            if suffix == 'feedback':
                return ('Trimite feedback prin API v1', 'FeedbackSubmitted', 'Compatibil cu fluxul v2. submission_key deduplică trimiterea; status_token apare numai la prima execuție. Starea nu publică automat textul.')
            if suffix in ['manage/import', 'manage/import-pbt']:
                return ('Verifică sau importă date prin API v1', 'PilotImportReport' if suffix.endswith('import-pbt') else 'ImportReport', 'dry_run=true este implicit. Aplicarea cere rol owner/quality și import; reintroduce datele în spațiul privat. Compatibilitatea schemelor istorice este documentată în ghidul de transfer.')
        if path.startswith('/api/v2/home/') and '/oauth/' not in path:
            suffix = path[len('/api/v2/home/'):]
            home_meta = {
                'session': ('Citește sesiunea personală Acasă', 'HomeSession', 'available=false indică funcție neactivată, nu inventar pierdut. Sesiunea web include csrf; OAuth este fixat pe o familie și poate include scopes/grant_id. Fără autentificare session=null. Nu se returnează preferințe de sănătate.'),
                'login/request': ('Solicită legătura de conectare prin email', 'HomeLoginRequested', 'Trimite email în corpul POST din aceeași origine. Legătura unică expiră în 15 minute; tokenul călătorește în fragmentul URL, apoi în POST. Răspunsul nu returnează tokenul și nu conectează dispozitivul.'),
                'login/preview': ('Verifică legătura de conectare fără consumare', 'HomeLoginPreview', 'Returnează email și expirare, fără conectare. Tokenul invalid, expirat sau consumat produce 410. Un scanner de email nu conectează automat dispozitivul.'),
                'login/confirm': ('Confirmă legătura și conectează dispozitivul', 'HomeConnected', 'Consumă o singură dată legătura și setează cookie oa_home HttpOnly, Secure, Path=/api/v2/home/. Contul/familia se creează la confirmare, nu la simpla cerere de email. Nu returnează secretul cookie-ului în JSON.'),
                'changes': ('Citește schimbările inventarului personal', 'HomeChanges', 'Cursor numeric after, independent de cursorul ID al producătorului. Returnează snapshoturi curente, tombstones și istoric imuabil; continuă cu cursor până când has_more=false. Nu sincronizează lista de cumpărături sau preferințele de alergeni.'),
                'sync': ('Sincronizează atomic inventarul și istoricul', 'HomeSyncResult', 'Maximum 50 operații și 50 evenimente history. event_id este cheia stabilă; expected_revision previne suprascrierea concurentă. La conflict întoarce 409 cu structura HomeSyncResult, fără aplicare parțială a operațiilor noi. Același ID cu alte date produce eroare standard 409.'),
                'members': ('Listează membrii familiei curente', page('HomeMember', False), 'Lista autorizată include email și rol, maximum 100 rânduri; nu este publică și nu folosește cursor.'),
                'households': ('Listează familiile accesibile', page('HomeHousehold', False), 'Sesiunea personală poate vedea apartenențele proprii; un grant OAuth vede exclusiv familia aprobată. Maximum 100 rânduri, fără cursor.'),
                'invites': ('Creează o invitație de familie', 'HomeInvitation', 'Owner și home:manage pentru OAuth. Invitația expiră după 7 zile și poate adăuga un editor o singură dată; nu transferă proprietatea. URL-ul este confidențial și nu se jurnalizează.'),
                'invites/preview': ('Previzualizează invitația fără acceptare', 'HomeInvitationPreview', 'Returnează numai numele familiei; nu consumă invitația și nu adaugă un membru. Tokenul indisponibil produce 410.'),
                'join': ('Acceptă invitația de familie', 'HomeSelected', 'Numai sesiunea personală, aceeași origine și X-Home-CSRF. Grantul OAuth nu poate trece la altă familie. Invitația se consumă o singură dată; maximum 20 membri.'),
                'switch': ('Selectează familia sesiunii personale', 'HomeSelected', 'Nu creează o apartenență. Grantul OAuth nu poate schimba familia; aplicația cere o nouă autorizare pentru altă familie.'),
                'rename': ('Redenumește familia curentă', 'HomeRenamed', 'Numai owner, cu home:manage pentru OAuth; nu schimbă identitatea familiei sau granturile existente.'),
                'members/{id}/remove': ('Elimină un editor din familie', 'HomeRemoved', 'id este account_id, nu ID-ul dispozitivului. Ownerul nu se poate elimina singur. Copiile descărcate anterior nu pot fi retrase de pe dispozitiv.'),
                'devices/{id}/revoke': ('Revocă un dispozitiv propriu', 'Revoked', 'id este device_id. Invalidează și granturile legate de dispozitiv; o aplicație OAuth poate revoca numai dispozitivul său.'),
                'logout': ('Deconectează dispozitivul Acasă', 'Revoked', 'Revocă dispozitivul curent și granturile sale, expiră cookie-ul personal. Nu șterge inventarul local sau contul producătorului.'),
                'grants': ('Listează aplicațiile autorizate pentru Acasă', page('HomeGrant', False), 'Exclusiv sesiunea personală; un token OAuth nu poate administra alte granturi. Maximum 100, fără cursor. scopes_json este șir JSON pentru compatibilitate.'),
                'grants/{id}/revoke': ('Revocă autorizarea personală a unei aplicații', 'Revoked', 'Exclusiv sesiunea personală și X-Home-CSRF. Invalidează imediat access/refresh ale grantului, fără a șterge inventarul familiei.'),
                'receipts/preview': ('Citește bonul digital confidențial', 'Receipt', 'Acces prin token-capability de 64 caractere în corpul POST, nu în query. Răspuns no-store; nu adaugă automat alimente și nu acordă acces la inventarul familiei.'),
            }
            if suffix == 'photos/{id}':
                return ('Încarcă fotografia privată' if method == 'post' else 'Descarcă fotografia privată', 'HomePhotoStored' if method == 'post' else None, 'JPEG brut, maximum 1.000.000 octeți și 6000×6000 pixeli; 32.000.000 octeți și 5.000 fotografii per familie. Același ID și aceiași octeți sunt idempotente; alți octeți cu același ID produc 409. Fotografiile nu devin publice.')
            return home_meta.get(suffix)
        if path.startswith('/api/v2/manage/connectors'):
            if path.endswith('/connectors'):
                return ('Creează un conector partener' if method == 'post' else 'Listează conectorii organizației', 'ConnectorCreated' if method == 'post' else page('Connector', False), 'catalog importă produse/loturi în ciornă; retail_receipt creează bonuri confidențiale. Lista are maximum 200 rânduri, fără cursor. Niciun tip nu acordă publicare sau mandat GS1.')
            if path.endswith('/mappings'):
                return ('Adaugă o versiune imutabilă de mapare' if method == 'post' else 'Listează versiunile mapării', 'MappingCreated' if method == 'post' else page('ConnectorMapping', False), 'Maparea este declarativă și aparține conectorului. version_key este unic; o modificare cere o versiune nouă. Lista are maximum 200 rânduri, fără cursor; mapping este obiect decodat.')
            if path.endswith('/preview'):
                return ('Validează maparea unui eveniment fără scrieri', 'ConnectorPreview', 'Transformă payload în forma canonică și raportează amprenta mapării. Nu creează produse, loturi sau bonuri și nu cere Idempotency-Key. Validarea nu ține loc de aprobare pentru ingest.')
            if path.endswith('/ingest'):
                return ('Importă atomic un eveniment al partenerului', 'ConnectorIngested', 'Cere Idempotency-Key, event_key și external_id stabile. product creează produs/articol dacă nu există asocierea, apoi etichetă draft; batch cere versiunea publicată explicită; receipt întoarce un URL confidențial. Nu rescrie versiuni sau loturi existente.')
        if path == '/api/v2/public/food':
            return ('Citește profilul alimentar al codului scanat', 'ConsumerProduct', 'Acceptă GTIN, adresă nativă sau Digital Link. Returnează date aprobate ori valori unknown explicite. 200 cu source.kind=unavailable nu înseamnă că produsul a fost găsit. Un GTIN generic nu identifică lotul; sursele externe nu sunt interogate în această versiune. Exemplul PBT este simulat, cu date alimentare nevalidate.')
        if path == '/api/v2/public/capabilities':
            return ('Citește capabilitățile aplicației Acasă', 'ConsumerCapabilities', 'Valorile reflectă instalarea curentă; false nu este o promisiune de funcționalitate. Inventarul local nu depinde de cloud, OCR sau AI.')
        if path.startswith('/oauth/') or path == '/api/v2/home/oauth/authorize':
            if path.endswith('/token'):
                return ('Schimbă codul PKCE sau rotește refresh tokenul', 'OAuthTokenResponse', 'Acceptă JSON și formular application/x-www-form-urlencoded. authorization_code cere code, redirect_uri identic și code_verifier; refresh_token cere refresh_token. Access token maximum 900 secunde; refresh și grant au expirări proprii. Reutilizarea refresh-ului consumat revocă grantul, deci serializează refresh-ul.')
            if path.endswith('/revoke'):
                return ('Revocă grantul OAuth prin token', obj({}), 'client_id și token în corp. Revocă întregul grant pentru access/refresh cunoscut; tokenul necunoscut întoarce tot 200 cu {} pentru a nu confirma existența sa. Nu cere client_secret.')
            return ('Deschide consimțământul OAuth în browser', None, 'Deschide această adresă în browserul sistemului. GET nu acordă acces. Persoana alege explicit organizația sau familia și aprobă; callback-ul primește code, state și iss. Nu trimite parola utilizatorului din aplicație. PKCE S256 și callback exact sunt obligatorii.')
        if path in ['/api/manifest.json', '/.well-known/oauth-authorization-server', '/api/v2/public/status', '/.well-known/gs1resolver', '/resolver/contexts', '/resolver/linkset-context']:
            return discovery(path)
        if path.startswith('/api/sdk/') or path == '/api/guide.md':
            return ('Descarcă ' + {'python': 'clientul Python', 'javascript': 'clientul JavaScript', 'kotlin': 'exemplul Android Kotlin', 'guide.md': 'ghidul de integrare Markdown'}[path.rsplit('/', 1)[-1]], None, 'Conținut text public, fără credențiale. Python și JavaScript au teste de client; Kotlin este exemplu sursă, nu un APK compilat sau publicat. Păstrează tokenurile în stocarea sigură a aplicației.')
        if path.startswith('/01/'):
            return ('Rezolvă identificatorul GS1 ' + ('de lot' if '/10/' in path else 'cu serie' if '/21/' in path else 'cu TPX' if '/235/' in path else 'de variantă' if '/22/' in path else 'de produs'), None, 'Nu este o simplă redirecționare: selectează destinații după calificatori, linkType, limbă, format și context. implicit poate răspunde 307; linkType=linkset sau Accept: application/linkset+json returnează setul de legături. 300 prezintă alegeri; 404 poate include linkset cu alternative. Nu confirma un lot dintr-un cod generic. Vezi profilul pentru combinații, compresie și EPC testate.')
        raise AssertionError('Reference metadata missing: ' + method + ' ' + path)

    def discovery(path):
        # Extensible metadata maps have declared keys and explicit value types.
        url = {'type': 'string', 'format': 'uri'}
        endpoint = obj({'name': S(190), 'url': url})
        if path == '/api/v2/public/status':
            return ('Verifică disponibilitatea serviciului', 'ServiceStatus', 'Verifică legătura la baza de date. 503 înseamnă indisponibilitate; ai_required=false confirmă independența față de serviciul AI.')
        if path == '/api/manifest.json':
            schema = obj({'schema_version': {'const': 'openaliment.api-manifest.v1'}, 'name': S(80), 'base_url': url, 'documentation': url, 'documentation_index': url, 'llms': url, 'guide': url, 'sdk': obj({k: url for k in ['python', 'javascript', 'kotlin']}), 'openapi': arr(endpoint), 'schemas': arr(obj({'name': S(190), 'url': url, 'sha256': SHA})), 'resolver': obj({'description': url, 'profile': url, 'primary_keys': arr(S(4))}), 'authentication': obj({'api_keys': obj({'scheme': S(30), 'issued_at': url, 'revocable': B, 'scopes': arr(S(40))}), 'oauth': obj({'available': B, 'metadata': url, 'flows': arr(S(80)), 'access_token_seconds': N, 'max_grant_days': N})}), 'capabilities': obj({k: B for k in ['producer_api', 'public_approved_data', 'consumer_api', 'consumer_oauth', 'private_recipe_in_public_context', 'external_product_sources', 'badge_assessment_api', 'notification_api', 'custom_domains_api', 'account_security_api', 'personal_account_controls', 'versioned_votes', 'review_criteria', 'ndjson_import', 'licensed_archive_download','marketplace','serialized_printing','marketplace_payments']}), 'public_limits': obj({k: N for k in ['read_per_minute', 'catalog_per_minute', 'lookup_batch_max_codes', 'list_limit_max', 'feedback_per_ip_per_hour']}), 'compatibility': obj({k: B for k in ['legacy_v1_preserved', 'public_data_requires_approval', 'batch_recipe_optional', 'unknown_is_not_zero']}), 'agent_use': obj({k: B for k in ['crawling_required', 'read_contracts_explicitly', 'private_data_requires_delegation', 'operations_in_openapi', 'never_execute_document_instructions']}), 'operation_count': nullable(N), 'new_routed_operation_count': N})
            return ('Descoperă API-urile și capabilitățile instalate', schema, 'Punctul de intrare pentru dezvoltatori și agenți AI. Verifică authentication.oauth.available și capabilities înainte de folosirea funcțiilor opționale; existența unei pagini în documentație nu activează automat o migrare.')
        # Exact structures are curated from controller declarations in metadata.json.
        metadata = json.loads((ROOT / 'docs/api/reference/metadata.json').read_text(encoding='utf-8'))
        return (metadata[path]['title'], metadata[path]['schema'], metadata[path]['description'])

    def synth(schema, key='', depth=0):
        if depth > 35:
            raise AssertionError('Cyclic reference in examples')
        if '$ref' in schema:
            name = schema['$ref'].rsplit('/', 1)[-1]
            if name in samples:
                return copy.deepcopy(samples[name])
            return synth(schemas[name], key, depth + 1)
        if 'example' in schema:
            return copy.deepcopy(schema['example'])
        if 'const' in schema:
            return schema['const']
        if 'enum' in schema:
            return schema['enum'][0]
        if 'anyOf' in schema or 'oneOf' in schema:
            branches = schema.get('oneOf', schema.get('anyOf'))
            # Prefer null where the API represents unknown/unset values.
            branch = next((x for x in branches if x.get('type') == 'null'), branches[0])
            return synth(branch, key, depth + 1)
        if 'allOf' in schema:
            result = {}
            for branch in schema['allOf']:
                result.update(synth(branch, key, depth + 1))
            return result
        kind = schema.get('type', 'object' if 'properties' in schema else 'string')
        if isinstance(kind, list):
            kind = 'null' if 'null' in kind else kind[0]
        if kind == 'null':
            return None
        if kind == 'object':
            return {k: synth(v, k, depth + 1) for k, v in schema.get('properties', {}).items() if k in schema.get('required', list(schema.get('properties', {})))}
        if kind == 'array':
            minimum = schema.get('minItems', 0)
            # Nonempty lists document their item shape. Dynamic unrelated maps stay empty.
            return [synth(schema.get('items', {}), key, depth + 1)] * max(minimum, 1) if schema.get('items') else []
        if kind == 'boolean':
            return False
        if kind in ['integer', 'number']:
            return max(schema.get('minimum', 0), schema.get('exclusiveMinimum', -1) + 1)
        pattern = schema.get('pattern', '')
        if '[a-f0-9]{32}' in pattern:
            return hashlib.sha256(('openaliment-doc-' + key).encode()).hexdigest()[:32]
        if '[a-f0-9]{64}' in pattern:
            return hashlib.sha256(('openaliment-doc-' + key).encode()).hexdigest()
        if schema.get('format') == 'date-time':
            return ISO
        if schema.get('format') == 'date':
            return '2026-10-24'
        if schema.get('format') == 'email':
            return 'dezvoltator@example.invalid'
        if schema.get('format') == 'uri' or key.endswith('_url'):
            return 'https://example.invalid/resursa'
        values = {'name': 'Iaurt natural', 'category': 'lactate', 'version_key': 'E1', 'ingredients': 'Lapte, culturi lactice.', 'allergens': 'Lapte.', 'net_quantity': '200 g', 'operator': 'Producător exemplu, adresa declarată', 'lot_code': 'EXEMPLU-001', 'gtin14': '04006381333931', 'gtin_original': '4006381333931', 'gtin_status': 'unverified', 'identifier_status': 'unverified', 'state': 'draft', 'visibility': 'private', 'rights_status': 'unconfirmed', 'technical_status': 'experimental', 'status': 'pending', 'kind': 'review', 'role': 'owner', 'mime': 'text/plain', 'job_type': 'feedback_triage', 'output_audience': 'public', 'action': 'label.created', 'event_type': 'published', 'display_name': 'Producător exemplu', 'producer': 'Producător exemplu', 'scopes_json': '["read"]', 'redirect_uris_json': '["https://example.invalid/callback"]', 'snapshot_json': '{}', 'provenance_json': '{"method":"upload"}', 'auth_type': 'api_key', 'token_type': 'Bearer', 'scope': 'read', 'access_token': 'oa_at_' + '0' * 64, 'refresh_token': 'oa_rt_' + '0' * 64, 'token': 'oa_' + '0' * 64, 'csrf': '0' * 64, 'request_id': '0' * 32, 'code_challenge_method': 'S256', 'response_type': 'code'}
        values.update({'current_password':'CURRENT_PASSWORD_FROM_YOUR_ACCOUNT','new_password':'ParolaNoua-Exemplu-2026!','confirmation':'ParolaNoua-Exemplu-2026!'})
        value = values.get(key, 'exemplu')
        if re.search(r'\[0-9\]\{14\}', pattern):
            value = '04006381333931'
        minimum = schema.get('minLength', 0)
        if len(value) < minimum:
            value += 'x' * (minimum - len(value))
        return value[:schema.get('maxLength', len(value))]

    # Descriptions and examples shared by all parameter locations.
    param_descriptions = {
        'cursor': 'Cursor exclusiv: trimite next_cursor din pagina anterioară. Omite la prima cerere. Ordinea ID-urilor este lexicografică, nu cronologică.',
        'limit': 'Număr maxim de rezultate; vezi minimum, maximum și default. Nu este o mărire a cotei de cereri.',
        'page': 'Pagina numerică, începând cu 1; numai compatibilitate v1.',
        'q': 'Prefix literal al numelui din eticheta aprobată; nu caută rețete, text integral sau metadate interne.',
        'producer': 'ID-ul organizației producătoare, 32 caractere hexazecimale; nu denumirea sa.',
        'gtin': FIELDS['gtin'],
        'after': 'Cursor numeric de sincronizare, inițial 0. Nu folosi cursorul hexazecimal al colecțiilor producătorului.',
        'format': 'json implicit; ndjson pentru export în flux. Nu schimbă drepturile sau conținutul autorizat.',
        'external_id': 'Cursor extern exclusiv pentru bindings; trimite next_external_id din pagina anterioară.',
        'Idempotency-Key': 'Cheie stabilă ASCII, 8–128 caractere [A-Za-z0-9_.:-]. Persist-o cu operația; retrimite aceeași cheie, cale și corp după timeout. Refolosirea cu alte date produce 409. La import este necesară numai pentru dry_run=false.',
        'X-CSRF-Token': 'Tokenul csrf al sesiunii proprii; obligatoriu la scrieri cu cookie oa_session. Nu se cere când operația acceptă Bearer și este folosit un Bearer valid.',
        'X-Home-CSRF': 'Tokenul csrf din GET home/session pentru cookie oa_home. Nu se cere unui grant OAuth Bearer.',
        'X-Status-Token': 'Secretul status_token primit o singură dată la trimiterea feedbackului. Nu este o cheie API.',
        'code': 'Cod scanat ca șir. Codifică parametrul query cu URLSearchParams/urlencode o singură dată, păstrând URL-ul Digital Link interior.',
        'linkType': 'Relația cerută, de exemplu gs1:pip, sau linkset pentru toate destinațiile aplicabile.',
        'context': FIELDS['context'],
        'Accept': 'Negociere de format HTTP; application/linkset+json cere linkset, text/html permite pagina/alegerile.',
        'Accept-Language': 'Preferințe de limbă HTTP, de exemplu ro, en;q=0.8.',
        'lot': 'AI (10), lotul exact. Codifică segmentul o singură dată: A/B+00%? devine A%2FB%2B00%25%3F.',
        'variant': 'AI (22), varianta de consum; se combină cu lotul în ordinea 22 apoi 10.',
        'serial': 'AI (21), numărul de serie; nu se combină în acest profil cu varianta și lotul.',
        'tpx': 'AI (235), extensia terței părți; nivel separat de variantă/lot/serie.',
        'state': 'Valoare aleatoare opacă păstrată de aplicație și comparată identic la callback; protejează asocierea încercării OAuth.',
        'response_type': 'Valoarea code. Fluxul implicit nu este implementat.',
        'code_challenge': 'SHA-256(code_verifier), codificat base64url fără padding; 43 caractere.',
        'code_challenge_method': 'Valoarea S256; plain nu este acceptat.',
    }
    request_examples = {
        '/api/v2/manage/connectors': {'name': 'ERP demonstrativ', 'kind': 'catalog'},
        '/api/v2/manage/connectors/{connector_id}/mappings': {'version_key': 'M1', 'mapping': {'schema_version': 'openaliment.mapping.v1', 'fields': {'/trade_external_id': {'from': '/articol', 'op': 'text', 'required': True}, '/label_version_key': {'value': 'E1'}, '/lot_code': {'from': '/lot', 'op': 'text', 'required': True}}}},
        '/api/v2/manage/connectors/{connector_id}/preview': {'mapping_id': 'a' * 32, 'operation': 'batch', 'payload': {'articol': 'ERP-001', 'lot': 'EXEMPLU-001'}},
        '/api/v2/manage/connectors/{connector_id}/ingest': {'mapping_id': 'a' * 32, 'operation': 'batch', 'payload': {'articol': 'ERP-001', 'lot': 'EXEMPLU-001'}, 'event_key': 'eveniment-lot-001', 'external_id': 'ERP-LOT-001'},
        '/api/v2/home/login/request': {'email': 'client@example.invalid'},
        '/api/v2/home/login/confirm': {'token': '0' * 64, 'device_name': 'Telefonul meu'},
        '/api/v2/home/sync': {'operations': [], 'history': []},
        '/api/v2/home/rename': {'name': 'Acasă'},
        '/api/v2/home/switch': {'household_id': 'a' * 32},
        '/oauth/revoke': {'client_id': 'a' * 32, 'token': 'oa_at_' + '0' * 64},
    }
    schema_by_action = {a: v[1] for a, v in actions.items()}
    for path, methods in spec['paths'].items():
        for method, op in methods.items():
            result = classify(path, method, op) or special(path, method, op)
            title, response_schema, description = result
            if isinstance(response_schema,dict):annotate(response_schema)
            if op['operationId'] in ['communitySubmitFeedback', 'identityRegister']:
                op['responses']['201'] = op['responses'].pop('200')
            op['summary'] = title
            op['description'] = description
            op['x-example-data'] = 'synthetic; not a production record or valid credential'
            if path.startswith('/api/v1/'):
                op['x-compatibility'] = 'legacy-v1'
                op['description'] += ' API v1 este păstrat pentru compatibilitate; integrările noi folosesc v2.'
            if '/manage/' in path and 'x-auth-context' not in op:
                op['x-auth-context'] = 'producer'
                op['x-required-scope'] = 'export' if path.endswith('/export') else 'import' if '/import' in path else op.get('x-required-scope', 'read' if method == 'get' else 'write')
            if path in ['/api/v2/home/join', '/api/v2/home/switch', '/api/v2/home/grants', '/api/v2/home/grants/{id}/revoke'] or '/home/account/action/' in path:
                op['security'] = [{'HomeCookie': []}]
            if path.endswith('/vote') and method == 'delete':
                op.setdefault('parameters', []).append({'name':'expected_mutation_version','in':'query','schema':copy.deepcopy(N),'example':1,'description':'Versiunea stării votului citită înainte de retragere; o versiune veche este refuzată cu 409.'})
            if path.startswith('/api/v2/home/') and '/oauth/' not in path:
                op['x-auth-context'] = 'home' if op.get('security') else 'public'
            if path.startswith('/oauth/') and path.endswith('/authorize') or path.endswith('/home/oauth/authorize'):
                op['x-auth-context'] = 'browser-flow'
                for parameter in op['parameters']:
                    name=parameter['name']
                    parameter['schema']=copy.deepcopy({'client_id':ID,'redirect_uri':{'type':'string','maxLength':2048},'response_type':{'type':'string','const':'code'},'state':{'type':'string','minLength':8,'maxLength':512},'code_challenge':{'type':'string','pattern':'^[A-Za-z0-9_-]{43}$'},'code_challenge_method':{'type':'string','const':'S256'},'scope':S(500)}[name])
                    parameter['example']={'client_id':'a'*32,'redirect_uri':'ro.exemplu.aplicatie:/callback','response_type':'code','state':'STATE_ALEATOR_PASTRAT_PRIVAT','code_challenge':__import__('base64').urlsafe_b64encode(hashlib.sha256(('v'*64).encode()).digest()).rstrip(b'=').decode(),'code_challenge_method':'S256','scope':'home:read home:write' if '/home/' in path else 'read'}[name]
            if path in ['/api/v2/auth/token']:
                op['security'] = [{'Bearer': []}]
            if path == '/api/v2/public/trade-items' or path == '/api/v2/public/products/{id}/trade-items':
                # These are checked by the catalog handler, even for the product child route.
                declared = {p['name'] for p in op.get('parameters', [])}
                for name, schema in [('q', S(120)), ('category', S(80)), ('producer', ID), ('gtin', S(14))]:
                    if name not in declared:
                        op.setdefault('parameters', []).append({'name': name, 'in': 'query', 'schema': copy.deepcopy(schema)})
                for param in op['parameters']:
                    if param['name'] == 'producer':
                        param['schema'] = copy.deepcopy(ID)
            if path == '/api/v2/public/trade-items/{id}/ratings':
                op.setdefault('parameters', []).extend([{'name':k,'in':'query','schema':copy.deepcopy(ID),'example':'a'*32} for k in ['label_id','batch_id']])
            if path == '/api/v2/public/products/{id}/recipe-versions':
                op.setdefault('parameters', []).append({'name': 'version_key', 'in': 'query', 'schema': S(60)})
            if path == '/api/v1/products':
                op['parameters'] = [{'name': 'page', 'in': 'query', 'schema': {'type': 'integer', 'minimum': 1, 'maximum': 100000, 'default': 1}}, {'name': 'limit', 'in': 'query', 'schema': {'type': 'integer', 'minimum': 1, 'maximum': 100, 'default': 20}}]
            if path.startswith('/api/v2/home/') and method == 'get':
                op['parameters'] = [p for p in op.get('parameters', []) if p['name'] != 'X-Home-CSRF']
            for param in op.get('parameters', []):
                name = param['name']
                param['description'] = param_descriptions.get(name, FIELDS.get(name, ('ID-ul ' + ('conectorului' if name == 'connector_id' else 'resursei indicate în cale') + '; exclusiv din spațiul autorizat.') if name in ['id', 'connector_id'] else 'Filtru exact pentru ' + name + '; se combină prin AND cu celelalte filtre declarate.'))
                param.setdefault('example', synth(param.get('schema', {}), name))
                if name == 'gtin' and path.startswith('/01/'):
                    param['example'] = '09520000000011'
                    param['description'] = 'AI (01), exact 14 cifre, cu cifra de control validă. Exemplul 952 este exclusiv demonstrativ.'
                if name == 'limit':
                    param['example'] = param['schema'].get('default', 20)
                if name == 'lot':
                    param['example'] = 'DEMO-PBT-01'
                if name == 'code':
                    param['example'] = '9520000000011'
            for mime, media in op.get('requestBody', {}).get('content', {}).items():
                # Resolve external request schemas only in the aggregate, retaining behavior.
                if media.get('schema', {}).get('$ref', '').startswith('/api/schemas/'):
                    uri = media['schema']['$ref']
                    file, _, fragment = uri.partition('#/')
                    media['schema'] = local_schema(Path(file).name, fragment)
                annotate(media.get('schema', {}))
                if 'example' not in media:
                    example = request_examples.get(path)
                    if example is None and path.endswith(('/preview', '/join')) and '/home/' in path:
                        example = {'token': '0' * 64}
                    media['example'] = copy.deepcopy(example) if example is not None else synth(media['schema'])
            # Each successful representation carries an explicit example, including text/binary.
            for status, response in op['responses'].items():
                if not status.startswith('2'):
                    continue
                response['description'] = ('Cererea a fost acceptată pentru procesare ulterioară.' if status == '202' else 'Operația s-a încheiat; reluarea idempotentă poate întoarce aceeași resursă.' if status == '201' else 'Rezultatul autorizat al cererii.')
                if response_schema is not None:
                    response['content'] = {'application/json': {'schema': ref(response_schema) if isinstance(response_schema, str) else response_schema}}
                for mime, media in response.get('content', {}).items():
                    if mime.endswith('json') or mime == 'application/ld+json':
                        media['example'] = synth(media.get('schema', {}))
                    elif mime == 'application/x-ndjson':
                        header = {'schema_version': 'openaliment.transfer.stream.v1', 'source_base_url': BASE, 'exported_at': ISO, 'organization': {'id': 'a' * 32, 'display_name': 'Producător exemplu'}}
                        line = json.dumps(header, ensure_ascii=False, separators=(',', ':')) + '\n'
                        media['example'] = line + json.dumps({'end': True, 'records': 0, 'sha256': hashlib.sha256(line.encode()).hexdigest()}, separators=(',', ':')) + '\n'
                        media['x-example-note'] = 'Export demonstrativ fără rânduri; SHA-256 acoperă octeții UTF-8 ai primei linii, inclusiv LF.'
                    elif mime.startswith('image/') or mime in ['application/octet-stream','application/zip']:
                        media['example'] = '(octeți binari ai fișierului autorizat)'
                    else:
                        media['example'] = '# Conținut text public\n'
            if path.endswith('/export'):
                # Replacing the JSON projection above must not erase the streamed representation.
                success = op['responses']['200']
                header = {'schema_version': 'openaliment.transfer.stream.v1', 'source_base_url': BASE, 'exported_at': ISO, 'organization': {'id': 'a' * 32, 'display_name': 'Producător exemplu'}}
                line = json.dumps(header, ensure_ascii=False, separators=(',', ':')) + '\n'
                stream = line + json.dumps({'end': True, 'records': 0, 'sha256': hashlib.sha256(line.encode()).hexdigest()}, separators=(',', ':')) + '\n'
                success['content']['application/x-ndjson'] = {'schema': {'type': 'string', 'description': 'Unele documente sunt base64 în rânduri. Digestul exclude propria linie finală; include LF pentru toate liniile precedente.'}, 'example': stream}
            if path == '/oauth/revoke':
                op['responses']['200'].pop('content', None)
                op['responses']['200']['description'] = 'Revocare procesată, corp gol (inclusiv pentru token necunoscut). Nu apela response.json().'
                op['description'] = op['description'].replace('200 cu {}', '200 cu corp gol')
            if path.endswith('/authorize'):
                op['responses']['400']['content']['application/json']['example'] = {'error_code': 'invalid_request', 'message': 'Parametrii de autorizare nu sunt valizi.', 'request_id': '0' * 32}
                op['responses']['200']['content']={'text/html':{'schema':{'type':'string','description':'Pagină de consimțământ sau alegere a conectării. Exemplul este un fragment ilustrativ, nu un formular reutilizabil.'},'example':'<!doctype html>\n<html lang="ro"><title>Autorizează aplicația</title><h1>Autorizează aplicația</h1><p>Alege contul și permisiunile în browser.</p></html>'}}
            if path == '/resolver/linkset-context':
                content = op['responses']['200'].pop('content')
                media = content['application/json']
                media['example'] = json.loads((output / 'gs1/linkset-context.json').read_text(encoding='utf-8')) if (output / 'gs1/linkset-context.json').exists() else json.loads((ROOT / 'contracts/gs1/linkset-context.json').read_text(encoding='utf-8'))
                op['responses']['200']['content'] = {'application/ld+json': media}
            if path.startswith('/01/'):
                # Resolver errors deliberately have a smaller envelope than the REST API.
                target = obj({'href': {'type': 'string', 'format': 'uri'}, 'title': S(180), 'type': S(100), 'hreflang': arr(S(35))}, ['href'])
                linkset = obj({'linkset': arr(obj({'anchor': {'type': 'string', 'format': 'uri'}, 'itemDescription': S(2000)}, ['anchor'], target))})
                anchor = BASE + '/01/09520000000011' + ('/10/DEMO-PBT-01' if '/10/' in path else '')
                example = {'linkset': [{'anchor': anchor, 'itemDescription': 'Pâine Bună Tehnic — demonstrație', 'https://ref.gs1.org/voc/pip': [{'href': BASE + '/demo/paine-buna-tehnic', 'title': 'Pâine Bună Tehnic'}]}]}
                # Dynamic keys are link-relation URIs, each mapped to an array of typed targets.
                linkset['properties']['linkset']['items']['additionalProperties'] = arr(target)
                for status in ['200', '300', '404']:
                    op['responses'][status] = {'description': 'Linkset aplicabil.' if status == '200' else 'Mai multe destinații echivalente.' if status == '300' else 'Relația nu este disponibilă (linkset cu alternative) sau identificator necunoscut (eroare JSON).', 'content': {'application/linkset+json': {'schema': linkset, 'example': example}}}
                for status in ['400', '404', '429', '503']:
                    op['responses'][status].setdefault('content', {})['application/json'] = {'schema': obj({'error_code': S(80), 'message': S()}), 'example': {'error_code': 'not_found' if status == '404' else 'invalid_uri', 'message': 'Identificatorul sau relația nu este disponibilă.'}}
            # Document 503 status body rather than implying an error envelope for health checks.
            if path == '/api/v2/public/status':
                op['responses']['503'] = {'description': 'Baza de date nu este disponibilă.', 'content': {'application/json': {'schema': ref('ServiceStatus'), 'example': {'status': 'unavailable', 'checked_at': ISO, 'ai_required': False}}}}
            if not path.startswith('/01/') and not path.endswith('/authorize'):
                errors(path, method, op)
            if path == '/api/v2/home/sync':
                op['responses']['409']['content']['application/json'] = {'schema': {'oneOf': [ref('HomeSyncResult'), ref('Error')]}, 'examples': {'revision_conflict': {'summary': 'Revizie schimbată pe alt dispozitiv; nicio operație nouă aplicată.', 'value': {'accepted': [], 'history_accepted': [], 'conflicts': [{'item_id': 'a' * 32, 'current': {'server_revision': 2, 'seq': 4, 'deleted': True, 'item': None}}], 'cursor': 4, 'atomic': True}}, 'reused_event': {'summary': 'Același event_id cu alt payload.', 'value': {'error_code': 'home_idempotency', 'message': 'Evenimentul există cu alte date.', 'request_id': '0' * 32}}}}
            for response in op['responses'].values():
                for media in response.get('content',{}).values():annotate(media.get('schema',{}))
            op['x-reference-complete'] = True
    # Keep obsolete generic types for compatibility with consumers referring to components,
    # but no operation uses them as an unconstrained success response.
    for name, schema in schemas.items():
        annotate(schema)
        if name not in ['Record', 'Collection', 'MutationResult', 'PublicProjection', 'ApiKeyResult']:
            schema.setdefault('description', 'Structura ' + name + '. Exemplele sunt sintetice; permisiunile și stările reale se verifică la cerere.')


def errors(path, method, op):
    """Applicable failure classes with recovery; source-specific domain codes remain JSON."""
    public = not op.get('security')
    oauth = path in ['/oauth/token', '/oauth/revoke']
    home = path.startswith('/api/v2/home/')
    write = method in ['post', 'put', 'delete', 'patch']
    statuses = {'400', '429', '503'}
    if not public:
        statuses |= {'401', '403'}
    if '{' in path:
        statuses.add('404')
    if op.get('parameters') or op.get('requestBody'):
        statuses.add('422')
    if write:
        statuses |= {'403', '413', '422'}
        if any(p['name'] == 'Idempotency-Key' for p in op.get('parameters', [])) or home or method in ['put', 'delete']:
            statuses.add('409')
        if not public and any('Cookie' in k or 'Session' in k for s in op.get('security', []) for k in s):
            statuses.add('419')
    statuses |= {s for s in op['responses'] if s in ['410']}
    if path.endswith('/ai-jobs') and method == 'post':
        statuses |= {'404'}
    if path.endswith('/access-tokens') and method == 'post':
        statuses |= {'409'}
    if any(family in path for family in ['/marketplace/','/printing/','/printing-agent/']) and (write or path.endswith('/download')):
        statuses.add('409')
    if path.endswith('/export'):
        statuses.add('413')
    if path=='/api/v2/auth/token':
        statuses.add('422')
    if path == '/api/v2/identity/sessions' or path == '/api/v2/identity/registrations':
        statuses |= {'419'}
        if path.endswith('sessions'):
            statuses.add('401')
        else:
            statuses.add('409')
    if oauth:
        statuses = {'400', '429', '503'}
    desc = {
        '400': ('json' if write else 'credential_in_url', 'Cerere neinterpretabilă, JSON invalid sau credential în URL.', 'Corectează formatul; trimite credentialele în antet/corp conform fluxului, fără retry automat.'),
        '401': ('invalid_token', 'Credential absent, expirat, revocat sau sesiune neconectată.', 'Reautorizează utilizatorul sau schimbă cheia; retry-ul identic nu repară autentificarea.'),
        '403': ('scope', 'Rol, scope, apartenență sau origine insuficientă pentru această operație.', 'Verifică permisiunea ' + (op.get('x-required-scope') or 'contului') + ' și rolul curent; nu cere scopes suplimentare fără acord.'),
        '404': ('not_found', 'Resursa nu există, nu este publicată sau nu aparține spațiului autorizat.', 'Verifică ID-ul, resursa părinte și publicarea; răspunsul nu confirmă existența în altă organizație.'),
        '409': ('idempotency_conflict', 'Conflict cu o versiune/stare existentă sau cheie refolosită cu alte date.', 'Reîncarcă starea. Pentru timeout retrimite aceeași cheie și același corp; pentru o operație nouă folosește o cheie nouă.'),
        '410': ('login_link' if '/login/' in path else 'home_invite', 'Legătura a expirat, a fost consumată sau nu este validă.', 'Solicită o legătură nouă; nu retrimite la nesfârșit tokenul consumat.'),
        '413': ('body_size', 'Corpul, fișierul sau cota de resurse depășește limita aplicabilă.', 'Micșorează cererea ori cere operatorului ajustarea cotei; la upload verifică și limitele PHP/cPanel.'),
        '419': ('home_csrf' if home else 'csrf', 'Token CSRF absent sau necorespunzător sesiunii web.', 'Citește din nou sesiunea și folosește CSRF nou cu același cookie; Bearer nu folosește CSRF de browser.'),
        '422': ('validation', 'Câmp, relație între resurse sau condiție de publicare nevalidă.', 'Corectează datele și condițiile descrise în operație. Nu relua automat același corp invalid.'),
        '429': ('rate_limit', 'Buget de cereri depășit.', 'Respectă Retry-After dacă există; altfel aplică backoff cu jitter. La scrieri păstrează cheia de idempotentă.'),
        '503': ('service_unavailable', 'Funcție neactivată sau serviciu temporar indisponibil.', 'Verifică manifestul și starea serviciului. Reîncearcă limitat cu backoff; pentru ai_disabled nu bloca citirea QR.'),
    }
    # Operation-specific domain explanations (in addition to shared HTTP policy).
    rules = {
        'manageTradeCreate': {'409': ('gtin_claim', 'GTIN este deja revendicat; administratorul trebuie să clarifice mandatul.')},
        'manageLabelPublish': {'422': ('document_rights', 'Un document selectat nu are drepturile și publicarea aprobate.'), '409': ('state', 'Versiunea nu mai este draft; nu poate fi publicată din nou cu o operație nouă.')},
        'manageBatchCreate': {'422': ('label', 'Eticheta nu este published pentru același articol sau rețeta aparține altui produs.')},
        'manageBatchRelease': {'422': ('alert', 'Există alerte active sau eticheta aplicabilă nu este aprobată.'), '409': ('state', 'Starea curentă nu permite eliberarea lotului.')},
        'manageFeedbackModerate': {'422': ('quality', 'Feedbackul quality nu poate fi publicat; publicarea altor tipuri cere public_text.')},
        'manageResolverDraft': {'422': ('gtin_right', 'GTIN nu are drept de administrare verified sau metadatele destinației sunt incompatibile.'), '409': ('version_conflict', 'expected_version nu mai corespunde istoricului curent.')},
        'manageResolverPublish': {'409': ('default_conflict', 'Există deja o altă destinație implicită pentru aceiași calificatori.')},
        'manageQueueAiJob': {'503': ('ai_disabled', 'Analiza AI este dezactivată; citirea paginilor QR rămâne disponibilă.')},
        'manageCreateAccessToken': {'403': ('reauthentication', 'Autentificarea are peste 15 minute; confirmă current_password din sesiunea ownerului.')},
        'manageRotateAccessToken': {'403': ('reauthentication', 'Autentificarea are peste 15 minute; confirmă parola înainte de rotație.')},
    }
    original = op['responses']
    op['responses'] = {s: r for s, r in original.items() if int(s) < 400}
    for status in sorted(statuses, key=int):
        if path == '/api/v2/public/status' and status == '503':
            op['responses'][status] = original[status]
            continue
        code, cause, remedy = desc[status]
        if status in rules.get(op['operationId'], {}):
            code, cause = rules[op['operationId']][status]
        if oauth:
            code = 'invalid_grant' if status == '400' else 'temporarily_unavailable'
        envelope = {'error': code, 'error_description': cause, 'request_id': '0' * 32} if oauth else {'error_code': code, 'message': cause, 'request_id': '0' * 32}
        op['responses'][status] = {'description': cause + ' ' + remedy, 'x-recovery': remedy, 'content': {'application/json': {'schema': ref('OAuthError' if oauth else 'Error'), 'example': envelope}}, 'headers': {'X-Request-ID': {'description': 'ID de corelare pentru suport; nu include cheia sau corpul privat în sesizări.', 'schema': S(64)}}}
        if status == '429':
            op['responses'][status]['headers']['Retry-After'] = {'description': 'Secunde până la reîncercare, când limiterul care a respins cererea emite acest antet.', 'schema': N, 'example': 60}
