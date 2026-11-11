<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
$admin = require_admin();

$counts = [
    'paid' => 0,
    'pending' => 0,
    'processing' => 0,
    'failed' => 0,
    'expired' => 0,
    'refunded' => 0,
];
foreach (db()->query('SELECT status, COUNT(*) AS c FROM bookings GROUP BY status')->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['c'];
}

$revenue = (float) (db()->query("SELECT COALESCE(SUM(amount),0) AS s FROM bookings WHERE status = 'paid'")->fetch()['s'] ?? 0);
$ticketCount = (int) db()->query('SELECT COUNT(*) AS c FROM tickets WHERE is_active = 1')->fetch()['c'];
$hasPin = gate_pin_hash() !== null;
$recent = bookings_list(null, 8);
$awaiting = (int) ($counts['pending'] ?? 0) + (int) ($counts['processing'] ?? 0);

ob_start();
?>
<div class="admin-page-head">
  <div>
    <h2>Overview</h2>
    <p>Sales, payment status, and gate readiness.</p>
  </div>
  <div class="admin-actions">
    <a class="admin-btn admin-btn-primary admin-btn-sm" href="<?= e(app_url('admin/tickets.php')) ?>">Tickets</a>
    <a class="admin-btn admin-btn-secondary admin-btn-sm" href="<?= e(app_url('admin/gate-pin.php')) ?>">Gate PIN</a>
  </div>
</div>

<div class="admin-stats">
  <div class="admin-stat is-good">
    <div class="admin-stat-label">Paid</div>
    <div class="admin-stat-value"><?= (int) ($counts['paid'] ?? 0) ?></div>
    <div class="admin-stat-hint">Confirmed</div>
  </div>
  <div class="admin-stat is-accent">
    <div class="admin-stat-label">Revenue</div>
    <div class="admin-stat-value">₦<?= e(number_format($revenue, 0)) ?></div>
    <div class="admin-stat-hint">Paid only</div>
  </div>
  <div class="admin-stat is-warn">
    <div class="admin-stat-label">Awaiting</div>
    <div class="admin-stat-value"><?= $awaiting ?></div>
    <div class="admin-stat-hint">Pending</div>
  </div>
  <div class="admin-stat <?= $hasPin ? 'is-good' : 'is-danger' ?>">
    <div class="admin-stat-label">Gate PIN</div>
    <div class="admin-stat-value"><?= $hasPin ? 'ON' : 'OFF' ?></div>
    <div class="admin-stat-hint"><?= $ticketCount ?> active</div>
  </div>
</div>

<div class="admin-panel">
  <div class="admin-panel-head">
    <h3>Quick actions</h3>
  </div>
  <div class="admin-quick-links">
    <a class="admin-quick-link" href="<?= e(app_url('admin/bookings.php?status=paid')) ?>">
      <span>
        <strong>Paid bookings</strong>
        <span class="admin-muted">Verify guests</span>
      </span>
      <span aria-hidden="true">→</span>
    </a>
    <a class="admin-quick-link" href="<?= e(app_url('admin/tickets.php')) ?>">
      <span>
        <strong>Ticket catalog</strong>
        <span class="admin-muted">Landing prices</span>
      </span>
      <span aria-hidden="true">→</span>
    </a>
    <a class="admin-quick-link" href="<?= e(app_url('admin/gate-pin.php')) ?>">
      <span>
        <strong><?= $hasPin ? 'Rotate gate PIN' : 'Set gate PIN' ?></strong>
        <span class="admin-muted">QR unlock</span>
      </span>
      <span aria-hidden="true">→</span>
    </a>
  </div>
</div>

<div class="admin-panel">
  <div class="admin-panel-head">
    <h3>Recent bookings</h3>
    <a class="admin-btn admin-btn-ghost admin-btn-sm" href="<?= e(app_url('admin/bookings.php')) ?>">View all</a>
  </div>
  <?php if (!$recent): ?>
    <div class="admin-empty">No bookings yet.</div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Ref</th><th>Guest</th><th>Ticket</th><th>Amount</th><th>Status</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($recent as $b): ?>
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
            <td data-label=""><a class="admin-btn admin-btn-secondary admin-btn-sm" href="<?= e(app_url('admin/booking.php?id=' . (int) $b['id'])) ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php
render_admin('Overview', ob_get_clean(), 'dashboard');
