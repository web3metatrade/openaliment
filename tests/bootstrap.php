<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/autoload.php';

/** Test-only configuration; never falls back to production credentials. */
function oa_test_config(): array
{
    $file = getenv('OA_TEST_CONFIG') ?: __DIR__ . '/config.local.php';
    if (!is_file($file)) {
        if (getenv('OA_TEST_CONFIG')) {
            throw new RuntimeException('OA_TEST_CONFIG does not name a readable test configuration.');
        }
        $file = __DIR__ . '/config.example.php';
    }
    $config = require $file;
    if (!is_array($config) || !is_string($config['db']['dsn'] ?? null)
        || !preg_match('/\Amysql:[^\r\n]*\bdbname=openaliment_tests(?:;|\z)/D', $config['db']['dsn'])) {
        throw new RuntimeException('Only the disposable openaliment_tests database is permitted.');
    }
    $config['url'] = getenv('OA_TEST_BASE_URL') ?: $config['url'];
    $host = parse_url($config['url'], PHP_URL_HOST);
    if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
        throw new RuntimeException('Test HTTP must use a loopback host.');
    }
    $config['environment'] = 'test';
    $config['storage'] = __DIR__ . '/runtime/storage';
    $config['session_secure'] = false;
    $config['home_sync_enabled'] = true;
    $config['feedback_enabled'] = true;
    $config['operator_name'] = 'Operator de test';
    $config['operator_contact'] = 'operator@example.invalid';
    $config['privacy_text'] = 'Informare exclusiv pentru teste sintetice.';
    $config['mail'] = ['enabled' => true, 'transport' => 'test_file'];
    $config['ai']['enabled'] = false;
    $config['document_scanner'] = ['transport' => 'test_clean'];
    if (getenv('OA_TEST_SYNTHETIC_HANDOVER') === '1' || !is_readable(($config['handover_path'] ?? '') . '/HASHES.sha256')) {
        $synthetic = __DIR__ . '/runtime/legacy-handover';
        if (!is_readable($synthetic . '/HASHES.sha256')) {
            throw new RuntimeException('Run tests/generators/legacy_handover.py to prepare the synthetic legacy package.');
        }
        $config['handover_path'] = $synthetic;
    }
    // Broad regression suites share one loopback IP; limiter boundary tests use dedicated identities.
    $config['api_limits'] = array_fill_keys(['public','resolver','catalog','tools','docs','private-read','private-write','auth','download'],10000);
    foreach (['reports', 'previews', 'storage/private', 'storage/logs', 'storage/cache'] as $directory) {
        $path = __DIR__ . '/runtime/' . $directory;
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('Cannot prepare private test runtime.');
        }
    }
    return $config;
}

App\Core\Config::load(oa_test_config());
require_once __DIR__ . '/../app/bootstrap.php';
if (App\Core\Db::one('SELECT DATABASE() AS name')['name'] !== 'openaliment_tests') {
    throw new RuntimeException('The connected database is not openaliment_tests.');
}
