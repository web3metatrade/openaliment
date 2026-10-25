<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Auth, Config, Db, Problem};

/** Outbound agents receive approved label snapshots; the web server never opens a printer socket. */
final class Printing
{
    public const MAX_QUANTITY = 500;

    public static function devices(array $p): array
    {
        Marketplace::installed();
        return ['items' => Db::all('SELECT id,name,format,created_at,last_seen_at,revoked_at FROM print_devices WHERE tenant_id=? ORDER BY created_at,id LIMIT 200', [$p['tenant']])];
    }

    public static function device(array $p, ?string $id, array $data): array
    {
        Marketplace::installed();
        ApiKeys::recent($p, $data);
        return Db::atomic(function () use ($p, $id, $data): array {
            Db::one('SELECT id FROM organizations WHERE id=? FOR UPDATE', [$p['tenant']]);
            $secret = 'oa_print_' . bin2hex(random_bytes(32));
            if ($id === null) {
                $name = Catalog::text($data, 'name', 120);
                $format = $data['format'] ?? 'json';
                if (!in_array($format, ['json', 'zpl'], true)) {
                    throw new Problem(422, 'print_format', 'Formatul trebuie să fie JSON sau ZPL.');
                }
                if ((int) Db::one('SELECT COUNT(*) AS n FROM print_devices WHERE tenant_id=?', [$p['tenant']])['n'] >= 200) {
                    throw new Problem(413, 'print_quota', 'Limita de echipamente a fost atinsă.');
                }
                $id = Db::id();
                Db::insert('print_devices', ['id' => $id, 'tenant_id' => $p['tenant'], 'name' => $name,
                    'format' => $format, 'credential_hash' => hash('sha256', $secret), 'created_at' => Db::now()]);
            } else {
                self::ownedDevice($p, $id, true);
                Db::run('UPDATE print_devices SET credential_hash=?,revoked_at=NULL WHERE id=?', [hash('sha256', $secret), $id]);
            }
            Catalog::audit($p, 'print.device_credential', $id);
            return ['id' => $id, 'token' => $secret, 'secret_available' => true];
        });
    }

    private static function ownedDevice(array $p, string $id, bool $lock = false): array
    {
        return Db::one('SELECT * FROM print_devices WHERE id=? AND tenant_id=?' . ($lock ? ' FOR UPDATE' : ''), [$id, $p['tenant']])
            ?? throw new Problem(404, 'print_device', 'Echipamentul nu a fost găsit.');
    }

    public static function revoke(array $p, string $id): array
    {
        ApiKeys::owner($p);
        return Db::atomic(function () use ($p, $id): array {
            self::ownedDevice($p, $id, true);
            Db::run('UPDATE print_devices SET revoked_at=COALESCE(revoked_at,?) WHERE id=?', [Db::now(), $id]);
            Catalog::audit($p, 'print.device_revoked', $id);
            return ['id' => $id, 'revoked' => true];
        });
    }

    public static function agent(string $secret): array
    {
        Marketplace::installed();
        if (!preg_match('/\Aoa_print_[a-f0-9]{64}\z/D', $secret)) {
            throw new Problem(401, 'print_credential', 'Accesul echipamentului nu este valid.');
        }
        $device = Db::one('SELECT id,tenant_id,format FROM print_devices WHERE credential_hash=? AND revoked_at IS NULL', [hash('sha256', $secret)])
            ?? throw new Problem(401, 'print_credential', 'Accesul echipamentului nu este valid.');
        Auth::rate('print-agent|' . $device['id'], 120);
        Db::run('UPDATE print_devices SET last_seen_at=? WHERE id=? AND (last_seen_at IS NULL OR last_seen_at<?)', [Db::now(), $device['id'], gmdate('Y-m-d H:i:s', time() - 60)]);
        return $device;
    }

