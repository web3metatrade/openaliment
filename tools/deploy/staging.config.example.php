<?php
declare(strict_types=1);

// Initial staging only. The packaging tool writes this to private config/local.php.
// Complete only the staging database password on hosting; never reuse production credentials.
$expectedRoot = '/home/apelero/dev.openaliment.ro';
if (realpath(dirname(__DIR__)) !== realpath($expectedRoot)) {
    throw new RuntimeException('Staging configuration cannot be used outside its dedicated project.');
}
$database = 'apelero_dev_openaliment';
$databaseUser = 'apelero_dev_openaliment';
$databasePassword = '';
if (in_array($database, ['apelero_openaliment', 'openaliment_tests'], true) || $databaseUser === 'apelero_openaliment') {
    throw new RuntimeException('Production or disposable test credentials are not allowed for staging.');
}
$config = require __DIR__ . '/config.example.php';
$config['url'] = 'https://dev.openaliment.ro';
// Keep production HTTPS/session validation. Do not enable the local test environment.
$config['environment'] = 'production';
$config['db'] = ['dsn' => 'mysql:host=localhost;port=3306;dbname=' . $database . ';charset=utf8mb4',
    'user' => $databaseUser, 'password' => $databasePassword];
$config['storage'] = dirname(__DIR__) . '/storage';
$config['session_secure'] = true;
$config['registration_enabled'] = false;
$config['home_sync_enabled'] = false;
$config['feedback_enabled'] = false;
$config['mail'] = ['enabled' => false];
$config['ai'] = ['enabled' => false, 'key' => '', 'model' => '', 'daily_jobs' => 0];
$config['operator_name'] = 'OpenAliment — mediu de verificare';
$config['operator_contact'] = 'alex@patrut.com';
$config['privacy_text'] = 'Mediu de verificare cu date sintetice, destinat echipei OpenAliment.';
$config['setup_token_hash'] = '__OA_STAGING_SETUP_HASH__';
$config['gs1_transport_verified'] = false;
return $config;
