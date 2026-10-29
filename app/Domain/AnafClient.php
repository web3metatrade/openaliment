<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Config, Db, Problem};

/** Fixed public ANAF v9 endpoint. Never sends account credentials to ANAF. */
final class AnafClient
{
    public const ENDPOINT = 'https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva';
    public const MAPPING = 'openaliment.anaf.v1';
    public const MAX_BYTES = 1048576;

    public static function cui(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 32) {
            throw new Problem(422, 'cui', 'Introdu un CUI românesc, cu sau fără prefixul RO.');
        }
        $value = preg_replace('/[ \t]/', '', strtoupper(trim($value)));
        $value = preg_replace('/\ARO/', '', $value);
        if (!preg_match('/\A[1-9][0-9]{1,9}\z/D', $value)) {
            throw new Problem(422, 'cui', 'CUI-ul trebuie să conțină între 2 și 10 cifre.');
        }
        return $value;
    }

    public static function day(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Bucharest')))->format('Y-m-d');
    }

    public static function decode(string $raw, string $cui, string $day): array
    {
        if (strlen($raw) > self::MAX_BYTES) {
            throw new Problem(503, 'anaf_response', 'Răspunsul ANAF nu poate fi verificat momentan.');
        }
        try {
            $body = json_decode($raw, true, 24, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new Problem(503, 'anaf_response', 'ANAF nu a furnizat un răspuns valid.');
        }
        if (!is_array($body) || (isset($body['cod']) && $body['cod'] !== 200)
            || !is_array($body['found'] ?? null) || !array_is_list($body['found'])
            || !is_array($body['notFound'] ?? null) || !array_is_list($body['notFound'])
            || count($body['found']) + count($body['notFound']) !== 1) {
            throw new Problem(503, 'anaf_response', 'Răspunsul ANAF nu confirmă CUI-ul solicitat.');
        }
        if ($body['notFound']) {
            if ((!is_int($body['notFound'][0]) && !is_string($body['notFound'][0])) || (string) $body['notFound'][0] !== $cui) {
                throw new Problem(503, 'anaf_identity', 'ANAF a returnat un alt CUI.');
            }
            return ['state' => 'not_found', 'record' => null];
        }
        $record = $body['found'][0];
        $general = is_array($record) ? ($record['date_generale'] ?? null) : null;
        if (!is_array($general) || (!is_int($general['cui'] ?? null) && !is_string($general['cui'] ?? null)) || (string) ($general['cui'] ?? '') !== $cui
            || ($general['data'] ?? null) !== $day || !is_string($general['denumire'] ?? null)
            || trim($general['denumire']) === '' || strlen($general['denumire']) > 10000) {
            throw new Problem(503, 'anaf_identity', 'Datele ANAF nu corespund CUI-ului și datei solicitate.');
        }
        foreach (['adresa_sediu_social', 'adresa_domiciliu_fiscal'] as $group) {
            if (isset($record[$group]) && !is_array($record[$group])) {
                throw new Problem(503, 'anaf_response', 'Adresa ANAF nu poate fi verificată.');
            }
        }
        $periods = $record['inregistrare_scop_Tva']['perioade_TVA'] ?? null;
        if ($periods !== null && !is_array($periods)) {
            throw new Problem(503, 'anaf_response', 'Perioadele TVA nu pot fi verificate.');
        }
        if (is_array($periods) && array_is_list($periods)) {
            foreach ($periods as $period) {
                if (!is_array($period) || array_is_list($period) && $period !== []) {
                    throw new Problem(503, 'anaf_response', 'Perioadă TVA incorectă.');
                }
            }
        }
        foreach (['inregistrare_scop_Tva' => 'scpTVA', 'inregistrare_RTVAI' => 'statusTvaIncasare', 'stare_inactiv' => 'statusInactivi', 'inregistrare_SplitTVA' => 'statusSplitTVA', 'date_generale' => 'statusRO_e_Factura'] as $group => $key) {
            if (isset($record[$group]) && !is_array($record[$group])) {
                throw new Problem(503, 'anaf_response', 'Structura datelor ANAF nu poate fi verificată.');
            }
            $value = $record[$group][$key] ?? null;
            if ($value !== null && !is_bool($value)) {
                throw new Problem(503, 'anaf_response', 'Starea fiscală ANAF nu poate fi verificată.');
            }
        }
        $count = 0;
        self::bounded($record, 0, $count);
        $definition = json_decode(file_get_contents(ROOT . '/contracts/anaf-fields.json'), true, 24, JSON_THROW_ON_ERROR);
        foreach ($definition['fields'] as $field) {
            $parts = explode('.', $field['path']);
            $group = $record[$parts[0]] ?? [];
            $key = end($parts);
            $values = [$group[$key] ?? null];
            if (str_contains($field['path'], '[]')) {
                $list = $group['perioade_TVA'] ?? [];
                if (is_array($list) && !array_is_list($list)) $list = [$list];
                $values = array_map(static fn(array $period): mixed => $period[$key] ?? null, $list);
            }
            foreach ($values as $value) {
                if ($value === null || $key === 'cui') continue;
                if (($field['type'] === 'boolean' && !is_bool($value)) || ($field['type'] !== 'boolean' && !is_string($value))) {
                    throw new Problem(503, 'anaf_response', 'Tipul unui câmp ANAF necesită verificare.');
                }
            }
        }
        return ['state' => 'found', 'record' => $record];
    }

    private static function bounded(mixed $value, int $depth, int &$count): void
    {
        if (++$count > 2000 || $depth > 10 || (is_string($value) && strlen($value) > 20000)) {
            throw new Problem(503, 'anaf_response', 'Răspunsul ANAF depășește structura acceptată.');
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (strlen((string) $key) > 180) {
                    throw new Problem(503, 'anaf_response', 'Câmp ANAF incorect.');
                }
                self::bounded($item, $depth + 1, $count);
            }
        }
    }

    public static function fetch(string $cui, string $day): string
    {
        // Fixtures are available only with the isolated test environment and database.
        if (Config::get('environment') === 'test') {
            if (Db::one('SELECT DATABASE() AS name')['name'] !== 'openaliment_tests') {
                throw new \RuntimeException('ANAF fixture requires the disposable database.');
            }
            $fixtures = Config::get('anaf_test_responses', []);
            if (!isset($fixtures[$cui]) || !is_string($fixtures[$cui])) {
                throw new Problem(503, 'anaf_unavailable', 'ANAF nu răspunde momentan. Reîncearcă verificarea.');
            }
            $raw = str_replace('__QUERY_DAY__', $day, $fixtures[$cui]);
            $statuses = Config::get('anaf_test_http_statuses', []);
            return self::response((int) ($statuses[$cui] ?? 200), 'application/json', $raw, $cui, $day);
        }
        $options = OrganizationRegistry::settings();
        if (($options['network_enabled'] ?? true) !== true || !extension_loaded('curl')) {
            throw new Problem(503, 'anaf_unavailable', 'Verificarea ANAF nu este disponibilă momentan.');
        }
        $body = '';
        $handle = curl_init(self::ENDPOINT);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => Db::json([['cui' => (int) $cui, 'data' => $day]]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_USERAGENT => 'OpenAliment/ANAF-v9 (+https://openaliment.ro)',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > self::MAX_BYTES) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        try {
            $ok = curl_exec($handle);
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $type = strtolower(trim(explode(';', (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE))[0]));
        } finally {
            curl_close($handle);
        }
        if ($ok === false) {
            header('Retry-After: 30');
            throw new Problem(503, 'anaf_unavailable', 'ANAF nu răspunde momentan. Datele tale nu au fost înregistrate.');
        }
        return self::response($status, $type, $body, $cui, $day);
    }

    /** A negative ANAF lookup can use HTTP 404; a generic 404 is never a result. */
    public static function response(int $status, string $contentType, string $body, string $cui, string $day): string
    {
        $type = strtolower(trim(explode(';', $contentType)[0]));
        if (!in_array($status, [200, 404], true) || $type !== 'application/json') {
            if (!headers_sent()) {
                header('Retry-After: 30');
            }
            throw new Problem(503, 'anaf_unavailable', 'ANAF nu răspunde momentan. Datele tale nu au fost înregistrate.');
        }
        $parsed = self::decode($body, $cui, $day);
        if ($status === 404 && $parsed['state'] !== 'not_found') {
            throw new Problem(503, 'anaf_response', 'Răspunsul ANAF nu confirmă CUI-ul solicitat.');
        }
        return $body;
    }
}
