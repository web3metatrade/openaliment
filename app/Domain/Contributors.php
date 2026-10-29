<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Db, Problem};

final class Contributors
{
    public const CATEGORIES = ['code', 'documentation', 'research', 'design', 'testing', 'translation', 'support', 'community'];

    public static function projection(array $row, bool $private = false): array
    {
        if (!$private && (!$row['approved_json'] || !$row['consent_at'] || in_array($row['state'], ['withdrawn', 'suspended'], true))) {
            throw new Problem(404, 'contributor', 'Profilul nu este disponibil.');
        }
        $out = ['id' => $row['id'], 'slug' => $row['slug'], 'profile' => json_decode($private ? $row['draft_json'] : $row['approved_json'], true),
            'state' => $private ? $row['state'] : 'published'];
        if ($private) {
            $out += ['revision' => (int) $row['revision'], 'submitted_revision' => $row['submitted_revision'] === null ? null : (int) $row['submitted_revision'],
                'consented' => $row['consent_version'] !== null && (int) $row['consent_version'] === (int) $row['revision']];
        }
        return $out;
    }

    public static function catalog(): array
    {
        [$limit, $cursor] = Api::page();
        $q = mb_substr(is_string($_GET['q'] ?? null) ? $_GET['q'] : '', 0, 100);
        $after = $cursor === '' ? '' : (Db::one('SELECT public_name FROM contributor_profiles WHERE id=?', [$cursor])['public_name'] ?? '');
        $prefix = str_replace(['=', '%', '_'], ['==', '=%', '=_'], $q) . '%';
        $category = is_string($_GET['category'] ?? null) ? mb_substr($_GET['category'], 0, 40) : '';
        $rows = Db::all("SELECT * FROM contributor_profiles WHERE approved_json IS NOT NULL AND consent_at IS NOT NULL AND state NOT IN ('withdrawn','suspended') AND (public_name>? OR (public_name=? AND id>?)) AND public_name LIKE ? ESCAPE '=' AND (?='' OR JSON_CONTAINS(approved_json,?,'$.categories')) ORDER BY public_name,id LIMIT " . ($limit + 1), [$after, $after, $cursor, $prefix, $category, Db::json($category)]);
        return Api::collection($rows, $limit, self::projection(...));
    }

    public static function profile(string $id): array
    {
        return self::projection(Db::one('SELECT * FROM contributor_profiles WHERE id=?', [Api::id($id)])
            ?? throw new Problem(404, 'contributor', 'Profilul nu este disponibil.'));
    }

    public static function own(array $user): array
    {
        return ['items' => array_map(static fn(array $r): array => self::projection($r, true), Db::all('SELECT * FROM contributor_profiles WHERE user_id=? ORDER BY id LIMIT 25', [$user['id']]))];
    }

    public static function save(array $user, ?string $id, array $data, bool $curator = false): array
    {
        if ($curator) {
            ProgramAccess::staff($user, 'community_editor');
        }
        $profile = ['name' => ProgramAccess::text($data, 'name', 120), 'summary' => ProgramAccess::text($data, 'summary', 1000),
            'categories' => $data['categories'] ?? [], 'links' => $data['links'] ?? [], 'asset_id' => $data['asset_id'] ?? null];
        if (!is_array($profile['categories']) || !array_is_list($profile['categories']) || !$profile['categories'] || array_diff($profile['categories'], self::CATEGORIES)) {
            throw new Problem(422, 'contributor_categories', 'Selectează contribuțiile persoanei.');
        }
        if (!is_array($profile['links']) || !array_is_list($profile['links']) || count($profile['links']) > 5) {
            throw new Problem(422, 'contributor_links', 'Sunt permise cel mult cinci legături.');
        }
        $profile['links'] = array_map(Marketplace::webUrl(...), $profile['links']);
        if ($profile['asset_id'] !== null) {
            ProgramMedia::owned($user, $profile['asset_id'], 'avatar');
        }
        $row = $id ? Db::one('SELECT * FROM contributor_profiles WHERE id=? FOR UPDATE', [Api::id($id)]) : null;
        if ($id && (!$row || (!$curator && $row['user_id'] !== $user['id']))) {
            throw new Problem(404, 'contributor', 'Profilul nu a fost găsit.');
        }
        $revision = $row ? (int) $row['revision'] + 1 : 1;
        if ($row) {
            ProgramAccess::revision($row, $data);
        }
        $id ??= Db::id();
        $slug = $row['slug'] ?? ProgramAccess::text($data, 'slug', 100);
        if (!preg_match('/\A[a-z0-9][a-z0-9-]{2,99}\z/D', $slug)) {
            throw new Problem(422, 'slug', 'Folosește 3–100 de litere mici, cifre sau liniuțe.');
        }
        $consented = !$curator && ($data['consent'] ?? false) === true;
        if ($row) {
            Db::run('UPDATE contributor_profiles SET draft_json=?,revision=?,submitted_revision=NULL,consent_version=?,consent_at=?,updated_at=? WHERE id=?',
                [Db::json($profile), $revision, $consented ? $revision : $row['consent_version'], $consented ? Db::now() : $row['consent_at'], Db::now(), $id]);
        } else {
            if ((int) Db::one('SELECT COUNT(*) AS n FROM contributor_profiles WHERE user_id=?', [$user['id']])['n'] >= 10) {
                throw new Problem(422, 'profile_quota', 'Limita de profiluri a fost atinsă.');
            }
            Db::insert('contributor_profiles', ['id' => $id, 'user_id' => $curator ? null : $user['id'], 'slug' => $slug,
                'draft_json' => Db::json($profile), 'consent_version' => $consented ? $revision : null,
                'consent_at' => $consented ? Db::now() : null, 'created_at' => Db::now(), 'updated_at' => Db::now()]);
        }
        ProgramAccess::event($user, 'contributor', $id, 'saved', ['revision' => $revision, 'snapshot' => $profile, 'consented' => $consented]);
        return self::projection(Db::one('SELECT * FROM contributor_profiles WHERE id=?', [$id]), true);
    }

