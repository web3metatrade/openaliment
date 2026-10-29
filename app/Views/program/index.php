<?php
declare(strict_types=1);
use App\Core\{Db, Session};
use App\Domain\{ProgramHtml, ProgramLearning};

$form = static function (string $action, string $id = '', array $hidden = [], array $parameters = []): void {
    $route = null;
    foreach (\App\Core\ProgramRoutes::all() as $r) { if ($r['handler'] === 'program.' . $action) { $route = $r; break; } }
    if (!$route) { throw new LogicException('Unknown program form'); }
    $path = str_replace('{id}', $id, $route['path']);
    foreach ($parameters as $key => $value) { $path = str_replace('{' . $key . '}', $value, $path); }
    echo '<form method="post" class="form-stack program-form">' . csrf() . '<input type="hidden" name="_method" value="' . e($route['method']) . '"><input type="hidden" name="_endpoint" value="' . e($path) . '"><input type="hidden" name="_key" value="' . Db::id() . '">';
    foreach ($hidden as $key => $value) { echo '<input type="hidden" name="' . e($key) . '" value="' . e((string) $value) . '">'; }
};
$field = static function (string $key, string $label, mixed $value = '', string $type = 'text', bool $required = true) use ($values): void {
    $v = $type === 'password' ? '' : ($values[$key] ?? $value);
    echo '<label>' . e($label) . '<input class="form-control" name="' . e($key) . '" type="' . e($type) . '" value="' . e(is_scalar($v) ? (string) $v : '') . '"' . ($required ? ' required' : '') . ($type === 'password' ? ' minlength="12" maxlength="200" autocomplete="new-password"' : ' maxlength="2000"') . '></label>';
};
$area = static function (string $key, string $label, mixed $value = '', int $rows = 4) use ($values): void {
    static $count = 0; $controlId = 'program-text-' . ++$count;
    $v = $values[$key] ?? $value;
    if (is_array($v)) { $v = json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
    echo '<div class="program-control"><label for="' . $controlId . '">' . e($label) . '</label><textarea id="' . $controlId . '" class="form-control" name="' . e($key) . '" rows="' . $rows . '" maxlength="1200000">' . e((string) $v) . '</textarea></div>';
};
$check = static function (string $key, string $label, bool $checked = false, bool $required = true): void {
    echo '<input type="hidden" name="' . e($key) . '" value="0"><label class="check-label"><input type="checkbox" name="' . e($key) . '" value="1"' . ($checked ? ' checked' : '') . ($required ? ' required' : '') . '> ' . e($label) . '</label>';
};
$submit = static function (string $label, bool $secondary = false): void { echo '<button class="btn btn-' . ($secondary ? 'outline-primary' : 'primary') . '" type="submit">' . e($label) . '</button></form>'; };
$page = static function (array $collection): void {
    if ($collection['has_more'] ?? false) {
        $filters = array_intersect_key($_GET, array_flip(['q', 'category', 'specialization', 'region', 'limit']));
        echo '<a class="btn btn-outline-primary" href="?' . e(http_build_query($filters + ['cursor' => $collection['next_cursor']], '', '&', PHP_QUERY_RFC3986)) . '">Mai multe</a>';
    }
};
?>
<div class="container page-space program-page">
<nav class="program-nav" aria-label="Comunitatea OpenAliment"><a href="/contribuitori">Contribuitori</a><a href="/parteneri">Parteneri autorizați</a><a href="/invatare">Cursuri</a><?php if ($programUser): ?><a href="/invatare/cont">Învățarea mea</a><a href="/parteneri/cont">Firma mea</a><a href="/manage/security">Securitatea contului</a><form method="post" action="/logout"><?=csrf()?><button class="btn btn-sm btn-outline-primary" type="submit">Ieși din cont</button></form><?php else: ?><a href="/login">Intră în cont</a><?php endif ?></nav>
<div class="program-heading"><div class="eyebrow">COMUNITATEA OPENALIMENT</div><h1 class="page-title"><?=e($title)?></h1></div>
<?php if ($programError): ?><div class="notice" role="alert"><?=e($programError)?></div><?php endif ?>
<?php
$templates = ['contributors' => 'community', 'contributor-public' => 'community', 'contributor-own' => 'community',
    'partners' => 'partners', 'partner-public' => 'partners', 'partner-hub' => 'partners', 'partner-register' => 'partners', 'partner-application' => 'partners', 'authorization' => 'partners',
    'courses' => 'learning', 'course' => 'learning', 'lesson' => 'learning', 'learning-hub' => 'learning', 'enrollment' => 'learning', 'registration' => 'learning', 'credential' => 'learning',
    'exam' => 'assessment', 'result' => 'assessment', 'lab' => 'assessment', 'confirm' => 'confirm', 'admin' => 'admin', 'applications' => 'applications'];
require ROOT . '/app/Views/program/' . $templates[$screen] . '.php';
?>
<p class="program-footnote">Program de formare și autorizare OpenAliment. Autorizațiile descriu competențele evaluate în cadrul acestui program; nu reprezintă acreditări GS1 sau certificări de siguranță alimentară.</p>
</div>
<?php if (in_array($screen, ['credential', 'authorization'], true)): ?><script src="/assets/vendor/qrcodegen.js" defer></script><script src="/assets/qr.js?v=2" defer></script><?php endif ?><script src="/assets/program.js?v=1" defer></script>