    private static function printable(array $p, string $batchId): array
    {
        $batch = Catalog::owned('batches', $batchId, $p);
        $public = Catalog::publicBatch($batchId);
        $item = Catalog::owned('trade_items', $batch['trade_item_id'], $p);
        if ($item['gtin_status'] !== 'verified' || !$item['gtin14'] || $public['alerts']
            || !in_array($batch['state'], ['planned', 'released'], true)) {
            throw new Problem(422, 'print_approval', 'Tipărirea cere un GTIN verificat și un lot publicat, fără alertă activă.');
        }
        if (!DigitalLink::canGenerate($batch['lot_code'])) {
            throw new Problem(422, 'print_transport', 'Lotul necesită verificarea transportului GS1 pe domeniul de tipărire.');
        }
        return [$batch, $item, $public];
    }

    public static function create(array $p, array $data): array
    {
        Marketplace::installed();
        Auth::write($p);
        $deviceId = Api::id($data['device_id'] ?? '');
        $batchId = Api::id($data['batch_id'] ?? '');
        $quantity = $data['quantity'] ?? null;
        if (!is_int($quantity) || $quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new Problem(422, 'print_quantity', 'Comanda poate conține între 1 și 500 de ambalaje.');
        }
        $provided = $data['serials'] ?? null;
        if ($provided !== null && (!is_array($provided) || !array_is_list($provided) || count($provided) !== $quantity)) {
            throw new Problem(422, 'print_serials', 'Trimite câte o serie pentru fiecare ambalaj sau folosește alocarea automată.');
        }
        if ($provided !== null) {
            $transportReady = null;
            foreach ($provided as $serial) {
                if (!is_string($serial)) {
                    throw new Problem(422, 'print_serials', 'Seria trebuie să fie text.');
                }
                ResolverUri::qualifier('21', $serial);
                if (in_array($serial, ['.', '..'], true)
                    || (!preg_match('/\A[A-Za-z0-9_-]+\z/D', $serial) && !($transportReady ??= ResolverRegistry::transportReady()))) {
                    throw new Problem(422, 'print_transport', 'Seria nu poate fi tipărită sigur prin transportul GS1 verificat.');
                }
            }
            if (count(array_unique($provided, SORT_STRING)) !== count($provided)) {
                throw new Problem(409, 'print_serials', 'Seriile din comandă trebuie să fie distincte.');
            }
        }
        return Db::atomic(function () use ($p, $deviceId, $batchId, $quantity, $provided): array {
            $device = self::ownedDevice($p, $deviceId, true);
            if ($device['revoked_at'] !== null) {
                throw new Problem(422, 'print_device', 'Echipamentul este deconectat.');
            }
            [$batch, $item, $public] = self::printable($p, $batchId);
            Db::one('SELECT id FROM organizations WHERE id=? FOR UPDATE', [$p['tenant']]);
            Auth::rate('print-units|' . $p['tenant'], 100, 3600);
            Db::run('INSERT IGNORE INTO print_serial_counters (gtin14,next_value) VALUES (?,1)', [$item['gtin14']]);
            $counter = Db::one('SELECT next_value FROM print_serial_counters WHERE gtin14=? FOR UPDATE', [$item['gtin14']]);
            $next = (int) $counter['next_value'];
            if ($next + $quantity > 999999999999) {
                throw new Problem(413, 'print_serial_limit', 'Intervalul de serii automate a fost epuizat.');
            }
            $serials = $provided ?? array_map(static fn($n) => 'OA' . str_pad((string) $n, 12, '0', STR_PAD_LEFT), range($next, $next + $quantity - 1));
            // One GTIN lock serializes manual imports and automatically allocated identifiers alike.
            foreach ($serials as $serial) {
                if (Db::one('SELECT id FROM print_units WHERE gtin14=? AND serial=?', [$item['gtin14'], $serial])) {
                    throw new Problem(409, 'print_serial_used', 'O serie este deja alocată. Seriile existente nu pot fi înlocuite.');
                }
            }
            $following = $provided === null ? $next + $quantity : $next;
            foreach ($provided ?? [] as $serial) {
                if (preg_match('/\AOA([0-9]{12})\z/D', $serial, $match)) {
                    $following = max($following, (int) $match[1] + 1);
                }
            }
            Db::run('UPDATE print_serial_counters SET next_value=? WHERE gtin14=?', [$following, $item['gtin14']]);
            $job = self::newJob($p, $device, $batchId, $quantity);
            foreach ($serials as $position => $serial) {
                $id = Db::id();
                $snapshot = ['name' => $public['label']['name'], 'gtin14' => $item['gtin14'], 'lot_code' => $batch['lot_code'],
                    'serial' => $serial, 'product_info_version_id' => $batch['product_info_version_id'],
                    'production_date' => $public['batch']['data']['production_date'] ?? null,
                    'best_before' => $public['batch']['data']['best_before'] ?? null, 'use_by' => $public['batch']['data']['use_by'] ?? null];
                $link = self::digitalLink($item['gtin14'], $batch['lot_code'], $serial, $snapshot);
                if ($device['format']==='zpl') self::zplCapacity($link);
                Db::insert('print_units', ['id' => $id, 'tenant_id' => $p['tenant'], 'trade_item_id' => $item['id'],
                    'batch_id' => $batchId, 'gtin14' => $item['gtin14'], 'serial' => $serial, 'digital_link' => $link,
                    'snapshot_json' => Db::json($snapshot), 'created_at' => Db::now()]);
                Db::insert('print_job_units', ['tenant_id' => $p['tenant'], 'job_id' => $job, 'unit_id' => $id, 'position' => $position]);
            }
            self::event($job, 'queued', 'Comandă creată de un utilizator autorizat.');
            Catalog::audit($p, 'print.job_created', $job, ['quantity' => $quantity]);
            return ['id' => $job, 'state' => 'queued', 'quantity' => $quantity];
        });
    }

