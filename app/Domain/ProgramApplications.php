<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Db, Problem};

final class ProgramApplications
{
    public static function clients(array $user): array
    {
        return ['items' => array_map(static fn(array $r): array => ['id' => $r['id'], 'name' => $r['name'], 'redirect_uris' => json_decode($r['redirect_uris_json'], true),
            'scopes' => json_decode($r['scopes_json'], true), 'active' => (bool) $r['active'], 'created_at' => $r['created_at']], Db::all('SELECT * FROM api_clients WHERE owner_user_id=? ORDER BY created_at DESC LIMIT 20', [$user['id']]))];
    }

    public static function create(array $user, array $data): array
    {
        $data['scopes'] ??= ['learning:read'];
        return OAuth::clientCreate(['user' => $user['id'], 'tenant' => null, 'token' => null], $data);
    }

    public static function deactivate(array $user, string $id): array
    {
        if (!Db::one('SELECT id FROM api_clients WHERE id=? AND owner_user_id=?', [Api::id($id), $user['id']])) {
            throw new Problem(404, 'oauth_client', 'Aplicația nu a fost găsită.');
        }
        Db::run('UPDATE api_clients SET active=0 WHERE id=?', [$id]);
        foreach (Db::all('SELECT id FROM api_oauth_grants WHERE client_id=? AND revoked_at IS NULL', [$id]) as $r) { OAuth::revokeGrant($r['id']); }
        ProgramAccess::event($user, 'oauth_client', $id, 'deactivated');
        return ['id' => $id, 'state' => 'revoked'];
    }

    public static function grants(array $user): array
    {
        return ['items' => array_map(static fn(array $r): array => ['id' => $r['id'], 'client_id' => $r['client_id'], 'client_name' => $r['name'],
            'scopes' => json_decode($r['scopes_json'], true), 'expires_at' => $r['expires_at'], 'state' => $r['revoked_at'] ? 'revoked' : ($r['expires_at'] > Db::now() ? 'active' : 'expired')],
            Db::all("SELECT g.*,c.name FROM api_oauth_grants g JOIN api_clients c ON c.id=g.client_id WHERE g.user_id=? AND g.realm='program' ORDER BY g.created_at DESC,g.id DESC LIMIT 200", [$user['id']]))];
    }

    public static function revoke(array $user, string $id): array
    {
        if (!Db::one("SELECT id FROM api_oauth_grants WHERE id=? AND user_id=? AND realm='program'", [Api::id($id), $user['id']])) {
            throw new Problem(404, 'oauth_grant', 'Autorizarea nu a fost găsită.');
        }
        OAuth::revokeGrant($id); ProgramAccess::event($user, 'oauth', $id, 'revoked');
        return ['id' => $id, 'state' => 'revoked'];
    }
}
