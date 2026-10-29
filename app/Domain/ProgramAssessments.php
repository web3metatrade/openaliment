<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Config, Db, Problem};

/** Immutable exam editions. Candidate DTOs never include marking keys. */
final class ProgramAssessments
{
    public const BLUEPRINT = ['identity' => [6, 20], 'uri' => [6, 20], 'resolver' => [6, 20], 'data' => [4, 15], 'api' => [4, 15], 'operations' => [4, 10]];

    public static function validate(array $data): array
    {
        $questions = $data['questions'] ?? null;
        if (!is_array($questions) || !array_is_list($questions) || count($questions) < 100 || count($questions) > 1000) {
            throw new Problem(422, 'question_bank', 'Ediția cere între 100 și 1.000 de întrebări revizuite.');
        }
        if (($data['synthetic'] ?? false) && Config::get('environment') !== 'test') {
            throw new Problem(422, 'synthetic_bank', 'Banca sintetică este destinată exclusiv testelor locale.');
        }
        $ids = []; $pool = []; $out = [];
        foreach ($questions as $q) {
            $key = ProgramAccess::text($q, 'key', 100);
            if (str_starts_with($key, 'SYNTHETIC-') && Config::get('environment') !== 'test') {
                throw new Problem(422, 'synthetic_bank', 'Identificatorii băncii de test nu se publică pe hosting.');
            }
            $domain = ProgramAccess::text($q, 'domain', 40);
            $type = $q['type'] ?? 'single';
            if (isset($ids[$key]) || !isset(self::BLUEPRINT[$domain]) || !in_array($type, ['single', 'multiple', 'matching', 'uri'], true)) {
                throw new Problem(422, 'question', 'ID duplicat, domeniu sau tip de întrebare incorect.');
            }
            $ids[$key] = true;
            $options = $q['options'] ?? [];
            if (!is_array($options) || !array_is_list($options) || count($options) < 2 || count($options) > 10) {
                throw new Problem(422, 'question_options', 'Întrebarea cere 2–10 variante/componente.');
            }
            $optionKeys = [];
            foreach ($options as &$option) {
                $option = ['key' => ProgramAccess::text($option, 'key', 40), 'text' => ProgramAccess::text($option, 'text', 2000)];
                if (isset($optionKeys[$option['key']])) {
                    throw new Problem(422, 'question_option_id', 'Variantele au ID-uri duplicate.');
                }
                $optionKeys[$option['key']] = true;
            }
            unset($option);
            $answer = $q['answer'] ?? null;
            if (is_array($answer)) {
                foreach ($answer as $value) {
                    if (!is_string($value) || strlen($value) > 500) { throw new Problem(422, 'question_answer', 'Componentă de răspuns incorectă.'); }
                }
            }
            if ($type === 'single' && (!is_string($answer) || !isset($optionKeys[$answer]))) {
                throw new Problem(422, 'question_answer', 'Răspunsul trebuie să indice o variantă existentă.');
            }
            if ($type === 'multiple' && (!is_array($answer) || !array_is_list($answer) || !$answer || count($answer) !== count(array_unique($answer)) || array_diff($answer, array_keys($optionKeys)))) {
                throw new Problem(422, 'question_answer', 'Setul de răspunsuri este incorect.');
            }
            if (in_array($type, ['matching', 'uri'], true) && (!is_array($answer) || array_is_list($answer) || !$answer || count($answer) > 10 || array_diff_key($answer, $optionKeys) || array_diff_key($optionKeys, $answer))) {
                throw new Problem(422, 'question_answer', 'Asocierea trebuie să fie un obiect de răspunsuri.');
            }
            if (is_array($answer)) {
                foreach ($answer as $value) {
                    if (!is_string($value) || strlen($value) > 500) {
                        throw new Problem(422, 'question_answer', 'Componentă de răspuns incorectă.');
                    }
                }
            }
            $critical = ($q['critical'] ?? false) === true;
            $pool[$domain][$critical ? 'critical' : 'regular'][] = $key;
            $out[] = ['key' => $key, 'domain' => $domain, 'type' => $type, 'critical' => $critical,
                'stem' => ProgramAccess::text($q, 'stem', 4000), 'options' => $options, 'answer' => $answer,
                'explanation' => ProgramAccess::text($q, 'explanation', 4000),
                'reference' => Marketplace::webUrl($q['reference'] ?? null)];
        }
        foreach (self::BLUEPRINT as $domain => [$count]) {
            if (count($pool[$domain]['critical'] ?? []) < 1 || count($pool[$domain]['regular'] ?? []) < $count - 1) {
                throw new Problem(422, 'question_coverage', 'Banca nu acoperă toate domeniile și întrebările critice.');
            }
        }
        return ['schema_version' => 'openaliment.assessment.v1', 'title' => ProgramAccess::text($data, 'title', 190),
            'version' => ProgramAccess::text($data, 'version', 40), 'questions' => $out,
            'synthetic' => ($data['synthetic'] ?? false) === true, 'policy' => ['blueprint' => self::BLUEPRINT,
                'minutes' => 45, 'minimum_percent' => 85, 'domain_minimum_percent' => 70, 'all_critical_required' => true,
                'retry_hours' => 24, 'attempts_per_week' => 3, 'validity_days' => 365]];
    }