    private static function newJob(array $p, array $device, string $batch, int $quantity, ?string $original = null, ?string $reason = null): string
    {
        Db::run('INSERT IGNORE INTO print_usage (tenant_id) VALUES (?)', [$p['tenant']]);
        $usage = Db::one('SELECT units_reserved,jobs_created FROM print_usage WHERE tenant_id=? FOR UPDATE', [$p['tenant']]);
        $reserved = $original === null ? $quantity : 0;
        $limits = Config::get('print_limits', []);
        $unitLimit = max(1, (int) ($limits['units'] ?? 1000000));
        $jobLimit = max(1, (int) ($limits['jobs'] ?? 20000));
        if ((int) $usage['units_reserved'] + $reserved > $unitLimit || (int) $usage['jobs_created'] + 1 > $jobLimit) {
            throw new Problem(413, 'print_quota', 'Capacitatea de tipărire alocată organizației a fost atinsă. Contactează operatorul pentru dimensionare.');
        }
        Db::run('UPDATE print_usage SET units_reserved=units_reserved+?,jobs_created=jobs_created+1 WHERE tenant_id=?', [$reserved, $p['tenant']]);
        $id = Db::id();
        Db::insert('print_jobs', ['id' => $id, 'tenant_id' => $p['tenant'], 'device_id' => $device['id'],
            'batch_id' => $batch, 'created_by' => $p['user'], 'quantity' => $quantity, 'format' => $device['format'],
            'reprint_of' => $original, 'reason' => $reason, 'created_at' => Db::now(), 'updated_at' => Db::now()]);
        return $id;
    }

    private static function event(string $id, string $state, string $evidence): void
    {
        $job = Db::one('SELECT tenant_id FROM print_jobs WHERE id=?', [$id]);
        if (!$job) {
            throw new \LogicException('A print event requires an existing job.');
        }
        Db::insert('print_job_events', ['id' => Db::id(), 'tenant_id' => $job['tenant_id'], 'job_id' => $id, 'state' => $state, 'evidence' => $evidence, 'created_at' => Db::now()]);
    }

    private static function ownedJob(array $p, string $id, bool $lock = false): array
    {
        return Db::one('SELECT id,tenant_id,device_id,batch_id,created_by,state,quantity,format,reprint_of,reason,claimed_at,updated_at,created_at FROM print_jobs WHERE id=? AND tenant_id=?' . ($lock ? ' FOR UPDATE' : ''), [$id, $p['tenant']])
            ?? throw new Problem(404, 'print_job', 'Comanda nu a fost găsită.');
    }

