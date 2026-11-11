<?php

declare(strict_types=1);

function bachs_base_url(): string
{
    $mode = (string) config('bachs.mode', 'sandbox');
    return $mode === 'live' ? 'https://api.bachs.io' : 'https://sandbox-api.bachs.io';
}

function bachs_request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array
{
    $url = rtrim(bachs_base_url(), '/') . $path;
    $headers = [
        'Authorization: Bearer ' . config('bachs.secret_key'),
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($idempotencyKey) {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        throw new RuntimeException('Bachs connection failed: ' . $error);
    }

    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid Bachs response (HTTP ' . $status . ').');
    }
    if ($status >= 400) {
        $detail = $decoded['detail'] ?? ($decoded['error_code'] ?? 'Bachs request failed');
        throw new RuntimeException((string) $detail);
    }
    return $decoded;
}

/**
 * Normalize Nigerian / international phones to E.164-ish form for Bachs.
 */
function normalize_phone_e164(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') {
        return $phone;
    }
    if (str_starts_with($digits, '234') && strlen($digits) >= 13) {
        return '+' . $digits;
    }
    if (str_starts_with($digits, '0') && strlen($digits) === 11) {
        return '+234' . substr($digits, 1);
    }
    if (strlen($digits) === 10 && (
        str_starts_with($digits, '7')
        || str_starts_with($digits, '8')
        || str_starts_with($digits, '9')
    )) {
        return '+234' . $digits;
    }
    if ($phone[0] === '+') {
        return '+' . $digits;
    }
    return '+' . $digits;
}

function bachs_create_checkout(array $booking, ?string $idempotencyKey = null): array
{
    $payload = [
        'pricing' => [
            'currency' => strtoupper((string) $booking['currency']),
            'amount' => money_string($booking['amount']),
        ],
        'customer' => [
            'email' => $booking['email'],
            'name' => mb_substr(trim((string) $booking['full_name']), 0, 120),
            'phone_number' => normalize_phone_e164((string) $booking['phone']),
        ],
        'reference' => $booking['reference'],
        'success_url' => app_url('thank-you.php?ref=' . urlencode($booking['reference']) . '&token=' . urlencode($booking['access_token'])),
        'cancel_url' => app_url(
            'resume.php?ref=' . urlencode((string) $booking['reference'])
            . '&token=' . urlencode((string) $booking['access_token'])
            . '&cancelled=1'
        ),
        'metadata' => [
            'booking_id' => (string) $booking['id'],
            'reference' => (string) $booking['reference'],
            'ticket_id' => (string) $booking['ticket_id'],
        ],
        'expires_in_minutes' => max(1, min(1440, (int) config('bachs.checkout_expires_minutes', 60))),
        'customer_creation' => 'always',
    ];

    $methods = config('bachs.payment_method_types', []);
    if (is_array($methods) && $methods) {
        $payload['payment_method_types'] = array_values($methods);
    }

    $key = $idempotencyKey ?: ('checkout-' . $booking['reference']);
    return bachs_request('POST', '/v1/checkout-sessions', $payload, $key);
}

function bachs_get_checkout(string $checkoutId): array
{
    return bachs_request('GET', '/v1/checkout-sessions/' . rawurlencode($checkoutId));
}

/**
 * Prefer X-Bachs-Signature-V2 per docs.bachs.io/guides/webhooks/overview.
 * Accept ANY matching v1= during secret rotation. Reject skew > 300s.
 */
function bachs_verify_webhook_signature(string $rawBody, string $signatureHeader, string $legacyTimestamp = '', string $legacySignature = ''): bool
{
    $secret = (string) config('bachs.webhook_secret', '');
    if ($secret === '') {
        return false;
    }

    $timestamp = null;
    $signatures = [];

    if ($signatureHeader !== '') {
        foreach (explode(',', $signatureHeader) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $timestamp = $v;
            } elseif ($k === 'v1' && $v !== '') {
                $signatures[] = strtolower($v);
            }
        }
    } else {
        // Legacy headers still sent by Bachs; keep as fallback.
        $timestamp = $legacyTimestamp;
        if ($legacySignature !== '') {
            $signatures[] = strtolower($legacySignature);
        }
    }

    if (!is_numeric((string) $timestamp) || !$signatures) {
        return false;
    }
    if (abs(time() - (int) $timestamp) > 300) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }
    return false;
}

