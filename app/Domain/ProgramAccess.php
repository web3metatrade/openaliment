<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Auth, Config, Db, Migrations, Problem, Session};

/** Program permissions never imply producer membership or platform administration. */
final class ProgramAccess
{
    public const ROLES = ['community_editor', 'course_editor', 'course_publisher', 'exam_reviewer', 'partner_reviewer'];

    public static function installed(): void
    {
        if (!Migrations::program()) {
            throw new Problem(503, 'program_setup', 'Programul nu este disponibil momentan.');
        }
    }

    public static function user(): array
    {
        $user = Auth::user();
        if (!$user) {
            $_SESSION['application_return'] ??= '/invatare/cont';
            throw new Problem(401, 'authentication', 'Conectează-te la cont.');
        }
        Passkeys::full($user);
        return $user;
    }

    public static function actor(string $scope, bool $sessionOnly = false): array
    {
        if (($secret = Api::bearer()) !== null) {
            if ($sessionOnly || $scope === '') {
                throw new Problem(403, 'browser_session', 'Operațiunea necesită sesiunea contului în browser.');
            }
            $grant = OAuth::access($secret, $scope, 'program');
            $user = Db::one('SELECT id,email,display_name,email_verified FROM users WHERE id=? AND active=1', [$grant['user_id']]);
            return $user + ['is_admin' => false, 'program_token' => $grant['token_id']];
        }
        return self::user();
    }

