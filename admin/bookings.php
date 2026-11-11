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
<div class="admin-page-head">
  <div>
    <h2>Bookings</h2>
    <p>Filter by payment state. Paid bookings unlock QR tickets.</p>
  </div>
</div>

<div class="admin-panel">
  <div class="admin-panel-head">
    <h3><?= $status !== '' ? ucfirst($status) : 'All' ?></h3>
    <div class="admin-filters">
      <a class="admin-chip <?= $status === '' ? 'is-active' : '' ?>" href="?">All</a>
      <?php foreach (BOOKING_STATUSES as $s): ?>
        <a class="admin-chip <?= $status === $s ? 'is-active' : '' ?>" href="?status=<?= e($s) ?>"><?= e($s) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!$bookings): ?>
    <div class="admin-empty">No bookings in this view.</div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Ref</th><th>Guest</th><th>Ticket</th><th>Amount</th><th>Status</th><th>Paid</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td data-label="Ref"><?= e($b['reference']) ?></td>
            <td data-label="Guest">
              <?= e($b['full_name']) ?>
              <div class="admin-muted"><?= e($b['email']) ?></div>
            </td>
            <td data-label="Ticket">
              <?= e($b['ticket_title']) ?>
              <?php if ($b['price_label']): ?><div class="admin-muted"><?= e($b['price_label']) ?></div><?php endif; ?>
            </td>
            <td data-label="Amount">₦<?= e(number_format((float) $b['amount'], 2)) ?></td>
            <td data-label="Status"><span class="<?= e(admin_status_class((string) $b['status'])) ?>"><?= e($b['status']) ?></span></td>
            <td data-label="Paid"><?= e((string) ($b['paid_at'] ?: '—')) ?></td>
            <td data-label=""><a class="admin-btn admin-btn-secondary admin-btn-sm" href="<?= e(app_url('admin/booking.php?id=' . (int) $b['id'])) ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php
render_admin('Bookings', ob_get_clean(), 'bookings');
