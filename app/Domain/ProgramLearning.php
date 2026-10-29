<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Db, Problem};

final class ProgramLearning
{
    public const TRACKS = ['erp-integration', 'printing-integration', 'platform-operations', 'product-data'];
    public const POLICY = 'openaliment-partners-1.0';

    public static function manifest(array $data): array
    {
        $title = ProgramAccess::text($data, 'title', 190);
        $version = ProgramAccess::text($data, 'version', 40);
        $license = ProgramAccess::text($data, 'license', 40);
        if (!in_array($license, ['CC-BY-4.0', 'CC-BY-SA-4.0', 'CC0-1.0'], true)) {
            throw new Problem(422, 'course_license', 'Alege explicit licența materialelor proprii.');
        }
        $modules = $data['modules'] ?? null;
        if (!is_array($modules) || !array_is_list($modules) || count($modules) < 1 || count($modules) > 30) {
            throw new Problem(422, 'course_modules', 'Cursul trebuie să aibă 1–30 de module.');
        }
        $ids = [];
        foreach ($modules as &$module) {
            if (!is_array($module)) {
                throw new Problem(422, 'course_module', 'Modul incorect.');
            }
            $module['title'] = ProgramAccess::text($module, 'title', 190);
            $module['key'] = ProgramAccess::text($module, 'key', 40);
            $track = $module['specialization'] ?? null;
            if ($track !== null && !in_array($track, self::TRACKS, true)) {
                throw new Problem(422, 'specialization', 'Specializare necunoscută.');
            }
            $module['specialization'] = $track;
            if (!is_array($module['lessons'] ?? null) || !array_is_list($module['lessons']) || !$module['lessons'] || count($module['lessons']) > 20) {
                throw new Problem(422, 'course_lessons', 'Adaugă 1–20 de lecții în modul.');
            }
            foreach ($module['lessons'] as &$lesson) {
                $lesson = self::validateLesson($lesson);
                if (isset($ids[$lesson['id']])) {
                    throw new Problem(422, 'course_lesson_id', 'ID-urile lecțiilor trebuie să fie unice.');
                }
                $ids[$lesson['id']] = true;
            }
            unset($lesson);
        }
        unset($module);
        if (count($ids) > 200 || strlen(Db::json($modules)) > 700000) {
            throw new Problem(413, 'course_size', 'Cursul depășește limita de conținut.');
        }
        return ['schema_version' => 'openaliment.course.v1', 'title' => $title, 'version' => $version, 'license' => $license,
            'summary' => ProgramAccess::text($data, 'summary', 2000), 'modules' => $modules, 'policy_version' => self::POLICY];
    }

    private static function validateLesson(mixed $lesson): array
    {
        if (!is_array($lesson)) {
            throw new Problem(422, 'lesson', 'Lecție incorectă.');
        }
        $id = Api::id(ProgramAccess::text($lesson, 'id', 32));
        $body = ProgramAccess::text($lesson, 'body', 20000);
        $resources = $lesson['resources'] ?? [];
        if (!is_array($resources) || !array_is_list($resources) || count($resources) > 10) {
            throw new Problem(422, 'lesson_resources', 'Lista resurselor este incorectă.');
        }
        foreach ($resources as &$resource) {
            $resource = ['title' => ProgramAccess::text($resource, 'title', 190), 'url' => Marketplace::webUrl($resource['url'] ?? null)];
        }
        unset($resource);
        $practice = $lesson['practice'] ?? null;
        if (!is_array($practice) || !is_array($practice['options'] ?? null) || !array_is_list($practice['options']) || count($practice['options']) < 2 || count($practice['options']) > 6
            || !is_int($practice['answer'] ?? null) || !isset($practice['options'][$practice['answer']])) {
            throw new Problem(422, 'lesson_practice', 'Exercițiul trebuie să aibă variante și un răspuns corect.');
        }
        foreach ($practice['options'] as $option) {
            if (!is_string($option) || mb_strlen($option) > 1000 || trim($option) === '') {
                throw new Problem(422, 'lesson_option', 'Variantă incorectă.');
            }
        }
        return ['id' => $id, 'title' => ProgramAccess::text($lesson, 'title', 190), 'objective' => ProgramAccess::text($lesson, 'objective', 2000),
            'body' => $body, 'resources' => $resources, 'practice' => ['question' => ProgramAccess::text($practice, 'question', 2000),
                'options' => $practice['options'], 'answer' => $practice['answer'], 'explanation' => ProgramAccess::text($practice, 'explanation', 3000)]];
    }

