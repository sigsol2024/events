<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

$tickets = tickets_all(true);
$out = [];
foreach ($tickets as $ticket) {
    $prices = [];
    foreach ($ticket['prices'] as $price) {
        $prices[] = [
            'id' => (int) $price['id'],
            'label' => (string) $price['label'],
            'amount' => money_string($price['amount']),
            'display' => format_ngn($price['amount']),
        ];
    }
    $out[] = [
        'id' => (int) $ticket['id'],
        'title' => $ticket['title'],
        'description' => $ticket['description'],
        'pricing_mode' => $ticket['pricing_mode'],
        'prices' => $prices,
        'book_url' => app_url('book.php?ticket_id=' . (int) $ticket['id']),
    ];
}

json_response(['tickets' => $out]);