    public static function submit(array $user, string $id, array $data): array
    {
        $row = ProgramAccess::owned('contributor_profiles', $id, $user);
        ProgramAccess::revision($row, $data);
        if ($row['state'] === 'suspended') {
            throw new Problem(409, 'contributor_suspended', 'Profilul necesită verificarea administratorului.');
        }
        if (($data['consent'] ?? false) !== true) {
            throw new Problem(422, 'consent', 'Confirmă publicarea profilului tău.');
        }
        Db::run("UPDATE contributor_profiles SET submitted_revision=revision,consent_version=revision,consent_at=?,state='submitted' WHERE id=?", [Db::now(), $id]);
        ProgramAccess::event($user, 'contributor', $id, 'submitted');
        return self::projection(Db::one('SELECT * FROM contributor_profiles WHERE id=?', [$id]), true);
    }

    public static function review(array $user, string $id, array $data): array
    {
        ProgramAccess::staff($user, 'community_editor');
        $row = Db::one('SELECT * FROM contributor_profiles WHERE id=? FOR UPDATE', [Api::id($id)])
            ?? throw new Problem(404, 'contributor', 'Profilul nu a fost găsit.');
        ProgramAccess::revision($row, $data);
        $action = ProgramAccess::text($data, 'action', 30);
        $reason = ProgramAccess::text($data, 'reason', 2000);
        if ($action === 'approve') {
            if ($row['state'] !== 'submitted' || (int) $row['submitted_revision'] !== (int) $row['revision']) {
                throw new Problem(409, 'contributor_submission', 'Trimite mai întâi revizia curentă pentru verificare.');
            }
            if (!$row['consent_at'] || (int) $row['consent_version'] !== (int) $row['revision']) {
                throw new Problem(409, 'contributor_consent', 'Este necesar acordul persoanei pentru versiunea curentă.');
            }
            $snapshot = json_decode($row['draft_json'], true);
            if ($snapshot['asset_id']) {
                ProgramMedia::approve($snapshot['asset_id']);
            }
            Db::run("UPDATE contributor_profiles SET approved_json=draft_json,public_name=?,approved_by=?,state='published' WHERE id=?", [$snapshot['name'], $user['id'], $id]);
        } elseif (in_array($action, ['needs_changes', 'suspend'], true)) {
            Db::run('UPDATE contributor_profiles SET state=?' . ($action === 'suspend' ? ',approved_json=NULL' : '') . ' WHERE id=?', [$action === 'suspend' ? 'suspended' : 'needs_changes', $id]);
        } else {
            throw new Problem(422, 'decision', 'Decizie incorectă.');
        }
        ProgramAccess::event($user, 'contributor', $id, $action, ['reason' => $reason]);
        return self::projection(Db::one('SELECT * FROM contributor_profiles WHERE id=?', [$id]), true);
    }

    public static function withdraw(array $user, string $id): array
    {
        ProgramAccess::owned('contributor_profiles', $id, $user);
        Db::run("UPDATE contributor_profiles SET state='withdrawn',consent_at=NULL,approved_json=NULL WHERE id=?", [$id]);
        ProgramAccess::event($user, 'contributor', $id, 'withdrawn');
        return ['id' => $id, 'state' => 'withdrawn'];
    }

    public static function export(array $user, string $id): array
    {
        $r = ProgramAccess::owned('contributor_profiles', $id, $user);
        return ['schema_version' => 'openaliment.contributor-export.v1', 'profile' => self::projection($r, true),
            'history' => array_map(static fn(array $e): array => ['id' => $e['id'], 'action' => $e['action'], 'at' => $e['created_at'], 'actor_id' => $e['user_id'],
                'details' => json_decode($e['details_json'], true) ?: new \stdClass()], Db::all("SELECT * FROM program_events WHERE object_kind='contributor' AND object_id=? AND receipt_key IS NULL ORDER BY created_at,id LIMIT 500", [$id]))];
    }
}
