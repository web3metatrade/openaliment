<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Db, Problem};

/** Explicit projections keep exam keys, private evidence and company memberships out of public results. */
final class ProgramQueries
{
    public static function me(array $user): array
    {
        return ['id' => $user['id'], 'name' => $user['display_name'], 'email' => $user['email'], 'email_verified' => (bool) $user['email_verified'],
            'specializations' => ProgramLearning::TRACKS, 'policy_version' => ProgramLearning::POLICY,
            'roles' => array_column(Db::all('SELECT role FROM program_staff_grants WHERE user_id=? AND revoked_at IS NULL', [$user['id']]), 'role'),
            'is_admin' => (bool) $user['is_admin']];
    }

    public static function enrollments(array $user): array
    {
        return ProgramAccess::list('SELECT * FROM learning_enrollments WHERE user_id=?', [$user['id']], static fn(array $r): array => ['id' => $r['id'],
            'release_id' => $r['release_id'], 'specialization' => $r['specialization'], 'state' => $r['state'], 'revision' => (int) $r['revision'], 'assessment_seconds' => (int) $r['assessment_seconds']]);
    }

    public static function results(array $user, bool $review = false): array
    {
        if ($review) { ProgramAccess::staff($user, 'exam_reviewer'); }
        [$limit, $cursor] = Api::page();
        $where = $review ? '' : ' AND a.user_id=?';
        $rows = Db::all('SELECT r.id FROM assessment_results r JOIN assessment_attempts a ON a.id=r.attempt_id WHERE r.id>?' . $where . ' ORDER BY r.id LIMIT ' . ($limit + 1), $review ? [$cursor] : [$cursor, $user['id']]);
        return Api::collection($rows, $limit, static fn(array $r): array => ProgramAssessments::result($user, $r['id'], $review));
    }

    public static function labs(array $user): array
    {
        return ProgramAccess::list('SELECT * FROM assessment_lab_runs WHERE user_id=?', [$user['id']], static fn(array $r): array => ProgramLabs::view($user, $r['id']));
    }

    public static function credentials(array $user, bool $review = false): array
    {
        if ($review) { ProgramAccess::staff($user, 'exam_reviewer'); }
        return ProgramAccess::list('SELECT * FROM learning_credentials WHERE ' . ($review ? '1=1' : 'user_id=?'), $review ? [] : [$user['id']], static fn(array $r): array => ProgramAssessments::credential($r, true));
    }

    public static function credential(string $id): array
    {
        return ProgramAssessments::credential(Db::one('SELECT * FROM learning_credentials WHERE public_id=?', [Api::id($id)])
            ?? throw new Problem(404, 'credential', 'Calificarea nu a fost găsită.'));
    }

    public static function authorization(string $id): array
    {
        return ProgramPartners::authorization(Db::one('SELECT * FROM partner_authorizations WHERE public_id=?', [Api::id($id)])
            ?? throw new Problem(404, 'authorization', 'Autorizarea nu a fost găsită.'));
    }

    public static function affiliations(array $user, ?string $profile = null): array
    {
        if ($profile !== null) { ProgramPartners::manage($user, $profile); }
        return ProgramAccess::list('SELECT * FROM partner_affiliations WHERE ' . ($profile ? 'profile_id=?' : 'user_id=?'), [$profile ?? $user['id']], static fn(array $r): array => ['id' => $r['id'], 'profile_id' => $r['profile_id'],
            'user_id' => $r['user_id'], 'role' => $r['role'], 'state' => $r['state'], 'started_at' => $r['started_at'], 'ended_at' => $r['ended_at']]);
    }

    public static function export(array $user): array
    {
        $enrollments = [];
        foreach (Db::all('SELECT * FROM learning_enrollments WHERE user_id=? LIMIT 100', [$user['id']]) as $r) {
            $e = ProgramLearning::enrollment($user, $r['id']); unset($e['lessons']); $enrollments[] = $e;
        }
        $results = array_map(static fn(array $r): array => ProgramAssessments::result($user, $r['id']), Db::all('SELECT r.id FROM assessment_results r JOIN assessment_attempts a ON a.id=r.attempt_id WHERE a.user_id=? LIMIT 500', [$user['id']]));
        return ['schema_version' => 'openaliment.learner-export.v1', 'exported_at' => Db::now(), 'authoritative_for_authorization' => false,
            'learner' => self::me($user), 'enrollments' => $enrollments, 'results' => $results,
            'credentials' => array_map(static fn(array $r): array => ProgramAssessments::credential($r, true), Db::all('SELECT * FROM learning_credentials WHERE user_id=? LIMIT 100', [$user['id']]))];
    }

    public static function importPreview(array $user, array $data): array
    {
        $package = $data['package'] ?? null;
        if (!is_array($package) || ($package['schema_version'] ?? '') !== 'openaliment.learner-export.v1' || !is_array($package['enrollments'] ?? null)
            || count($package['enrollments']) > 100 || strlen(Db::json($package)) > 1000000) {
            throw new Problem(422, 'learner_import', 'Export de cursant incorect sau prea mare.');
        }
        return ['valid' => true, 'enrollment_count' => count($package['enrollments']), 'credentials_accepted' => 0,
            'completion_accepted' => false, 'note' => 'Rezultatele și calificările importate nu acordă acces sau autorizare. Progresul verificat rămâne cel din cont.'];
    }

