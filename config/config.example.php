<?php
declare(strict_types=1);
// Copy to config/local.php, outside public/. Never publish or commit that file.
return [
    'environment' => 'production',
    'url' => 'https://openaliment.ro',
    'timezone' => 'Europe/Bucharest',
    'db' => ['dsn' => 'mysql:host=localhost;port=3306;dbname=;charset=utf8mb4', 'user' => '', 'password' => ''],
    'storage' => dirname(__DIR__) . '/storage',
    'session_secure' => true,
    'registration_enabled' => false,
    'home_sync_enabled' => false,
    // Enable for administrators only after each administrator has enrolled a verified passkey.
    'mfa_required_for_admin' => false,
    // Configure a private ClamAV daemon if available; never publish a document as clean by default.
    'document_scanner' => ['transport' => 'disabled', 'endpoint' => 'unix:///run/clamav/clamd.ctl'],
    // Exact origins for a trusted ERP web frontend. OAuth web origins come from registered callbacks.
    'api_allowed_origins' => [],
    // Configure only the actual reverse proxies you control; otherwise X-Forwarded-For is ignored.
    'trusted_proxy_cidrs' => [],
    'api_limits' => ['public'=>240, 'resolver'=>600, 'catalog'=>60, 'tools'=>30, 'docs'=>120, 'private-read'=>240, 'private-write'=>120, 'auth'=>30, 'download'=>30],
    // Global/client budgets complement per-IP limits. Raise only after capacity review.
    'security_limits' => ['login_emails_hour'=>300, 'registrations_hour'=>100, 'oauth_requests_client_hour'=>1000],
    // hash('sha256', random 32-byte setup token). Remove after installation.
    'setup_token_hash' => '',
    'gs1_transport_verified' => false,
    'gs1_lot_pattern' => '/\A[A-Za-z0-9_-]{1,20}\z/D',
    'feedback_enabled' => false,
    'operator_name' => '',
    'operator_contact' => '',
    'privacy_text' => '',
    'ai' => ['enabled' => false, 'key' => '', 'model' => '', 'daily_jobs' => 30],
    'handover_path' => dirname(__DIR__) . '/OpenBread_Codex_v1_2026-10-23',
];