/**
 * Only treat a checkout as paid when Bachs payment_status is explicitly "paid".
 * Do NOT use session status "completed" alone (covers free/setup too).
 * Do NOT accept no_payment_required for paid event tickets.
 */
function bachs_session_is_paid(array $session): bool
{
    $paymentStatus = strtolower((string) ($session['payment_status'] ?? ''));
    return $paymentStatus === 'paid';
}

function bachs_extract_amount_currency(array $payload): array
{
    $amount = null;
    $currency = null;

    if (isset($payload['amount']) && $payload['amount'] !== '' && $payload['amount'] !== null) {
        $amount = (string) $payload['amount'];
    } elseif (isset($payload['pricing']['amount'])) {
        $amount = (string) $payload['pricing']['amount'];
    } elseif (isset($payload['charge']['amount'])) {
        $amount = (string) $payload['charge']['amount'];
    } elseif (isset($payload['expected_amount'])) {
        // OVERPAID collections: compare against what was due
        $amount = (string) $payload['expected_amount'];
    }

    if (isset($payload['currency']) && $payload['currency'] !== '' && $payload['currency'] !== null) {
        $currency = (string) $payload['currency'];
    } elseif (isset($payload['pricing']['currency'])) {
        $currency = (string) $payload['pricing']['currency'];
    } elseif (isset($payload['charge']['currency'])) {
        $currency = (string) $payload['charge']['currency'];
    }

    return [$amount, $currency];
}

function webhook_event_seen(string $eventId): bool
{
    $stmt = db()->prepare('SELECT id FROM webhook_events WHERE event_id = ? LIMIT 1');
    $stmt->execute([$eventId]);
    return (bool) $stmt->fetch();
}

/**
 * Claim an event id before processing (unique). Returns false if already claimed.
 * On processing failure, release so Bachs 5xx retries can re-run.
 */
function webhook_event_claim(string $eventId, string $type, string $payload): bool
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO webhook_events (event_id, event_type, payload) VALUES (?, ?, ?)'
        );
        $stmt->execute([$eventId, $type, $payload]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function webhook_event_release(string $eventId): void
{
    db()->prepare('DELETE FROM webhook_events WHERE event_id = ?')->execute([$eventId]);
}

/**
 * Fulfill a paid booking from webhook or thank-you reconciliation.
 * Amount + currency MUST be verified (never skip).
 */
function fulfill_paid_booking(array $booking, ?string $checkoutId, ?string $paidAmount, ?string $paidCurrency): bool
{
    if ($paidAmount === null || $paidAmount === '' || $paidCurrency === null || $paidCurrency === '') {
        if ($checkoutId) {
            $session = bachs_get_checkout($checkoutId);
            if (!bachs_session_is_paid($session)) {
                throw new RuntimeException('Checkout is not paid yet.');
            }
            [$paidAmount, $paidCurrency] = bachs_extract_amount_currency($session);
        }
    }

    if ($paidAmount === null || $paidAmount === '') {
        throw new RuntimeException('Cannot verify paid amount.');
    }
    if ($paidCurrency === null || $paidCurrency === '') {
        throw new RuntimeException('Cannot verify paid currency.');
    }
    if (!amounts_match((string) $booking['amount'], $paidAmount)) {
        throw new RuntimeException('Paid amount does not match booking amount.');
    }
    if (strtoupper($paidCurrency) !== strtoupper((string) $booking['currency'])) {
        throw new RuntimeException('Paid currency does not match booking currency.');
    }

    $didFulfill = booking_mark_paid($booking, $checkoutId);
    $fresh = booking_find_by_id((int) $booking['id']);
    if ($fresh && $didFulfill) {
        send_booking_emails($fresh);
    }
    return $didFulfill;
}

/**
 * Decide whether a Bachs webhook event should fulfill a booking.
 */
function bachs_webhook_indicates_paid(string $type, array $data): bool
{
    if ($type === 'collection.succeeded') {
        $status = strtoupper((string) ($data['status'] ?? ''));
        // SUCCEEDED = exact amount; OVERPAID = more than due (use expected_amount for match)
        return in_array($status, ['SUCCEEDED', 'OVERPAID'], true);
    }

    if ($type === 'checkout.completed') {
        // Docs: payment_status is "paid" when a charge was made.
        return strtolower((string) ($data['payment_status'] ?? '')) === 'paid';
    }

    return false;
}
