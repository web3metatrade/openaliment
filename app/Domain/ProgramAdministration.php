<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Db, Problem};

final class ProgramAdministration
{
    public static function queue(array $user, string $kind): array
    {
        [$table, $role] = match ($kind) {
            'contributors' => ['contributor_profiles', 'community_editor'],
            'partners' => ['partner_applications', 'partner_reviewer'],
            'labs' => ['assessment_lab_runs', 'exam_reviewer'],
            'courses' => ['learning_courses', 'course_editor'],
            'banks' => ['assessment_banks', 'course_editor'],
            default => throw new Problem(404, 'program_queue', 'Secțiune necunoscută.'),
        };
        if ($kind === 'courses') { ProgramAccess::anyStaff($user, ['course_editor', 'course_publisher']); }
        elseif ($kind === 'banks') { ProgramAccess::anyStaff($user, ['course_editor', 'exam_reviewer']); }
        else { ProgramAccess::staff($user, $role); }
        return ProgramAccess::list('SELECT * FROM ' . $table . ' WHERE 1=1', [], static function (array $r) use ($kind, $user): array {
            return match ($kind) {
                'contributors' => Contributors::projection($r, true),
                'partners' => ProgramPartners::application($user, $r['id'], true),
                'labs' => ProgramLabs::view($user, $r['id'], true),
                'courses' => ProgramLearning::edit($user, $r['id']),
                'banks' => ['id' => $r['id'], 'title' => $r['title'], 'revision' => (int) $r['revision'], 'current_release_id' => $r['current_release_id']],
            };
        });
    }

    public static function accommodations(array $user, string $id, array $data): array
    {
        ProgramAccess::staff($user, 'exam_reviewer');
        $row = Db::one('SELECT * FROM learning_enrollments WHERE id=? FOR UPDATE', [Api::id($id)])
            ?? throw new Problem(404, 'enrollment', 'Înscrierea nu a fost găsită.');
        ProgramAccess::revision($row, $data);
        $seconds = $data['assessment_seconds'] ?? null;
        if (!is_int($seconds) || $seconds < 2700 || $seconds > 10800 || Db::one('SELECT id FROM assessment_attempts WHERE enrollment_id=? LIMIT 1', [$id])) {
            throw new Problem(409, 'accommodation', 'Durata de 45–180 de minute se stabilește înaintea primei tentative.');
        }
        $reason = ProgramAccess::text($data, 'reason', 1000);
        Db::run('UPDATE learning_enrollments SET assessment_seconds=?,revision=revision+1 WHERE id=?', [$seconds, $id]);
        ProgramAccess::event($user, 'enrollment', $id, 'accommodation', ['seconds' => $seconds, 'reason' => $reason]);
        return ['id' => $id, 'revision' => (int) $row['revision'] + 1, 'assessment_seconds' => $seconds];
    }

    public static function verifyPhone(array $user, string $id, array $data): array
    {
        ProgramAccess::staff($user, 'partner_reviewer');
        $row = Db::one("SELECT c.*,a.user_id FROM partner_contacts c JOIN partner_applications a ON a.id=c.application_id WHERE c.id=? AND c.kind='phone' AND c.withdrawn_at IS NULL", [Api::id($id)])
            ?? throw new Problem(404, 'partner_contact', 'Contactul nu a fost găsit.');
        if ($row['user_id'] === $user['id']) {
            throw new Problem(403, 'independent_review', 'Contactul propriu se verifică de alt evaluator.');
        }
        ProgramAccess::revision($row, $data);
        $reason = ProgramAccess::text($data, 'reason', 2000);
        Db::run('UPDATE partner_contacts SET verified_at=?,expires_at=? WHERE id=?', [Db::now(), gmdate('Y-m-d H:i:s', time() + 365 * 86400), $id]);
        ProgramAccess::event($user, 'partner_contact', $id, 'phone_verified', ['evidence' => $reason]);
        ProgramPartners::reconcileFor($row['user_id']);
        return ['id' => $id, 'state' => 'verified'];
    }

