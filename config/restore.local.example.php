<?php
declare(strict_types=1);
// Copy to restore.local.php outside public. Only an empty database and an empty directory are accepted.
return [
    'confirm_empty_isolated_destination' => false,
    'archive' => '/home/apelero/openaliment-backups/ARCHIVE.zip',
    'key_file' => '/home/apelero/openaliment-backup.key',
    'destination' => '/home/apelero/openaliment-restore',
    'latest_erasure_ledger' => '/home/apelero/openaliment-erasure-latest.ndjson',
    'db' => ['dsn' => 'mysql:host=localhost;port=3306;dbname=EMPTY_RESTORE_DATABASE;charset=utf8mb4', 'user' => '', 'password' => ''],
];
