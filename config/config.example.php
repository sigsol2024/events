<?php
/**
 * Copy this file to config.php on the server and fill in real values.
 * config.php is gitignored — never commit live secrets.
 */
return [
    'app_name' => "Tee's Beach House Edition",
    'app_url' => 'https://example.com', // no trailing slash

    // Fallback only — CSS/JS normally bust cache via filemtime in asset_url().
    'asset_version' => '1',

    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'events_booking',
        'user' => 'db_user',
        'pass' => 'db_password',
        'charset' => 'utf8mb4',
    ],

    'bachs' => [
        'mode' => 'sandbox', // sandbox | live
        'secret_key' => 'sk_sandbox_xxxxxxxx',
        'webhook_secret' => 'whsec_xxxxxxxx',
        // Optional: restrict corridors. Leave empty array to use account defaults.
        'payment_method_types' => ['NGN_CARD', 'NGN_BANK_TRANSFER'],
        'checkout_expires_minutes' => 60,
    ],

    'mail' => [
        'from_email' => 'noreply@example.com',
        'from_name' => "Tee's Beach House",
        'admin_notify_email' => 'admin@example.com',
        // Optional SMTP (leave host empty to use PHP mail())
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_encryption' => 'tls', // tls | ssl | ''
    ],

    // Required by setup-admin.php (create/reset admin). Change this.
    'setup_key' => 'change-me-to-a-long-random-string',

    // Signed thank-you / access tokens
    'app_secret' => 'change-me-to-another-long-random-string',

    'currency' => 'NGN',
    'min_age' => 18,
];