    public static function credential(array $user, string $id, array $data): array
    {
        ProgramAccess::staff($user, 'exam_reviewer');
        $r = Db::one('SELECT * FROM learning_credentials WHERE id=?', [Api::id($id)])
            ?? throw new Problem(404, 'credential', 'Calificarea nu a fost găsită.');
        $action = ProgramAccess::text($data, 'action', 20);
        if (!in_array($action, ['suspend', 'revoke', 'restore'], true) || $r['user_id'] === $user['id']) {
            throw new Problem(403, 'credential_decision', 'Calificarea proprie necesită alt evaluator.');
        }
        $reason = ProgramAccess::text($data, 'reason', 2000);
        if ($action === 'restore' && ($r['state'] !== 'suspended' || $r['expires_at'] <= Db::now())) {
            throw new Problem(409, 'credential_state', 'Doar o calificare suspendată și neexpirată poate fi reactivată.');
        }
        $state = ['suspend' => 'suspended', 'revoke' => 'revoked', 'restore' => 'active'][$action];
        Db::run('UPDATE learning_credentials SET state=?,reason=?,revoked_at=? WHERE id=?', [$state, $reason, $state === 'revoked' ? Db::now() : null, $id]);
        ProgramAccess::event($user, 'credential', $id, $action, ['reason' => $reason]);
        ProgramPartners::reconcileFor($r['user_id']);
        return ProgramAssessments::credential(Db::one('SELECT * FROM learning_credentials WHERE id=?', [$id]), true);
    }

    public static function authorization(array $user, string $id, array $data): array
    {
        ProgramAccess::staff($user, 'partner_reviewer');
        $r = Db::one('SELECT * FROM partner_authorizations WHERE id=? OR public_id=?', [Api::id($id), $id])
            ?? throw new Problem(404, 'authorization', 'Autorizarea nu a fost găsită.');
        if (Db::one("SELECT id FROM partner_affiliations WHERE profile_id=? AND user_id=? AND state='active'", [$r['profile_id'], $user['id']])) {
            throw new Problem(403, 'independent_review', 'Firma afiliată se evaluează de altă persoană.');
        }
        $action = ProgramAccess::text($data, 'action', 20);
        $state = match ($action) { 'suspend' => 'suspended', 'revoke' => 'revoked', 'restore' => 'active', default => throw new Problem(422, 'decision', 'Decizie incorectă.') };
        if ($state === 'active' && ($r['state'] !== 'suspended' || $r['expires_at'] <= Db::now() || ProgramPartners::requirements($r['profile_id']))) {
            throw new Problem(409, 'authorization_state', 'Reactivarea cere o autorizare suspendată și toate condițiile îndeplinite.');
        }
        $reason = ProgramAccess::text($data, 'reason', 2000);
        Db::run('UPDATE partner_authorizations SET state=?,reason=? WHERE id=?', [$state, $reason, $r['id']]);
        ProgramAccess::event($user, 'partner_authorization', $r['id'], $action, ['reason' => $reason]);
        return ProgramPartners::authorization(Db::one('SELECT * FROM partner_authorizations WHERE id=?', [$r['id']]));
    }

    public static function staff(array $user): array
    {
        if (!$user['is_admin']) { throw new Problem(403, 'admin', 'Operațiune rezervată administratorului.'); }
        return ProgramAccess::list('SELECT * FROM program_staff_grants WHERE 1=1', [], static fn(array $r): array => ['id' => $r['id'], 'user_id' => $r['user_id'], 'role' => $r['role'], 'state' => $r['revoked_at'] ? 'revoked' : 'active']);
    }

    public static function revokeStaff(array $user, string $id, array $data): array
    {
        if (!$user['is_admin']) { throw new Problem(403, 'admin', 'Operațiune rezervată administratorului.'); }
        OrganizationRegistry::recentAccount($user['id'], $data);
        $reason = ProgramAccess::text($data, 'reason', 2000);
        Db::run('UPDATE program_staff_grants SET revoked_at=COALESCE(revoked_at,?) WHERE id=?', [Db::now(), Api::id($id)]);
        ProgramAccess::event($user, 'staff', $id, 'revoked', ['reason' => $reason]);
        return ['id' => $id, 'state' => 'revoked'];
    }

    public static function authorizeEvents(array $user, string $kind): void
    {
        $role = match ($kind) { 'contributor' => 'community_editor', 'partner_application', 'partner_authorization' => 'partner_reviewer', default => 'exam_reviewer' };
        if ($kind === 'staff') { if (!$user['is_admin']) { throw new Problem(403, 'admin', 'Operațiune rezervată administratorului.'); } }
        elseif (in_array($kind, ['course', 'assessment_bank', 'assessment_release'], true)) { ProgramAccess::anyStaff($user, ['course_editor', 'course_publisher', 'exam_reviewer']); }
        else { ProgramAccess::staff($user, $role); }
    }

    public static function events(array $user, string $kind, string $id): array
    {
        self::authorizeEvents($user, $kind);
        return ProgramAccess::list('SELECT * FROM program_events WHERE object_kind=? AND object_id=? AND receipt_key IS NULL', [$kind, Api::id($id)], static fn(array $r): array => ['id' => $r['id'], 'action' => $r['action'], 'at' => $r['created_at'], 'actor_id' => $r['user_id'], 'details' => json_decode($r['details_json'], true) ?: new \stdClass()]);
    }
}
