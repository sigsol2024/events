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

function ticket_card_drink_svg(): string
{
    return <<<'SVG'
<button class="float-drink" type="button" aria-label="Shake cocktail">
  <svg class="drink drink--mini" viewBox="0 0 90 120">
    <ellipse cx="45" cy="112" rx="18" ry="4" fill="rgba(0,0,0,0.15)"/>
    <path d="M28 30h34l-5 70H33L28 30Z" fill="#ffb703"/>
    <path d="M32 36h26l-3.5 52H35.5L32 36Z" fill="#fb8500"/>
    <rect x="40" y="16" width="10" height="16" rx="2" fill="#fff6d8"/>
    <path d="M36 18c6-10 18-10 24 2" stroke="#e91e8c" stroke-width="2.5" fill="none"/>
    <circle cx="60" cy="18" r="5" fill="#e91e8c"/>
  </svg>
</button>
SVG;
}

/**
 * Server-render public ticket cards for the landing page.
 */
function render_landing_ticket_cards(): string
{
    try {
        $tickets = tickets_all(true);
    } catch (Throwable $e) {
        return '<p class="ticket-note" style="grid-column:1/-1;">Tickets are temporarily unavailable. Please refresh shortly.</p>';
    }

    if (!$tickets) {
        return '<p class="ticket-note" style="grid-column:1/-1;">Tickets will appear here once published.</p>';
    }

    $html = '';
    foreach ($tickets as $index => $ticket) {
        $featured = $index > 0 ? ' vip' : ' regular';
        $featuredExtra = $index === 2 ? ' featured' : '';
        $btnClass = $index === 0 ? 'btn btn-primary' : 'btn btn-magenta';
        $btnLabel = $index === 0 ? 'Reserve' : 'Book Now';
        $bookUrl = app_url('book.php?ticket_id=' . (int) $ticket['id']);

        $prices = $ticket['prices'] ?? [];
        if (($ticket['pricing_mode'] ?? '') === 'tiered' && count($prices) > 1) {
            $pricesHtml = '<div class="price-split">';
            foreach ($prices as $p) {
                $pricesHtml .= '<div><span class="price-label">' . e((string) ($p['label'] ?: 'Option')) . '</span>'
                    . '<span class="price">' . e(format_ngn($p['amount'])) . '</span></div>';
            }
            $pricesHtml .= '</div>';
        } else {
            $display = isset($prices[0]) ? format_ngn($prices[0]['amount']) : '';
            $pricesHtml = '<span class="price">' . e($display) . '</span>';
        }

        $html .= '<article class="ticket-panel' . $featured . $featuredExtra . '">'
            . ticket_card_drink_svg()
            . '<h3>' . e((string) $ticket['title']) . '</h3>'
            . '<p class="ticket-note">' . e((string) ($ticket['description'] ?? '')) . '</p>'
            . $pricesHtml
            . '<a class="' . $btnClass . '" href="' . e($bookUrl) . '">' . e($btnLabel) . '</a>'
            . '</article>';
    }

    return $html;
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

/**
 * For tiered tickets, map gender to Guys/Girls price rows when those labels exist.
 */
function resolve_price_id_for_gender(array $ticket, string $gender, ?int $priceId = null): ?int
{
    if (($ticket['pricing_mode'] ?? '') !== 'tiered') {
        return $priceId;
    }

    $gender = strtolower(trim($gender));
    $want = $gender === 'male' ? 'guys' : ($gender === 'female' ? 'girls' : '');
    if ($want === '') {
        return $priceId;
    }

    foreach ($ticket['prices'] ?? [] as $price) {
        $label = strtolower(trim((string) ($price['label'] ?? '')));
        if ($label === $want || str_contains($label, $want)) {
            return (int) $price['id'];
        }
    }

    return $priceId;
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