    public static function units(array $p): array
    {
        Marketplace::installed();
        [$limit, $cursor] = Api::page();
        return Api::collection(Db::all('SELECT id,trade_item_id,batch_id,gtin14,serial,digital_link,snapshot_json,created_at FROM print_units WHERE tenant_id=? AND id>? ORDER BY id LIMIT ' . ($limit + 1), [$p['tenant'], $cursor]), $limit,
            static function (array $row): array {
                $row['snapshot'] = json_decode($row['snapshot_json'], true, 32, JSON_THROW_ON_ERROR);
                unset($row['snapshot_json']);
                return $row;
            });
    }

    public static function jobs(array $p): array
    {
        Marketplace::installed();
        [$limit, $cursor] = Api::page();
        return Api::collection(Db::all('SELECT id,device_id,batch_id,state,quantity,format,reprint_of,created_at,updated_at FROM print_jobs WHERE tenant_id=? AND id>? ORDER BY id LIMIT ' . ($limit + 1), [$p['tenant'], $cursor]), $limit);
    }

    public static function job(array $p, string $id): array
    {
        Marketplace::installed();
        $job = self::ownedJob($p, $id);
        $job['units'] = Db::all('SELECT u.id,u.serial,u.digital_link,u.snapshot_json FROM print_job_units j JOIN print_units u ON u.id=j.unit_id WHERE j.job_id=? ORDER BY j.position LIMIT 500', [$id]);
        foreach ($job['units'] as &$unit) {
            $unit['snapshot'] = json_decode($unit['snapshot_json'], true, 32, JSON_THROW_ON_ERROR);
            unset($unit['snapshot_json']);
        }
        $job['events'] = Db::all('SELECT state,evidence,created_at FROM print_job_events WHERE job_id=? ORDER BY created_at,id LIMIT 100', [$id]);
        return $job;
    }

    public static function cancel(array $p, string $id, array $data): array
    {
        Auth::write($p);
        return Db::atomic(function () use ($p, $id, $data): array {
            $job = self::ownedJob($p, $id, true);
            if ($job['state'] !== 'queued') {
                throw new Problem(409, 'print_state', 'Comanda a fost preluată sau închisă și nu mai poate fi anulată.');
            }
            $reason = Catalog::text($data, 'reason', 1000);
            if($device['format']==='zpl')foreach(self::job($p,$id)['units'] as $unit)self::zplCapacity($unit['digital_link']);
            Db::run("UPDATE print_jobs SET state='cancelled',updated_at=? WHERE id=?", [Db::now(), $id]);
            self::event($id, 'cancelled', $reason);
            Catalog::audit($p, 'print.job_cancelled', $id, ['reason' => $reason]);
            return ['id' => $id, 'state' => 'cancelled'];
        });
    }

    public static function reprint(array $p, string $id, array $data): array
    {
        Auth::approve($p);
        return Db::atomic(function () use ($p, $id, $data): array {
            $preview = self::ownedJob($p, $id);
            $device = self::ownedDevice($p, isset($data['device_id']) ? Api::id($data['device_id']) : $preview['device_id'], true);
            $old = self::ownedJob($p, $id, true);
            if (!in_array($old['state'], ['dispatched', 'confirmed', 'failed', 'uncertain', 'cancelled'], true)) {
                throw new Problem(409, 'print_state', 'Comanda este încă în curs.');
            }
            if (($data['same_serials_confirmed'] ?? false) !== true) {
                throw new Problem(422, 'print_reprint', 'Confirmă reutilizarea acelorași serii pentru înlocuirea etichetelor.');
            }
            self::printable($p, $old['batch_id']);
            if ($device['revoked_at'] !== null) {
                throw new Problem(422, 'print_device', 'Echipamentul este deconectat.');
            }
            $reason = Catalog::text($data, 'reason', 1000);
            $job = self::newJob($p, $device, $old['batch_id'], (int) $old['quantity'], $id, $reason);
            Db::run('INSERT INTO print_job_units (tenant_id,job_id,unit_id,position) SELECT tenant_id,?,unit_id,position FROM print_job_units WHERE job_id=? AND tenant_id=?', [$job, $id, $p['tenant']]);
            self::event($job, 'queued', $reason);
            Catalog::audit($p, 'print.job_reprinted', $job, ['original_job' => $id, 'reason' => $reason]);
            return ['id' => $job, 'state' => 'queued', 'reprint_of' => $id];
        });
    }