    public static function bank(array $user, ?string $id, array $data): array
    {
        ProgramAccess::staff($user, 'course_editor');
        $bank = self::validate($data['bank'] ?? []);
        if ($id) {
            $row = Db::one('SELECT * FROM assessment_banks WHERE id=?', [Api::id($id)])
                ?? throw new Problem(404, 'assessment_bank', 'Banca nu a fost găsită.');
            ProgramAccess::revision($row, $data);
            Db::run('UPDATE assessment_banks SET title=?,draft_json=?,revision=revision+1 WHERE id=?', [$bank['title'], Db::json($bank), $id]);
        } else {
            $id = Db::id();
            Db::insert('assessment_banks', ['id' => $id, 'title' => $bank['title'], 'draft_json' => Db::json($bank), 'created_by' => $user['id'], 'created_at' => Db::now()]);
        }
        ProgramAccess::event($user, 'assessment_bank', $id, 'saved', ['questions' => count($bank['questions'])]);
        return self::bankView($user, $id);
    }

    public static function bankView(array $user, string $id): array
    {
        ProgramAccess::anyStaff($user, ['course_editor', 'exam_reviewer']);
        $row = Db::one('SELECT * FROM assessment_banks WHERE id=?', [Api::id($id)])
            ?? throw new Problem(404, 'assessment_bank', 'Banca nu a fost găsită.');
        return ['id' => $id, 'revision' => (int) $row['revision'], 'current_release_id' => $row['current_release_id'], 'bank' => json_decode($row['draft_json'], true)];
    }

    public static function publish(array $user, string $id, array $data): array
    {
        ProgramAccess::staff($user, 'exam_reviewer');
        $row = Db::one('SELECT * FROM assessment_banks WHERE id=? FOR UPDATE', [Api::id($id)])
            ?? throw new Problem(404, 'assessment_bank', 'Banca nu a fost găsită.');
        ProgramAccess::revision($row, $data);
        if (($data['reviewed'] ?? false) !== true || ($data['rights_confirmed'] ?? false) !== true) {
            throw new Problem(422, 'assessment_review', 'Confirmă revizuirea întrebărilor și drepturile.');
        }
        $course = ProgramLearning::release(Api::id(ProgramAccess::text($data, 'course_release_id', 32)));
        $bank = self::validate(json_decode($row['draft_json'], true));
        if ($course['state'] !== 'published' || Db::one('SELECT id FROM assessment_releases WHERE bank_id=? AND version_key=?', [$id, $bank['version']])) {
            throw new Problem(409, 'assessment_release', 'Ediție indisponibilă sau versiune deja publicată.');
        }
        $release = Db::id(); $json = Db::json($bank);
        Db::insert('assessment_releases', ['id' => $release, 'bank_id' => $id, 'course_release_id' => $course['id'], 'version_key' => $bank['version'],
            'private_json' => $json, 'sha256' => hash('sha256', $json), 'published_by' => $user['id'], 'published_at' => Db::now()]);
        Db::run('UPDATE assessment_banks SET current_release_id=? WHERE id=?', [$release, $id]);
        ProgramAccess::event($user, 'assessment_bank', $id, 'published', ['release_id' => $release]);
        return ['id' => $release, 'bank_id' => $id, 'course_release_id' => $course['id'], 'state' => 'published', 'question_count' => count($bank['questions'])];
    }

