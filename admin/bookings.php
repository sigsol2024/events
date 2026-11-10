<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
$admin = require_admin();

$status = trim((string) ($_GET['status'] ?? ''));
if ($status !== '' && !in_array($status, BOOKING_STATUSES, true)) {
    $status = '';
}
$bookings = bookings_list($status ?: null, 200);

ob_start();
?>
<section class="app-shell wide">
  <div class="app-card">
    <?php include __DIR__ . '/_nav.php'; ?>
    <p class="eyebrow">Sales</p>
    <h1>Bookings</h1>
    <div class="admin-nav">
      <a href="?">All</a>
      <?php foreach (BOOKING_STATUSES as $s): ?>
        <a href="?status=<?= e($s) ?>"><?= e($s) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Ref</th><th>Guest</th><th>Ticket</th><th>Amount</th><th>Status</th><th>Paid</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td><?= e($b['reference']) ?></td>
            <td><?= e($b['full_name']) ?><br /><span style="opacity:.7;"><?= e($b['email']) ?></span></td>
            <td><?= e($b['ticket_title']) ?><?= $b['price_label'] ? '<br />' . e($b['price_label']) : '' ?></td>
            <td>₦<?= e(number_format((float) $b['amount'], 2)) ?></td>
            <td><span class="status-pill"><?= e($b['status']) ?></span></td>
            <td><?= e((string) ($b['paid_at'] ?: '—')) ?></td>
            <td><a href="<?= e(app_url('admin/booking.php?id=' . (int) $b['id'])) ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<?php
render_layout('Bookings', ob_get_clean(), ['show_nav' => false]);
