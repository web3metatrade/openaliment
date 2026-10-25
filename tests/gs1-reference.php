<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/gs1-curl.php';
use App\Core\{Config, Problem};
use App\Domain\{Gs1Standards, Gs1TestTransport};
$checks = [];
function gsCheck(string $name, bool $ok): void {global $checks;if (!$ok) {throw new RuntimeException($name);} $checks[]=['name'=>$name,'passed'=>true];echo 'PASS '.$name.PHP_EOL;}
function gsReject(string $name, callable $run): void {try {$run();} catch (Problem $e) {gsCheck($name,$e->status===503);return;}throw new RuntimeException('Missing rejection: '.$name);}
$target = Config::get('storage') . '/private/standards/gs1/uri-syntax-1.7.0.pdf';
$official = file_get_contents($target);
$record = Gs1Standards::catalog()['documents'][0];
if (!hash_equals($record['sha256'], hash('sha256', $official))) {throw new RuntimeException('Verified official test cache is required.');}
try {
    file_put_contents($target, 'previous private reference');
    $damaged = $official; $damaged[500] = $damaged[500] === 'A' ? 'B' : 'A';
    Gs1TestTransport::$bytes = $damaged;
    gsReject('A changed PDF cannot pass a pinned official hash', static fn() => Gs1Standards::preserve('uri-syntax'));
    gsCheck('Failed integrity leaves prior reference untouched', file_get_contents($target) === 'previous private reference');
    Gs1TestTransport::$bytes = $official; Gs1TestTransport::$status = 302;
    gsReject('Official source redirects are refused', static fn() => Gs1Standards::preserve('uri-syntax'));
    Gs1TestTransport::$status = 200; Gs1TestTransport::$bytes = $official . 'oversized';
    gsReject('Oversized response is bounded during transfer', static fn() => Gs1Standards::preserve('uri-syntax'));
    Gs1TestTransport::$bytes = $official;
    gsCheck('Exact official PDF is preserved without mutation', Gs1Standards::preserve('uri-syntax')['verified'] && hash_file('sha256', $target) === $record['sha256']);
    gsCheck('Transport requires HTTPS TLS validation and forbids redirects', Gs1TestTransport::$options[CURLOPT_SSL_VERIFYPEER] === true && Gs1TestTransport::$options[CURLOPT_SSL_VERIFYHOST] === 2 && Gs1TestTransport::$options[CURLOPT_FOLLOWLOCATION] === false && Gs1TestTransport::$options[CURLOPT_PROTOCOLS] === CURLPROTO_HTTPS);
    $before = Gs1TestTransport::$requests; Gs1Standards::preserve('uri-syntax');
    gsCheck('Verified cache avoids repeated network requests', Gs1TestTransport::$requests === $before);
    gsCheck('Failed transfers leave no temporary files', glob(dirname($target) . '/.gs1-*') === []);
} finally {file_put_contents($target, $official);}
file_put_contents(__DIR__.'/runtime/reports/gs1-reference.json',json_encode(['passed'=>count($checks),'failed'=>0,'checks'=>$checks,'network'=>'process-local transport double; official bytes from pinned cache'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['passed'=>count($checks),'failed'=>0]).PHP_EOL;
