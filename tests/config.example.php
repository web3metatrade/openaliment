<?php
declare(strict_types=1);

// Optional overrides belong in tests/config.local.php or OA_TEST_CONFIG.
// This configuration can only target the disposable openaliment_tests database.
return array_replace(require __DIR__ . '/../config/config.example.php', [
    'environment' => 'test',
    'url' => getenv('OA_TEST_BASE_URL') ?: 'http://127.0.0.1:8088',
    'db' => [
        'dsn' => 'mysql:host=' . (getenv('OA_TEST_DB_HOST') ?: '127.0.0.1')
            . ';port=' . (getenv('OA_TEST_DB_PORT') ?: '3306')
            . ';dbname=openaliment_tests;charset=utf8mb4',
        'user' => getenv('OA_TEST_DB_USER') ?: 'openaliment_tests',
        'password' => getenv('OA_TEST_DB_PASSWORD') ?: '',
    ],
    'storage' => __DIR__ . '/runtime/storage',
    'session_secure' => false,
    'registration_enabled' => true,
    'home_sync_enabled' => true,
    'feedback_enabled' => true,
    'operator_name' => 'Operator de test',
    'operator_contact' => 'operator@example.invalid',
    'privacy_text' => 'Informare exclusiv pentru teste sintetice.',
    'mail' => ['enabled' => true, 'transport' => 'test_file'],
    'ai' => ['enabled' => false, 'key' => '', 'model' => '', 'daily_jobs' => 30],
]);
