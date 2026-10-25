<?php
declare(strict_types=1);

require __DIR__ . '/../app/autoload.php';

$checks = [];
$check = static function (string $name, bool $passed) use (&$checks): void {
    if (!$passed) {
        throw new RuntimeException($name);
    }
    $checks[] = ['name' => $name, 'passed' => true];
};
$check('Autoload does not read private application configuration', App\Core\Config::get('db') === null);
App\Core\Config::load([]);
App\Core\Config::initialize();
$check('An explicitly empty configuration does not fall back to private credentials', App\Core\Config::get('db') === null);
App\Core\Config::load(['url' => 'https://example.invalid', 'sentinel' => 'test-only']);
require __DIR__ . '/../app/bootstrap.php';
$check('Application bootstrap preserves an explicitly injected test configuration', App\Core\Config::get('sentinel') === 'test-only');
$check('Bootstrap keeps the application timezone', date_default_timezone_get() === 'Europe/Bucharest');
$check('Extracted view helpers escape markup and quotes', e('<script>"') === '&lt;script&gt;&quot;');
$check('Extracted URL helper keeps the configured base URL', url('/b/example') === 'https://example.invalid/b/example');
$check('Extracted icon helper keeps accessible inline SVG output', str_contains(icon('box'), 'aria-hidden="true"'));

$_SERVER['HTTP_HOST']='openaliment.ro';
App\Core\Config::load(['url'=>'https://example.invalid','operator_name'=>'Operator independent','privacy_text'=>'Informarea proprie','operator_contact'=>'contact@example.invalid']);
$identity=App\Domain\PlatformInformation::privacy();
$check('Another installation keeps its own operator regardless of Host header', $identity['operator']==='Operator independent' && $identity['operator_text']==='Informarea proprie' && $identity['contact']==='contact@example.invalid');
App\Core\Config::load(['url'=>'https://openaliment.ro/','operator_name'=>'Old project identity','privacy_text'=>'Old public copy','operator_contact'=>'contact@example.invalid']);
$identity=App\Domain\PlatformInformation::privacy();
$check('Canonical project identity preserves configured contact and API projection', $identity===App\Domain\ApiPublic::vocabulary('/api/v2/public/policies/privacy') && $identity['operator']==='OpenAliment' && $identity['contact']==='contact@example.invalid' && $identity['version']==='privacy-2026-10-25');
unset($_SERVER['HTTP_HOST']);
echo json_encode(['passed' => count($checks), 'failed' => 0, 'checks' => $checks], JSON_THROW_ON_ERROR) . PHP_EOL;