    public static function save(array $user, ?string $id, array $data): array
    {
        ProgramAccess::staff($user, 'course_editor');
        $manifest = self::manifest($data['manifest'] ?? []);
        if ($id) {
            $row = Db::one('SELECT * FROM learning_courses WHERE id=? FOR UPDATE', [Api::id($id)])
                ?? throw new Problem(404, 'course', 'Cursul nu a fost găsit.');
            ProgramAccess::revision($row, $data);
            Db::run('UPDATE learning_courses SET title=?,draft_json=?,revision=revision+1 WHERE id=?', [$manifest['title'], Db::json($manifest), $id]);
        } else {
            $id = Db::id();
            $slug = ProgramAccess::text($data, 'slug', 100);
            if (!preg_match('/\A[a-z0-9][a-z0-9-]{2,99}\z/D', $slug)) {
                throw new Problem(422, 'slug', 'Adresa cursului este incorectă.');
            }
            Db::insert('learning_courses', ['id' => $id, 'slug' => $slug, 'title' => $manifest['title'], 'draft_json' => Db::json($manifest),
                'created_by' => $user['id'], 'created_at' => Db::now()]);
        }
        ProgramAccess::event($user, 'course', $id, 'saved');
        return self::edit($user, $id);
    }

    public static function edit(array $user, string $id): array
    {
        ProgramAccess::anyStaff($user, ['course_editor', 'course_publisher']);
        $row = Db::one('SELECT * FROM learning_courses WHERE id=?', [Api::id($id)])
            ?? throw new Problem(404, 'course', 'Cursul nu a fost găsit.');
        return ['id' => $row['id'], 'slug' => $row['slug'], 'revision' => (int) $row['revision'], 'state' => $row['state'],
            'current_release_id' => $row['current_release_id'], 'manifest' => json_decode($row['draft_json'], true)];
    }

    public static function publish(array $user, string $id, array $data): array
    {
        ProgramAccess::staff($user, 'course_publisher');
        $row = Db::one('SELECT * FROM learning_courses WHERE id=? FOR UPDATE', [Api::id($id)])
            ?? throw new Problem(404, 'course', 'Cursul nu a fost găsit.');
        ProgramAccess::revision($row, $data);
        if (($data['rights_confirmed'] ?? false) !== true) {
            throw new Problem(422, 'course_rights', 'Confirmă drepturile și licența materialelor.');
        }
        $manifest = self::manifest(json_decode($row['draft_json'], true));
        if (Db::one('SELECT id FROM learning_releases WHERE course_id=? AND version_key=?', [$id, $manifest['version']])) {
            throw new Problem(409, 'course_edition', 'Ediția există deja. Alege o versiune nouă.');
        }
        $release = Db::id(); $json = Db::json($manifest);
        foreach ($manifest['modules'] as $module) {
            foreach ($module['lessons'] as $lesson) {
                foreach ($lesson['resources'] as $resource) { ProgramMedia::publishLessonResource($resource['url']); }
            }
        }
        Db::insert('learning_releases', ['id' => $release, 'course_id' => $id, 'version_key' => $manifest['version'], 'manifest_json' => $json,
            'sha256' => hash('sha256', $json), 'published_by' => $user['id'], 'published_at' => Db::now()]);
        Db::run("UPDATE learning_courses SET current_release_id=?,state='published' WHERE id=?", [$release, $id]);
        ProgramAccess::event($user, 'course', $id, 'published', ['release_id' => $release]);
        return self::course($id);
    }

    public static function catalog(): array
    {
        return ProgramAccess::list("SELECT * FROM learning_courses WHERE state='published'", [], static fn(array $r): array => self::course($r['id']));
    }

