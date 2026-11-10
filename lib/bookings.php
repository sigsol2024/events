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

function booking_update_checkout(int $id, string $checkoutId, string $reference): void
{
    // Refuse to bind a checkout id that already belongs to another booking.
    $clash = db()->prepare(
        'SELECT id FROM bookings WHERE bachs_checkout_id = ? AND id <> ? LIMIT 1'
    );
    $clash->execute([$checkoutId, $id]);
    if ($clash->fetch()) {
        throw new RuntimeException('Checkout session already linked to another booking.');
    }

    $stmt = db()->prepare(
        'UPDATE bookings SET bachs_checkout_id = ?, bachs_reference = ?, status = IF(status = \'pending\', \'processing\', status), updated_at = NOW() WHERE id = ?'
    );
    $stmt->execute([$checkoutId, $reference, $id]);
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