    public static function applicationExport(array $user, string $id): array
    {
        $application = ProgramPartners::application($user, $id);
        return ['schema_version' => 'openaliment.partner-export.v1', 'exported_at' => Db::now(), 'application' => $application,
            'history' => array_map(static fn(array $r): array => ['id' => $r['id'], 'action' => $r['action'], 'at' => $r['created_at'], 'details' => json_decode($r['details_json'], true) ?: new \stdClass()],
                Db::all("SELECT * FROM program_events WHERE object_kind='partner_application' AND object_id=? AND receipt_key IS NULL ORDER BY created_at LIMIT 500", [$id])),
            'credentials_transferred' => false, 'producer_data_transferred' => false];
    }

    public static function importPositions(array $user, array $data): array
    {
        self::importPreview($user, $data); $count = 0;
        foreach ($data['package']['enrollments'] as $source) {
            $row = Db::one('SELECT * FROM learning_enrollments WHERE user_id=? AND release_id=? AND specialization=?', [$user['id'], $source['release_id'] ?? '', $source['specialization'] ?? '']);
            if (!$row && isset($source['course_ref'])) {
                $ref = $source['course_ref'];
                $row = Db::one('SELECT e.* FROM learning_enrollments e JOIN learning_releases r ON r.id=e.release_id JOIN learning_courses c ON c.id=r.course_id WHERE e.user_id=? AND e.specialization=? AND c.slug=? AND r.version_key=? AND r.sha256=? LIMIT 1',
                    [$user['id'], $source['specialization'] ?? '', $ref['slug'] ?? '', $ref['version'] ?? '', $ref['sha256'] ?? '']);
            }
            if (!$row) { continue; }
            $lessons = ProgramLearning::lessons(ProgramLearning::release($row['release_id']), $row['specialization']);
            foreach ($source['progress'] ?? [] as $progress) {
                $lesson = $progress['lesson_id'] ?? ''; $position = $progress['position_key'] ?? '';
                if (!isset($lessons[$lesson]) || !is_string($position) || mb_strlen($position) > 100) { continue; }
                $old = Db::one('SELECT * FROM learning_progress WHERE enrollment_id=? AND lesson_id=?', [$row['id'], $lesson]);
                if ($old) { Db::run('UPDATE learning_progress SET position_key=?,revision=revision+1,updated_at=? WHERE id=?', [$position, Db::now(), $old['id']]); }
                else { Db::insert('learning_progress', ['id' => Db::id(), 'enrollment_id' => $row['id'], 'lesson_id' => $lesson, 'position_key' => $position, 'updated_at' => Db::now()]); }
                $count++;
            }
        }
        ProgramAccess::event($user, 'account', $user['id'], 'positions_imported', ['positions' => $count, 'source_sha256' => hash('sha256', Db::json($data['package']))]);
        return ['saved_positions' => $count, 'credentials_accepted' => 0, 'completion_accepted' => false];
    }

    public static function applicationAppeal(array $user, string $id, array $data): array
    {
        ProgramAccess::owned('partner_applications', $id, $user);
        $reason = ProgramAccess::text($data, 'reason', 4000);
        ProgramAccess::event($user, 'partner_application', $id, 'appealed', ['reason' => $reason]);
        return ['id' => $id, 'state' => 'appeal_received'];
    }

    public static function refresh(array $user, string $id): array
    {
        ProgramPartners::manage($user, $id);
        \App\Core\RateLimit::check('partner-refresh|' . $id, 2, 3600);
        $p = Db::one('SELECT * FROM partner_profiles WHERE id=?', [$id]);
        $observation = OrganizationRegistry::observe($p['cui']);
        self::applyObservation($p, $observation);
        ProgramPartners::reconcileFor($user['id']);
        return ['id' => $id, 'requirements' => ProgramPartners::requirements($id), 'company' => OrganizationRegistry::projectObservation($observation)];
    }

    public static function applyObservation(array $profile, array $observation): void
    {
        $fiscal = ProgramPartners::fiscal($observation);
        $parsed = OrganizationRegistry::projectObservation($observation);
        $current = Db::one('SELECT snapshot_json FROM partner_profile_versions WHERE id=?', [$profile['current_version_id']]);
        if ($fiscal === 'active' && $current && json_decode($current['snapshot_json'], true)['legal_name'] !== ($parsed['record']['date_generale']['denumire'] ?? '')) {
            $fiscal = 'review_required';
        }
        Db::run('UPDATE partner_profiles SET observation_id=?,fiscal_checked_at=?,fiscal_state=? WHERE id=?', [$observation['id'], $observation['fetched_at'], $fiscal, $profile['id']]);
    }
}
