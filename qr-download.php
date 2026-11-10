<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$ref = trim((string) ($_GET['ref'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$booking = $ref !== '' ? booking_find_by_reference($ref) : null;

if (
    !$booking
    || $token === ''
    || !hash_equals((string) $booking['access_token'], $token)
    || ($booking['status'] ?? '') !== 'paid'
    || empty($booking['ticket_code'])
) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$url = ticket_qr_url($booking);
$src = qr_image_src($url, 400);
$png = @file_get_contents($src);
if ($png === false) {
    http_response_code(502);
    echo 'Unable to generate QR image right now.';
    exit;
}

$filename = 'ticket-' . preg_replace('/[^A-Za-z0-9\-]/', '', $booking['reference']) . '.png';
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($png));
echo $png;
exit;