    public static function staff(array $user, string $role): void
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new \LogicException('Unknown program role');
        }
        if ($user['is_admin']) {
            Passkeys::full($user);
            return;
        }
        if (!Db::one('SELECT id FROM program_staff_grants WHERE user_id=? AND role=? AND revoked_at IS NULL', [$user['id'], $role])) {
            throw new Problem(403, 'program_role', 'Nu ai dreptul de a administra această secțiune.');
        }
    }

    public static function owned(string $table, string $id, array $user): array
    {
        $allowed = ['learning_enrollments', 'assessment_attempts', 'assessment_lab_runs', 'contributor_profiles', 'partner_applications'];
        if (!in_array($table, $allowed, true)) {
            throw new \LogicException('Unknown program object');
        }
        return Db::one('SELECT * FROM ' . $table . ' WHERE id=? AND user_id=?' . (Db::pdo()->inTransaction() ? ' FOR UPDATE' : ''), [Api::id($id), $user['id']])
            ?? throw new Problem(404, 'program_object', 'Resursa nu a fost găsită.');
    }

    public static function anyStaff(array $user, array $roles): void
    {
        foreach ($roles as $role) {
            try { self::staff($user, $role); return; } catch (Problem $error) { if ($error->status !== 403) { throw $error; } }
        }
        throw new Problem(403, 'program_role', 'Nu ai dreptul de a administra această secțiune.');
    }

    public static function independent(array $reviewer, string $learner): void
    {
        if ($reviewer['id'] === $learner
            || Db::one('SELECT a.tenant_id FROM memberships a JOIN memberships b ON b.tenant_id=a.tenant_id WHERE a.user_id=? AND b.user_id=? LIMIT 1', [$reviewer['id'], $learner])
            || Db::one("SELECT a.id FROM partner_affiliations a JOIN partner_affiliations b ON b.profile_id=a.profile_id AND b.state='active' WHERE a.user_id=? AND b.user_id=? AND a.state='active' LIMIT 1", [$reviewer['id'], $learner])) {
            throw new Problem(403, 'independent_review', 'Evaluarea necesită o altă persoană, fără afiliere comună cu persoana evaluată.');
        }
    }

    public static function text(array $data, string $key, int $max = 2000, bool $optional = false): string
    {
        if ($optional && !isset($data[$key])) {
            return '';
        }
        return Catalog::text($data, $key, $max);
    }

    public static function revision(array $row, array $data): void
    {
        if (!isset($data['expected_revision']) || !is_int($data['expected_revision']) || (int) $row['revision'] !== $data['expected_revision']) {
            throw new Problem(409, 'program_revision', 'Datele s-au modificat. Reîncarcă versiunea curentă.');
        }
    }

    public static function event(array $user, string $kind, string $id, string $action, array $details = []): void
    {
        Db::insert('program_events', ['id' => Db::id(), 'user_id' => $user['id'], 'object_kind' => $kind, 'object_id' => $id,
            'action' => $action, 'details_json' => Db::json($details), 'created_at' => Db::now()]);
    }

    public static function list(string $sql, array $params, callable $project): array
    {
        [$limit, $cursor] = Api::page();
        return Api::collection(Db::all($sql . ' AND id>? ORDER BY id LIMIT ' . ($limit + 1), [...$params, $cursor]), $limit, $project);
    }

    public static function home(array $user): string
    {
        if (Migrations::program() && !Db::one('SELECT tenant_id FROM memberships WHERE user_id=? LIMIT 1', [$user['id']])) {
            return '/invatare/cont';
        }
        if (Migrations::program() && !$user['is_admin'] && Db::one('SELECT id FROM partner_applications WHERE user_id=? LIMIT 1', [$user['id']])) { return '/parteneri/cont'; }
        return '/manage';
    }

    public static function mutation(array $user, array $data, callable $run): array
    {
        $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '';
        if (!preg_match('/\A[A-Za-z0-9_.:-]{8,128}\z/D', $key)) {
            throw new Problem(422, 'idempotency', 'Trimite o cheie Idempotency-Key de 8–128 de caractere.');
        }
        unset($data['current_password']);
        if (isset($data['input']) && is_array($data['input'])) { unset($data['input']['current_password']); }
        $receipt = hash('sha256', $user['id'] . '|' . $key);
        $hash = hash_hmac('sha256', 'program.request.v1|' . ($_SERVER['REQUEST_METHOD'] ?? '') . '|' . ($_SERVER['REQUEST_URI'] ?? '') . '|' . Db::json(self::canonical($data)), \App\Core\PrivateKey::key());
        return Db::atomic(static function () use ($user, $receipt, $hash, $run, $data): array {
            Db::one('SELECT id FROM users WHERE id=? FOR UPDATE', [$user['id']]);
            $row = Db::one('SELECT request_hash,result_json FROM program_events WHERE receipt_key=?', [$receipt]);
            if ($row) {
                if (!hash_equals($row['request_hash'], $hash)) {
                    throw new Problem(409, 'idempotency_conflict', 'Cheia a fost folosită pentru altă cerere.');
                }
                return json_decode($row['result_json'], true, 64, JSON_THROW_ON_ERROR);
            }
            $result = $run();
            self::storageBudget($user, strlen(Db::json($result)) + strlen(Db::json($data)));
            Db::insert('program_events', ['id' => Db::id(), 'user_id' => $user['id'], 'object_kind' => 'receipt',
                'object_id' => $user['id'], 'action' => 'mutation', 'details_json' => '{}', 'receipt_key' => $receipt,
                'request_hash' => $hash, 'result_json' => Db::json($result), 'created_at' => Db::now()]);
            return $result;
        });
    }

    private static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) { return $value; }
        if (!array_is_list($value)) { ksort($value, SORT_STRING); }
        foreach ($value as &$item) { $item = self::canonical($item); }
        unset($item); return $value;
    }

    private static function storageBudget(array $user, int $bytes): void
    {
        if ($user['is_admin'] || Db::one('SELECT id FROM program_staff_grants WHERE user_id=? AND revoked_at IS NULL LIMIT 1', [$user['id']])) { return; }
        $units = max(1, (int) ceil($bytes / 1024));
        while ($units > 0) {
            $cost = min(100, $units); $units -= $cost;
            \App\Core\RateLimit::check('program-write-kib-user|' . $user['id'], \App\Core\SecurityBudget::limit('program_write_user_kib_day', 2048), 86400, $cost);
            \App\Core\RateLimit::check('program-write-kib-global', \App\Core\SecurityBudget::limit('program_write_kib_day', 20480), 86400, $cost);
        }
    }

    public static function grant(array $admin, array $data): array
    {
        if (!$admin['is_admin']) {
            throw new Problem(403, 'admin', 'Operațiune rezervată administratorului.');
        }
        OrganizationRegistry::recentAccount($admin['id'], $data);
        $role = self::text($data, 'role', 40);
        if (!in_array($role, self::ROLES, true)) {
            throw new Problem(422, 'program_role', 'Selectează un rol al programului.');
        }
        $target = Api::id(self::text($data, 'user_id', 32));
        if (!Db::one('SELECT id FROM users WHERE id=? AND active=1 FOR UPDATE', [$target])) {
            throw new Problem(422, 'program_user', 'Contul nu este disponibil.');
        }
        $active = Db::one('SELECT id FROM program_staff_grants WHERE user_id=? AND role=? AND revoked_at IS NULL LIMIT 1', [$target, $role]);
        if ($active) { return ['id' => $active['id'], 'role' => $role, 'user_id' => $target, 'state' => 'active']; }
        $id = Db::id();
        Db::insert('program_staff_grants', ['id' => $id, 'user_id' => $target, 'role' => $role, 'granted_by' => $admin['id'], 'created_at' => Db::now()]);
        self::event($admin, 'staff', $id, 'granted', ['role' => $role, 'user_id' => $target]);
        return ['id' => $id, 'role' => $role, 'user_id' => $target, 'state' => 'active'];
    }
}
