# Matricea acțiunilor API · comunitate, parteneri și învățare

109 operații. Generată din `contracts/program/routes.json`; fiecare operație are contract și probă HTTP în `tests/test_program_http.py`.

Regenerare: `python tools/build/program_matrix.py`. Verificare: aceeași comandă cu `--check`.

| Acțiune / handler | Metodă și adresă | Acces și scope | Schema cererii |
| --- | --- | --- | --- |
| Listează contribuitorii. `program.public_contributors` | `GET /api/v2/public/program/contributors` | `public` / `—` | `—` |
| Citește profilul public aprobat. `program.public_contributors_get` | `GET /api/v2/public/program/contributors/{id}` | `public` / `—` | `—` |
| Listează partenerii autorizați. `program.public_partners` | `GET /api/v2/public/program/partners` | `public` / `—` | `—` |
| Citește profilul public aprobat. `program.public_partners_get` | `GET /api/v2/public/program/partners/{id}` | `public` / `—` | `—` |
| Listează cursurile publicate. `program.public_courses` | `GET /api/v2/public/program/courses` | `public` / `—` | `—` |
| Citește profilul public aprobat. `program.public_courses_get` | `GET /api/v2/public/program/courses/{id}` | `public` / `—` | `—` |
| Citește ediția imuabilă a cursului. `program.public_course_release` | `GET /api/v2/public/program/course-releases/{id}` | `public` / `—` | `—` |
| Citește lecția și exercițiul formativ. `program.public_lesson` | `GET /api/v2/public/program/course-releases/{id}/lessons/{lesson_id}` | `public` / `—` | `—` |
| Verifică starea unei calificări personale. `program.public_credential` | `GET /api/v2/public/program/credentials/{id}` | `public` / `—` | `—` |
| Verifică autorizarea OpenAliment a firmei. `program.public_authorization` | `GET /api/v2/public/program/authorizations/{id}` | `public` / `—` | `—` |
| Creează cont personal fără organizație fictivă. `program.register` | `POST /api/v2/program/registrations` | `session-optional` / `—` | `ProgramRegistration` |
| Examinează solicitarea fără consumarea legăturii. `program.verification_preview` | `POST /api/v2/program/verifications/preview` | `session-optional` / `—` | `ProgramToken` |
| Confirmă explicit o legătură unică. `program.verification_confirm` | `POST /api/v2/program/verifications/confirm` | `session-optional` / `—` | `ProgramConfirmation` |
| Citește profilul personal și specializările. `program.me` | `GET /api/v2/program/me` | `program` / `learning:read` | `—` |
| Trimite dovada de email sau invitația. `program.verification_request` | `POST /api/v2/program/me/verifications` | `program-session` / `learning:read` | `ProgramVerification` |
| Exportă progresul și rezultatele proprii, fără cheia examenului. `program.export` | `GET /api/v2/program/me/export` | `program` / `learning:read` | `—` |
| Validează exportul; importul nu acordă calificări. `program.import_preview` | `POST /api/v2/program/me/import-preview` | `program` / `learning:write` | `ProgramImportPreview` |
| Importă numai pozițiile de lectură compatibile, fără rezultate sau calificări. `program.import_positions` | `POST /api/v2/program/me/imports` | `program` / `learning:write` | `ProgramImportPreview` |
| Schimbă parola contului personal și revocă accesurile vechi. `program.password_change` | `POST /api/v2/program/me/password` | `program-session` / `learning:read` | `ProgramPassword` |
| Revocă sesiunile și accesurile contului după confirmarea parolei. `program.access_revoke` | `POST /api/v2/program/me/access-revocations` | `program-session` / `learning:read` | `ProgramCurrentPassword` |
| Încarcă și reencodează o imagine cu drepturi confirmate. `program.asset_upload` | `POST /api/v2/program/assets` | `program` / `assets:write` | `ProgramAsset` |
| Citește metadatele imaginii proprii. `program.asset_get` | `GET /api/v2/program/assets/{id}` | `program` / `assets:read` | `—` |
| Retrage imediat imaginea proprie. `program.asset_withdraw` | `DELETE /api/v2/program/assets/{id}` | `program` / `assets:write` | `ProgramEmpty` |
| Listează înscrierile proprii. `program.enrollments` | `GET /api/v2/program/me/enrollments` | `program` / `learning:read` | `—` |
| Alege ediția și specializarea. `program.enroll` | `POST /api/v2/program/me/enrollments` | `program` / `learning:write` | `ProgramEnrollment` |
| Citește progresul înscrierii proprii. `program.enrollment_get` | `GET /api/v2/program/me/enrollments/{id}` | `program` / `learning:read` | `—` |
| Salvează progresul cu controlul reviziei. `program.progress` | `PUT /api/v2/program/me/enrollments/{id}/lessons/{lesson_id}` | `program` / `learning:write` | `ProgramProgress` |
| Verifică exercițiul formativ și salvează progresul. `program.practice` | `POST /api/v2/program/me/enrollments/{id}/lessons/{lesson_id}/practice` | `program` / `learning:write` | `ProgramPractice` |
| Începe sau reia examenul cu termen fix pe server. `program.attempt_start` | `POST /api/v2/program/me/attempts` | `program-session` / `learning:read` | `ProgramAttempt` |
| Reia examenul fără divulgarea cheii de corectare. `program.attempt_get` | `GET /api/v2/program/me/attempts/{id}` | `program-session` / `learning:read` | `—` |
| Salvează răspunsul la întrebarea proprie. `program.answer` | `PUT /api/v2/program/me/attempts/{id}/answers/{item_id}` | `program-session` / `learning:read` | `ProgramAnswer` |
| Predă tentativa și aplică pragurile exacte. `program.attempt_submit` | `POST /api/v2/program/me/attempts/{id}/submit` | `program-session` / `learning:read` | `ProgramEmpty` |
| Listează rezultatele proprii. `program.results` | `GET /api/v2/program/me/results` | `program` / `learning:read` | `—` |
| Citește rezultatul și istoricul reevaluării. `program.result_get` | `GET /api/v2/program/me/results/{id}` | `program` / `learning:read` | `—` |
| Contestă un rezultat cu motiv păstrat în istoric. `program.appeal` | `POST /api/v2/program/me/results/{id}/appeals` | `program-session` / `learning:read` | `ProgramReason` |
| Listează probele practice proprii. `program.labs` | `GET /api/v2/program/me/labs` | `program` / `learning:read` | `—` |
| Începe proba practică a specializării. `program.lab_create` | `POST /api/v2/program/me/labs` | `program` / `learning:write` | `ProgramLabCreate` |
| Citește proba practică și feedbackul evaluatorului. `program.lab_get` | `GET /api/v2/program/me/labs/{id}` | `program` / `learning:read` | `—` |
| Salvează datele și dovezile probei practice. `program.lab_save` | `PUT /api/v2/program/me/labs/{id}` | `program` / `learning:write` | `ProgramLabSave` |
| Predă proba pentru verificare automată și evaluare. `program.lab_submit` | `POST /api/v2/program/me/labs/{id}/submit` | `program-session` / `learning:read` | `ProgramEmpty` |
| Listează calificările proprii și valabilitatea. `program.credentials` | `GET /api/v2/program/me/credentials` | `program` / `learning:read` | `—` |
| Alege publicarea numelui pe verificarea calificării. `program.credential_visibility` | `PUT /api/v2/program/me/credentials/{id}/visibility` | `program-session` / `learning:read` | `ProgramVisibility` |
| Listează profilurile tale de contribuitor. `program.contributors_own` | `GET /api/v2/program/me/contributors` | `program` / `contributors:read` | `—` |
| Creează profilul tău cu acord explicit. `program.contributor_create` | `POST /api/v2/program/me/contributors` | `program` / `contributors:write` | `ProgramContributor` |
| Salvează revizia profilului fără schimbarea versiunii aprobate. `program.contributor_save` | `PUT /api/v2/program/me/contributors/{id}` | `program` / `contributors:write` | `ProgramContributor` |
| Trimite profilul și acordul pentru revizia curentă. `program.contributor_submit` | `POST /api/v2/program/me/contributors/{id}/submit` | `program` / `contributors:write` | `ProgramConsent` |
| Retrage publicarea profilului tău. `program.contributor_withdraw` | `POST /api/v2/program/me/contributors/{id}/withdraw` | `program` / `contributors:write` | `ProgramEmpty` |
| Verifică un CUI în ANAF fără confirmarea reprezentării. `program.company_check` | `POST /api/v2/program/me/partners/company-checks` | `program` / `partners:write` | `ProgramCompanyCheck` |
| Recitește ultima selecție de companie din contul propriu. `program.company_check_get` | `GET /api/v2/program/me/partners/company-checks/{id}` | `program` / `partners:read` | `—` |
| Listează cererile tale de partener. `program.applications` | `GET /api/v2/program/me/partners/applications` | `program` / `partners:read` | `—` |
| Confirmă compania și creează cererea, inclusiv schimbarea firmei. `program.application_create` | `POST /api/v2/program/me/partners/applications` | `program` / `partners:write` | `ProgramApplication` |
| Citește cererea, datele ANAF și condițiile rămase. `program.application_get` | `GET /api/v2/program/me/partners/applications/{id}` | `program` / `partners:read` | `—` |
| Salvează prezentarea fără modificarea CUI-ului. `program.application_save` | `PUT /api/v2/program/me/partners/applications/{id}` | `program` / `partners:write` | `ProgramPartnerPresentation` |
| Înlocuiește un contact și invalidează dovada veche. `program.contact_save` | `POST /api/v2/program/me/partners/applications/{id}/contacts` | `program` / `partners:write` | `ProgramContact` |
| Trimite cererea și prezentarea pentru verificare. `program.application_submit` | `POST /api/v2/program/me/partners/applications/{id}/submit` | `program` / `partners:write` | `ProgramSubmission` |
| Retrage cererea și publicarea firmei. `program.application_withdraw` | `POST /api/v2/program/me/partners/applications/{id}/withdraw` | `program` / `partners:write` | `ProgramRevision` |
| Contestă o decizie privind cererea. `program.application_appeal` | `POST /api/v2/program/me/partners/applications/{id}/appeals` | `program` / `partners:write` | `ProgramReason` |
| Exportă datele companiei și istoricul programului. `program.application_export` | `GET /api/v2/program/me/partners/applications/{id}/export` | `program` / `partners:read` | `—` |
| Listează afilierile tale, fără acces la rezultatele colegilor. `program.affiliations` | `GET /api/v2/program/me/partners/affiliations` | `program` / `partners:read` | `—` |
| Încheie afilierea proprie sau administrată. `program.affiliation_end` | `DELETE /api/v2/program/me/partners/affiliations/{id}` | `program` / `partners:write` | `ProgramEmpty` |
| Listează afilierile gestionate de reprezentantul verificat. `program.members` | `GET /api/v2/program/me/partners/profiles/{id}/members` | `program` / `partners:read` | `—` |
| Reverifică ANAF în bugetul comun de cereri. `program.partner_refresh` | `POST /api/v2/program/me/partners/profiles/{id}/refresh` | `program` / `partners:write` | `ProgramEmpty` |
| Parcurge lista privată pentru administrarea programului. `program.admin_contributors` | `GET /api/v2/admin/program/contributors` | `program-staff` / `—` | `—` |
| Parcurge lista privată pentru administrarea programului. `program.admin_partners` | `GET /api/v2/admin/program/partners` | `program-staff` / `—` | `—` |
| Parcurge lista privată pentru administrarea programului. `program.admin_labs` | `GET /api/v2/admin/program/labs` | `program-staff` / `—` | `—` |
| Parcurge lista privată pentru administrarea programului. `program.admin_courses` | `GET /api/v2/admin/program/courses` | `program-staff` / `—` | `—` |
| Parcurge lista privată pentru administrarea programului. `program.admin_banks` | `GET /api/v2/admin/program/banks` | `program-staff` / `—` | `—` |
| Pregătește un profil curatoriat, fără publicare automată. `program.admin_contributor_create` | `POST /api/v2/admin/program/contributors` | `program-staff` / `—` | `ProgramContributor` |
| Actualizează profilul curatoriat și cere acordul noii revizii. `program.admin_contributor_save` | `PUT /api/v2/admin/program/contributors/{id}` | `program-staff` / `—` | `ProgramContributor` |
| Aprobă, cere corecturi sau suspendă profilul. `program.admin_contributor_review` | `POST /api/v2/admin/program/contributors/{id}/review` | `program-staff` / `—` | `ProgramContributorReview` |
| Creează o ciornă din documentul JSON validat. `program.admin_course_create` | `POST /api/v2/admin/program/courses` | `program-staff` / `—` | `ProgramCourse` |
| Citește ciorna privată pentru editare și export. `program.admin_course_get` | `GET /api/v2/admin/program/courses/{id}` | `program-staff` / `—` | `—` |
| Importă sau editează revizia ciornei, fără rescrierea edițiilor. `program.admin_course_save` | `PUT /api/v2/admin/program/courses/{id}` | `program-staff` / `—` | `ProgramCourse` |
| Publică ediția imuabilă după verificările cerute. `program.admin_course_publish` | `POST /api/v2/admin/program/courses/{id}/publish` | `program-staff` / `—` | `ProgramCoursePublish` |
| Oprește folosirea pentru înscrieri sau tentative noi. `program.admin_course_retire` | `POST /api/v2/admin/program/courses/{id}/retire` | `program-staff` / `—` | `ProgramReason` |
| Creează o ciornă din documentul JSON validat. `program.admin_bank_create` | `POST /api/v2/admin/program/banks` | `program-staff` / `—` | `ProgramBank` |
| Citește ciorna privată pentru editare și export. `program.admin_bank_get` | `GET /api/v2/admin/program/banks/{id}` | `program-staff` / `—` | `—` |
| Importă sau editează revizia ciornei, fără rescrierea edițiilor. `program.admin_bank_save` | `PUT /api/v2/admin/program/banks/{id}` | `program-staff` / `—` | `ProgramBank` |
| Publică ediția imuabilă după verificările cerute. `program.admin_bank_publish` | `POST /api/v2/admin/program/banks/{id}/publish` | `program-staff` / `—` | `ProgramBankPublish` |
| Oprește folosirea pentru înscrieri sau tentative noi. `program.admin_bank_retire` | `POST /api/v2/admin/program/assessment-releases/{id}/retire` | `program-staff` / `—` | `ProgramReason` |
| Citește proba și dovada privată pentru evaluare. `program.admin_lab_get` | `GET /api/v2/admin/program/labs/{id}` | `program-staff` / `—` | `—` |
| Evaluează independent proba cu rubrică explicită. `program.admin_lab_review` | `POST /api/v2/admin/program/labs/{id}/review` | `program-staff` / `—` | `ProgramLabReview` |
| Citește dosarul companiei pentru verificare. `program.admin_partner_get` | `GET /api/v2/admin/program/partners/{id}` | `program-staff` / `—` | `—` |
| Verifică reprezentarea și aprobă sau suspendă dosarul. `program.admin_partner_review` | `POST /api/v2/admin/program/partners/{id}/review` | `program-staff` / `—` | `ProgramPartnerReview` |
| Înregistrează proba verificării independente a telefonului. `program.admin_contact_verify` | `POST /api/v2/admin/program/contacts/{id}/verify` | `program-staff` / `—` | `ProgramPhoneReview` |
| Suspendă, revocă sau reactivează autorizarea. `program.admin_authorization` | `POST /api/v2/admin/program/authorizations/{id}/decision` | `program-staff` / `—` | `ProgramDecision` |
| Listează rezultatele pentru contestații și verificări. `program.admin_results` | `GET /api/v2/admin/program/results` | `program-staff` / `—` | `—` |
| Citește rezultatul pentru reevaluare fără răspunsuri publice. `program.admin_result_get` | `GET /api/v2/admin/program/results/{id}` | `program-staff` / `—` | `—` |
| Păstrează o versiune nouă a rezultatului după reevaluare. `program.admin_result_decision` | `POST /api/v2/admin/program/results/{id}/decision` | `program-staff` / `—` | `ProgramResultDecision` |
| Stabilește durata adaptată înaintea primei tentative. `program.admin_accommodation` | `POST /api/v2/admin/program/enrollments/{id}/accommodations` | `program-staff` / `—` | `ProgramAccommodation` |
| Listează calificările și stările lor pentru administrare. `program.admin_credentials` | `GET /api/v2/admin/program/credentials` | `program-staff` / `—` | `—` |
| Suspendă, revocă sau reactivează calificarea. `program.admin_credential` | `POST /api/v2/admin/program/credentials/{id}/decision` | `program-staff` / `—` | `ProgramDecision` |
| Listează rolurile delegate, separat de administrarea platformei. `program.admin_staff` | `GET /api/v2/admin/program/staff` | `program-staff` / `—` | `—` |
| Deleagă un rol al programului după reconfirmarea contului. `program.admin_staff_grant` | `POST /api/v2/admin/program/staff` | `program-staff` / `—` | `ProgramStaff` |
| Retrage rolul delegat după reconfirmarea contului. `program.admin_staff_revoke` | `DELETE /api/v2/admin/program/staff/{id}` | `program-staff` / `—` | `ProgramReason` |
| Citește auditul unui obiect conform rolului delegat. `program.admin_events` | `POST /api/v2/admin/program/events` | `program-staff` / `—` | `ProgramEvents` |
| Execută o tranșă limitată de mentenanță a programului. `program.admin_worker` | `POST /api/v2/admin/program/worker` | `program-staff` / `—` | `ProgramEmpty` |
| Citește revizia privată a profilului propriu. `program.contributor_get` | `GET /api/v2/program/me/contributors/{id}` | `program` / `contributors:read` | `—` |
| Exportă profilul propriu și istoricul reviziilor și acordurilor. `program.contributor_export` | `GET /api/v2/program/me/contributors/{id}/export` | `program` / `contributors:read` | `—` |
| Citește profilul curatoriat pentru verificare. `program.admin_contributor_get` | `GET /api/v2/admin/program/contributors/{id}` | `program-staff` / `—` | `—` |
| Listează înscrierile pentru adaptarea evaluării. `program.admin_enrollments` | `GET /api/v2/admin/program/enrollments` | `program-staff` / `—` | `—` |
| Citește proba privată pentru soluționarea contestației. `program.admin_attempt_get` | `GET /api/v2/admin/program/attempts/{id}` | `program-staff` / `—` | `—` |
| Exportă ediția privată imuabilă a băncii pentru audit și migrare. `program.admin_bank_release_get` | `GET /api/v2/admin/program/assessment-releases/{id}` | `program-staff` / `—` | `—` |
| Descarcă în JSON conținutul imaginii proprii reencodate. `program.asset_content` | `GET /api/v2/program/assets/{id}/content` | `program` / `assets:read` | `—` |
| Listează aplicațiile OAuth create de contul personal. `program.oauth_clients` | `GET /api/v2/program/me/applications` | `program-session` / `learning:read` | `—` |
| Înregistrează un client public cu PKCE pentru aplicații. `program.oauth_client_create` | `POST /api/v2/program/me/applications` | `program-session` / `learning:read` | `ProgramOAuthClient` |
| Dezactivează aplicația proprie și revocă autorizările ei. `program.oauth_client_revoke` | `DELETE /api/v2/program/me/applications/{id}` | `program-session` / `learning:read` | `ProgramEmpty` |
| Listează autorizările personale ale programului. `program.oauth_grants` | `GET /api/v2/program/me/grants` | `program-session` / `learning:read` | `—` |
| Revocă accesul unei aplicații la program. `program.oauth_grant_revoke` | `DELETE /api/v2/program/me/grants/{id}` | `program-session` / `learning:read` | `ProgramEmpty` |
