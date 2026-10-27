<?php
declare(strict_types=1);

// Optional private config/external.local.php. Administrator activation is separate.
// dev and production share an outbound IP: keep their allocations within one budget.
return [
    'network_enabled' => true,
    'source_environment' => 'staging', // OFF test API on dev; choose production before installing the production catalog.
    'product_reads_per_minute' => 2, // staging; production allocation: 8
    'max_products' => 10000,
    'max_database_bytes' => 209715200,
    'max_storage_bytes' => 524288000,
    'max_active_jobs' => 500,
    'fresh_hours' => 24,
    'stale_days' => 7,
    'sensitive_days' => 30,
    'contact' => 'alex@patrut.com',
    // Remote writes are disabled. Producer packages require explicit sharing grants.
    'remote_write_enabled' => false,
];
