<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Auth, Config, Db, Migrations, Problem, RateLimit, Session};

/** Source observations and tenant profiles never imply representation or GS1 rights. */
final class OrganizationRegistry
{
    public static function settings(): array
    {
        $options = Config::get('anaf');
        if ($options === null && Config::get('environment') !== 'test' && is_file(ROOT . '/config/anaf.local.php')) {
            $options = require ROOT . '/config/anaf.local.php';
        }
        if ($options !== null && !is_array($options)) throw new \RuntimeException('Invalid private ANAF configuration.');
        $options ??= [];
        $settings = ['network_enabled' => ($options['network_enabled'] ?? true) === true];
        foreach (['cache_seconds' => [21600,60,21600], 'not_found_seconds' => [300,30,300], 'check_seconds' => [1200,60,1200], 'session_limit' => [6,1,20], 'ip_limit' => [20,1,100], 'hourly_network_limit' => [300,1,3000]] as $key => [$default,$minimum,$maximum]) {
            $settings[$key] = max($minimum, min($maximum, (int) ($options[$key] ?? $default)));
        }
        return $settings;
    }
    public static function required(): void
    {
        if (!Migrations::anaf()) {
            throw new Problem(503, 'anaf_setup', 'Verificarea organizațiilor este în curs de activare.');
        }
    }

    private static function owner(?array $p): string
    {
        if ($p !== null) {
            return hash('sha256', 'legal-profile|' . $p['tenant'] . '|' . $p['user']);
        }
        Session::start();
        $_SESSION['anaf_owner'] ??= bin2hex(random_bytes(32));
        return hash('sha256', 'registration|' . $_SESSION['anaf_owner']);
    }

