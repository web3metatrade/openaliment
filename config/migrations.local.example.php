<?php
declare(strict_types=1);
// Copy to migrations.local.php outside public. This account may alter ONLY the same application database.
// The runtime account in local.php can then use SELECT, INSERT, UPDATE, DELETE without CREATE/ALTER/DROP.
return [
    'dsn' => 'mysql:host=localhost;port=3306;dbname=YOUR_DATABASE;charset=utf8mb4',
    'user' => 'YOUR_MIGRATION_USER',
    'password' => '',
];
