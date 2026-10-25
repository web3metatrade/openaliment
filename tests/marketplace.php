<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
ob_start();

use App\Core\{Auth, Config, Db, Migrations, Problem, Session};
use App\Domain\{Accounts, Catalog, Gs1Standards, Marketplace, Printing, ResolverUri};

$checks = [];
function mpCheck(string $name, bool $ok): void {
    global $checks;
    if (!$ok) { throw new RuntimeException('FAIL ' . $name); }
    $checks[] = ['name' => $name, 'passed' => true];
    echo 'PASS ' . $name . PHP_EOL;
}
function mpReject(string $name, callable $run, int $status): void {
    try { $run(); } catch (Problem $error) { mpCheck($name, $error->status === $status); return; }
    throw new RuntimeException('Missing rejection: ' . $name);
}
Migrations::upgradeMarketplace();
Migrations::upgradeMarketplace();
mpCheck('Migration 008 is additive and repeatable', Migrations::marketplace());
$password = bin2hex(random_bytes(18));
$account = Accounts::register(['email' => 'market-' . Db::id() . '@example.invalid', 'password' => $password, 'display_name' => 'Synthetic publisher', 'organization' => 'Synthetic manufacturer'], true);
$other = Accounts::register(['email' => 'market-other-' . Db::id() . '@example.invalid', 'password' => $password, 'display_name' => 'Other publisher', 'organization' => 'Other manufacturer']);
Db::run('UPDATE users SET email_verified=1 WHERE id=?', [$account['id']]);
Session::login($account['id']);
$user = Auth::user();
$p = Auth::principal();
$stranger = ['tenant' => $other['tenant_id'], 'user' => $other['id'], 'role' => 'owner', 'admin' => false, 'token' => null];
$listing = ['slug' => 'market-' . Db::id(), 'name' => 'Synthetic ERP and printer connector', 'summary' => 'A synthetic integration used only in isolated tests.', 'description' => 'Local connector <script>alert(1)</script>', 'publisher' => 'Synthetic developer', 'category' => 'community-tool', 'website' => 'https://developer.example/', 'support_url' => 'https://developer.example/support', 'license' => 'Proprietary test license', 'license_url' => 'https://developer.example/license', 'tags' => ['erp', 'printing'], 'capabilities' => ['catalog-sync', 'zpl'], 'platforms' => ['windows-x64'], 'compatibility' => ['arbitrary-future-equipment'], 'pricing' => 'free'];
$app = Marketplace::save($user, null, $listing)['id'];
mpReject('An unapproved listing is private', fn() => Marketplace::catalog($listing['slug']), 404);
mpReject('Another user cannot manage the listing', fn() => Marketplace::owned($other['id'], $app), 404);
mpReject('Unverified contact cannot submit a listing', fn() => Marketplace::submit(array_replace($user, ['email_verified' => 0]), $app), 403);
Marketplace::submit($user, $app);
mpReject('Listing review rejects stale revision', fn() => Marketplace::review($user, $app, ['decision' => 'approve_listing', 'reason' => 'Synthetic review', 'expected_revision' => 2]), 409);
Marketplace::review($user, $app, ['decision' => 'approve_listing', 'reason' => 'Synthetic review', 'expected_revision' => 1]);
mpCheck('Community categories and compatibility are extensible', Marketplace::catalog($listing['slug'])['items'][0]['category'] === 'community-tool');
mpCheck('Software license remains explicit and commercial operations disabled', Marketplace::catalog($listing['slug'])['items'][0]['license'] === 'Proprietary test license' && Marketplace::catalog()['commerce_enabled'] === false);
Marketplace::save($user, $app, array_replace($listing, ['name' => 'Unapproved changed title', 'expected_revision' => 1]));
mpCheck('Editing draft cannot replace approved public snapshot', Marketplace::catalog($listing['slug'])['items'][0]['name'] === $listing['name']);
mpReject('Concurrent editing cannot overwrite a newer revision', fn() => Marketplace::save($user, $app, $listing + ['expected_revision' => 1]), 409);
foreach (['http://developer.example/file.exe', 'https://user:secret@developer.example/file.exe', 'https://127.0.0.1/file.exe', 'https://printer.local/file.exe', 'https://developer.example:8443/file.exe'] as $url) {
    mpReject('Unsafe artifact source rejected: ' . parse_url($url, PHP_URL_SCHEME), fn() => Marketplace::webUrl($url), 422);
}
$releaseData = ['version' => '1.0.0', 'artifact_url' => 'https://developer.example/releases/connector.exe', 'sha256' => hash('sha256', 'synthetic artifact'), 'size_bytes' => 18, 'platforms' => ['windows-x64'], 'changelog' => 'Synthetic version'];
$release = Marketplace::release($user, $app, $releaseData)['id'];
mpCheck('Unapproved releases excluded from public catalog', Marketplace::catalog($listing['slug'])['items'][0]['releases'] === []);
mpReject('Published version key is immutable', fn() => Marketplace::release($user, $app, $releaseData), 409);
mpReject('Approval requires explicit artifact verification', fn() => Marketplace::review($user, $app, ['decision' => 'approve_release', 'release_id' => $release, 'reason' => 'Synthetic review']), 422);
Marketplace::review($user, $app, ['decision' => 'approve_release', 'release_id' => $release, 'reason' => 'Synthetic hash verification, not a real executable analysis.', 'artifact_verified' => true]);
mpCheck('Approved release exposes exact artifact hash', Marketplace::catalog($listing['slug'])['items'][0]['releases'][0]['sha256'] === $releaseData['sha256']);
Marketplace::save($user, $app, array_replace($listing, ['license' => 'MIT', 'pricing' => 'paid', 'expected_revision' => 2]));
Marketplace::submit($user, $app);
Marketplace::review($user, $app, ['decision' => 'approve_listing', 'reason' => 'Synthetic changed future terms', 'expected_revision' => 3]);
$historicalRelease = Marketplace::catalog($listing['slug'])['items'][0]['releases'][0];
mpCheck('New commercial terms cannot retroactively relicense an earlier free release', $historicalRelease['license'] === $listing['license'] && $historicalRelease['pricing'] === 'free' && $historicalRelease['artifact_url'] === $releaseData['artifact_url']);
mpCheck('Selecting software grants no API access', Marketplace::select($p, $app, true)['api_access_granted'] === false);
Marketplace::review($user, $app, ['decision' => 'suspend', 'reason' => 'Synthetic withdrawal']);
mpReject('Suspension removes public listing and releases', fn() => Marketplace::catalog($listing['slug']), 404);

