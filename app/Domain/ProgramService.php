<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Db, Problem};

/** Shared use cases for HTML forms and the documented API. */
final class ProgramService
{
    public static function run(string $action, ?array $user, string $id = '', array $data = [], array $parameters = []): array
    {
        return match ($action) {
            'public_contributors' => Contributors::catalog(),
            'public_contributors_get' => Contributors::profile($id),
            'public_partners' => ProgramPartners::catalog(),
            'public_partners_get' => ProgramPartners::profile($id),
            'public_courses' => ProgramLearning::catalog(),
            'public_courses_get' => ProgramLearning::course($id),
            'public_course_release' => self::publicRelease($id),
            'public_lesson' => ProgramLearning::lesson($id, $parameters['lesson_id']),
            'public_credential' => ProgramQueries::credential($id),
            'public_authorization' => ProgramQueries::authorization($id),
            'register' => ProgramVerifications::register($data),
            'verification_preview' => ProgramVerifications::preview($data),
            'verification_confirm' => ProgramVerifications::confirm($user, $data),
            'me' => ProgramQueries::me($user),
            'verification_request' => ProgramVerifications::request($user, $data),
            'export' => ProgramQueries::export($user),
            'import_preview' => ProgramQueries::importPreview($user, $data),
            'import_positions' => ProgramQueries::importPositions($user, $data),
            'password_change', 'access_revoke' => self::security($user, $data, $action),
            'asset_upload' => ProgramMedia::put($user, $data),
            'asset_get' => ProgramMedia::metadata(ProgramMedia::owned($user, $id)),
            'asset_content' => self::assetContent($user, $id),
            'asset_withdraw' => ProgramMedia::withdraw($user, $id),
            'enrollments' => ProgramQueries::enrollments($user),
            'enroll' => ProgramLearning::enroll($user, $data),
            'enrollment_get' => ProgramLearning::enrollment($user, $id),
            'progress', 'practice' => ProgramLearning::progress($user, $id, $parameters['lesson_id'], $data, $action === 'practice'),
            'attempt_start' => ProgramAssessments::start($user, $data),
            'attempt_get' => ProgramAssessments::attempt($user, $id),
            'answer' => ProgramAssessments::answer($user, $id, $parameters['item_id'], $data),
            'attempt_submit' => ProgramAssessments::submit($user, $id),
            'results' => ProgramQueries::results($user),
            'result_get' => ProgramAssessments::result($user, $id),
            'appeal' => ProgramAssessments::appeal($user, $id, $data),
            'labs' => ProgramQueries::labs($user),
            'lab_create' => ProgramLabs::create($user, $data),
            'lab_get' => ProgramLabs::view($user, $id),
            'lab_save' => ProgramLabs::save($user, $id, $data),
            'lab_submit' => ProgramLabs::submit($user, $id),
            'credentials' => ProgramQueries::credentials($user),
            'credential_visibility' => ProgramAssessments::visibility($user, $id, $data),
            'contributors_own' => Contributors::own($user),
            'contributor_get' => Contributors::projection(ProgramAccess::owned('contributor_profiles', $id, $user), true),
            'contributor_export' => Contributors::export($user, $id),
            'contributor_create', 'contributor_save' => Contributors::save($user, $id ?: null, $data),
            'contributor_submit' => Contributors::submit($user, $id, $data),
            'contributor_withdraw' => Contributors::withdraw($user, $id),
            'company_check' => ProgramPartners::check($user, $data),
            'company_check_get' => ProgramPartners::checkResult($user, $id),
            'applications' => ProgramPartners::own($user),
            'application_create' => ProgramPartners::create($user, $data),
            'application_get' => ProgramPartners::application($user, $id),
            'application_save' => ProgramPartners::save($user, $id, $data),
            'contact_save' => ProgramPartners::contact($user, $id, $data),
            'application_submit' => ProgramPartners::submit($user, $id, $data),
            'application_withdraw' => ProgramPartners::withdraw($user, $id, $data),
            'application_appeal' => ProgramQueries::applicationAppeal($user, $id, $data),
            'application_export' => ProgramQueries::applicationExport($user, $id),
            'affiliations' => ProgramQueries::affiliations($user),
            'affiliation_end' => ProgramPartners::endAffiliation($user, $id),
            'members' => ProgramQueries::affiliations($user, $id),
            'partner_refresh' => ProgramQueries::refresh($user, $id),
            'admin_contributors', 'admin_partners', 'admin_labs', 'admin_courses', 'admin_banks' => ProgramAdministration::queue($user, substr($action, 6)),
            'admin_contributor_create', 'admin_contributor_save' => Contributors::save($user, $id ?: null, $data, true),
            'admin_contributor_get' => self::adminContributor($user, $id),
            'admin_contributor_review' => Contributors::review($user, $id, $data),
            'admin_course_create', 'admin_course_save' => ProgramLearning::save($user, $id ?: null, $data),
            'admin_course_get' => ProgramLearning::edit($user, $id),
            'admin_course_publish' => ProgramLearning::publish($user, $id, $data),
            'admin_course_retire' => ProgramLearning::retire($user, $id, $data),
            'admin_bank_create', 'admin_bank_save' => ProgramAssessments::bank($user, $id ?: null, $data),
            'admin_bank_get' => ProgramAssessments::bankView($user, $id),
            'admin_bank_publish' => ProgramAssessments::publish($user, $id, $data),
            'admin_bank_retire' => ProgramAssessments::retire($user, $id, $data),
            'admin_lab_get' => ProgramLabs::view($user, $id, true),
            'admin_lab_review' => ProgramLabs::review($user, $id, $data),
            'admin_partner_get' => ProgramPartners::application($user, $id, true),
            'admin_partner_review' => ProgramPartners::review($user, $id, $data),
            'admin_contact_verify' => ProgramAdministration::verifyPhone($user, $id, $data),
            'admin_authorization' => ProgramAdministration::authorization($user, $id, $data),
            'admin_results' => ProgramQueries::results($user, true),
            'admin_result_get' => ProgramAssessments::result($user, $id, true),
            'admin_attempt_get' => ProgramAssessments::reviewAttempt($user, $id),
            'admin_bank_release_get' => ProgramAssessments::edition($user, $id),
            'admin_enrollments' => self::adminEnrollments($user),
            'admin_result_decision' => ProgramAssessments::decideAppeal($user, $id, $data),
            'admin_accommodation' => ProgramAdministration::accommodations($user, $id, $data),
            'admin_credentials' => ProgramQueries::credentials($user, true),
            'admin_credential' => ProgramAdministration::credential($user, $id, $data),
            'admin_staff' => ProgramAdministration::staff($user),
            'admin_staff_grant' => ProgramAccess::grant($user, $data),
            'admin_staff_revoke' => ProgramAdministration::revokeStaff($user, $id, $data),
            'admin_events' => ProgramAdministration::events($user, $data['object_kind'], $data['object_id']),
            'admin_worker' => self::worker($user),
            'oauth_clients' => ProgramApplications::clients($user),
            'oauth_client_create' => ProgramApplications::create($user, $data),
            'oauth_client_revoke' => ProgramApplications::deactivate($user, $id),
            'oauth_grants' => ProgramApplications::grants($user),
            'oauth_grant_revoke' => ProgramApplications::revoke($user, $id),
            default => throw new Problem(404, 'program_operation', 'Operațiunea nu a fost găsită.'),
        };
    }