    private static function release(string $id): array
    {
        $row = Db::one('SELECT * FROM assessment_releases WHERE id=?', [$id])
            ?? throw new Problem(404, 'assessment_release', 'Ediția evaluării nu a fost găsită.');
        if (!hash_equals($row['sha256'], hash('sha256', $row['private_json']))) {
            throw new Problem(503, 'assessment_integrity', 'Evaluarea nu poate fi verificată.');
        }
        return $row + ['bank' => json_decode($row['private_json'], true)];
    }

    public static function edition(array $user, string $id): array
    {
        ProgramAccess::anyStaff($user, ['course_editor', 'exam_reviewer']);
        $r = self::release(Api::id($id));
        return ['id' => $r['id'], 'bank_id' => $r['bank_id'], 'course_release_id' => $r['course_release_id'], 'version' => $r['version_key'],
            'state' => $r['state'], 'sha256' => $r['sha256'], 'bank' => $r['bank']];
    }

    private static function shuffled(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = random_int(0, $i); [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
        return $items;
    }

    public static function draw(array $questions): array
    {
        $out = [];
        foreach (self::BLUEPRINT as $domain => [$count]) {
            $critical = array_values(array_filter($questions, static fn(array $q): bool => $q['domain'] === $domain && $q['critical']));
            $regular = array_values(array_filter($questions, static fn(array $q): bool => $q['domain'] === $domain && !$q['critical']));
            $out[] = self::shuffled($critical)[0];
            array_push($out, ...array_slice(self::shuffled($regular), 0, $count - 1));
        }
        return self::shuffled($out);
    }

    public static function start(array $user, array $data): array
    {
        $enrollment = ProgramAccess::owned('learning_enrollments', Api::id(ProgramAccess::text($data, 'enrollment_id', 32)), $user);
        if (!$user['email_verified']) {
            throw new Problem(403, 'email_verification', 'Confirmă emailul contului înaintea evaluării.');
        }
        $lessons = ProgramLearning::lessons(ProgramLearning::release($enrollment['release_id']), $enrollment['specialization']);
        $done = (int) Db::one('SELECT COUNT(*) AS n FROM learning_progress WHERE enrollment_id=? AND completed=1 AND practice_passed=1', [$enrollment['id']])['n'];
        if ($done !== count($lessons)) {
            throw new Problem(409, 'course_incomplete', 'Finalizează lecțiile și exercițiile înaintea examenului.');
        }
        $active = Db::one("SELECT id FROM assessment_attempts WHERE user_id=? AND enrollment_id=? AND state='in_progress' AND due_at>?", [$user['id'], $enrollment['id'], Db::now()]);
        if ($active) {
            return self::attempt($user, $active['id']);
        }
        if (!Db::one("SELECT id FROM learning_courses WHERE current_release_id=? AND state='published'", [$enrollment['release_id']])) {
            throw new Problem(409, 'course_edition', 'Pentru o evaluare nouă, înscrie-te la ediția curentă a cursului. Progresul vechi rămâne în cont.');
        }
        $week = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
        if ((int) Db::one('SELECT COUNT(*) AS n FROM assessment_attempts WHERE user_id=? AND started_at>?', [$user['id'], $week])['n'] >= 3) {
            throw new Problem(429, 'assessment_attempt_limit', 'Ai folosit cele trei tentative din ultimele șapte zile.');
        }
        $last = Db::one('SELECT started_at FROM assessment_attempts WHERE user_id=? ORDER BY started_at DESC,id DESC LIMIT 1', [$user['id']]);
        if ($last && strtotime($last['started_at'] . ' UTC') > time() - 86400) {
            throw new Problem(409, 'assessment_retry', 'Reîncercarea este disponibilă după 24 de ore.');
        }
        $release = Db::one("SELECT id FROM assessment_releases WHERE course_release_id=? AND state='published' ORDER BY publication_order DESC LIMIT 1", [$enrollment['release_id']])
            ?? throw new Problem(503, 'assessment_unavailable', 'Evaluarea acestei ediții nu este disponibilă.');
        $bank = self::release($release['id']); $id = Db::id();
        Db::insert('assessment_attempts', ['id' => $id, 'user_id' => $user['id'], 'enrollment_id' => $enrollment['id'], 'release_id' => $release['id'],
            'started_at' => Db::now(), 'due_at' => gmdate('Y-m-d H:i:s', time() + (int) ($enrollment['assessment_seconds'] ?? 2700))]);
        foreach (self::draw($bank['bank']['questions']) as $position => $question) {
            $served = ['type' => $question['type'], 'stem' => $question['stem'], 'options' => self::shuffled($question['options'])];
            Db::insert('assessment_items', ['id' => Db::id(), 'attempt_id' => $id, 'question_key' => $question['key'], 'domain_key' => $question['domain'],
                'critical' => (int) $question['critical'], 'position' => $position, 'served_json' => Db::json($served)]);
        }
        ProgramAccess::event($user, 'assessment_attempt', $id, 'started');
        return self::attempt($user, $id);
    }

    public static function attempt(array $user, string $id): array
    {
        $row = ProgramAccess::owned('assessment_attempts', $id, $user);
        $items = Db::all('SELECT * FROM assessment_items WHERE attempt_id=? ORDER BY position', [$id]);
        return ['id' => $id, 'enrollment_id' => $row['enrollment_id'], 'state' => $row['state'], 'due_at' => $row['due_at'], 'result_id' => $row['result_id'],
            'server_time' => Db::now(), 'items' => array_map(static fn(array $r): array => ['id' => $r['id'], 'position' => (int) $r['position'],
                'question' => json_decode($r['served_json'], true), 'revision' => (int) $r['revision'],
                'answer' => $r['response_json'] === null ? null : json_decode($r['response_json'], true)], $items)];
    }

    public static function answer(array $user, string $id, string $item, array $data): array
    {
        $attempt = ProgramAccess::owned('assessment_attempts', $id, $user);
        if ($attempt['state'] !== 'in_progress' || $attempt['due_at'] <= Db::now()) {
            throw new Problem(409, 'assessment_closed', 'Tentativa este încheiată sau timpul a expirat.');
        }
        $row = Db::one('SELECT * FROM assessment_items WHERE id=? AND attempt_id=? FOR UPDATE', [Api::id($item), $id])
            ?? throw new Problem(404, 'assessment_item', 'Întrebarea nu a fost găsită.');
        ProgramAccess::revision($row, $data);
        $answer = $data['answer'] ?? null;
        if ((!is_string($answer) && !is_array($answer)) || strlen(Db::json($answer)) > 5000 || (is_array($answer) && count($answer) > 10)) {
            throw new Problem(422, 'assessment_answer', 'Răspuns incorect.');
        }
        if (is_array($answer)) {
            foreach ($answer as $value) {
                if (!is_string($value) || strlen($value) > 500) {
                    throw new Problem(422, 'assessment_answer', 'Componentă incorectă.');
                }
            }
        }
        Db::run('UPDATE assessment_items SET response_json=?,revision=revision+1,saved_at=? WHERE id=?', [Db::json($answer), Db::now(), $item]);
        return ['id' => $item, 'revision' => (int) $row['revision'] + 1, 'saved' => true];
    }

    public static function correct(array $question, mixed $answer): bool
    {
        $expected = $question['answer'];
        if ($question['type'] === 'single') {
            return is_string($answer) && $answer === $expected;
        }
        if (!is_array($answer) || count($answer) !== count($expected)) {
            return false;
        }
        if ($question['type'] === 'multiple') {
            if (!array_is_list($answer) || count(array_unique($answer)) !== count($answer)) {
                return false;
            }
            sort($answer); sort($expected);
        } else {
            ksort($answer); ksort($expected);
        }
        return $answer === $expected;
    }

    public static function grade(array $questions, array $responses): array
    {
        $domains = []; $critical = true;
        foreach ($questions as $q) {
            $right = self::correct($q, $responses[$q['key']] ?? null);
            $domains[$q['domain']]['correct'] = ($domains[$q['domain']]['correct'] ?? 0) + (int) $right;
            $domains[$q['domain']]['total'] = ($domains[$q['domain']]['total'] ?? 0) + 1;
            if ($q['critical'] && !$right) {
                $critical = false;
            }
        }
        $numerator = 0; $allDomains = true;
        foreach (self::BLUEPRINT as $domain => [$count, $weight]) {
            $d = $domains[$domain] ?? ['correct' => 0, 'total' => 0];
            if ($d['total'] !== $count) {
                throw new \LogicException('Assessment draw does not match blueprint');
            }
            $numerator += $d['correct'] * $weight * intdiv(12, $count);
            $allDomains = $allDomains && $d['correct'] * 100 >= $count * 70;
        }
        return ['score_basis_points' => intdiv($numerator * 100, 12), 'critical_passed' => $critical,
            'passed' => $numerator >= 85 * 12 && $critical && $allDomains, 'domains' => $domains];
    }

    public static function submit(array $user, string $id): array
    {
        $attempt = ProgramAccess::owned('assessment_attempts', $id, $user);
        if ($attempt['result_id']) {
            return self::result($user, $attempt['result_id']);
        }
        if ($attempt['state'] !== 'in_progress') {
            throw new Problem(409, 'assessment_state', 'Tentativa nu mai poate fi predată.');
        }
        $bank = self::release($attempt['release_id']);
        $items = Db::all('SELECT * FROM assessment_items WHERE attempt_id=? ORDER BY position', [$id]);
        $byKey = array_column($bank['bank']['questions'], null, 'key');
        $questions = []; $responses = [];
        foreach ($items as $item) {
            $questions[] = $byKey[$item['question_key']];
            $responses[$item['question_key']] = $item['response_json'] === null ? null : json_decode($item['response_json'], true);
        }
        $grade = self::grade($questions, $responses); $resultId = Db::id();
        Db::insert('assessment_results', ['id' => $resultId, 'attempt_id' => $id, 'version' => 1,
            'score_basis_points' => $grade['score_basis_points'], 'passed' => (int) $grade['passed'], 'critical_passed' => (int) $grade['critical_passed'],
            'details_json' => Db::json(['domains' => $grade['domains'], 'policy' => $bank['bank']['policy']]), 'created_at' => Db::now()]);
        Db::run("UPDATE assessment_attempts SET state='graded',submitted_at=?,result_id=? WHERE id=?", [Db::now(), $resultId, $id]);
        ProgramAccess::event($user, 'assessment_attempt', $id, 'graded', ['result_id' => $resultId, 'passed' => $grade['passed']]);
        ProgramJobs::notice($user['id'], 'result', $resultId, 'Rezultatul evaluării teoretice este disponibil în contul tău.');
        self::issue($user, $attempt['enrollment_id']);
        return self::result($user, $resultId);
    }

    public static function result(array $user, string $id, bool $reviewer = false): array
    {
        if ($reviewer) {
            ProgramAccess::staff($user, 'exam_reviewer');
        }
        $row = Db::one('SELECT r.*,a.user_id,a.enrollment_id FROM assessment_results r JOIN assessment_attempts a ON a.id=r.attempt_id WHERE r.id=?', [Api::id($id)]);
        if (!$row || (!$reviewer && $row['user_id'] !== $user['id'])) {
            throw new Problem(404, 'assessment_result', 'Rezultatul nu a fost găsit.');
        }
        $out = ['id' => $row['id'], 'attempt_id' => $row['attempt_id'], 'enrollment_id' => $row['enrollment_id'], 'version' => (int) $row['version'],
            'score_basis_points' => (int) $row['score_basis_points'], 'passed' => (bool) $row['passed'], 'critical_passed' => (bool) $row['critical_passed'],
            'details' => json_decode($row['details_json'], true), 'appeal' => $row['appeal_json'] ? json_decode($row['appeal_json'], true) : null];
        if ($reviewer) { $out['learner'] = ['id' => $row['user_id'], 'name' => Db::one('SELECT display_name FROM users WHERE id=?', [$row['user_id']])['display_name']]; }
        return $out;
    }

    public static function reviewAttempt(array $reviewer, string $id): array
    {
        ProgramAccess::staff($reviewer, 'exam_reviewer');
        $r = Db::one('SELECT * FROM assessment_attempts WHERE id=?', [Api::id($id)])
            ?? throw new Problem(404, 'assessment_attempt', 'Tentativa nu a fost găsită.');
        $user = Db::one('SELECT * FROM users WHERE id=?', [$r['user_id']]);
        $data = self::attempt($user, $id); $bank = self::release($r['release_id']); $byKey = array_column($bank['bank']['questions'], null, 'key');
        $items = array_column(Db::all('SELECT id,question_key FROM assessment_items WHERE attempt_id=?', [$id]), 'question_key', 'id');
        foreach ($data['items'] as &$item) {
            $q = $byKey[$items[$item['id']]];
            $item['marking'] = ['answer' => $q['answer'], 'explanation' => $q['explanation'], 'reference' => $q['reference']];
        }
        unset($item); return $data + ['learner_id' => $user['id'], 'private_review' => true];
    }

    public static function appeal(array $user, string $id, array $data): array
    {
        $result = self::result($user, $id);
        self::evidenceAvailable($result['attempt_id']);
        Db::run('UPDATE assessment_results SET appeal_json=? WHERE id=?', [Db::json(['reason' => ProgramAccess::text($data, 'reason', 4000), 'state' => 'pending', 'at' => Db::now()]), $id]);
        ProgramAccess::event($user, 'assessment_result', $id, 'appealed', ['reason' => $data['reason']]);
        return self::result($user, $id);
    }

    public static function decideAppeal(array $reviewer, string $id, array $data): array
    {
        ProgramAccess::staff($reviewer, 'exam_reviewer');
        $old = Db::one('SELECT r.*,a.user_id,a.result_id AS current_result_id,a.enrollment_id FROM assessment_results r JOIN assessment_attempts a ON a.id=r.attempt_id WHERE r.id=? FOR UPDATE', [Api::id($id)])
            ?? throw new Problem(404, 'assessment_result', 'Rezultatul nu a fost găsit.');
        ProgramAccess::independent($reviewer, $old['user_id']);
        self::evidenceAvailable($old['attempt_id']);
        if ($old['user_id'] === $reviewer['id'] || $old['current_result_id'] !== $id) {
            throw new Problem(409, 'assessment_review', 'Evaluarea cere un rezultat curent și un evaluator independent.');
        }
        $reason = ProgramAccess::text($data, 'reason', 4000);
        $invalid = $data['invalid_question_ids'] ?? [];
        if (!is_array($invalid) || !array_is_list($invalid) || count($invalid) > 30) {
            throw new Problem(422, 'assessment_review', 'Lista întrebărilor anulate este incorectă.');
        }
        $attempt = Db::one('SELECT * FROM assessment_attempts WHERE id=?', [$old['attempt_id']]);
        $bank = self::release($attempt['release_id']); $byKey = array_column($bank['bank']['questions'], null, 'key');
        $items = Db::all('SELECT * FROM assessment_items WHERE attempt_id=? ORDER BY position', [$attempt['id']]);
        if (array_diff($invalid, array_column($items, 'id'))) {
            throw new Problem(422, 'assessment_review', 'Poți anula doar întrebări din tentativa curentă.');
        }
        $questions = []; $answers = [];
        foreach ($items as $item) {
            $q = $byKey[$item['question_key']]; $questions[] = $q;
            $answers[$q['key']] = in_array($item['id'], $invalid, true) ? $q['answer'] : ($item['response_json'] === null ? null : json_decode($item['response_json'], true));
        }
        $grade = self::grade($questions, $answers); $next = Db::id();
        Db::insert('assessment_results', ['id' => $next, 'attempt_id' => $old['attempt_id'], 'version' => (int) $old['version'] + 1,
            'score_basis_points' => $grade['score_basis_points'], 'passed' => (int) $grade['passed'], 'critical_passed' => (int) $grade['critical_passed'],
            'details_json' => Db::json(['domains' => $grade['domains'], 'policy' => $bank['bank']['policy'], 'invalid_question_ids' => $invalid]),
            'reviewer_id' => $reviewer['id'], 'reason' => $reason, 'created_at' => Db::now()]);
        Db::run('UPDATE assessment_attempts SET result_id=? WHERE id=?', [$next, $old['attempt_id']]);
        Db::run('UPDATE assessment_results SET appeal_json=? WHERE id=?', [Db::json(['state' => 'decided', 'reason' => $reason, 'new_result_id' => $next, 'at' => Db::now()]), $id]);
        // A replaced result cannot continue to support an active credential.
        Db::run("UPDATE learning_credentials SET state='revoked',revoked_at=?,reason='Rezultat înlocuit prin reevaluare' WHERE result_id=? AND state='active'", [Db::now(), $id]);
        ProgramAccess::event($reviewer, 'assessment_result', $id, 'regraded', ['new_result_id' => $next, 'reason' => $reason]);
        $student = Db::one('SELECT * FROM users WHERE id=? AND active=1', [$old['user_id']]);
        if ($student) {
            foreach (Db::all('SELECT id FROM learning_enrollments WHERE user_id=? AND release_id=?', [$student['id'], $bank['course_release_id']]) as $enrollment) {
                self::issue($student, $enrollment['id']);
            }
        }
        return self::result($reviewer, $next, true);
    }

    public static function retire(array $user, string $id, array $data): array
    {
        ProgramAccess::staff($user, 'exam_reviewer');
        $reason = ProgramAccess::text($data, 'reason', 2000);
        Db::run("UPDATE assessment_releases SET state='retired' WHERE id=?", [Api::id($id)]);
        ProgramAccess::event($user, 'assessment_release', $id, 'retired', ['reason' => $reason]);
        return ['id' => $id, 'state' => 'retired'];
    }

    public static function visibility(array $user, string $id, array $data): array
    {
        $r = Db::one('SELECT * FROM learning_credentials WHERE id=? AND user_id=?', [Api::id($id), $user['id']])
            ?? throw new Problem(404, 'credential', 'Calificarea nu a fost găsită.');
        if (!is_bool($data['public_name'] ?? null)) {
            throw new Problem(422, 'credential_visibility', 'Alege dacă numele tău poate fi publicat.');
        }
        Db::run('UPDATE learning_credentials SET public_name=? WHERE id=?', [(int) $data['public_name'], $id]);
        ProgramAccess::event($user, 'credential', $id, 'visibility_changed');
        return self::credential(Db::one('SELECT * FROM learning_credentials WHERE id=?', [$id]), true);
    }

    public static function issue(array $user, string $enrollmentId): ?array
    {
        $enrollment = ProgramAccess::owned('learning_enrollments', $enrollmentId, $user);
        $result = Db::one("SELECT r.id FROM assessment_results r JOIN assessment_attempts a ON a.id=r.attempt_id AND a.result_id=r.id AND a.state='graded' JOIN learning_enrollments e ON e.id=a.enrollment_id WHERE a.user_id=? AND e.release_id=? AND r.passed=1 AND r.created_at>? ORDER BY r.created_at DESC,r.version DESC LIMIT 1",
            [$user['id'], $enrollment['release_id'], gmdate('Y-m-d H:i:s', time() - 365 * 86400)]);
        $lessons = ProgramLearning::lessons(ProgramLearning::release($enrollment['release_id']), $enrollment['specialization']);
        $completed = (int) Db::one('SELECT COUNT(*) AS n FROM learning_progress WHERE enrollment_id=? AND completed=1 AND practice_passed=1', [$enrollmentId])['n'];
        if (!$result || $completed !== count($lessons) || !ProgramLabs::qualified($enrollment)) {
            return null;
        }
        $existing = Db::one('SELECT * FROM learning_credentials WHERE result_id=? AND specialization=?', [$result['id'], $enrollment['specialization']]);
        if ($existing) {
            return self::credential($existing, true);
        }
        $id = Db::id();
        Db::insert('learning_credentials', ['id' => $id, 'public_id' => Db::id(), 'user_id' => $user['id'], 'enrollment_id' => $enrollmentId,
            'result_id' => $result['id'], 'specialization' => $enrollment['specialization'], 'issued_at' => Db::now(), 'expires_at' => gmdate('Y-m-d H:i:s', time() + 365 * 86400)]);
        ProgramAccess::event($user, 'credential', $id, 'issued');
        ProgramJobs::notice($user['id'], 'credential', $id, 'Ai obținut o calificare în programul OpenAliment. Verifică specializarea și valabilitatea în cont.');
        ProgramPartners::reconcileFor($user['id']);
        return self::credential(Db::one('SELECT * FROM learning_credentials WHERE id=?', [$id]), true);
    }

    private static function evidenceAvailable(string $attempt): void
    {
        if (Db::one("SELECT id FROM program_events WHERE object_kind='assessment_attempt' AND object_id=? AND action='evidence_minimized' LIMIT 1", [$attempt])) {
            throw new Problem(409, 'assessment_archived', 'Dovezile acestei tentative au depășit perioada de păstrare. Decizia și istoricul sunt disponibile; contactează programul pentru o verificare administrativă.');
        }
    }

    public static function credential(array $row, bool $private = false): array
    {
        $enrollment = Db::one('SELECT release_id,policy_version FROM learning_enrollments WHERE id=?', [$row['enrollment_id']]);
        $active = $row['state'] === 'active' && $row['expires_at'] > Db::now();
        $out = ['id' => $private ? $row['id'] : $row['public_id'], 'public_id' => $row['public_id'], 'state' => $active ? 'active' : ($row['state'] === 'active' ? 'expired' : $row['state']),
            'specialization' => $row['specialization'], 'issuer' => 'OpenAliment', 'issued_at' => $row['issued_at'], 'expires_at' => $row['expires_at'],
            'course_release_id' => $enrollment['release_id'],
            'policy_version' => $enrollment['policy_version'], 'verification_url' => rtrim((string) Config::get('url'), '/') . '/invatare/calificari/' . $row['public_id']];
        if ($private) {
            $out += ['enrollment_id' => $row['enrollment_id'], 'result_id' => $row['result_id']];
        } elseif ($row['public_name']) {
            $out['name'] = Db::one('SELECT display_name FROM users WHERE id=?', [$row['user_id']])['display_name'];
        }
        return $out;
    }
}