$product = Catalog::product($p, ['name' => 'Synthetic non-bread label', 'category' => 'dairy'])['id'];
$base = '952' . str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT);
$sum = 0; for ($i = 0; $i < 12; $i++) { $sum += (int) $base[$i] * ($i % 2 ? 3 : 1); }
$gtin = '0' . $base . ((10 - $sum % 10) % 10);
$item = Catalog::trade($p, $product, ['name' => 'Synthetic trade item', 'gtin' => $gtin, 'claim_evidence' => 'Isolated test only'])['id'];
Db::run("UPDATE trade_items SET gtin_status='verified',verified_at=? WHERE id=?", [Db::now(), $item]);
$label = Catalog::label($p, $item, ['version_key' => 'PRINT-L1', 'name' => 'Synthetic food ^XZ', 'category' => 'dairy', 'ingredients' => 'Milk', 'allergens' => 'Milk', 'net_quantity' => '200 g', 'operator' => 'Synthetic manufacturer', 'storage' => 'Refrigerated'])['id'];
Catalog::publishLabel($p, $label);
$batch = Catalog::batch($p, $item, ['product_info_version_id' => $label, 'lot_code' => 'LOT/A+%', 'production_date' => '2026-10-25', 'best_before' => '2026-10-30'])['id'];
Catalog::publishBatch($p, $batch);
Db::run('INSERT INTO resolver_transport (id,verified,verified_at,verified_by,evidence_json) VALUES (1,1,?,?,?) ON DUPLICATE KEY UPDATE verified=1', [Db::now(), $user['id'], Db::json(['simulation' => true, 'purpose' => 'Isolated character-transport tests; not physical printer proof'])]);
$device = Printing::device($p, null, ['name' => 'Synthetic line', 'format' => 'zpl']);
$blockedByDatabase = false;
try {
    Db::insert('print_jobs', ['id' => Db::id(), 'tenant_id' => $other['tenant_id'], 'device_id' => $device['id'], 'batch_id' => $batch, 'created_by' => $other['id'], 'quantity' => 1, 'format' => 'json', 'updated_at' => Db::now(), 'created_at' => Db::now()]);
} catch (PDOException $error) {
    $blockedByDatabase = $error->getCode() === '23000';
}
mpCheck('Database foreign keys block cross-tenant print associations', $blockedByDatabase);
mpCheck('Device credential stored as hash only', Db::one('SELECT credential_hash FROM print_devices WHERE id=?', [$device['id']])['credential_hash'] === hash('sha256', $device['token']));
mpCheck('Device listing never exposes its credential', !str_contains(Db::json(Printing::devices($p)), $device['token']));
mpReject('Viewer cannot create a print job', fn() => Printing::create(array_replace($p, ['role' => 'viewer']), ['device_id' => $device['id'], 'batch_id' => $batch, 'quantity' => 1]), 403);
mpReject('Another organization cannot use the printer', fn() => Printing::create($stranger, ['device_id' => $device['id'], 'batch_id' => $batch, 'quantity' => 1]), 404);
$job = Printing::create($p, ['device_id' => $device['id'], 'batch_id' => $batch, 'quantity' => 2]);
$detail = Printing::job($p, $job['id']);
$uri = parse_url($detail['units'][0]['digital_link']);
$parsed = ResolverUri::parse($uri['path'], $uri['query']);
mpCheck('QR contains GS1 GTIN, encoded lot, serial and production attributes', $parsed['gtin'] === $gtin && $parsed['qualifiers']['10'] === 'LOT/A+%' && $parsed['qualifiers']['21'] === $detail['units'][0]['serial'] && $parsed['parameters']['11'] === '261025' && $parsed['parameters']['15'] === '261030' && !isset($parsed['parameters']['17']));
mpCheck('Distinct serial per unit without uploading recipe', $detail['units'][0]['serial'] !== $detail['units'][1]['serial'] && Db::one('SELECT recipe_version_id FROM batches WHERE id=?', [$batch])['recipe_version_id'] === null);
mpReject('Allocated serial cannot be reused', fn() => Printing::create($p, ['device_id' => $device['id'], 'batch_id' => $batch, 'quantity' => 1, 'serials' => [$detail['units'][0]['serial']]]), 409);
mpReject('Another tenant cannot read print snapshots', fn() => Printing::job($stranger, $job['id']), 404);
$agent = Printing::agent($device['token']);
$claim = Printing::claim($agent);
mpCheck('Claim returns only its own job and one-time claim token', $claim['job']['id'] === $job['id'] && strlen($claim['claim_token']) === 64 && $claim['physical_print_confirmed'] === false);
mpCheck('ZPL escapes input control commands and embeds exact URI', substr_count($claim['job']['zpl'], '^XZ') === 2 && str_contains($claim['job']['zpl'], '_5E_58_5A') && str_contains($claim['job']['zpl'], '^BQN,2,5'));
mpCheck('Claim cannot be delivered twice while processing', Printing::claim($agent)['reason'] === 'job_in_progress');
mpReject('A claimed job cannot be cancelled', fn() => Printing::cancel($p, $job['id'], ['reason' => 'Synthetic']), 409);
mpReject('Acknowledgement bound to claim secret', fn() => Printing::acknowledge($agent, $job['id'], ['claim_token' => str_repeat('a', 64), 'state' => 'confirmed', 'evidence' => 'Synthetic']), 403);
$ack = ['claim_token' => $claim['claim_token'], 'state' => 'dispatched', 'evidence' => 'Simulator only: bytes written to local output, no real printer.'];
mpCheck('Data dispatch is not physical print confirmation', Printing::acknowledge($agent, $job['id'], $ack)['physical_print_confirmed'] === false);
mpCheck('Repeated acknowledgement is idempotent', Printing::acknowledge($agent, $job['id'], $ack)['state'] === 'dispatched');
mpReject('Reprint requires explicit same-identity confirmation', fn() => Printing::reprint($p, $job['id'], ['reason' => 'Replacement']), 422);
$reprint = Printing::reprint($p, $job['id'], ['reason' => 'Replacement of damaged labels', 'same_serials_confirmed' => true]);
mpCheck('Reprint preserves original identities and snapshots', Printing::job($p, $reprint['id'])['units'] === $detail['units']);
$stale = Printing::claim($agent);
Db::run('UPDATE print_jobs SET claimed_at=? WHERE id=?', [gmdate('Y-m-d H:i:s', time() - 400), $stale['job']['id']]);
Printing::claim($agent);
mpCheck('Lost acknowledgement becomes uncertain without auto requeue', Printing::job($p, $stale['job']['id'])['state'] === 'uncertain');
$oldUri = $detail['units'][0]['digital_link'];
$l2 = Catalog::label($p, $item, ['version_key' => 'PRINT-L2', 'name' => 'Updated food', 'category' => 'dairy', 'ingredients' => 'Milk and fruit', 'allergens' => 'Milk', 'net_quantity' => '200 g', 'operator' => 'Synthetic manufacturer', 'storage' => 'Refrigerated'])['id'];
Catalog::publishLabel($p, $l2);
mpCheck('Product update preserves old printed URI and label snapshot', Printing::job($p, $job['id'])['units'][0]['digital_link'] === $oldUri && Printing::job($p, $job['id'])['units'][0]['snapshot']['name'] === 'Synthetic food ^XZ');
$waiting = Printing::create($p, ['device_id' => $device['id'], 'batch_id' => $batch, 'quantity' => 1]);
$alert = Catalog::alert($p, $batch, ['message' => 'Synthetic recall', 'state' => 'on_hold']);
mpCheck('New alert blocks queued print job before dispatch', Printing::claim($agent)['reason'] === 'approval_changed' && Printing::job($p, $waiting['id'])['state'] === 'failed');
Catalog::resolveAlert($p, $alert['id'], ['resolution' => 'Synthetic test complete']);
Catalog::releaseBatch($p, $batch);
$rotated = Printing::device($p, $device['id'], []);
mpReject('Old printer credential stops after rotation', fn() => Printing::agent($device['token']), 401);
Printing::revoke($p, $device['id']);
mpReject('Revoked printer credential stops immediately', fn() => Printing::agent($rotated['token']), 401);
$replacementDevice = Printing::device($p, null, ['name' => 'Replacement test line', 'format' => 'json']);
$moved = Printing::reprint($p, $job['id'], ['device_id' => $replacementDevice['id'], 'reason' => 'Replace damaged labels on another device', 'same_serials_confirmed' => true]);
mpCheck('Replacement device preserves the same GS1 identities', Printing::job($p, $moved['id'])['device_id'] === $replacementDevice['id'] && Printing::job($p, $moved['id'])['units'] === $detail['units']);
$caseJob = Printing::create($p, ['device_id' => $replacementDevice['id'], 'batch_id' => $batch, 'quantity' => 2, 'serials' => ['CaseSensitive', 'casesensitive']]);
mpCheck('GS1 serial uniqueness is case sensitive', count(Printing::job($p, $caseJob['id'])['units']) === 2);
mpReject('Invalid GS1 date is never silently reformatted', fn() => Printing::digitalLink($gtin, 'L1', 'S1', ['production_date' => '2026-02-31']), 422);
mpReject('Browser-normalized serial cannot be printed', fn() => Printing::digitalLink($gtin, 'L1', '..', []), 422);
mpReject('Ambiguous GS1 century cannot change the encoded expiry', fn() => Printing::digitalLink($gtin, 'L1', 'S1', ['use_by' => ((int)date('Y') + 51) . '-01-01']), 422);
$dates = parse_url(Printing::digitalLink($gtin, 'L1', 'S1', ['production_date' => '2026-10-25', 'use_by' => '2026-10-30']));
mpCheck('Use-by uses AI 17 and does not invent AI 15', str_contains($dates['query'], '17=261030') && !str_contains($dates['query'], '15='));
mpCheck('Official standards catalog pins four authoritative PDFs', count(Gs1Standards::catalog()['documents']) === 4 && Gs1Standards::catalog()['authoritative_format'] === 'official_pdf');
mpReject('Standards preservation accepts no arbitrary URL', fn() => Gs1Standards::preserve('https://evil.example/file.pdf'), 404);