    public static function claim(array $device): array
    {
        return Db::atomic(function () use ($device): array {
            // Serialize the queue per device; lost acknowledgements never cause automatic retransmission.
            Db::one('SELECT id FROM print_devices WHERE id=? FOR UPDATE', [$device['id']]);
            if (!Db::one('SELECT id FROM print_devices WHERE id=? AND revoked_at IS NULL', [$device['id']])) {
                throw new Problem(401, 'print_credential', 'Echipamentul este deconectat.');
            }
            foreach (Db::all("SELECT id FROM print_jobs WHERE device_id=? AND state='claimed' AND claimed_at<? FOR UPDATE", [$device['id'], gmdate('Y-m-d H:i:s', time() - 300)]) as $stale) {
                Db::run("UPDATE print_jobs SET state='uncertain',updated_at=? WHERE id=?", [Db::now(), $stale['id']]);
                self::event($stale['id'], 'uncertain', 'Confirmarea nu a sosit. Verificarea operatorului este necesară înaintea retipăririi.');
            }
            if (Db::one("SELECT id FROM print_jobs WHERE device_id=? AND state='claimed'", [$device['id']])) {
                return ['job' => null, 'reason' => 'job_in_progress'];
            }
            $job = Db::one("SELECT id,batch_id FROM print_jobs WHERE device_id=? AND state='queued' ORDER BY created_at,id LIMIT 1 FOR UPDATE", [$device['id']]);
            if (!$job) {
                return ['job' => null, 'reason' => 'empty'];
            }
            try {
                self::printable(['tenant' => $device['tenant_id']], $job['batch_id']);
            } catch (Problem) {
                Db::run("UPDATE print_jobs SET state='failed',updated_at=? WHERE id=?", [Db::now(), $job['id']]);
                self::event($job['id'], 'failed', 'Informațiile aprobate sau starea lotului nu mai permit tipărirea.');
                return ['job' => null, 'reason' => 'approval_changed'];
            }
            $claim = bin2hex(random_bytes(32));
            Db::run("UPDATE print_jobs SET state='claimed',claim_hash=?,claimed_at=?,updated_at=? WHERE id=?", [hash('sha256', $claim), Db::now(), Db::now(), $job['id']]);
            self::event($job['id'], 'claimed', 'Comanda a fost preluată de agentul echipamentului.');
            $payload = self::job(['tenant' => $device['tenant_id']], $job['id']);
            if ($payload['format'] === 'zpl') {
                $payload['zpl'] = self::zpl($payload['units']);
            }
            return ['job' => $payload, 'claim_token' => $claim, 'physical_print_confirmed' => false];
        });
    }

    public static function acknowledge(array $device, string $id, array $data): array
    {
        return Db::atomic(function () use ($device, $id, $data): array {
            $job = Db::one('SELECT * FROM print_jobs WHERE id=? AND device_id=? AND tenant_id=? FOR UPDATE', [$id, $device['id'], $device['tenant_id']])
                ?? throw new Problem(404, 'print_job', 'Comanda nu a fost găsită.');
            $token = $data['claim_token'] ?? '';
            if (!is_string($token) || !is_string($job['claim_hash']) || !hash_equals($job['claim_hash'], hash('sha256', $token))) {
                throw new Problem(403, 'print_claim', 'Confirmarea nu aparține preluării curente.');
            }
            $state = $data['state'] ?? '';
            if (!in_array($state, ['dispatched', 'confirmed', 'failed', 'uncertain'], true)) {
                throw new Problem(422, 'print_state', 'Stare de confirmare incorectă.');
            }
            $evidence = Catalog::text($data, 'evidence', 1500);
            if ($job['state'] === $state) {
                return ['id' => $id, 'state' => $state, 'physical_print_confirmed' => $state === 'confirmed'];
            }
            if ($job['state'] !== 'claimed' && !($job['state'] === 'dispatched' && $state === 'confirmed')) {
                throw new Problem(409, 'print_state', 'Comanda a fost deja închisă sau necesită verificarea operatorului.');
            }
            Db::run('UPDATE print_jobs SET state=?,updated_at=? WHERE id=?', [$state, Db::now(), $id]);
            self::event($id, $state, $evidence);
            return ['id' => $id, 'state' => $state, 'physical_print_confirmed' => $state === 'confirmed'];
        });
    }

