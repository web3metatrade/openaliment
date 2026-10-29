<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Db, Problem};

/** Bounded virtual exercises. No producer objects, network calls or executable uploads. */
final class ProgramLabs
{
    public const TRACK_SCENARIOS = ['erp-integration' => 'P03', 'printing-integration' => 'P04', 'platform-operations' => 'P05', 'product-data' => 'P06'];

    public static function create(array $user, array $data): array
    {
        $enrollment = ProgramAccess::owned('learning_enrollments', Api::id(ProgramAccess::text($data, 'enrollment_id', 32)), $user);
        $scenario = ProgramAccess::text($data, 'scenario', 3);
        if (!in_array($scenario, ['P01', 'P02', self::TRACK_SCENARIOS[$enrollment['specialization']]], true)) {
            throw new Problem(422, 'lab_scenario', 'Proba nu aparține specializării.');
        }
        if ((int) Db::one('SELECT COUNT(*) AS n FROM assessment_lab_runs WHERE user_id=? AND created_at>?', [$user['id'], gmdate('Y-m-d H:i:s', time() - 86400)])['n'] >= 20) {
            throw new Problem(429, 'lab_limit', 'Poți începe cel mult 20 de probe practice pe zi.');
        }
        if ((int) Db::one('SELECT COUNT(*) AS n FROM assessment_lab_runs WHERE user_id=? AND created_at>?', [$user['id'], gmdate('Y-m-d H:i:s', time() - 365 * 86400)])['n'] >= 60) {
            throw new Problem(429, 'lab_storage_limit', 'Limita anuală de probe a contului a fost atinsă. Contactează programul pentru verificarea dosarului.');
        }
        \App\Core\SecurityBudget::check('program_labs_day', 2000, 86400);
        $id = Db::id();
        Db::insert('assessment_lab_runs', ['id' => $id, 'user_id' => $user['id'], 'enrollment_id' => $enrollment['id'], 'scenario' => $scenario,
            'payload_json' => Db::json(json_decode(file_get_contents(ROOT . '/contracts/program/labs.json'), true, 24, JSON_THROW_ON_ERROR)['scenarios'][$scenario]['template']), 'updated_at' => Db::now(), 'created_at' => Db::now()]);
        ProgramAccess::event($user, 'lab', $id, 'created', ['scenario' => $scenario]);
        return self::view($user, $id);
    }

    public static function view(array $user, string $id, bool $reviewer = false): array
    {
        if ($reviewer) {
            ProgramAccess::staff($user, 'exam_reviewer');
            $row = Db::one('SELECT * FROM assessment_lab_runs WHERE id=?', [Api::id($id)]);
        } else {
            $row = ProgramAccess::owned('assessment_lab_runs', $id, $user);
        }
        if (!$row) {
            throw new Problem(404, 'lab', 'Proba nu a fost găsită.');
        }
        $out = ['id' => $row['id'], 'enrollment_id' => $row['enrollment_id'], 'scenario' => $row['scenario'], 'revision' => (int) $row['revision'],
            'state' => $row['state'], 'payload' => json_decode($row['payload_json'], true) ?: new \stdClass(),
            'checks' => $row['checks_json'] ? json_decode($row['checks_json'], true) : [],
            'review' => $row['review_json'] ? json_decode($row['review_json'], true) : null, 'evidence_asset_id' => $row['evidence_asset_id'], 'simulation' => true];
        if ($reviewer) { $out['learner'] = ['id' => $row['user_id'], 'name' => Db::one('SELECT display_name FROM users WHERE id=?', [$row['user_id']])['display_name']]; }
        return $out;
    }

    public static function save(array $user, string $id, array $data): array
    {
        $row = ProgramAccess::owned('assessment_lab_runs', $id, $user);
        ProgramAccess::revision($row, $data);
        if (in_array($row['state'], ['validated', 'submitted'], true)) {
            throw new Problem(409, 'lab_state', 'Proba predată este păstrată în istoric.');
        }
        $payload = $data['payload'] ?? null;
        if (!is_array($payload) || strlen(Db::json($payload)) > 100000 || count($payload) > 20) {
            throw new Problem(422, 'lab_payload', 'Datele laboratorului depășesc formatul permis.');
        }
        \App\Core\RateLimit::check('program-lab-saves|' . $user['id'], 20, 3600);
        $evidence = $data['evidence_asset_id'] ?? null;
        if ($evidence !== null) {
            ProgramMedia::owned($user, Api::id($evidence), 'lab_evidence');
        }
        Db::run("UPDATE assessment_lab_runs SET payload_json=?,evidence_asset_id=?,revision=revision+1,state='draft',updated_at=? WHERE id=?", [Db::json($payload), $evidence, Db::now(), $id]);
        ProgramAccess::event($user, 'lab', $id, 'saved', ['revision' => (int) $row['revision'] + 1, 'sha256' => hash('sha256', Db::json($payload))]);
        return self::view($user, $id);
    }