    private static function publicRelease(string $id): array
    {
        $r = ProgramLearning::release($id);
        if ($r['state'] !== 'published') { throw new Problem(404, 'course_release', 'Ediția nu este disponibilă.'); }
        return $r;
    }

    private static function assetContent(array $user, string $id): array
    {
        $row = ProgramMedia::owned($user, $id); $asset = ProgramMedia::read($user, $id);
        return ProgramMedia::metadata($row) + ['content_base64' => base64_encode(file_get_contents($asset['path']))];
    }

    private static function security(array $user, array $data, string $action): array
    {
        Accounts::secure(['user' => $user['id'], 'tenant' => null, 'token' => null, 'role' => 'personal'], $data + ['action' => $action === 'password_change' ? 'password' : 'revoke']);
        return ['id' => $user['id'], 'access_revoked' => true, 'csrf' => \App\Core\Session::csrf()];
    }

    private static function adminContributor(array $user, string $id): array
    {
        ProgramAccess::staff($user, 'community_editor');
        return Contributors::projection(Db::one('SELECT * FROM contributor_profiles WHERE id=?', [Api::id($id)])
            ?? throw new Problem(404, 'contributor', 'Profilul nu a fost găsit.'), true);
    }

    private static function adminEnrollments(array $user): array
    {
        ProgramAccess::staff($user, 'exam_reviewer');
        return ProgramAccess::list('SELECT * FROM learning_enrollments WHERE 1=1', [], static fn(array $r): array => ['id' => $r['id'], 'user_id' => $r['user_id'],
            'release_id' => $r['release_id'], 'specialization' => $r['specialization'], 'state' => $r['state'], 'revision' => (int) $r['revision'], 'assessment_seconds' => (int) $r['assessment_seconds']]);
    }

    private static function worker(array $user): array
    {
        if (!$user['is_admin']) { throw new Problem(403, 'admin', 'Operațiune rezervată administratorului.'); }
        return ProgramJobs::work();
    }
}
