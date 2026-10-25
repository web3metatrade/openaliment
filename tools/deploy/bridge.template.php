<?php
declare(strict_types=1);

// Generated maintenance endpoint. Keep the matching client key outside public/.
const OA_DEPLOY_KEY_HASH = '__OA_KEY_HASH__';
const OA_DEPLOY_EXPIRES = __OA_EXPIRES__;
const OA_DEPLOY_ID = '__OA_ID__';
const OA_DEPLOY_HOST = 'openaliment.ro';
const OA_DEPLOY_MAX_BODY = 12582912;
const OA_DEPLOY_MAX_BYTES = 8388608;
const OA_DEPLOY_MAX_FILE = 2097152;

ini_set('display_errors', '0');
header_remove('X-Powered-By');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

final class OaDeployFailure extends RuntimeException {
    public function __construct(public readonly int $status, public readonly string $reason) {
        parent::__construct($reason);
    }
}

function oa_fail(int $status, string $reason): never { throw new OaDeployFailure($status, $reason); }
function oa_result(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
function oa_json(string $path): array {
    $raw = @file_get_contents($path);
    if ($raw === false) oa_fail(404, 'deployment_not_found');
    $value = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($value)) oa_fail(409, 'invalid_journal');
    return $value;
}
function oa_write(string $path, string $data, int $mode = 0600): void {
    $temporary = dirname($path) . '/.oa-' . bin2hex(random_bytes(16)) . '.tmp';
    $handle = @fopen($temporary, 'xb');
    if (!$handle) oa_fail(503, 'write_unavailable');
    try {
        if (!@chmod($temporary, $mode)) oa_fail(503, 'permissions_unavailable');
        $offset = 0;
        while ($offset < strlen($data)) {
            $written = fwrite($handle, substr($data, $offset));
            if ($written === false || $written === 0) oa_fail(503, 'write_failed');
            $offset += $written;
        }
        if (!fflush($handle)) oa_fail(503, 'write_failed');
        if (function_exists('fsync') && !fsync($handle)) oa_fail(503, 'write_failed');
    } finally { fclose($handle); }
    if (!@rename($temporary, $path)) {
        @unlink($temporary);
        oa_fail(503, 'atomic_replace_failed');
    }
}
function oa_save(string $directory, array $journal): void {
    oa_write($directory . '/journal.json', json_encode($journal, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}
function oa_path(string $relative): string {
    if (strlen($relative) > 220 || !preg_match('~\A[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)+\z~D', $relative)) oa_fail(422, 'path_not_allowed');
    foreach (explode('/', $relative) as $part) {
        if ($part === '.' || $part === '..' || (str_starts_with($part, '.') && $relative !== 'public/.htaccess')) oa_fail(422, 'path_not_allowed');
    }
    $allowed = preg_match('~\Aapp/[A-Za-z0-9_./-]+\.php\z~D', $relative)
        || preg_match('~\Apublic/assets/[A-Za-z0-9_./-]+\.(?:css|js|svg|png|jpg|jpeg|webp|woff2)\z~D', $relative)
        || in_array($relative, ['public/index.php', 'public/.htaccess', 'tools/worker.php'], true)
        || preg_match('~\Acontracts/[A-Za-z0-9_./-]+\.(?:json|yaml|yml)\z~D', $relative)
        || preg_match('~\Adatabase/migrations/[A-Za-z0-9_-]+\.sql\z~D', $relative);
    if (!$allowed) oa_fail(422, 'path_not_allowed');
    // lstat every component: neither an existing link nor a linked ancestor is allowed.
    $cursor = OA_DEPLOY_ROOT;
    $parts = explode('/', $relative);
    foreach ($parts as $i => $part) {
        $cursor .= '/' . $part;
        clearstatcache(true, $cursor);
        $stat = @lstat($cursor);
        if ($stat !== false) {
            $type = $stat['mode'] & 0170000;
            if ($type === 0120000) oa_fail(422, 'symlink_not_allowed');
            if ($i < count($parts) - 1 && $type !== 0040000) oa_fail(422, 'invalid_parent');
            if ($i === count($parts) - 1 && $type !== 0100000) oa_fail(422, 'invalid_target');
        }
    }
    return OA_DEPLOY_ROOT . '/' . $relative;
}
function oa_hash(string $path): ?string {
    clearstatcache(true, $path);
    if (!file_exists($path)) return null;
    $digest = @hash_file('sha256', $path);
    if ($digest === false) oa_fail(503, 'file_not_readable');
    return $digest;
}
function oa_id(mixed $id): string {
    if (!is_string($id) || !preg_match('/\A[a-f0-9]{32}\z/D', $id)) oa_fail(422, 'invalid_deployment_id');
    return $id;
}
function oa_digest(mixed $value, bool $nullable = false): ?string {
    if ($nullable && $value === null) return null;
    if (!is_string($value) || !preg_match('/\A[a-f0-9]{64}\z/D', $value)) oa_fail(422, 'invalid_digest');
    return $value;
}
function oa_audit(string $action, string $id, array $paths = []): void {
    $entry = json_encode(['at' => gmdate(DATE_ATOM), 'action' => $action, 'deployment_id' => $id, 'paths' => $paths], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (@file_put_contents(OA_DEPLOY_STORE . '/audit.ndjson', $entry . "\n", FILE_APPEND | LOCK_EX) === false) oa_fail(503, 'audit_unavailable');
    @chmod(OA_DEPLOY_STORE . '/audit.ndjson', 0600);
}
function oa_check_content(string $path, string $data): void {
    if (str_ends_with($path, '.php')) {
        if (!function_exists('token_get_all')) oa_fail(503, 'tokenizer_required');
        if (!str_starts_with($data, '<?php')) oa_fail(422, 'php_opening_tag_required');
        try { token_get_all($data, TOKEN_PARSE); } catch (ParseError) { oa_fail(422, 'php_syntax_invalid'); }
    }
}
function oa_parent(string $path): void {
    $directory = dirname($path);
    if (!is_dir($directory) && !@mkdir($directory, 0755, true)) oa_fail(503, 'parent_not_writable');
}
function oa_restore(string $directory, array &$journal): void {
    // Check ALL paths and backups before changing any path.
    foreach ($journal['files'] as $i => $file) {
        $current = oa_hash(oa_path($file['path']));
        if ($current !== $file['sha256'] && $current !== $file['before_sha256']) oa_fail(409, 'rollback_conflict');
        if ($file['before_sha256'] !== null && oa_hash($directory . '/before-' . $i . '.bin') !== $file['before_sha256']) oa_fail(409, 'backup_integrity_failed');
    }
    $journal['state'] = 'restoring'; oa_save($directory, $journal);
    foreach (array_reverse($journal['files'], true) as $i => $file) {
        $path = oa_path($file['path']);
        if (oa_hash($path) === $file['before_sha256']) continue;
        if ($file['before_sha256'] === null) {
            if (!@unlink($path)) oa_fail(503, 'restore_failed');
        } else {
            $data = @file_get_contents($directory . '/before-' . $i . '.bin');
            if ($data === false) oa_fail(503, 'backup_not_readable');
            oa_write($path, $data, $file['before_mode']);
        }
        if (str_ends_with($path, '.php') && function_exists('opcache_invalidate')) @opcache_invalidate($path, true);
    }
    $journal['state'] = 'rolled_back'; $journal['rolled_back_at'] = gmdate(DATE_ATOM); oa_save($directory, $journal);
    oa_audit('rolled_back', $journal['id'], array_column($journal['files'], 'path'));
}

try {
    $https = in_array(strtolower((string)($_SERVER['HTTPS'] ?? '')), ['on', '1'], true);
    $key = (string)($_SERVER['HTTP_X_OPENALIMENT_KEY'] ?? '');
    if (!$https || strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== OA_DEPLOY_HOST
        || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || time() >= OA_DEPLOY_EXPIRES
        || !preg_match('/\A[a-f0-9]{64}\z/D', $key) || !hash_equals(OA_DEPLOY_KEY_HASH, hash('sha256', $key))) {
        http_response_code(404); exit;
    }
    unset($key);
    if (PHP_VERSION_ID < 80400) oa_fail(503, 'php_84_required');
    if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== 'https://' . OA_DEPLOY_HOST) oa_fail(403, 'origin_not_allowed');
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') oa_fail(415, 'json_required');
    if (isset($_SERVER['HTTP_CONTENT_ENCODING']) || (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > OA_DEPLOY_MAX_BODY) oa_fail(413, 'request_too_large');
    $raw = file_get_contents('php://input', false, null, 0, OA_DEPLOY_MAX_BODY + 1);
    if ($raw === false || strlen($raw) > OA_DEPLOY_MAX_BODY) oa_fail(413, 'request_too_large');
    try { $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); } catch (JsonException) { oa_fail(400, 'invalid_json'); }
    if (!is_array($input) || !str_starts_with(ltrim($raw), '{')) oa_fail(400, 'json_object_required');
    $root = realpath(__DIR__ . '/..');
    if (!$root || !is_dir($root . '/app') || !is_file($root . '/config/local.php') || !is_dir($root . '/storage') || is_link($root . '/storage')) oa_fail(503, 'project_layout_invalid');
    define('OA_DEPLOY_ROOT', str_replace('\\', '/', $root));
    $store = OA_DEPLOY_ROOT . '/storage/maintenance';
    if (is_link($store)) oa_fail(503, 'private_storage_invalid');
    if (!is_dir($store) && !@mkdir($store, 0700)) oa_fail(503, 'private_storage_unavailable');
    define('OA_DEPLOY_STORE', $store . '/' . OA_DEPLOY_ID);
    if (is_link(OA_DEPLOY_STORE)) oa_fail(503, 'private_storage_invalid');
    if (!is_dir(OA_DEPLOY_STORE) && !@mkdir(OA_DEPLOY_STORE, 0700)) oa_fail(503, 'private_storage_unavailable');
    if (is_file(OA_DEPLOY_STORE . '/disabled.lock')) { http_response_code(404); exit; }
    $lock = @fopen(OA_DEPLOY_STORE . '/operation.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) oa_fail(409, 'maintenance_busy');
    @chmod(OA_DEPLOY_STORE . '/operation.lock', 0600);
    $action = $input['action'] ?? '';
    if ($action === 'status') oa_result(['protocol' => 'openaliment.deploy.v1', 'php' => PHP_VERSION, 'expires_at' => gmdate(DATE_ATOM, OA_DEPLOY_EXPIRES), 'capabilities' => ['inspect', 'read', 'stage', 'commit', 'rollback', 'deployment', 'disable'], 'tokenizer' => function_exists('token_get_all'), 'app_writable' => is_writable(OA_DEPLOY_ROOT . '/app'), 'public_writable' => is_writable(OA_DEPLOY_ROOT . '/public'), 'max_file_bytes' => OA_DEPLOY_MAX_FILE, 'max_deployment_bytes' => OA_DEPLOY_MAX_BYTES, 'database_execution' => false]);
    if ($action === 'inspect' || $action === 'read') {
        $paths = $input['paths'] ?? null;
        if (!is_array($paths) || !array_is_list($paths) || count($paths) < 1 || count($paths) > ($action === 'read' ? 5 : 100)) oa_fail(422, 'invalid_paths');
        $files = []; $bytes = 0;
        foreach ($paths as $relative) {
            if (!is_string($relative)) oa_fail(422, 'path_not_allowed');
            $path = oa_path($relative); $hash = oa_hash($path); $size = $hash === null ? 0 : filesize($path);
            $entry = ['path' => $relative, 'sha256' => $hash, 'bytes' => $size];
            if ($action === 'read' && $hash !== null) {
                $bytes += $size;
                if ($size > OA_DEPLOY_MAX_FILE || $bytes > OA_DEPLOY_MAX_BYTES) oa_fail(413, 'read_too_large');
                $data = @file_get_contents($path);
                if ($data === false || hash('sha256', $data) !== $hash) oa_fail(409, 'read_conflict');
                $entry['content_b64'] = base64_encode($data);
            }
            $files[] = $entry;
        }
        oa_result(['files' => $files]);
    }
    if ($action === 'disable') {
        oa_audit('disabled', OA_DEPLOY_ID); oa_write(OA_DEPLOY_STORE . '/disabled.lock', gmdate(DATE_ATOM)); oa_result(['disabled' => true]);
    }
    if (!in_array($action, ['stage', 'commit', 'rollback', 'deployment'], true)) oa_fail(422, 'unknown_action');
    $id = oa_id($input['deployment_id'] ?? null); $directory = OA_DEPLOY_STORE . '/' . $id;
    if (is_link($directory)) oa_fail(503, 'private_storage_invalid');
    if ($action === 'stage') {
        if (file_exists($directory)) oa_fail(409, 'deployment_already_exists');
        $files = $input['files'] ?? null;
        if (!is_array($files) || !array_is_list($files) || count($files) < 1 || count($files) > 32) oa_fail(422, 'invalid_files');
        $validated = []; $seen = []; $total = 0;
        foreach ($files as $file) {
            if (!is_array($file) || !is_string($file['path'] ?? null) || !is_string($file['content_b64'] ?? null) || !array_key_exists('before_sha256', $file)) oa_fail(422, 'invalid_file');
            $path = oa_path($file['path']);
            if (isset($seen[strtolower($file['path'])])) oa_fail(422, 'duplicate_path');
            $seen[strtolower($file['path'])] = true;
            $before = oa_digest($file['before_sha256'], true); $hash = oa_digest($file['sha256'] ?? null);
            $data = base64_decode($file['content_b64'], true);
            if ($data === false || hash('sha256', $data) !== $hash) oa_fail(422, 'content_digest_mismatch');
            $total += strlen($data);
            if (strlen($data) > OA_DEPLOY_MAX_FILE || $total > OA_DEPLOY_MAX_BYTES) oa_fail(413, 'deployment_too_large');
            if (oa_hash($path) !== $before) oa_fail(409, 'file_conflict');
            oa_check_content($file['path'], $data);
            $validated[] = ['path' => $file['path'], 'sha256' => $hash, 'before_sha256' => $before, 'data' => $data];
        }
        $label = $input['label'] ?? '';
        if (!is_string($label) || strlen($label) > 160 || preg_match('/[\x00-\x1F]/', $label)) oa_fail(422, 'invalid_label');
        if (!@mkdir($directory, 0700)) oa_fail(503, 'private_storage_unavailable');
        $journal = ['id' => $id, 'label' => $label, 'state' => 'staged', 'created_at' => gmdate(DATE_ATOM), 'files' => []];
        foreach ($validated as $i => $file) {
            oa_write($directory . '/after-' . $i . '.bin', $file['data']); unset($file['data']); $journal['files'][] = $file;
        }
        oa_save($directory, $journal); oa_audit('staged', $id, array_column($journal['files'], 'path')); oa_result($journal, 201);
    }
    $journal = oa_json($directory . '/journal.json');
    if ($action === 'deployment') oa_result($journal);
    if ($action === 'rollback') {
        if ($journal['state'] === 'rolled_back') oa_result($journal);
        if (!in_array($journal['state'], ['applied', 'applying', 'restoring', 'recovery_required'], true)) oa_fail(409, 'deployment_not_applied');
        oa_restore($directory, $journal); oa_result($journal);
    }
    if ($journal['state'] === 'applied') {
        foreach ($journal['files'] as $file) if (oa_hash(oa_path($file['path'])) !== $file['sha256']) oa_fail(409, 'file_conflict');
        oa_result($journal);
    }
    if ($journal['state'] !== 'staged') oa_fail(409, 'deployment_not_staged');
    foreach ($journal['files'] as $i => &$file) {
        $path = oa_path($file['path']);
        if (oa_hash($path) !== $file['before_sha256']) oa_fail(409, 'file_conflict');
        if (oa_hash($directory . '/after-' . $i . '.bin') !== $file['sha256']) oa_fail(409, 'staging_integrity_failed');
        $file['before_mode'] = file_exists($path) ? (fileperms($path) & 0777) : 0644;
        if ($file['before_sha256'] !== null) {
            $data = @file_get_contents($path);
            if ($data === false || hash('sha256', $data) !== $file['before_sha256']) oa_fail(409, 'file_conflict');
            oa_write($directory . '/before-' . $i . '.bin', $data);
        }
    }
    unset($file);
    $journal['state'] = 'applying'; oa_save($directory, $journal); oa_audit('applying', $id, array_column($journal['files'], 'path'));
    try {
        foreach ($journal['files'] as $i => $file) {
            $path = oa_path($file['path']); oa_parent($path); oa_path($file['path']);
            // Recheck immediately before writing, including changes outside this bridge.
            if (oa_hash($path) !== $file['before_sha256']) oa_fail(409, 'file_conflict');
            $data = @file_get_contents($directory . '/after-' . $i . '.bin');
            if ($data === false || hash('sha256', $data) !== $file['sha256']) oa_fail(409, 'staging_integrity_failed');
            oa_write($path, $data, $file['before_mode']);
            if (oa_hash($path) !== $file['sha256']) oa_fail(503, 'write_verification_failed');
            if (str_ends_with($path, '.php') && function_exists('opcache_invalidate')) @opcache_invalidate($path, true);
        }
        $journal['state'] = 'applied'; $journal['applied_at'] = gmdate(DATE_ATOM); oa_save($directory, $journal);
        oa_audit('applied', $id, array_column($journal['files'], 'path'));
    } catch (Throwable $error) {
        try { oa_restore($directory, $journal); } catch (Throwable) { $journal['state'] = 'recovery_required'; oa_save($directory, $journal); }
        throw $error;
    }
    oa_result($journal);
} catch (OaDeployFailure $error) {
    oa_result(['error' => $error->reason], $error->status);
} catch (Throwable) {
    oa_result(['error' => 'maintenance_unavailable'], 503);
}
