<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Config, Db, Mailer, Migrations, Problem};

/** Bounded tasks; no job accepts SQL, a filesystem destination or executable content. */
final class ProgramJobs
{
    public static function notice(string $user, string $kind, string $object, string $message, ?string $key = null): void
    {
        if (!in_array($kind, ['result', 'lab', 'credential', 'partner', 'expiry'], true)) { throw new \LogicException('Unknown program notice'); }
        if (mb_strlen($message) > 1000) { throw new \LogicException('Program notice too long'); }
        $key ??= 'notice:' . Db::id();
        Db::run('INSERT INTO program_jobs (id,job_key,kind,payload_json,available_at,created_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE job_key=VALUES(job_key)',
            [Db::id(), $key, 'notice', Db::json(['user_id' => $user, 'kind' => $kind, 'object_id' => $object, 'message' => $message]), Db::now(), Db::now()]);
    }

    public static function work(): array
    {
        if (!Migrations::program()) { return ['state' => 'not_installed']; }
        if (!Db::one("SELECT GET_LOCK('oa_program_worker',0) AS acquired")['acquired']) { return ['state' => 'busy']; }
        try { return self::run(); }
        finally { Db::run("SELECT RELEASE_LOCK('oa_program_worker')"); }
    }

    private static function run(): array
    {
        $expired = 0;
        foreach (Db::all("SELECT id,user_id FROM assessment_attempts WHERE state='in_progress' AND due_at<=? ORDER BY due_at LIMIT 5", [Db::now()]) as $r) {
            Db::atomic(static function () use ($r, &$expired): void {
                $user = Db::one('SELECT * FROM users WHERE id=? FOR UPDATE', [$r['user_id']]);
                $attempt = Db::one('SELECT state FROM assessment_attempts WHERE id=? FOR UPDATE', [$r['id']]);
                if ($user && $attempt['state'] === 'in_progress') { ProgramAssessments::submit($user, $r['id']); $expired++; }
            });
        }
        $fiscal = 'not_due';
        $p = Db::one("SELECT * FROM partner_profiles WHERE state='active' AND fiscal_checked_at<? AND NOT EXISTS(SELECT id FROM program_jobs j WHERE j.job_key=CONCAT('fiscal:',partner_profiles.id) AND j.available_at>?) ORDER BY fiscal_checked_at LIMIT 1", [gmdate('Y-m-d H:i:s', time() - 27 * 86400), Db::now()]);
        if ($p) {
            // One fiscal call per worker execution and shared ANAF rate budget across installations.
            try {
                $observation = OrganizationRegistry::observe($p['cui']);
                ProgramQueries::applyObservation($p, $observation);
                foreach (Db::all("SELECT user_id FROM partner_affiliations WHERE profile_id=? AND state='active'", [$p['id']]) as $a) { ProgramPartners::reconcileFor($a['user_id']); }
                $fiscal = 'refreshed';
                Db::run("DELETE FROM program_jobs WHERE job_key=? AND kind='fiscal_backoff'", ['fiscal:' . $p['id']]);
            } catch (Problem $error) {
                $fiscal = 'unavailable';
                $old = Db::one('SELECT attempts FROM program_jobs WHERE job_key=?', ['fiscal:' . $p['id']]);
                $attempts = min(20, (int) ($old['attempts'] ?? 0) + 1);
                Db::run("INSERT INTO program_jobs (id,job_key,kind,payload_json,state,attempts,available_at,created_at) VALUES (?,?,'fiscal_backoff','{}','cooldown',?,?,?) ON DUPLICATE KEY UPDATE attempts=VALUES(attempts),available_at=VALUES(available_at)",
                    [Db::id(), 'fiscal:' . $p['id'], $attempts, gmdate('Y-m-d H:i:s', time() + min(86400, 300 * (2 ** min(8, $attempts - 1)))), Db::now()]);
                Operations::record('program.fiscal', 'failed', ['code' => $error->errorCode]);
            }
        }
        $receipts = Db::run("DELETE FROM program_events WHERE receipt_key IS NOT NULL AND created_at<? LIMIT 100", [gmdate('Y-m-d H:i:s', time() - 90 * 86400)])->rowCount();
        Db::run("DELETE FROM program_verifications WHERE expires_at<? AND NOT (purpose='contributor_consent' AND state='confirmed') LIMIT 100", [gmdate('Y-m-d H:i:s', time() - 30 * 86400)]);
        foreach (Db::all("SELECT id,user_id,expires_at FROM learning_credentials WHERE state='active' AND expires_at>? AND expires_at<? ORDER BY expires_at LIMIT 10", [Db::now(), gmdate('Y-m-d H:i:s', time() + 30 * 86400)]) as $c) {
            $days = (int) ceil((strtotime($c['expires_at'] . ' UTC') - time()) / 86400);
            $window = $days <= 7 ? '7' : '30';
            self::notice($c['user_id'], 'expiry', $c['id'], 'O calificare din contul tău se apropie de expirare. Verifică valabilitatea și condițiile de reînnoire.', 'expiry:' . $c['id'] . ':' . $window);
        }
        $mail = self::mail(); $purged = self::purgeImages(); $minimized = self::minimizeEvidence();
        Db::run("DELETE FROM program_jobs WHERE state IN ('sent','cancelled','failed') AND created_at<? LIMIT 100", [gmdate('Y-m-d H:i:s', time() - 90 * 86400)]);
        Operations::record('program.worker', 'completed', ['expired_attempts' => $expired, 'fiscal' => $fiscal]);
        return ['state' => 'completed', 'expired_attempts' => $expired, 'fiscal' => $fiscal, 'receipts_removed' => $receipts, 'notification' => $mail, 'images_purged' => $purged, 'evidence_minimized' => $minimized];
    }

