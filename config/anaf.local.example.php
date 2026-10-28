<?php
declare(strict_types=1);

// Optional private config/anaf.local.php, outside public/. No API credential is required.
return [
    'network_enabled' => true,
    'cache_seconds' => 21600,
    'not_found_seconds' => 300,
    'check_seconds' => 1200,
    'session_limit' => 6,
    'ip_limit' => 20,
    'hourly_network_limit' => 300,
];