    public static function evaluate(string $scenario, array $p): array
    {
        $checks = [];
        $add = static function (string $name, bool $passed) use (&$checks): void {
            $checks[] = compact('name', 'passed');
        };
        if ($scenario === 'P01') {
            $parsed = [];
            foreach (['product', 'batch', 'unit'] as $key) {
                $uri = $p[$key] ?? '';
                try {
                    $url = is_string($uri) ? parse_url($uri) : false;
                    if (!$url || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass']) || isset($url['fragment'])) {
                        throw new Problem(422, 'lab_uri', 'Adresă HTTPS necesară.');
                    }
                    $parsed[$key] = ResolverUri::parse($url['path'] ?? '', $url['query'] ?? '');
                    $add('URI ' . $key . ' conform profilului GTIN', true);
                } catch (Problem) {
                    $add('URI ' . $key . ' conform profilului GTIN', false);
                }
            }
            $add('Identificatori demonstrativi, același articol', count($parsed) === 3 && count(array_unique(array_column($parsed, 'gtin'))) === 1 && ($parsed['product']['gtin'] ?? '') === '09520000000011');
            $add('Produs fără calificatori de lot sau serie', isset($parsed['product']) && !$parsed['product']['qualifiers']);
            $add('Lot exact, inclusiv caractere rezervate', ($parsed['batch']['qualifiers']['10'] ?? '') === 'LOT/A+B%1' && !isset($parsed['batch']['qualifiers']['21']));
            $add('Unitatea păstrează lotul și are serie', ($parsed['unit']['qualifiers']['10'] ?? '') === 'LOT/A+B%1' && ($parsed['unit']['qualifiers']['21'] ?? '') !== '');
        } elseif ($scenario === 'P02') {
            $events = $p['events'] ?? [];
            $labels = []; $batch = null; $category = null; $alert = null; $released = false; $newLabel = false;
            if (!is_array($events) || !array_is_list($events) || count($events) > 40) {
                throw new Problem(422, 'lab_events', 'Trimite cel mult 40 de evenimente.');
            }
            foreach ($events as $event) {
                if (!is_array($event)) {
                    throw new Problem(422, 'lab_event', 'Eveniment incorect.');
                }
                $op = $event['operation'] ?? '';
                if ($op === 'product') {
                    $category = ProgramAccess::text($event, 'category', 80);
                } elseif ($op === 'label') {
                    $key = ProgramAccess::text($event, 'version', 40);
                    if (isset($labels[$key])) {
                        throw new Problem(409, 'lab_immutable', 'Eticheta existentă nu se rescrie.');
                    }
                    $labels[$key] = ['name' => ProgramAccess::text($event, 'name', 190), 'ingredients' => ProgramAccess::text($event, 'ingredients', 4000), 'published' => false];
                    if ($batch !== null) {
                        $newLabel = true;
                    }
                } elseif ($op === 'publish_label' && isset($labels[$event['version'] ?? ''])) {
                    $labels[$event['version']]['published'] = true;
                } elseif ($op === 'batch') {
                    $label = $labels[$event['label_version'] ?? ''] ?? null;
                    if (!$label || !$label['published'] || isset($event['recipe_version_id']) || $batch !== null) {
                        throw new Problem(422, 'lab_batch', 'Lotul folosește o etichetă publicată și nu cere o rețetă.');
                    }
                    $batch = ['lot' => ProgramAccess::text($event, 'lot', 80), 'label' => $label];
                } elseif ($op === 'release_batch' && $batch) {
                    $released = true;
                } elseif ($op === 'alert' && $batch) {
                    $alert = ProgramAccess::text($event, 'message', 1000);
                } else {
                    throw new Problem(422, 'lab_operation', 'Operațiune sau ordine incorectă în laborator.');
                }
            }
            $add('Categorie diferită de pâine', $category !== null && !in_array(mb_strtolower($category), ['bread', 'paine', 'pâine'], true));
            $add('Lot publicabil fără rețetă', $batch !== null && $released);
            $add('Versiune nouă fără rescrierea etichetei lotului', $batch !== null && $newLabel && $batch['label'] === reset($labels));
            $add('Alertă curentă vizibilă', $alert !== null);
            $add('Simularea nu acordă siglă sau licență asupra rețetelor', ($p['open_badge'] ?? false) === false && !isset($p['private_recipe']));
            $add('Pagina nu depinde de AI', ($p['ai_enabled'] ?? null) === false);
        } elseif ($scenario === 'P03') {
            $events = $p['events'] ?? [];
            if (!is_array($events) || !array_is_list($events) || count($events) > 30) {
                throw new Problem(422, 'lab_events', 'Lista evenimentelor este incorectă.');
            }
            $receipts = []; $versions = []; $duplicates = 0; $conflicts = 0; $denials = 0;
            foreach ($events as $e) {
                $key = ProgramAccess::text($e, 'event_key', 80);
                $resource = ProgramAccess::text($e, 'resource', 80);
                if (($e['tenant'] ?? '') !== 'A' || ($e['scope'] ?? '') !== 'write') {
                    $denials++; continue;
                }
                $hash = hash('sha256', Db::json($e));
                if (isset($receipts[$key])) {
                    $receipts[$key] === $hash ? $duplicates++ : $conflicts++;
                    continue;
                }
                if (($e['expected_revision'] ?? null) !== ($versions[$resource] ?? 0)) {
                    $conflicts++; continue;
                }
                $receipts[$key] = $hash; $versions[$resource] = ($versions[$resource] ?? 0) + 1;
            }
            $add('Retry identic fără duplicare', $duplicates >= 1 && count($versions) === 1);
            $add('Conflict de revizie tratat', $conflicts >= 1);
            $add('Accesul altei organizații sau read-only refuzat', $denials >= 2);
            $add('Export virtual coerent', ($p['export_revision'] ?? null) === reset($versions));
        } elseif ($scenario === 'P04') {
            $serials = $p['serials'] ?? [];
            $lot = ProgramAccess::text($p, 'lot', 20);
            ResolverUri::qualifier('10', $lot);
            if (!is_array($serials) || !array_is_list($serials) || count($serials) > 20) {
                throw new Problem(422, 'lab_serials', 'Lista seriilor este incorectă.');
            }
            foreach ($serials as $serial) {
                if (!is_string($serial)) {
                    throw new Problem(422, 'lab_serial', 'Seria trebuie să fie text.');
                }
                ResolverUri::qualifier('21', $serial);
            }
            $add('Zece serii distincte', count($serials) === 10 && count(array_unique($serials)) === 10);
            $add('GTIN demonstrativ corect', ($p['gtin'] ?? '') === '09520000000011');
            $add('Format de transport declarat', in_array($p['format'] ?? '', ['json', 'zpl'], true));
            $add('Reimprimarea este justificată și nu schimbă seria', is_string($p['reprint_reason'] ?? null) && mb_strlen($p['reprint_reason']) >= 20 && in_array($p['reprint_serial'] ?? null, $serials, true));
            $add('ACK separat de dovada tipăririi', ($p['ack_is_physical_proof'] ?? null) === false);
        } elseif ($scenario === 'P05') {
            $descriptor = $p['descriptor'] ?? [];
            $add('Profilul declarat indică numai cheile probate', ($descriptor['primary_keys'] ?? []) === ['01']);
            $add('Redirectul simplu nu este declarat resolver complet', ($p['simple_redirect_is_full_resolver'] ?? null) === false);
            $original = $p['backup_snapshot'] ?? null; $restored = $p['restored_snapshot'] ?? null;
            $add('Snapshotul restaurat păstrează datele', is_array($original) && $original && $original === $restored);
            $add('Restaurarea nu este făcută pe producție', ($p['restore_environment'] ?? '') === 'isolated');
            $add('Alertele și cheile compromise au măsuri distincte', ($p['incident_actions'] ?? []) === ['restrict_affected_batch', 'revoke_compromised_key', 'verify_history']);
        } elseif ($scenario === 'P06') {
            $add('Date externe marcate cu proveniență', ($p['external_source'] ?? '') === 'openfoodfacts' && ($p['external_is_producer_approved'] ?? null) === false);
            $add('Rețeta privată rămâne privată', ($p['public_fields'] ?? []) === ['name', 'ingredients', 'allergens', 'net_quantity']);
            $add('Schimbarea firmei păstrează vechiul CUI', ($p['old_company_preserved'] ?? null) === true && ($p['old_data_transferred_automatically'] ?? null) === false);
            $add('Calificarea nu este autorizație a firmei noi', ($p['new_mandate_required'] ?? null) === true);
            $add('Estimarea pilotului nu este rezultat validat', ($p['pilot_evidence_state'] ?? '') === 'experimental');
        } else {
            throw new Problem(422, 'lab_scenario', 'Probă necunoscută.');
        }
        return $checks;
    }