    /** Keep decisions and their fingerprints; minimize old answers only after the evidence period. */
    public static function minimizeEvidence(): int
    {
        $count = 0; $failedBefore = gmdate('Y-m-d H:i:s', time() - 365 * 86400); $validBefore = gmdate('Y-m-d H:i:s', time() - 730 * 86400);
        $rows = Db::all("SELECT a.* FROM assessment_attempts a WHERE a.state='graded' AND a.submitted_at<? AND NOT EXISTS(SELECT id FROM learning_credentials c WHERE c.result_id IN (SELECT id FROM assessment_results WHERE attempt_id=a.id) AND c.expires_at>?) AND NOT EXISTS(SELECT id FROM assessment_results r WHERE r.attempt_id=a.id AND JSON_UNQUOTE(JSON_EXTRACT(r.appeal_json,'$.state'))='pending') AND NOT EXISTS(SELECT id FROM program_events e WHERE e.object_kind='assessment_attempt' AND e.object_id=a.id AND e.action='evidence_minimized') ORDER BY a.submitted_at LIMIT 3", [$failedBefore, $validBefore]);
        foreach ($rows as $row) {
            Db::atomic(static function () use ($row, &$count): void {
                Db::one('SELECT id FROM users WHERE id=? FOR UPDATE', [$row['user_id']]);
                Db::one('SELECT id FROM assessment_attempts WHERE id=? FOR UPDATE', [$row['id']]);
                if (Db::one('SELECT c.id FROM learning_credentials c JOIN assessment_results r ON r.id=c.result_id WHERE r.attempt_id=? AND c.expires_at>? LIMIT 1', [$row['id'], gmdate('Y-m-d H:i:s', time() - 730 * 86400)])) { return; }
                if (Db::one("SELECT id FROM program_events WHERE object_kind='assessment_attempt' AND object_id=? AND action='evidence_minimized' LIMIT 1", [$row['id']])) { return; }
                if (Db::one("SELECT id FROM assessment_results WHERE attempt_id=? AND JSON_UNQUOTE(JSON_EXTRACT(appeal_json,'$.state'))='pending' LIMIT 1", [$row['id']])) { return; }
                $answers = Db::all('SELECT id,response_json FROM assessment_items WHERE attempt_id=? ORDER BY position', [$row['id']]);
                Db::run('UPDATE assessment_items SET response_json=NULL WHERE attempt_id=?', [$row['id']]);
                // Labs may support a newer credential on the same enrollment, so protect that evidence too.
                if (!Db::one('SELECT id FROM learning_credentials WHERE enrollment_id=? AND expires_at>? LIMIT 1', [$row['enrollment_id'], gmdate('Y-m-d H:i:s', time() - 730 * 86400)])) {
                    foreach (Db::all("SELECT * FROM assessment_lab_runs WHERE enrollment_id=? AND updated_at<? AND state<>'submitted'", [$row['enrollment_id'], gmdate('Y-m-d H:i:s', time() - 365 * 86400)]) as $lab) {
                        self::minimizeLab($lab);
                    }
                }
                ProgramAccess::event(['id' => $row['user_id']], 'assessment_attempt', $row['id'], 'evidence_minimized', ['answers_sha256' => hash('sha256', Db::json($answers)), 'policy' => '12 months; credential evidence expiry plus 24 months']);
                $count++;
            });
        }
        // Draft labs without a theory attempt also have a retention period.
        foreach (Db::all("SELECT l.* FROM assessment_lab_runs l WHERE l.updated_at<? AND l.state<>'submitted' AND NOT EXISTS(SELECT id FROM learning_credentials c WHERE c.enrollment_id=l.enrollment_id AND c.expires_at>?) AND NOT EXISTS(SELECT r.id FROM assessment_results r JOIN assessment_attempts a ON a.id=r.attempt_id WHERE a.enrollment_id=l.enrollment_id AND JSON_UNQUOTE(JSON_EXTRACT(r.appeal_json,'$.state'))='pending') AND NOT EXISTS(SELECT id FROM program_events e WHERE e.object_kind='lab' AND e.object_id=l.id AND e.action='evidence_minimized') ORDER BY l.created_at LIMIT 3", [$failedBefore, $validBefore]) as $lab) {
            Db::atomic(static function () use ($lab, $failedBefore, $validBefore, &$count): void {
                Db::one('SELECT id FROM users WHERE id=? FOR UPDATE', [$lab['user_id']]);
                $fresh = Db::one('SELECT * FROM assessment_lab_runs WHERE id=? FOR UPDATE', [$lab['id']]);
                if ($fresh['state'] === 'submitted' || ($fresh['updated_at'] ?? $fresh['created_at']) >= $failedBefore || Db::one('SELECT id FROM learning_credentials WHERE enrollment_id=? AND expires_at>? LIMIT 1', [$lab['enrollment_id'], $validBefore])) { return; }
                if (Db::one("SELECT r.id FROM assessment_results r JOIN assessment_attempts a ON a.id=r.attempt_id WHERE a.enrollment_id=? AND JSON_UNQUOTE(JSON_EXTRACT(r.appeal_json,'$.state'))='pending' LIMIT 1", [$lab['enrollment_id']])) { return; }
                self::minimizeLab($fresh); $count++;
            });
        }
        return $count;
    }

