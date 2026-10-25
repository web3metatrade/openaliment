<?php
declare(strict_types=1);
// Copy to backup.local.php outside public. Create a random key file outside public and the backup directory.
// Keep a separate offline copy of that key: losing it makes the backups unreadable.
return [
    'directory' => '/home/apelero/openaliment-backups',
    'key_file' => '/home/apelero/openaliment-backup.key',
];