    public static function lookup(array $data, ?array $p = null): array
    {
        self::required();
        if ($p === null && !RegistrationSettings::enabled()) {
            throw new Problem(403, 'registration', 'Înscrierile sunt disponibile prin administrator.');
        }
        if ($p !== null) {
            self::profileOwner($p);
        }
        $cui = AnafClient::cui($data['cui'] ?? null);
        $profile = $p === null ? null : Db::one('SELECT * FROM organization_legal_profiles WHERE tenant_id=?', [$p['tenant']]);
        if ($profile && $profile['cui'] !== $cui) {
            throw new Problem(409, 'legal_identity', 'Profilul există pentru alt CUI. Schimbarea organizației necesită verificarea administratorului.');
        }
        $owner = self::owner($p);
        $testing = Config::get('environment') === 'test';
        $settings = self::settings();
        RateLimit::check('anaf-session|' . $owner, $testing ? 10000 : $settings['session_limit'], 900);
        RateLimit::check('anaf-ip|' . Api::clientIp(), $testing ? 10000 : $settings['ip_limit'], 900);
        $id = Db::id();
        $purpose = $p === null ? 'registration' : 'profile';
        $lock = 'anaf-check-' . substr($owner, 0, 40);
        if (!Db::one('SELECT GET_LOCK(?,0) AS acquired', [$lock])['acquired']) {
            throw new Problem(409, 'anaf_busy', 'O verificare este deja în curs.');
        }
        try {
            Db::atomic(static function () use ($id, $owner, $purpose, $p, $cui, $profile, $settings): void {
                Db::run("UPDATE organization_registration_checks SET state='superseded' WHERE owner_hash=? AND purpose=? AND state IN ('pending','active')", [$owner, $purpose]);
                Db::insert('organization_registration_checks', [
                    'id' => $id, 'owner_hash' => $owner, 'purpose' => $purpose,
                    'tenant_id' => $p['tenant'] ?? null, 'user_id' => $p['user'] ?? null,
                    'cui' => $cui, 'expected_version_id' => $profile['current_version_id'] ?? null,
                    'created_at' => Db::now(), 'expires_at' => gmdate('Y-m-d H:i:s', time() + $settings['check_seconds']),
                ]);
            });
        } finally {
            Db::run('SELECT RELEASE_LOCK(?)', [$lock]);
        }
        // Release the session before waiting for ANAF; concurrent corrections can invalidate this check.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        try {
            $observation = self::observe($cui);
            $changed = Db::run("UPDATE organization_registration_checks SET observation_id=?,state='active' WHERE id=? AND state='pending'", [$observation['id'], $id])->rowCount();
            if ($changed !== 1) {
                throw new Problem(409, 'cui_changed', 'CUI-ul a fost modificat. Folosește rezultatul ultimei verificări.');
            }
        } catch (\Throwable $error) {
            Db::run("UPDATE organization_registration_checks SET state='failed' WHERE id=? AND state='pending'", [$id]);
            throw $error;
        }
        return self::check($id, $p);
    }

    private static function observe(string $cui): array
    {
        $day = AnafClient::day();
        $cached = Db::one('SELECT * FROM anaf_observations WHERE cui=? AND queried_for=? AND reusable_until>? ORDER BY fetched_at DESC,id DESC LIMIT 1', [$cui, $day, Db::now()]);
        if ($cached) {
            return $cached;
        }
        self::reserveNetwork();
        $raw = AnafClient::fetch($cui, $day);
        $parsed = AnafClient::decode($raw, $cui, $day);
        $row = [
            'id' => Db::id(), 'cui' => $cui, 'queried_for' => $day, 'api_version' => 'v9',
            'mapping_version' => AnafClient::MAPPING, 'result_state' => $parsed['state'],
            'response_json' => $raw, 'response_sha256' => hash('sha256', $raw), 'fetched_at' => Db::now(),
            'reusable_until' => gmdate('Y-m-d H:i:s', time() + self::settings()[$parsed['state'] === 'found' ? 'cache_seconds' : 'not_found_seconds']),
        ];
        Db::insert('anaf_observations', $row);
        return $row;
    }

    private static function reserveNetwork(): void
    {
        if (Config::get('environment') === 'test') {
            return;
        }
        RateLimit::check('anaf-upstream-hour', self::settings()['hourly_network_limit'], 3600);
        // Both cPanel installations have the same parent and outbound IP.
        $path = dirname(ROOT) . '/openaliment-anaf-rate.json';
        if (is_link($path)) {
            throw new Problem(503, 'anaf_rate', 'Verificarea ANAF nu este disponibilă momentan.');
        }
        $file = @fopen($path, 'c+');
        if (!$file || !flock($file, LOCK_EX | LOCK_NB)) {
            if ($file) fclose($file);
            header('Retry-After: 2');
            throw new Problem(429, 'anaf_rate', 'O altă verificare ANAF este în curs. Reîncearcă în două secunde.');
        }
        try {
            @chmod($path, 0600);
            $raw = stream_get_contents($file, 2048);
            $state = $raw === '' ? [] : json_decode($raw, true);
            if (!is_array($state)) {
                throw new Problem(503, 'anaf_rate', 'Verificarea ANAF nu este disponibilă momentan.');
            }
            if (($state['next_at'] ?? 0) > microtime(true)) {
                header('Retry-After: 2');
                throw new Problem(429, 'anaf_rate', 'Reîncearcă verificarea în două secunde.');
            }
            $bytes = Db::json(['next_at' => microtime(true) + 1.1]);
            rewind($file);
            if (!ftruncate($file, 0) || fwrite($file, $bytes) !== strlen($bytes)) {
                throw new \RuntimeException('ANAF rate state unavailable.');
            }
            fflush($file);
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    public static function check(string $id, ?array $p = null): array
    {
        self::required();
        Api::id($id);
        $row = Db::one('SELECT * FROM organization_registration_checks WHERE id=? AND owner_hash=? AND purpose=?', [$id, self::owner($p), $p === null ? 'registration' : 'profile']);
        if (!$row) throw new Problem(404, 'anaf_check', 'Verificarea nu a fost găsită în această sesiune.');
        if ($row['state'] !== 'active') throw new Problem(409, 'anaf_check_state', 'Folosește rezultatul ultimei verificări a CUI-ului.');
        if ($row['expires_at'] <= Db::now()) throw new Problem(409, 'anaf_check_expired', 'Reverifică CUI-ul înainte de confirmarea firmei.');
        $observation = Db::one('SELECT * FROM anaf_observations WHERE id=?', [$row['observation_id']]);
        $result = self::projectObservation($observation);
        if ($observation['queried_for'] !== AnafClient::day()) throw new Problem(409, 'anaf_check_expired', 'Reverifică situația firmei pentru ziua curentă.');
        $result += ['id' => $id, 'expires_at' => self::iso($row['expires_at']), 'expected_version_id' => $row['expected_version_id']];
        $before = null;
        if ($p !== null && $row['expected_version_id']) {
            $old = Db::one('SELECT record_json FROM organization_legal_profile_versions WHERE tenant_id=? AND id=?', [$p['tenant'], $row['expected_version_id']]);
            $before = $old ? json_decode($old['record_json'], true, 24, JSON_THROW_ON_ERROR) : null;
        }
        $result['differences'] = self::differences($before, $result['record']);
        return $result;
    }

    public static function projectObservation(array $row): array
    {
        if (!hash_equals($row['response_sha256'], hash('sha256', $row['response_json']))) {
            throw new Problem(503, 'anaf_integrity', 'Integritatea răspunsului ANAF nu poate fi confirmată.');
        }
        $parsed = AnafClient::decode($row['response_json'], $row['cui'], $row['queried_for']);
        $record = $parsed['record'];
        $warnings = [];
        if (($record['stare_inactiv']['statusInactivi'] ?? null) === true) $warnings[] = 'Firma figurează în registrul contribuabililor inactivi la data consultată.';
        if (!empty($record['stare_inactiv']['dataRadiere'])) $warnings[] = 'ANAF furnizează o dată de radiere. Este necesară verificarea situației organizației.';
        return [
            'cui' => $row['cui'], 'state' => $parsed['state'], 'record' => $record, 'warnings' => $warnings,
            'source' => ['provider' => 'anaf', 'api_version' => $row['api_version'], 'mapping_version' => $row['mapping_version'],
                'endpoint' => AnafClient::ENDPOINT, 'queried_for' => $row['queried_for'], 'fetched_at' => self::iso($row['fetched_at']), 'sha256' => $row['response_sha256']],
        ];
    }

    public static function registration(array $data): ?array
    {
        if (!isset($data['organization_lookup_id'])) return null;
        if (!is_string($data['organization_lookup_id'])) throw new Problem(422, 'anaf_check', 'Reverifică CUI-ul.');
        $result = self::check($data['organization_lookup_id']);
        if ($result['state'] !== 'found' || AnafClient::cui($data['cui'] ?? null) !== $result['cui']) {
            throw new Problem(422, 'cui_confirmation', 'CUI-ul confirmat nu corespunde firmei afișate.');
        }
        return $result;
    }

    /** Called inside the same transaction as account creation. */
    public static function attachRegistration(array $account, array $check): void
    {
        $row = Db::one("SELECT * FROM organization_registration_checks WHERE id=? AND owner_hash=? AND purpose='registration' FOR UPDATE", [$check['id'], self::owner(null)]);
        if (!$row || $row['state'] !== 'active' || $row['expires_at'] <= Db::now()) throw new Problem(409, 'anaf_check_state', 'Reverifică CUI-ul înainte de crearea contului.');
        self::save($account['tenant_id'], $account['id'], $row);
        Db::run("UPDATE organization_registration_checks SET state='consumed',consumed_at=? WHERE id=?", [Db::now(), $row['id']]);
    }

    private static function save(string $tenant, string $user, array $check): void
    {
        $observation = Db::one('SELECT * FROM anaf_observations WHERE id=?', [$check['observation_id']]);
        if ($observation['queried_for'] !== AnafClient::day()) throw new Problem(409, 'anaf_check_expired', 'Reverifică situația firmei pentru ziua curentă.');
        $result = self::projectObservation($observation);
        if ($result['state'] !== 'found') throw new Problem(422, 'anaf_not_found', 'Firma nu a fost găsită în ANAF.');
        $current = Db::one('SELECT * FROM organization_legal_profiles WHERE tenant_id=? FOR UPDATE', [$tenant]);
        if (($current['current_version_id'] ?? null) !== $check['expected_version_id']) throw new Problem(409, 'legal_version', 'Profilul a fost actualizat între timp. Reverifică datele.');
        if ($current && $current['cui'] !== $check['cui']) throw new Problem(409, 'legal_identity', 'CUI-ul profilului nu poate fi înlocuit prin reverificare.');
        if (!$current) Db::insert('organization_legal_profiles', ['tenant_id' => $tenant, 'cui' => $check['cui'], 'created_at' => Db::now()]);
        $json = Db::json($result['record']);
        $id = Db::id();
        Db::insert('organization_legal_profile_versions', ['id' => $id, 'tenant_id' => $tenant, 'observation_id' => $observation['id'],
            'legal_name' => $result['record']['date_generale']['denumire'], 'record_json' => $json, 'record_sha256' => hash('sha256', $json),
            'confirmed_by' => $user, 'confirmed_at' => Db::now()]);
        Db::run('UPDATE organization_legal_profiles SET current_version_id=? WHERE tenant_id=?', [$id, $tenant]);
        Catalog::audit(['tenant' => $tenant, 'user' => $user], 'organization.legal_profile_confirmed', $id, ['source' => 'anaf', 'cui' => $check['cui'], 'observation_sha256' => $observation['response_sha256']]);
    }

    public static function profile(array $p): array
    {
        self::required();
        $row = Db::one('SELECT v.* FROM organization_legal_profiles p JOIN organization_legal_profile_versions v ON v.tenant_id=p.tenant_id AND v.id=p.current_version_id WHERE p.tenant_id=?', [$p['tenant']]);
        return ['profile' => $row ? self::version($row) : null, 'representation_verified_by_anaf' => false, 'gs1_rights_granted_by_anaf' => false];
    }

    private static function version(array $row): array
    {
        if (!hash_equals($row['record_sha256'], hash('sha256', $row['record_json']))) throw new Problem(503, 'anaf_integrity', 'Integritatea profilului nu poate fi confirmată.');
        $observation = Db::one('SELECT * FROM anaf_observations WHERE id=?', [$row['observation_id']]);
        return self::projectObservation($observation) + ['id' => $row['id'], 'legal_name' => $row['legal_name'], 'origin' => $row['origin'], 'confirmed_at' => self::iso($row['confirmed_at']), 'directly_verified' => $row['origin'] === 'anaf'];
    }

    public static function history(array $p, ?int $viewLimit = null): array
    {
        self::required();
        [$limit, $cursor] = Api::page();
        if ($viewLimit !== null) $limit = max(1, min(5, $viewLimit));
        return Api::collection(Db::all('SELECT * FROM organization_legal_profile_versions WHERE tenant_id=? AND id>? ORDER BY id LIMIT ' . ($limit + 1), [$p['tenant'], $cursor]), $limit, static fn(array $row): array => self::version($row));
    }

    public static function confirm(array $p, array $data): array
    {
        self::profileOwner($p);
        if (!$p['token']) self::recentAccount($p['user'], $data);
        $id = Api::id(Catalog::text($data, 'organization_lookup_id', 32));
        $result = self::check($id, $p);
        if ($result['state'] !== 'found') throw new Problem(422, 'anaf_not_found', 'Firma nu a fost găsită în ANAF.');
        if (!array_key_exists('expected_version_id', $data) || $data['expected_version_id'] !== $result['expected_version_id']) throw new Problem(409, 'legal_version', 'Confirmă versiunea curentă a profilului.');
        Db::atomic(static function () use ($p, $id): void {
            Db::one('SELECT id FROM organizations WHERE id=? FOR UPDATE', [$p['tenant']]);
            $row = Db::one('SELECT * FROM organization_registration_checks WHERE id=? AND owner_hash=? FOR UPDATE', [$id, self::owner($p)]);
            if (!$row || $row['state'] !== 'active' || $row['expires_at'] <= Db::now()) throw new Problem(409, 'anaf_check_state', 'Reverifică datele firmei.');
            self::save($p['tenant'], $p['user'], $row);
            Db::run("UPDATE organization_registration_checks SET state='consumed',consumed_at=? WHERE id=?", [Db::now(), $id]);
        });
        return self::profile($p);
    }

    private static function profileOwner(array $p): void
    {
        Auth::write($p);
        if ($p['role'] !== 'owner') throw new Problem(403, 'role', 'Profilul fiscal se confirmă de proprietarul organizației.');
    }

    public static function recentAccount(string $userId, array $data): void
    {
        Session::start();
        if (Api::bearer() !== null || ($_SESSION['user_id'] ?? null) !== $userId) {
            throw new Problem(403, 'browser_session', 'Confirmă accesul în sesiunea contului propriu.');
        }
        $user = Db::one('SELECT id,is_admin,password_hash FROM users WHERE id=? AND active=1', [$userId]);
        if (!$user) throw new Problem(401, 'authentication', 'Contul nu este disponibil.');
        if (Passkeys::required($user)) Passkeys::recent($user);
        if (($_SESSION['authenticated_at'] ?? 0) >= time() - 900) return;
        Auth::rate('legal-reauth|' . $userId, 5, 900);
        if (!\App\Core\Passwords::verify($data['current_password'] ?? null, $user['password_hash'])) {
            throw new Problem(403, 'reauthentication', 'Confirmă parola contului pentru această operație.');
        }
        $_SESSION['authenticated_at'] = time();
    }

    private static function differences(?array $before, ?array $after): array
    {
        if ($before === null || $after === null) return [];
        $flatten = static function (array $value, string $prefix = '') use (&$flatten): array {
            $out = [];
            foreach ($value as $key => $item) {
                $path = $prefix . '/' . str_replace(['~', '/'], ['~0', '~1'], (string) $key);
                if (is_array($item)) $out += $flatten($item, $path);
                else $out[$path] = $item;
            }
            return $out;
        };
        $a = $flatten($before); $b = $flatten($after); $out = [];
        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $key) {
            if (($a[$key] ?? null) !== ($b[$key] ?? null) || array_key_exists($key, $a) !== array_key_exists($key, $b)) $out[] = ['path' => $key, 'before' => $a[$key] ?? null, 'after' => $b[$key] ?? null];
        }
        return $out;
    }

    public static function cleanup(int $limit): array
    {
        if (!Migrations::anaf()) return [];
        $old = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
        $checks = Db::run('DELETE FROM organization_registration_checks WHERE expires_at<? LIMIT ' . $limit, [$old])->rowCount();
        $observations = Db::run('DELETE FROM anaf_observations WHERE fetched_at<? AND NOT EXISTS (SELECT 1 FROM organization_registration_checks c WHERE c.observation_id=anaf_observations.id) AND NOT EXISTS (SELECT 1 FROM organization_legal_profile_versions v WHERE v.observation_id=anaf_observations.id) LIMIT ' . $limit, [gmdate('Y-m-d H:i:s', time() - 30 * 86400)])->rowCount();
        return ['anaf_checks' => $checks, 'anaf_observations' => $observations];
    }

    private static function iso(string $date): string
    {
        return gmdate(DATE_ATOM, strtotime($date . ' UTC'));
    }
}
