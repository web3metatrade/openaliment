<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Auth, Config, Db, Migrations, Problem};

/** Software listings are separate from food documentation, GS1 rights and API grants. */
final class Marketplace
{
    public static function installed(): void
    {
        if (!Migrations::marketplace()) {
            throw new Problem(503, 'marketplace_update', 'Marketplace-ul nu este disponibil temporar.');
        }
    }

    public static function webUrl(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $value)) {
            throw new Problem(422, 'marketplace_url', 'Folosește o adresă HTTPS publică.');
        }
        $parts = parse_url($value);
        $host = strtolower($parts['host'] ?? '');
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443)
            || !preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.[a-z]{2,63}\z/D', $host)
            || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')
            || filter_var($host, FILTER_VALIDATE_IP)) {
            throw new Problem(422, 'marketplace_url', 'Folosește o adresă HTTPS publică, fără date de autentificare.');
        }
        return $value;
    }

    private static function codes(mixed $values, int $max = 20): array
    {
        if (!is_array($values) || !array_is_list($values) || count($values) > $max) {
            throw new Problem(422, 'marketplace_codes', 'Lista de compatibilități este incorectă.');
        }
        foreach ($values as $value) {
            if (!is_string($value) || !preg_match('/\A[a-z0-9][a-z0-9._:-]{0,63}\z/D', $value)) {
                throw new Problem(422, 'marketplace_codes', 'Folosește coduri cu litere mici, cifre, punct, liniuță sau două puncte.');
            }
        }
        return array_values(array_unique($values));
    }

    public static function listing(array $data): array
    {
        $result = [];
        foreach (['name' => 120, 'summary' => 240, 'description' => 10000, 'publisher' => 180, 'license' => 120] as $key => $max) {
            $result[$key] = Catalog::text($data, $key, $max);
        }
        $result['category'] = self::codes([$data['category'] ?? ''])[0];
        foreach (['tags', 'capabilities', 'platforms', 'compatibility'] as $key) {
            $result[$key] = self::codes($data[$key] ?? []);
        }
        foreach (['website', 'support_url', 'license_url'] as $key) {
            $result[$key] = self::webUrl($data[$key] ?? null);
        }
        $result['pricing'] = $data['pricing'] ?? 'free';
        if (!in_array($result['pricing'], ['free', 'paid', 'quote'], true)) {
            throw new Problem(422, 'marketplace_pricing', 'Tipul ofertei este incorect.');
        }
        $result['oauth_client_id'] = null;
        if (!empty($data['oauth_client_id'])) {
            $result['oauth_client_id'] = Api::id($data['oauth_client_id']);
        }
        return $result;
    }

    public static function owned(string $user, string $id, bool $lock = false): array
    {
        self::installed();
        return Db::one('SELECT * FROM marketplace_apps WHERE id=? AND owner_user_id=?' . ($lock ? ' FOR UPDATE' : ''), [$id, $user])
            ?? throw new Problem(404, 'marketplace_app', 'Integrarea nu a fost găsită.');
    }

    private static function event(string $app, string $user, string $action, array $details): void
    {
        Db::insert('marketplace_events', ['id' => Db::id(), 'app_id' => $app, 'actor_user_id' => $user,
            'action' => $action, 'details_json' => Db::json($details), 'created_at' => Db::now()]);
    }

    public static function save(array $user, ?string $id, array $data): array
    {
        self::installed();
        $listing = self::listing($data);
        if ($listing['oauth_client_id'] !== null && !Db::one('SELECT id FROM api_clients WHERE id=? AND owner_user_id=? AND active=1', [$listing['oauth_client_id'], $user['id']])) {
            throw new Problem(422, 'marketplace_client', 'Selectează o aplicație OAuth proprie și activă.');
        }
        return Db::atomic(function () use ($user, $id, $data, $listing): array {
            Db::one('SELECT id FROM users WHERE id=? FOR UPDATE', [$user['id']]);
            if ($id === null) {
                if ((int) Db::one('SELECT COUNT(*) AS n FROM marketplace_apps WHERE owner_user_id=?', [$user['id']])['n'] >= 50) {
                    throw new Problem(413, 'marketplace_quota', 'Limita de integrări publicate de acest cont a fost atinsă.');
                }
                $slug = Catalog::text($data, 'slug', 100);
                if (!preg_match('/\A[a-z0-9][a-z0-9-]{2,99}\z/D', $slug)) {
                    throw new Problem(422, 'marketplace_slug', 'Adresa trebuie să conțină 3–100 de litere mici, cifre și liniuțe.');
                }
                if (Db::one('SELECT id FROM marketplace_apps WHERE slug=?', [$slug])) {
                    throw new Problem(409, 'marketplace_slug', 'Adresa este deja utilizată.');
                }
                $id = Db::id();
                Db::insert('marketplace_apps', ['id' => $id, 'owner_user_id' => $user['id'], 'slug' => $slug,
                    'draft_json' => Db::json($listing), 'created_at' => Db::now(), 'updated_at' => Db::now()]);
                $revision = 1;
            } else {
                $app = self::owned($user['id'], $id, true);
                if (($data['expected_revision'] ?? null) !== (int) $app['revision']) {
                    throw new Problem(409, 'marketplace_revision', 'Oferta a fost modificată. Reîncarcă versiunea curentă.');
                }
                $revision = (int) $app['revision'] + 1;
                Db::run('UPDATE marketplace_apps SET draft_json=?,revision=?,submitted_revision=NULL,updated_at=? WHERE id=?', [Db::json($listing), $revision, Db::now(), $id]);
            }
            self::event($id, $user['id'], 'listing.saved', ['revision' => $revision, 'snapshot' => $listing]);
            return ['id' => $id, 'revision' => $revision];
        });
    }

    public static function submit(array $user, string $id): array
    {
        return Db::atomic(function () use ($user, $id): array {
            $app = self::owned($user['id'], $id, true);
            if (!$user['email_verified']) {
                throw new Problem(403, 'marketplace_identity', 'Publicarea necesită un contact verificat.');
            }
            Db::run('UPDATE marketplace_apps SET submitted_revision=revision,updated_at=? WHERE id=?', [Db::now(), $id]);
            self::event($id, $user['id'], 'listing.submitted', ['revision' => (int) $app['revision']]);
            return ['id' => $id, 'submitted_revision' => (int) $app['revision']];
        });
    }

    public static function release(array $user, string $id, array $data): array
    {
        return Db::atomic(function () use ($user, $id, $data): array {
            $app = self::owned($user['id'], $id, true);
            if ((int) Db::one('SELECT COUNT(*) AS n FROM marketplace_releases WHERE app_id=?', [$id])['n'] >= 200) {
                throw new Problem(413, 'marketplace_quota', 'Limita de versiuni a fost atinsă.');
            }
            $version = Catalog::text($data, 'version', 40);
            if (!preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+(?:-[a-zA-Z0-9.-]+)?\z/D', $version)) {
                throw new Problem(422, 'marketplace_version', 'Folosește o versiune de forma 1.2.3.');
            }
            if (Db::one('SELECT id FROM marketplace_releases WHERE app_id=? AND version=?', [$id, $version])) {
                throw new Problem(409, 'marketplace_version', 'Versiunea există deja și nu poate fi înlocuită.');
            }
            $sha = $data['sha256'] ?? '';
            $bytes = $data['size_bytes'] ?? null;
            if (!is_string($sha) || !preg_match('/\A[a-f0-9]{64}\z/D', $sha) || !is_int($bytes) || $bytes < 1 || $bytes > 2147483647) {
                throw new Problem(422, 'marketplace_artifact', 'Completează SHA-256 și dimensiunea exactă a pachetului.');
            }
            $terms = json_decode($app['approved_json'] ?? $app['draft_json'], true, 32, JSON_THROW_ON_ERROR);
            $manifest = ['schema_version' => 'openaliment.marketplace-release.v1', 'version' => $version,
                'license' => $terms['license'], 'license_url' => $terms['license_url'], 'pricing' => $terms['pricing'],
                'publisher' => $terms['publisher'], 'listing_revision' => (int) ($app['approved_revision'] ?? $app['revision']),
                'artifact_url' => self::webUrl($data['artifact_url'] ?? null), 'sha256' => $sha, 'size_bytes' => $bytes,
                'platforms' => self::codes($data['platforms'] ?? []), 'changelog' => Catalog::text($data, 'changelog', 10000),
                'signature_url' => empty($data['signature_url']) ? null : self::webUrl($data['signature_url'])];
            $release = Db::id();
            $json = Db::json($manifest);
            Db::insert('marketplace_releases', ['id' => $release, 'app_id' => $id, 'version' => $version,
                'manifest_json' => $json, 'manifest_sha256' => hash('sha256', $json), 'created_at' => Db::now()]);
            self::event($id, $user['id'], 'release.submitted', ['release_id' => $release, 'manifest' => $manifest]);
            return ['id' => $release, 'state' => 'submitted', 'manifest_sha256' => hash('sha256', $json)];
        });
    }

    public static function review(array $admin, string $id, array $data): array
    {
        if (empty($admin['is_admin'])) {
            throw new Problem(403, 'role', 'Acces restricționat.');
        }
        return Db::atomic(function () use ($admin, $id, $data): array {
            $app = Db::one('SELECT * FROM marketplace_apps WHERE id=? FOR UPDATE', [$id])
                ?? throw new Problem(404, 'marketplace_app', 'Integrarea nu a fost găsită.');
            $action = $data['decision'] ?? '';
            $reason = Catalog::text($data, 'reason', 1500);
            if ($action === 'approve_listing') {
                if (!Db::one('SELECT id FROM users WHERE id=? AND active=1 AND email_verified=1', [$app['owner_user_id']])) {
                    throw new Problem(422, 'marketplace_identity', 'Dezvoltatorul trebuie să aibă un cont activ și contact verificat.');
                }
                if ($app['submitted_revision'] === null || (int) $app['submitted_revision'] !== (int) $app['revision']
                    || ($data['expected_revision'] ?? null) !== (int) $app['revision']) {
                    throw new Problem(409, 'marketplace_revision', 'Versiunea trimisă pentru verificare s-a modificat.');
                }
                Db::run("UPDATE marketplace_apps SET state='published',approved_json=draft_json,approved_revision=revision,submitted_revision=NULL,updated_at=? WHERE id=?", [Db::now(), $id]);
            } elseif (in_array($action, ['approve_release', 'withdraw_release'], true)) {
                $release = Api::id($data['release_id'] ?? '');
                if (!Db::one('SELECT id FROM marketplace_releases WHERE id=? AND app_id=?', [$release, $id])) {
                    throw new Problem(404, 'marketplace_release', 'Versiunea nu a fost găsită.');
                }
                if ($action === 'approve_release' && ($data['artifact_verified'] ?? false) !== true) {
                    throw new Problem(422, 'marketplace_review', 'Confirmă verificarea pachetului și consemnează dovezile în motiv.');
                }
                Db::run('UPDATE marketplace_releases SET state=?,review_json=?,reviewed_by=?,reviewed_at=? WHERE id=?',
                    [$action === 'approve_release' ? 'approved' : 'withdrawn', Db::json(['reason' => $reason, 'artifact_verified' => $data['artifact_verified'] ?? false]), $admin['id'], Db::now(), $release]);
            } elseif ($action === 'suspend') {
                Db::run("UPDATE marketplace_apps SET state='suspended',updated_at=? WHERE id=?", [Db::now(), $id]);
            } else {
                throw new Problem(422, 'marketplace_decision', 'Decizie incorectă.');
            }
            self::event($id, $admin['id'], $action, $data);
            return ['id' => $id, 'decision' => $action];
        });
    }

    public static function catalog(?string $slug = null): array
    {
        self::installed();
        [$limit, $cursor] = Api::page();
        $query = $_GET['q'] ?? '';
        $category = $_GET['category'] ?? '';
        if (!is_string($query) || mb_strlen($query) > 120 || !is_string($category) || strlen($category) > 64) {
            throw new Problem(422, 'marketplace_filter', 'Filtrul de căutare este incorect.');
        }
        $where = "state='published' AND id>? AND EXISTS (SELECT 1 FROM users u WHERE u.id=marketplace_apps.owner_user_id AND u.active=1 AND u.email_verified=1)";
        $parameters = [$cursor];
        if ($query !== '') {
            $where .= " AND JSON_UNQUOTE(JSON_EXTRACT(approved_json,'$.name')) LIKE ? ESCAPE '!'";
            $parameters[] = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
        }
        if ($category !== '') {
            $where .= " AND JSON_UNQUOTE(JSON_EXTRACT(approved_json,'$.category'))=?";
            $parameters[] = $category;
        }
        $rows = $slug !== null
            ? Db::all("SELECT id,slug,approved_revision,approved_json FROM marketplace_apps WHERE state='published' AND slug=? AND EXISTS (SELECT 1 FROM users u WHERE u.id=marketplace_apps.owner_user_id AND u.active=1 AND u.email_verified=1)", [$slug])
            : Db::all('SELECT id,slug,approved_revision,approved_json FROM marketplace_apps WHERE ' . $where . ' ORDER BY id LIMIT ' . ($limit + 1), $parameters);
        $hasMore = $slug === null && count($rows) > $limit;
        if ($slug === null) {
            $rows = array_slice($rows, 0, $limit);
        }
        $items = [];
        foreach ($rows as $row) {
            $entry = json_decode($row['approved_json'], true, 32, JSON_THROW_ON_ERROR);
            $entry += ['id' => $row['id'], 'slug' => $row['slug'], 'revision' => (int) $row['approved_revision']];
            $entry['releases'] = [];
            foreach (Db::all("SELECT id,manifest_json,manifest_sha256,reviewed_at FROM marketplace_releases WHERE app_id=? AND state='approved' ORDER BY created_at DESC,id DESC LIMIT 50", [$row['id']]) as $release) {
                $entry['releases'][] = json_decode($release['manifest_json'], true, 32, JSON_THROW_ON_ERROR)
                    + ['id' => $release['id'], 'manifest_sha256' => $release['manifest_sha256'], 'reviewed_at' => $release['reviewed_at']];
            }
            foreach ($entry['releases'] as &$r) {
                if ($r['pricing'] !== 'free') {
                    $r['artifact_url'] = null;
                }
            }
            unset($r);
            $items[] = $entry;
        }
        if ($slug !== null && !$items) {
            throw new Problem(404, 'marketplace_app', 'Integrarea nu a fost găsită.');
        }
        return ['items' => $items, 'next_cursor' => $hasMore && $items ? end($items)['id'] : null, 'has_more' => $hasMore, 'limit' => $limit,
            'commerce_enabled' => false, 'distribution' => 'publisher_https',
            'authorization' => 'explicit_oauth_or_api_key', 'license_scope' => 'per_listing'];
    }

    public static function dashboard(array $user, bool $admin = false): array
    {
        self::installed();
        if ($admin && empty($user['is_admin'])) {
            throw new Problem(403, 'role', 'Acces restricționat.');
        }
        $rows = $admin ? Db::all('SELECT * FROM marketplace_apps ORDER BY updated_at DESC,id LIMIT 50')
            : Db::all('SELECT * FROM marketplace_apps WHERE owner_user_id=? ORDER BY updated_at DESC,id LIMIT 50', [$user['id']]);
        foreach ($rows as &$row) {
            $row['draft'] = json_decode($row['draft_json'], true, 32, JSON_THROW_ON_ERROR);
            unset($row['draft_json'], $row['approved_json']);
            $row['revision'] = (int) $row['revision'];
            $row['releases'] = Db::all('SELECT id,version,manifest_json,state,manifest_sha256,review_json FROM marketplace_releases WHERE app_id=? ORDER BY created_at DESC,id DESC LIMIT 20', [$row['id']]);
        }
        return ['items' => $rows];
    }

    public static function releases(array $user, string $id): array
    {
        self::owned($user['id'], $id);
        [$limit, $cursor] = Api::page();
        return Api::collection(Db::all('SELECT id,version,manifest_json,state,manifest_sha256,review_json FROM marketplace_releases WHERE app_id=? AND id>? ORDER BY id LIMIT ' . ($limit + 1), [$id, $cursor]), $limit);
    }

    public static function withdraw(array $user, string $id, array $data): array
    {
        return Db::atomic(function () use ($user, $id, $data): array {
            self::owned($user['id'], $id, true);
            $reason = Catalog::text($data, 'reason', 1500);
            $release = empty($data['release_id']) ? null : Api::id($data['release_id']);
            if ($release !== null) {
                if (!Db::one('SELECT id FROM marketplace_releases WHERE id=? AND app_id=?', [$release, $id])) {
                    throw new Problem(404, 'marketplace_release', 'Versiunea nu a fost găsită.');
                }
                Db::run("UPDATE marketplace_releases SET state='withdrawn',review_json=? WHERE id=?", [Db::json(['reason' => $reason, 'withdrawn_by_owner' => true]), $release]);
            } else {
                Db::run("UPDATE marketplace_apps SET state='withdrawn',updated_at=? WHERE id=?", [Db::now(), $id]);
            }
            self::event($id, $user['id'], 'owner.withdrawn', ['release_id' => $release, 'reason' => $reason]);
            return ['id' => $id, 'release_id' => $release, 'state' => 'withdrawn'];
        });
    }

    public static function select(array $p, string $id, bool $active): array
    {
        self::installed();
        Auth::write($p);
        if ($active && !Db::one("SELECT a.id FROM marketplace_apps a JOIN users u ON u.id=a.owner_user_id WHERE a.id=? AND a.state='published' AND u.active=1 AND u.email_verified=1", [$id])) {
            throw new Problem(404, 'marketplace_app', 'Integrarea nu a fost găsită.');
        }
        if (!$active && !Db::one('SELECT id FROM marketplace_selections WHERE tenant_id=? AND app_id=?', [$p['tenant'], $id])) {
            return ['id' => $id, 'active' => false, 'api_access_granted' => false];
        }
        Db::run('INSERT INTO marketplace_selections (id,tenant_id,app_id,user_id,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE active=VALUES(active),user_id=VALUES(user_id),updated_at=VALUES(updated_at)',
            [Db::id(), $p['tenant'], $id, $p['user'], $active ? 1 : 0, Db::now(), Db::now()]);
        Catalog::audit($p, 'marketplace.selection', $id, ['active' => $active]);
        return ['id' => $id, 'active' => $active, 'api_access_granted' => false];
    }

    public static function selections(array $p): array
    {
        self::installed();
        [$limit, $cursor] = Api::page();
        return Api::collection(Db::all('SELECT s.id,s.app_id,s.active,s.created_at,s.updated_at,a.state,a.approved_json FROM marketplace_selections s JOIN marketplace_apps a ON a.id=s.app_id WHERE s.tenant_id=? AND s.id>? ORDER BY s.id LIMIT ' . ($limit + 1), [$p['tenant'], $cursor]), $limit,
            static function (array $row): array {
                $row['name'] = json_decode($row['approved_json'], true)['name'] ?? '';
                unset($row['approved_json']);
                $row['active'] = (bool) $row['active'];
                return $row;
            });
    }
}
