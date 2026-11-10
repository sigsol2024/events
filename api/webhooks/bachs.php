<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

$raw = file_get_contents('php://input') ?: '';
$sigV2 = (string) ($_SERVER['HTTP_X_BACHS_SIGNATURE_V2'] ?? '');
$legacyTs = (string) ($_SERVER['HTTP_X_BACHS_TIMESTAMP'] ?? '');
$legacySig = (string) ($_SERVER['HTTP_X_BACHS_SIGNATURE'] ?? '');

if (!bachs_verify_webhook_signature($raw, $sigV2, $legacyTs, $legacySig)) {
    http_response_code(401);
    echo 'invalid signature';
    exit;
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo 'invalid payload';
    exit;
}

$eventId = (string) ($payload['id'] ?? '');
$type = (string) ($payload['type'] ?? '');
$data = (array) ($payload['data'] ?? []);

if ($eventId === '') {
    http_response_code(400);
    echo 'missing event id';
    exit;
}

// Claim event.id first (unique) so concurrent redeliveries are ignored.
// On processing failure we release the claim and return 5xx so Bachs retries.
if (!webhook_event_claim($eventId, $type, $raw)) {
    http_response_code(200);
    echo 'ok';
    exit;
}

$checkoutId = (string) ($data['checkout_id'] ?? '');
$reference = (string) ($data['reference'] ?? ($data['metadata']['reference'] ?? ''));
[$amount, $currency] = bachs_extract_amount_currency($data);

$booking = null;
if ($checkoutId !== '') {
    $booking = booking_find_by_checkout($checkoutId);
}
if (!$booking && $reference !== '') {
    $booking = booking_find_by_reference($reference);
}

if (!$booking) {
    // Keep claim so unknown events are not thrashed; ops can inspect payload.
    http_response_code(200);
    echo 'ignored';
    exit;
}

// Cross-check identifiers when both sides have them.
if (!empty($booking['bachs_checkout_id']) && $checkoutId !== ''
    && (string) $booking['bachs_checkout_id'] !== $checkoutId) {
    webhook_event_release($eventId);
    http_response_code(409);
    echo 'checkout mismatch';
    exit;
}
if ($reference !== '' && !empty($booking['reference'])
    && !hash_equals((string) $booking['reference'], $reference)) {
    webhook_event_release($eventId);
    http_response_code(409);
    echo 'reference mismatch';
    exit;
}

try {
    if (bachs_webhook_indicates_paid($type, $data)) {
        // Persist checkout id early if webhook arrived before book.php finished updating.
        if ($checkoutId !== '' && empty($booking['bachs_checkout_id'])) {
            booking_update_checkout((int) $booking['id'], $checkoutId, (string) $booking['reference']);
            $booking = booking_find_by_id((int) $booking['id']) ?: $booking;
        }

        fulfill_paid_booking(
            $booking,
            $checkoutId !== '' ? $checkoutId : null,
            $amount,
            $currency
        );
    } elseif ($type === 'checkout.expired') {
        // Only expire when checkout itself lapses. collection.failed keeps checkout open for retry.
        if (!in_array($booking['status'], ['paid', 'refunded'], true)) {
            booking_set_status((int) $booking['id'], 'expired');
        }
    } elseif ($type === 'refund.paid') {
        if (($booking['status'] ?? '') === 'paid') {
            booking_set_status((int) $booking['id'], 'refunded');
        }
    } elseif ($type === 'collection.failed' || $type === 'collection.underpaid') {
        // Leave booking pending/processing — customer can retry on the same checkout.
    } elseif ($type === 'checkout.completed') {
        // Completed but not paid (e.g. no_payment_required) — do not issue tickets.
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    // Amount/currency mismatches are data integrity issues — keep claim, do not retry forever.
    // Transient API/DB failures release the claim and return 5xx for Bachs retry.
    $permanent = str_contains($message, 'does not match')
        || str_contains($message, 'Cannot verify');

    if ($permanent) {
        http_response_code(200);
        echo 'rejected';
        exit;
    }

    webhook_event_release($eventId);
    http_response_code(500);
    echo 'processing error';
    exit;
}

http_response_code(200);
echo 'ok';
