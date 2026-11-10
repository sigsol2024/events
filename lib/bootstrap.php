<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    echo 'Missing config/config.php. Copy config/config.example.php and fill in your settings.';
    exit;
}

/** @var array $CONFIG */
$CONFIG = require $configPath;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/tickets.php';
require_once __DIR__ . '/bookings.php';
require_once __DIR__ . '/bachs.php';
require_once __DIR__ . '/qr.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/settings.php';

date_default_timezone_set('Africa/Lagos');
