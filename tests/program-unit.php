<?php
declare(strict_types=1);
require __DIR__ . '/../app/autoload.php';
require __DIR__ . '/fixtures/program.php';
use App\Core\{Config, Problem};
use App\Domain\{ProgramAssessments, ProgramHtml, ProgramLabs, ProgramLearning};
Config::load(['environment' => 'test', 'url' => 'http://localhost:8088']);
$passed = 0;
function pu(string $name, bool $ok): void { global $passed; if (!$ok) { throw new RuntimeException($name); } $passed++; }
function pur(string $name, callable $run, int $status): void { try { $run(); } catch (Problem $e) { pu($name, $e->status === $status); return; } throw new RuntimeException('Missing rejection: ' . $name); }
$bank = ProgramAssessments::validate(oa_program_test_bank());
pu('108 synthetic questions cover the blueprint', count($bank['questions']) === 108);
for ($i = 0; $i < 100; $i++) {
    $draw = ProgramAssessments::draw($bank['questions']);
    pu('Draw contains 30 distinct questions ' . $i, count($draw) === 30 && count(array_unique(array_column($draw, 'key'))) === 30);
    pu('Exactly six critical questions ' . $i, count(array_filter($draw, static fn(array $q): bool => $q['critical'])) === 6);
    $responses = array_column($draw, 'answer', 'key');
    pu('Perfect answers pass exact weighting ' . $i, ProgramAssessments::grade($draw, $responses)['score_basis_points'] === 10000 && ProgramAssessments::grade($draw, $responses)['passed']);
    $critical = array_values(array_filter($draw, static fn(array $q): bool => $q['critical']))[0];
    unset($responses[$critical['key']]);
    pu('Critical failure overrides high total ' . $i, !ProgramAssessments::grade($draw, $responses)['passed']);
}
pu('All-or-nothing multiple answers', !ProgramAssessments::correct(['type' => 'multiple', 'answer' => ['a', 'b']], ['a']));
pu('Multiple answer order is irrelevant', ProgramAssessments::correct(['type' => 'multiple', 'answer' => ['a', 'b']], ['b', 'a']));
pu('Duplicate selections fail', !ProgramAssessments::correct(['type' => 'multiple', 'answer' => ['a', 'b']], ['a', 'a']));
pu('Associations use exact keys', !ProgramAssessments::correct(['type' => 'uri', 'answer' => ['gtin' => '09520000000011']], ['GTIN' => '09520000000011']));
$bad = oa_program_test_bank(); $bad['questions'][1]['key'] = $bad['questions'][0]['key'];
pur('Duplicate bank question keys rejected', fn() => ProgramAssessments::validate($bad), 422);
Config::load(['environment' => 'production']);
pur('Synthetic bank cannot enter a hosted environment', fn() => ProgramAssessments::validate(oa_program_test_bank()), 422);
Config::load(['environment' => 'test', 'url' => 'http://localhost:8088']);
$course = ProgramLearning::manifest(json_decode(file_get_contents(ROOT . '/contracts/program/learning-content.json'), true));
pu('Initial curriculum has 16 modules and 32 lessons', count($course['modules']) === 16 && array_sum(array_map(static fn(array $m): int => count($m['lessons']), $course['modules'])) === 32);
foreach (oa_program_test_labs() as $scenario => $payload) { pu('Virtual scenario passes its contract ' . $scenario, !array_filter(ProgramLabs::evaluate($scenario, $payload), static fn(array $c): bool => !$c['passed'])); }
$bad = oa_program_test_labs()['P02']; $bad['open_badge'] = true;
pu('A badge alone cannot slip through P02', count(array_filter(ProgramLabs::evaluate('P02', $bad), static fn(array $c): bool => !$c['passed'])) > 0);
pu('Markdown cannot execute HTML', !str_contains(ProgramHtml::markdown('<script>alert(1)</script>'), '<script>'));
pu('Markdown cannot create javascript links', !str_contains(ProgramHtml::markdown('[x](javascript:alert(1))'), 'href='));
echo json_encode(['passed' => $passed, 'failed' => 0]) . PHP_EOL;
