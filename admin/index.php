<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
$admin = require_admin();

$counts = [
    'paid' => 0,
    'pending' => 0,
    'processing' => 0,
    'failed' => 0,
];
foreach (db()->query('SELECT status, COUNT(*) AS c FROM bookings GROUP BY status')->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['c'];
}
$ticketCount = (int) db()->query('SELECT COUNT(*) AS c FROM tickets WHERE is_active = 1')->fetch()['c'];
$hasPin = gate_pin_hash() !== null;

ob_start();
?>
<section class="app-shell wide">
  <div class="app-card">
    <?php include __DIR__ . '/_nav.php'; ?>
    <p class="eyebrow">Dashboard</p>
    <h1>Event admin</h1>
    <div class="form-grid two" style="margin-top:1.25rem;">
      <div class="alert">Active tickets: <strong><?= $ticketCount ?></strong></div>
      <div class="alert <?= $hasPin ? 'success' : 'error' ?>">Gate PIN: <?= $hasPin ? 'configured' : 'not set — generate one' ?></div>
      <div class="alert success">Paid bookings: <strong><?= (int) ($counts['paid'] ?? 0) ?></strong></div>
      <div class="alert">Pending / processing: <strong><?= (int) ($counts['pending'] ?? 0) + (int) ($counts['processing'] ?? 0) ?></strong></div>
    </div>
  </div>
</section>
<?php
render_layout('Admin', ob_get_clean(), ['show_nav' => false]);
