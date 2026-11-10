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
<section class="app-shell">
  <div class="app-card">
    <?php include __DIR__ . '/_nav.php'; ?>
    <p class="eyebrow">Booking</p>
    <h1><?= e($booking['reference']) ?></h1>
    <p><span class="status-pill"><?= e($booking['status']) ?></span></p>
    <table class="data">
      <tr><th>Guest</th><td><?= e($booking['full_name']) ?></td></tr>
      <tr><th>Email</th><td><?= e($booking['email']) ?></td></tr>
      <tr><th>Phone</th><td><?= e($booking['phone']) ?></td></tr>
      <tr><th>DOB</th><td><?= e($booking['date_of_birth']) ?></td></tr>
      <tr><th>Gender</th><td><?= e($booking['gender']) ?></td></tr>
      <tr><th>Ticket</th><td><?= e($booking['ticket_title']) ?> <?= $booking['price_label'] ? '· ' . e($booking['price_label']) : '' ?></td></tr>
      <tr><th>Amount</th><td>₦<?= e(number_format((float) $booking['amount'], 2)) ?> <?= e($booking['currency']) ?></td></tr>
      <tr><th>Bachs checkout</th><td><?= e((string) $booking['bachs_checkout_id']) ?></td></tr>
      <tr><th>Ticket code</th><td><?= e((string) $booking['ticket_code']) ?></td></tr>
      <tr><th>Paid at</th><td><?= e((string) $booking['paid_at']) ?></td></tr>
      <tr><th>Created</th><td><?= e($booking['created_at']) ?></td></tr>
      <tr><th>Admin emailed</th><td><?= e((string) ($booking['admin_notified_at'] ?: '—')) ?></td></tr>
      <tr><th>Buyer emailed</th><td><?= e((string) ($booking['buyer_notified_at'] ?: '—')) ?></td></tr>
    </table>
    <?php if (($booking['status'] ?? '') === 'paid' && $booking['ticket_code']): ?>
      <p style="margin-top:1rem;"><a class="btn btn-ghost" href="<?= e(app_url('ticket.php?code=' . urlencode($booking['ticket_code']))) ?>" target="_blank">Open gate page</a></p>
    <?php endif; ?>
  </div>
</section>
<?php
render_layout('Booking detail', ob_get_clean(), ['show_nav' => false]);