    public static function submit(array $user, string $id): array
    {
        $row = ProgramAccess::owned('assessment_lab_runs', $id, $user);
        if (in_array($row['state'], ['validated', 'submitted'], true)) {
            return self::view($user, $id);
        }
        \App\Core\RateLimit::check('program-lab-submits|' . $user['id'], 20, 3600);
        $checks = self::evaluate($row['scenario'], json_decode($row['payload_json'], true));
        $passed = !array_filter($checks, static fn(array $c): bool => !$c['passed']);
        Db::run('UPDATE assessment_lab_runs SET checks_json=?,state=?,updated_at=? WHERE id=?', [Db::json($checks), $passed ? 'submitted' : 'needs_changes', Db::now(), $id]);
        $evidence = ['automatic_checks_passed' => $passed, 'revision' => (int) $row['revision'], 'sha256' => hash('sha256', $row['payload_json'])];
        if ($passed) { $evidence += ['payload' => json_decode($row['payload_json'], true), 'evidence_asset_id' => $row['evidence_asset_id']]; }
        ProgramAccess::event($user, 'lab', $id, 'submitted', $evidence);
        return self::view($user, $id);
    }

    public static function review(array $reviewer, string $id, array $data): array
    {
        ProgramAccess::staff($reviewer, 'exam_reviewer');
        $row = Db::one('SELECT * FROM assessment_lab_runs WHERE id=? FOR UPDATE', [Api::id($id)])
            ?? throw new Problem(404, 'lab', 'Proba nu a fost găsită.');
        ProgramAccess::independent($reviewer, $row['user_id']);
        if ($row['user_id'] === $reviewer['id']) {
            throw new Problem(403, 'self_review', 'Propria probă necesită alt evaluator.');
        }
        if ($row['state'] !== 'submitted') {
            throw new Problem(409, 'lab_state', 'Proba nu este în așteptarea evaluării.');
        }
        $points = [];
        foreach (['identity', 'history', 'security', 'explanation'] as $criterion) {
            $point = $data[$criterion . '_points'] ?? null;
            if (!is_int($point) || $point < 0 || $point > 25) {
                throw new Problem(422, 'rubric', 'Fiecare criteriu are 0–25 de puncte.');
            }
            $points[$criterion] = $point;
        }
        $reason = ProgramAccess::text($data, 'reason', 4000);
        $mandatory = ($data['mandatory_checks'] ?? false) === true;
        if ($row['scenario'] === 'P04' && (!$row['evidence_asset_id'] || ($data['physical_proof_reviewed'] ?? false) !== true)) {
            $mandatory = false;
        }
        $passed = array_sum($points) >= 85 && $mandatory;
        $review = ['points' => $points, 'passed' => $passed, 'mandatory_checks' => $mandatory, 'reason' => $reason];
        Db::run('UPDATE assessment_lab_runs SET state=?,reviewer_id=?,review_json=?,updated_at=? WHERE id=?',
            [$passed ? 'validated' : 'needs_changes', $reviewer['id'], Db::json($review), Db::now(), $id]);
        ProgramAccess::event($reviewer, 'lab', $id, 'reviewed', $review + ['revision' => (int) $row['revision']]);
        ProgramJobs::notice($row['user_id'], 'lab', $id, 'Evaluarea unei probe practice este disponibilă în contul tău.');
        $student = Db::one('SELECT id,email_verified FROM users WHERE id=? AND active=1', [$row['user_id']]);
        if ($passed && $student) {
            ProgramAssessments::issue($student, $row['enrollment_id']);
        }
        return self::view($reviewer, $id, true);
    }

    public static function qualified(array $enrollment): bool
    {
        $needed = ['P01', 'P02', self::TRACK_SCENARIOS[$enrollment['specialization']]];
        $done = array_column(Db::all("SELECT DISTINCT scenario FROM assessment_lab_runs WHERE enrollment_id=? AND state='validated'", [$enrollment['id']]), 'scenario');
        return !array_diff($needed, $done);
    }
}
