<?php

declare(strict_types=1);

/**
 * Hit this on a schedule (every 5–15 minutes), e.g.:
 *   curl "https://YOUR_DOMAIN/api/cron/booking-maintenance.php?key=YOUR_SETUP_KEY"
 *
 * - Expires stale unpaid checkouts
 * - Emails buyers who started payment but didn’t finish
 */

require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

$key = (string) ($_GET['key'] ?? $_POST['key'] ?? '');
$configured = (string) config('setup_key', '');
if ($configured === '' || $key === '' || !hash_equals($configured, $key)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$expired = bookings_expire_stale(200);
$reminded = 0;
$failed = 0;
$minutes = max(5, (int) config('bachs.incomplete_reminder_minutes', 20));

foreach (bookings_needing_incomplete_reminder($minutes, 40) as $booking) {
    $booking = booking_expire_if_stale($booking);
    if (!in_array(($booking['status'] ?? ''), ['pending', 'processing'], true)) {
        continue;
    }
    try {
        if (send_incomplete_booking_email($booking, 'reminder')) {
            $reminded++;
        }
    } catch (Throwable) {
        $failed++;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'expired' => $expired,
    'reminded' => $reminded,
    'failed' => $failed,
]);
