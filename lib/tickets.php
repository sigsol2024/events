<?php

declare(strict_types=1);

function tickets_all(bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM tickets';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    $tickets = db()->query($sql)->fetchAll();
    foreach ($tickets as &$ticket) {
        $ticket['prices'] = ticket_prices((int) $ticket['id']);
    }
    unset($ticket);
    return $tickets;
}

function ticket_find(int $id, bool $activeOnly = false): ?array
{
    $sql = 'SELECT * FROM tickets WHERE id = ?';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        return null;
    }
    $ticket['prices'] = ticket_prices($id);
    return $ticket;
}

function ticket_prices(int $ticketId): array
{
    $stmt = db()->prepare(
        'SELECT * FROM ticket_prices WHERE ticket_id = ? ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute([$ticketId]);
    return $stmt->fetchAll();
}

function ticket_price_find(int $priceId): ?array
{
    $stmt = db()->prepare('SELECT * FROM ticket_prices WHERE id = ? LIMIT 1');
    $stmt->execute([$priceId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function resolve_ticket_amount(array $ticket, ?int $priceId): array
{
    $prices = $ticket['prices'] ?? ticket_prices((int) $ticket['id']);
    if (!$prices) {
        throw new RuntimeException('Ticket has no price configured.');
    }

    if ($ticket['pricing_mode'] === 'tiered') {
        if (!$priceId) {
            throw new InvalidArgumentException('Please select a price option.');
        }
        foreach ($prices as $price) {
            if ((int) $price['id'] === $priceId) {
                return [
                    'price_id' => (int) $price['id'],
                    'price_label' => (string) $price['label'],
                    'amount' => (float) $price['amount'],
                ];
            }
        }
        throw new InvalidArgumentException('Invalid price option selected.');
    }

    $price = $prices[0];
    return [
        'price_id' => (int) $price['id'],
        'price_label' => (string) ($price['label'] ?: $ticket['title']),
        'amount' => (float) $price['amount'],
    ];
}

function ticket_save(array $data, array $priceRows, ?int $id = null): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE tickets SET title = ?, description = ?, pricing_mode = ?, sort_order = ?, is_active = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([
                $data['title'],
                $data['description'],
                $data['pricing_mode'],
                (int) $data['sort_order'],
                (int) $data['is_active'],
                $id,
            ]);
            $pdo->prepare('DELETE FROM ticket_prices WHERE ticket_id = ?')->execute([$id]);
            $ticketId = $id;
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO tickets (title, description, pricing_mode, sort_order, is_active) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['title'],
                $data['description'],
                $data['pricing_mode'],
                (int) $data['sort_order'],
                (int) $data['is_active'],
            ]);
            $ticketId = (int) $pdo->lastInsertId();
        }

        $ins = $pdo->prepare(
            'INSERT INTO ticket_prices (ticket_id, label, amount, sort_order) VALUES (?, ?, ?, ?)'
        );
        foreach ($priceRows as $i => $row) {
            $ins->execute([
                $ticketId,
                $row['label'],
                money_string($row['amount']),
                $i + 1,
            ]);
        }
        $pdo->commit();
        return $ticketId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function ticket_delete(int $id): void
{
    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM bookings WHERE ticket_id = ?');
    $stmt->execute([$id]);
    if ((int) ($stmt->fetch()['c'] ?? 0) > 0) {
        $upd = db()->prepare('UPDATE tickets SET is_active = 0, updated_at = NOW() WHERE id = ?');
        $upd->execute([$id]);
        return;
    }
    db()->prepare('DELETE FROM tickets WHERE id = ?')->execute([$id]);
}