    public static function digitalLink(string $gtin, string $lot, string $serial, array $snapshot): string
    {
        $gtin = DigitalLink::gtin($gtin);
        $origin = parse_url((string) Config::get('url'));
        if (Config::get('environment') !== 'test' && (!$origin || ($origin['scheme'] ?? '') !== 'https'
            || empty($origin['host']) || isset($origin['user']) || isset($origin['pass']) || isset($origin['query']) || isset($origin['fragment']))) {
            throw new Problem(503, 'print_origin', 'Domeniul de tipărire trebuie să folosească HTTPS.');
        }
        ResolverUri::qualifier('10', $lot);
        ResolverUri::qualifier('21', $serial);
        if (in_array($lot, ['.', '..'], true) || in_array($serial, ['.', '..'], true)) {
            throw new Problem(422, 'print_segment', 'Lotul sau seria nu pot fi segmente normalizate de browser.');
        }
        $attributes = [];
        foreach (['11' => 'production_date', '15' => 'best_before', '17' => 'use_by'] as $ai => $key) {
            $date = $snapshot[$key] ?? null;
            if ($date === null || $date === '') {
                continue;
            }
            if (!is_string($date) || !preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/D', $date, $parts)
                || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                throw new Problem(422, 'print_date', 'Data lotului nu poate fi codificată ca dată GS1 YYMMDD.');
            }
            $attributes[$ai] = substr($parts[1], 2) . $parts[2] . $parts[3];
            if (FoodDate::gs1($attributes[$ai], 'best_before')['value'] !== $date) {
                throw new Problem(422, 'print_date', 'Anul nu poate fi reprezentat fără ambiguitate în fereastra GS1 curentă.');
            }
        }
        $uri = url(ResolverUri::path($gtin, ['10' => $lot, '21' => $serial]));
        return $uri . ($attributes ? '?' . http_build_query($attributes, '', '&', PHP_QUERY_RFC3986) : '');
    }

    private static function zplCapacity(string $uri): void
    {
        if(strlen($uri)>512)throw new Problem(422,'print_zpl_capacity','Adresa depășește capacitatea șablonului ZPL de referință. Folosește formatul JSON și un adaptor de etichetare dimensionat pentru echipament.');
    }

    public static function zpl(array $units): string
    {
        $hex = static fn(string $text): string => implode('', array_map(static fn($c) => '_' . strtoupper(bin2hex($c)), str_split($text)));
        $output = '';
        foreach ($units as $unit) {
            self::zplCapacity($unit['digital_link']);
            $snapshot = $unit['snapshot'];
            // At most 512 bytes fits byte-mode QR-L version 15 (77 modules).
            // Five dots/module, >=20 clear dots on every side; text stays outside that box.
            $output .= "^XA\n^PW600\n^LL650\n^LH0,0\n^CI28\n^FO40,25^A0N,25,25^FB520,2,0,L,0^FH^FD" . $hex($snapshot['name']) . "^FS\n"
                . '^FO40,120^BQN,2,5^FH^FDLA,' . $hex($unit['digital_link']) . "^FS\n"
                . '^FO40,570^A0N,20,20^FB520,2,0,L,0^FH^FD' . $hex('LOT ' . $snapshot['lot_code'] . ' / ' . $snapshot['serial']) . "^FS\n^PQ1\n^XZ\n";
        }
        return $output;
    }
}