    private static function minimizeLab(array $lab): void
    {
        Db::run("UPDATE assessment_lab_runs SET payload_json='{}',evidence_asset_id=NULL WHERE id=?", [$lab['id']]);
        foreach (Db::all("SELECT id,details_json FROM program_events WHERE object_kind='lab' AND object_id=? AND action='submitted'", [$lab['id']]) as $event) {
            Db::run('UPDATE program_events SET details_json=? WHERE id=?', [Db::json(['minimized' => true, 'sha256' => hash('sha256', $event['details_json'])]), $event['id']]);
        }
        if ($lab['evidence_asset_id'] && !Db::one('SELECT id FROM assessment_lab_runs WHERE evidence_asset_id=? LIMIT 1', [$lab['evidence_asset_id']])) {
            Db::run("UPDATE program_assets SET state='withdrawn',withdrawn_at=? WHERE id=?", [Db::now(), $lab['evidence_asset_id']]);
        }
        ProgramAccess::event(['id' => $lab['user_id']], 'lab', $lab['id'], 'evidence_minimized', ['sha256' => hash('sha256', $lab['payload_json'])]);
    }

    private static function mail(): string
    {
        $job = Db::atomic(static function (): ?array {
            $r = Db::one("SELECT * FROM program_jobs WHERE kind='notice' AND available_at<=? AND (state='pending' OR (state='processing' AND lease_until<?)) ORDER BY available_at,id LIMIT 1 FOR UPDATE", [Db::now(), Db::now()]);
            if ($r) { Db::run("UPDATE program_jobs SET state='processing',attempts=attempts+1,lease_until=? WHERE id=?", [gmdate('Y-m-d H:i:s', time() + 120), $r['id']]); }
            return $r;
        });
        if (!$job) { return 'not_due'; }
        $payload = json_decode($job['payload_json'], true, 8, JSON_THROW_ON_ERROR);
        $user = Db::one('SELECT email,email_verified,active FROM users WHERE id=?', [$payload['user_id']]);
        if (!$user || !$user['active'] || !$user['email_verified']) {
            Db::run("UPDATE program_jobs SET state='cancelled',lease_until=NULL WHERE id=?", [$job['id']]); return 'cancelled';
        }
        try {
            Mailer::securityMessage($user['email'], 'Actualizare în programul OpenAliment', $payload['message'] . "\n\nVezi detaliile în contul tău:\n" . rtrim((string) Config::get('url'), '/') . '/invatare/cont' . "\n\nOpenAliment\n");
            Db::run("UPDATE program_jobs SET state='sent',lease_until=NULL,last_error=NULL WHERE id=?", [$job['id']]); return 'sent';
        } catch (Problem) {
            $state = (int) $job['attempts'] >= 4 ? 'failed' : 'pending';
            Db::run("UPDATE program_jobs SET state=?,available_at=?,lease_until=NULL,last_error='mail_unavailable' WHERE id=?", [$state, gmdate('Y-m-d H:i:s', time() + min(86400, 300 * (2 ** min(8, (int) $job['attempts'])))), $job['id']]);
            return $state === 'failed' ? 'failed' : 'retry';
        }
    }

    private static function purgeImages(): int
    {
        $count = 0;
        foreach (Db::all("SELECT id FROM program_assets WHERE state='withdrawn' AND size_bytes>0 AND withdrawn_at<? ORDER BY withdrawn_at LIMIT 5", [gmdate('Y-m-d H:i:s', time() - 30 * 86400)]) as $r) {
            $path = rtrim((string) Config::get('storage'), '/\\') . '/private/program-assets/' . $r['id'] . '.png';
            if (is_link($path)) { continue; }
            if (is_file($path) && !unlink($path)) { continue; }
            Db::run('UPDATE program_assets SET size_bytes=0 WHERE id=?', [$r['id']]); $count++;
        }
        return $count;
    }
}