    public static function course(string $id): array
    {
        $row = Db::one("SELECT * FROM learning_courses WHERE id=? AND state='published'", [Api::id($id)])
            ?? throw new Problem(404, 'course', 'Cursul nu este disponibil.');
        $release = self::release($row['current_release_id']);
        return ['id' => $row['id'], 'slug' => $row['slug'], 'release_id' => $release['id'], 'manifest' => $release['manifest'], 'state' => 'published'];
    }

    public static function release(string $id): array
    {
        $row = Db::one('SELECT * FROM learning_releases WHERE id=?', [Api::id($id)])
            ?? throw new Problem(404, 'course_release', 'Ediția nu a fost găsită.');
        if (!hash_equals($row['sha256'], hash('sha256', $row['manifest_json']))) {
            throw new Problem(503, 'course_integrity', 'Conținutul nu poate fi verificat momentan.');
        }
        return ['id' => $row['id'], 'course_id' => $row['course_id'], 'state' => $row['state'], 'manifest' => json_decode($row['manifest_json'], true)];
    }

    public static function lessons(array $release, ?string $track = null): array
    {
        $lessons = [];
        foreach ($release['manifest']['modules'] as $module) {
            if ($module['specialization'] !== null && $module['specialization'] !== $track) {
                continue;
            }
            foreach ($module['lessons'] as $lesson) {
                $lessons[$lesson['id']] = $lesson + ['module' => $module['title'], 'module_key' => $module['key']];
            }
        }
        return $lessons;
    }

    public static function lesson(string $releaseId, string $lessonId): array
    {
        $release = self::release($releaseId);
        if ($release['state'] !== 'published') {
            throw new Problem(404, 'lesson', 'Lecția nu este publicată.');
        }
        foreach ($release['manifest']['modules'] as $module) {
            foreach ($module['lessons'] as $lesson) {
                if ($lesson['id'] === $lessonId) {
                    return $lesson + ['release_id' => $releaseId, 'module' => $module['title']];
                }
            }
        }
        throw new Problem(404, 'lesson', 'Lecția nu a fost găsită.');
    }

    public static function enroll(array $user, array $data): array
    {
        $releaseId = Api::id(ProgramAccess::text($data, 'release_id', 32));
        $release = self::release($releaseId);
        $track = ProgramAccess::text($data, 'specialization', 40);
        if ($release['state'] !== 'published' || !Db::one("SELECT id FROM learning_courses WHERE id=? AND current_release_id=? AND state='published'", [$release['course_id'], $releaseId]) || !in_array($track, self::TRACKS, true) || ($data['policy_accepted'] ?? false) !== true) {
            throw new Problem(422, 'enrollment', 'Alege specializarea și confirmă condițiile evaluării.');
        }
        $existing = Db::one('SELECT id FROM learning_enrollments WHERE user_id=? AND release_id=? AND specialization=?', [$user['id'], $releaseId, $track]);
        $id = $existing['id'] ?? Db::id();
        if (!$existing) {
            Db::insert('learning_enrollments', ['id' => $id, 'user_id' => $user['id'], 'release_id' => $releaseId,
                'specialization' => $track, 'policy_version' => self::POLICY, 'created_at' => Db::now()]);
            ProgramAccess::event($user, 'enrollment', $id, 'created');
        }
        return self::enrollment($user, $id);
    }

    public static function enrollment(array $user, string $id): array
    {
        $row = ProgramAccess::owned('learning_enrollments', $id, $user);
        $release = self::release($row['release_id']);
        $theory = Db::one("SELECT r.id FROM assessment_results r JOIN assessment_attempts a ON a.id=r.attempt_id AND a.result_id=r.id AND a.state='graded' JOIN learning_enrollments e ON e.id=a.enrollment_id WHERE a.user_id=? AND e.release_id=? AND r.passed=1 AND r.created_at>? ORDER BY r.created_at DESC,r.version DESC LIMIT 1", [$user['id'], $row['release_id'], gmdate('Y-m-d H:i:s', time() - 365 * 86400)]);
        return ['id' => $id, 'release_id' => $row['release_id'], 'specialization' => $row['specialization'], 'state' => $row['state'],
            'course_ref' => ['slug' => Db::one('SELECT slug FROM learning_courses WHERE id=?', [$release['course_id']])['slug'], 'version' => $release['manifest']['version'], 'sha256' => hash('sha256', Db::json($release['manifest']))],
            'revision' => (int) $row['revision'], 'assessment_seconds' => (int) $row['assessment_seconds'], 'theory_result_id' => $theory['id'] ?? null,
            'policy_version' => $row['policy_version'], 'lessons' => array_values(self::lessons($release, $row['specialization'])),
            'progress' => array_map(static fn(array $r): array => ['lesson_id' => $r['lesson_id'], 'revision' => (int) $r['revision'],
                'completed' => (bool) $r['completed'], 'practice_passed' => (bool) $r['practice_passed'], 'position_key' => $r['position_key']],
                Db::all('SELECT * FROM learning_progress WHERE enrollment_id=? ORDER BY lesson_id', [$id]))];
    }

