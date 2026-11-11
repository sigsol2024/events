<?php

declare(strict_types=1);

const BOOKING_STATUSES = ['pending', 'processing', 'paid', 'failed', 'expired', 'refunded'];

function booking_create(array $data): array
{
    $reference = make_booking_reference();
    $accessToken = make_access_token();
    $stmt = db()->prepare(
        'INSERT INTO bookings (
            reference, ticket_id, ticket_title, price_id, price_label,
            full_name, email, phone, date_of_birth, gender,
            amount, currency, status, access_token
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $reference,
        (int) $data['ticket_id'],
        $data['ticket_title'],
        $data['price_id'],
        $data['price_label'],
        $data['full_name'],
        strtolower($data['email']),
        $data['phone'],
        $data['date_of_birth'],
        $data['gender'],
        money_string($data['amount']),
        $data['currency'] ?? 'NGN',
        'pending',
        $accessToken,
    ]);

    return booking_find_by_id((int) db()->lastInsertId());
}

function booking_find_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM bookings WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function booking_find_by_reference(string $reference): ?array
{
    $stmt = db()->prepare('SELECT * FROM bookings WHERE reference = ? LIMIT 1');
    $stmt->execute([$reference]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function booking_find_by_checkout(string $checkoutId): ?array
{
    $stmt = db()->prepare('SELECT * FROM bookings WHERE bachs_checkout_id = ? LIMIT 1');
    $stmt->execute([$checkoutId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function booking_find_by_ticket_code(string $code): ?array
{
    $stmt = db()->prepare('SELECT * FROM bookings WHERE ticket_code = ? LIMIT 1');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function booking_find_by_access_token(string $token): ?array
{
    $stmt = db()->prepare('SELECT * FROM bookings WHERE access_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function booking_update_checkout(
    int $id,
    string $checkoutId,
    string $reference,
    ?string $checkoutUrl = null,
    ?DateTimeInterface $expiresAt = null
): void {
    // Refuse to bind a checkout id that already belongs to another booking.
    $clash = db()->prepare(
        'SELECT id FROM bookings WHERE bachs_checkout_id = ? AND id <> ? LIMIT 1'
    );
    $clash->execute([$checkoutId, $id]);
    if ($clash->fetch()) {
        throw new RuntimeException('Checkout session already linked to another booking.');
    }

    $expires = $expiresAt
        ? $expiresAt->format('Y-m-d H:i:s')
        : (new DateTimeImmutable('now'))
            ->modify('+' . max(1, (int) config('bachs.checkout_expires_minutes', 60)) . ' minutes')
            ->format('Y-m-d H:i:s');

    $stmt = db()->prepare(
        'UPDATE bookings
         SET bachs_checkout_id = ?,
             bachs_checkout_url = COALESCE(?, bachs_checkout_url),
             bachs_reference = ?,
             checkout_expires_at = ?,
             status = IF(status = \'pending\', \'processing\', status),
             updated_at = NOW()
         WHERE id = ?'
    );
    $stmt->execute([$checkoutId, $checkoutUrl, $reference, $expires, $id]);
}

/**
 * Latest unfinished booking for this email + ticket (pending/processing).
 */
function booking_find_open_for_email_ticket(string $email, int $ticketId): ?array
{
    $stmt = db()->prepare(
        "SELECT * FROM bookings
         WHERE email = ?
           AND ticket_id = ?
           AND status IN ('pending', 'processing')
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([strtolower(trim($email)), $ticketId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function booking_is_checkout_open(array $booking): bool
{
    if (!in_array(($booking['status'] ?? ''), ['pending', 'processing'], true)) {
        return false;
    }
    if (empty($booking['bachs_checkout_url']) && empty($booking['bachs_checkout_id'])) {
        return false;
    }
    if (!empty($booking['checkout_expires_at'])) {
        return strtotime((string) $booking['checkout_expires_at']) > time();
    }
    return true;
}

function booking_expire_if_stale(array $booking): array
{
    if (!in_array(($booking['status'] ?? ''), ['pending', 'processing'], true)) {
        return $booking;
    }
    if (!empty($booking['checkout_expires_at']) && strtotime((string) $booking['checkout_expires_at']) <= time()) {
        booking_set_status((int) $booking['id'], 'expired');
        return booking_find_by_id((int) $booking['id']) ?: $booking;
    }
    return $booking;
}

function booking_update_guest_details(int $id, array $data): void
{
    $stmt = db()->prepare(
        'UPDATE bookings SET
            full_name = ?, email = ?, phone = ?, date_of_birth = ?, gender = ?,
            price_id = ?, price_label = ?, amount = ?, currency = ?,
            updated_at = NOW()
         WHERE id = ?'
    );
    $stmt->execute([
        $data['full_name'],
        strtolower((string) $data['email']),
        $data['phone'],
        $data['date_of_birth'],
        $data['gender'],
        $data['price_id'],
        $data['price_label'],
        money_string($data['amount']),
        $data['currency'] ?? 'NGN',
        $id,
    ]);
}

function booking_resume_url(array $booking): string
{
    return app_url(
        'resume.php?ref=' . urlencode((string) $booking['reference'])
        . '&token=' . urlencode((string) $booking['access_token'])
    );
}

function booking_mark_incomplete_notified(int $id): void
{
    $stmt = db()->prepare(
        'UPDATE bookings SET incomplete_notified_at = NOW(), updated_at = NOW()
         WHERE id = ? AND incomplete_notified_at IS NULL'
    );
    $stmt->execute([$id]);
}

function booking_mark_incomplete_reminded(int $id): void
{
    $stmt = db()->prepare(
        'UPDATE bookings SET incomplete_reminded_at = NOW(), updated_at = NOW()
         WHERE id = ? AND incomplete_reminded_at IS NULL'
    );
    $stmt->execute([$id]);
}

/**
 * Open bookings that got the first email but still need a follow-up reminder.
 */
function bookings_needing_incomplete_reminder(int $olderThanMinutes = 20, int $limit = 50): array
{
    $stmt = db()->prepare(
        "SELECT * FROM bookings
         WHERE status IN ('pending', 'processing')
           AND incomplete_notified_at IS NOT NULL
           AND incomplete_reminded_at IS NULL
           AND incomplete_notified_at <= (NOW() - INTERVAL ? MINUTE)
           AND (checkout_expires_at IS NULL OR checkout_expires_at > NOW())
         ORDER BY id ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, $olderThanMinutes, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function bookings_expire_stale(int $limit = 100): int
{
    $stmt = db()->prepare(
        "UPDATE bookings
         SET status = 'expired', updated_at = NOW()
         WHERE status IN ('pending', 'processing')
           AND checkout_expires_at IS NOT NULL
           AND checkout_expires_at <= NOW()
         LIMIT ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount();
}

function booking_set_status(int $id, string $status): void
{
    if (!in_array($status, BOOKING_STATUSES, true)) {
        throw new InvalidArgumentException('Invalid booking status.');
    }
    $stmt = db()->prepare('UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $id]);
}

/**
 * Mark booking paid exactly once. Returns true if this call performed fulfillment.
 */
function booking_mark_paid(array $booking, ?string $providerCheckoutId = null): bool
{
    if (($booking['status'] ?? '') === 'paid' && !empty($booking['ticket_code'])) {
        return false;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ? FOR UPDATE');
        $stmt->execute([(int) $booking['id']]);
        $locked = $stmt->fetch();
        if (!$locked) {
            $pdo->rollBack();
            return false;
        }
        if ($locked['status'] === 'paid' && !empty($locked['ticket_code'])) {
            $pdo->commit();
            return false;
        }

        $ticketCode = $locked['ticket_code'] ?: make_ticket_code();
        $upd = $pdo->prepare(
            'UPDATE bookings
             SET status = ?, ticket_code = ?, paid_at = COALESCE(paid_at, NOW()),
                 bachs_checkout_id = COALESCE(?, bachs_checkout_id),
                 updated_at = NOW()
             WHERE id = ?'
        );
        $upd->execute(['paid', $ticketCode, $providerCheckoutId, (int) $locked['id']]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function booking_mark_notified(int $id, string $who): void
{
    $column = $who === 'admin' ? 'admin_notified_at' : 'buyer_notified_at';
    $stmt = db()->prepare("UPDATE bookings SET {$column} = NOW(), updated_at = NOW() WHERE id = ? AND {$column} IS NULL");
    $stmt->execute([$id]);
}

function bookings_list(?string $status = null, int $limit = 100): array
{
    if ($status) {
        $stmt = db()->prepare('SELECT * FROM bookings WHERE status = ? ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $status);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    $stmt = db()->prepare('SELECT * FROM bookings ORDER BY id DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function amounts_match(string $expected, string $actual): bool
{
    return money_string($expected) === money_string($actual);
}
