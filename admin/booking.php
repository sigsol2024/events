<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
$admin = require_admin();

$id = (int) ($_GET['id'] ?? 0);
$booking = $id ? booking_find_by_id($id) : null;
if (!$booking) {
    flash('ok', 'Booking not found.');
    redirect(app_url('admin/bookings.php'));
}

ob_start();
?>
<div class="admin-page-head">
  <div>
    <h2><?= e($booking['reference']) ?></h2>
    <p>Guest booking detail and payment identifiers.</p>
  </div>
  <div class="admin-actions">
    <a class="admin-btn admin-btn-secondary" href="<?= e(app_url('admin/bookings.php')) ?>">Back to bookings</a>
    <?php if (($booking['status'] ?? '') === 'paid' && $booking['ticket_code']): ?>
      <a class="admin-btn admin-btn-primary" href="<?= e(app_url('ticket.php?code=' . urlencode($booking['ticket_code']))) ?>" target="_blank" rel="noopener">Open gate page</a>
    <?php endif; ?>
  </div>
</div>

<div class="admin-panel">
  <div class="admin-panel-head">
    <h3>Status</h3>
    <span class="<?= e(admin_status_class((string) $booking['status'])) ?>"><?= e($booking['status']) ?></span>
  </div>

  <dl class="admin-detail-grid">
    <div class="admin-detail-row"><dt>Guest</dt><dd><?= e($booking['full_name']) ?></dd></div>
    <div class="admin-detail-row"><dt>Email</dt><dd><?= e($booking['email']) ?></dd></div>
    <div class="admin-detail-row"><dt>Phone</dt><dd><?= e($booking['phone']) ?></dd></div>
    <div class="admin-detail-row"><dt>Date of birth</dt><dd><?= e($booking['date_of_birth']) ?></dd></div>
    <div class="admin-detail-row"><dt>Gender</dt><dd><?= e(ucfirst((string) $booking['gender'])) ?></dd></div>
    <div class="admin-detail-row"><dt>Ticket</dt><dd><?= e($booking['ticket_title']) ?><?= $booking['price_label'] ? ' · ' . e($booking['price_label']) : '' ?></dd></div>
    <div class="admin-detail-row"><dt>Amount</dt><dd>₦<?= e(number_format((float) $booking['amount'], 2)) ?> <?= e($booking['currency']) ?></dd></div>
    <div class="admin-detail-row"><dt>Bachs checkout</dt><dd><?= e((string) ($booking['bachs_checkout_id'] ?: '—')) ?></dd></div>
    <div class="admin-detail-row"><dt>Ticket code</dt><dd><?= e((string) ($booking['ticket_code'] ?: '—')) ?></dd></div>
    <div class="admin-detail-row"><dt>Paid at</dt><dd><?= e((string) ($booking['paid_at'] ?: '—')) ?></dd></div>
    <div class="admin-detail-row"><dt>Created</dt><dd><?= e((string) $booking['created_at']) ?></dd></div>
    <div class="admin-detail-row"><dt>Admin emailed</dt><dd><?= e((string) ($booking['admin_notified_at'] ?: '—')) ?></dd></div>
    <div class="admin-detail-row"><dt>Buyer emailed</dt><dd><?= e((string) ($booking['buyer_notified_at'] ?: '—')) ?></dd></div>
  </dl>
</div>
<?php
render_admin('Booking detail', ob_get_clean(), 'bookings');
