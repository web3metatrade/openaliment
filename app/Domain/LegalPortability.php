<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Db, Migrations, Problem};

/** Tax history travels as provenance, never as a transferred representation mandate. */
final class LegalPortability
{
    public static function export(array $p): array
    {
        if (!Migrations::anaf()) return [];
        $rows = Db::all('SELECT v.id,v.origin,v.confirmed_at,o.cui,o.queried_for,o.response_json,o.response_sha256,o.fetched_at FROM organization_legal_profile_versions v JOIN anaf_observations o ON o.id=v.observation_id WHERE v.tenant_id=? ORDER BY v.confirmed_at,v.id LIMIT 10001', [$p['tenant']]);
        if (count($rows) > 10000) throw new Problem(413, 'legal_history_size', 'Istoricul fiscal depășește limita pachetului. Folosește paginarea API pentru exportul versiunilor.');
        return $rows;
    }

    public static function validate(mixed $rows): void
    {
        if ($rows && !Migrations::anaf()) throw new Problem(422, 'anaf_setup', 'Instalează profilul fiscal înainte de importul istoricului ANAF.');
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 10000) throw new Problem(422, 'legal_history', 'Istoricul fiscal nu este o listă acceptată.');
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['response_json'] ?? null) || strlen($row['response_json']) > AnafClient::MAX_BYTES
                || !is_string($row['response_sha256'] ?? null) || !hash_equals($row['response_sha256'], hash('sha256', $row['response_json']))
                || !is_string($row['queried_for'] ?? null) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $row['queried_for'])
                || !checkdate((int) substr($row['queried_for'], 5, 2), (int) substr($row['queried_for'], 8, 2), (int) substr($row['queried_for'], 0, 4))
                || !is_string($row['fetched_at'] ?? null) || !preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/D', $row['fetched_at'])) {
                throw new Problem(422, 'legal_history', 'Proveniența fiscală din pachet este incorectă.');
            }
            $cui = AnafClient::cui($row['cui'] ?? null);
            try { $record = AnafClient::decode($row['response_json'], $cui, $row['queried_for']); }
            catch (Problem) { throw new Problem(422, 'legal_history', 'Observația ANAF din transfer nu poate fi verificată.'); }
            if ($record['state'] !== 'found') throw new Problem(422, 'legal_history', 'Istoricul fiscal necesită o firmă identificată.');
        }
    }

    public static function import(array $p, array $rows): void
    {
        self::validate($rows);
        if (!$rows) return;
        $profile = Db::one('SELECT * FROM organization_legal_profiles WHERE tenant_id=? FOR UPDATE', [$p['tenant']]);
        $current = $profile['current_version_id'] ?? null;
        $initialCui = $profile['cui'] ?? $rows[0]['cui'];
        if (!$profile) Db::insert('organization_legal_profiles', ['tenant_id' => $p['tenant'], 'cui' => $initialCui, 'created_at' => Db::now()]);
        foreach ($rows as $row) {
            // A transfer may merge products from another company; preserve that profile in import history only.
            if ($row['cui'] !== $initialCui) continue;
            $observation = Db::id();
            Db::insert('anaf_observations', ['id' => $observation, 'cui' => $row['cui'], 'queried_for' => $row['queried_for'], 'api_version' => 'v9', 'mapping_version' => AnafClient::MAPPING,
                'result_state' => 'found', 'response_json' => $row['response_json'], 'response_sha256' => $row['response_sha256'], 'fetched_at' => $row['fetched_at'], 'reusable_until' => '2000-01-01 00:00:00']);
            $record = AnafClient::decode($row['response_json'], $row['cui'], $row['queried_for'])['record'];
            $json = Db::json($record); $id = Db::id();
            Db::insert('organization_legal_profile_versions', ['id' => $id, 'tenant_id' => $p['tenant'], 'observation_id' => $observation, 'legal_name' => $record['date_generale']['denumire'],
                'record_json' => $json, 'record_sha256' => hash('sha256', $json), 'origin' => 'imported', 'confirmed_by' => $p['user'], 'confirmed_at' => Db::now()]);
            if ($current === null) Db::run('UPDATE organization_legal_profiles SET current_version_id=? WHERE tenant_id=?', [$id, $p['tenant']]);
        }
    }
}