    public static function progress(array $user, string $id, string $lessonId, array $data, bool $practice = false): array
    {
        $enrollment = ProgramAccess::owned('learning_enrollments', $id, $user);
        $lessons = self::lessons(self::release($enrollment['release_id']), $enrollment['specialization']);
        $lesson = $lessons[$lessonId] ?? throw new Problem(404, 'lesson', 'Lecția nu aparține înscrierii.');
        $row = Db::one('SELECT * FROM learning_progress WHERE enrollment_id=? AND lesson_id=? FOR UPDATE', [$id, $lessonId]);
        if ($row) {
            ProgramAccess::revision($row, $data);
        } elseif (($data['expected_revision'] ?? null) !== 0) {
            throw new Problem(409, 'program_revision', 'Prima salvare folosește expected_revision=0.');
        }
        $passed = $practice ? (($data['answer'] ?? null) === $lesson['practice']['answer']) : (bool) ($row['practice_passed'] ?? false);
        $completed = $practice ? $passed : (($data['completed'] ?? false) === true);
        if ($completed && !$passed) {
            throw new Problem(422, 'practice_required', 'Rezolvă mai întâi exercițiul lecției.');
        }
        $revision = (int) ($row['revision'] ?? 0) + 1;
        $position = ProgramAccess::text($data, 'position_key', 100, true);
        if ($row) {
            Db::run('UPDATE learning_progress SET revision=?,completed=?,practice_passed=?,position_key=?,updated_at=? WHERE id=?',
                [$revision, (int) $completed, (int) $passed, $position, Db::now(), $row['id']]);
        } else {
            Db::insert('learning_progress', ['id' => Db::id(), 'enrollment_id' => $id, 'lesson_id' => $lessonId, 'revision' => $revision,
                'completed' => (int) $completed, 'practice_passed' => (int) $passed, 'position_key' => $position, 'updated_at' => Db::now()]);
        }
        if ($completed) { ProgramAssessments::issue($user, $id); }
        return ['enrollment_id' => $id, 'lesson_id' => $lessonId, 'revision' => $revision, 'completed' => $completed, 'practice_passed' => $passed,
            'feedback' => $practice ? $lesson['practice']['explanation'] : null];
    }

    public static function retire(array $user, string $id, array $data): array
    {
        ProgramAccess::staff($user, 'course_publisher');
        $reason = ProgramAccess::text($data, 'reason', 2000);
        Db::run("UPDATE learning_courses SET state='retired' WHERE id=?", [Api::id($id)]);
        ProgramAccess::event($user, 'course', $id, 'retired', ['reason' => $reason]);
        return ['id' => $id, 'state' => 'retired'];
    }

    public static function seed(array $user): void
    {
        ProgramAccess::staff($user, 'course_publisher');
        if (Db::one("SELECT id FROM learning_courses WHERE slug='integrare-gs1-digital-link'")) { return; }
        Db::atomic(static function () use ($user): void {
            $manifest = json_decode(file_get_contents(ROOT . '/contracts/program/learning-content.json'), true, 32, JSON_THROW_ON_ERROR);
            $course = self::save($user, null, ['slug' => 'integrare-gs1-digital-link', 'manifest' => $manifest]);
            self::publish($user, $course['id'], ['expected_revision' => $course['revision'], 'rights_confirmed' => true]);
        });
    }
}