$reference = getenv('OA_GS1_REFERENCE_DIR') ?: ROOT . '/.tools/standards/gs1';
$official = $reference . '/uri-syntax-1.7.0.pdf';
$record = Gs1Standards::catalog()['documents'][0];
if (!is_file($official) || !hash_equals($record['sha256'], hash_file('sha256', $official))) {
    throw new RuntimeException('Prepare the official URI PDF first: python tools/standards/fetch.py --only uri-syntax --directory tests/runtime/reference-gs1; set OA_GS1_REFERENCE_DIR to that directory.');
}
$cached = Config::get('storage') . '/private/standards/gs1';
if (!is_dir($cached)) { mkdir($cached, 0700, true); }
copy($official, $cached . '/uri-syntax-1.7.0.pdf');
mpCheck('Official private copy is pinned and does not need network access in HTTP tests', Gs1Standards::preserve('uri-syntax')['verified']);

file_put_contents(__DIR__ . '/runtime/marketplace-fixtures.json', Db::json(['email' => $user['email'], 'password' => $password, 'user_id' => $user['id'], 'other_email' => Db::one('SELECT email FROM users WHERE id=?', [$other['id']])['email'], 'batch_id' => $batch, 'p' => $p]));
file_put_contents(__DIR__ . '/runtime/reports/marketplace-domain.json', Db::json(['passed' => count($checks), 'failed' => 0, 'checks' => $checks, 'physical_printer_pos' => 'not tested; synthetic local agent only']));
echo Db::json(['passed' => count($checks), 'failed' => 0]) . PHP_EOL;

ob_end_flush();
