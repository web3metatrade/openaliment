<?php
declare(strict_types=1);

// Explicit local test server only. Never copy into public/ or use on hosting.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~^/assets/[A-Za-z0-9/_.-]+$~', $path)
    && !str_contains($path, '..') && is_file(__DIR__ . '/../../public' . $path)) {
    return false;
}
require __DIR__ . '/../../tests/bootstrap.php';
// Minimal legacy worker fixture for real browser lifecycle tests, never deployed.
if ($path === '/acasa/legacy-test-worker.js') {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-store');
    echo "self.addEventListener('install',event=>event.waitUntil(caches.open('openaliment-home-8').then(()=>self.skipWaiting())));self.addEventListener('activate',event=>event.waitUntil(self.clients.claim()));";
    return;
}
// A separate loopback probe can exercise queue admission without an AI worker/provider.
if(getenv('OA_TEST_QUEUE_AI')==='1'){
    $queueConfig=oa_test_config();$queueConfig['ai']['enabled']=true;\App\Core\Config::load($queueConfig);
}
require __DIR__ . '/../../public/index.php';
