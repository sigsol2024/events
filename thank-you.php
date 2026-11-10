<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$ref = trim((string) ($_GET['ref'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$booking = $ref !== '' ? booking_find_by_reference($ref) : null;

if (!$booking || $token === '' || !hash_equals((string) $booking['access_token'], $token)) {
    ob_start();
    ?>
    <section class="app-shell"><div class="app-card">
      <h1>Booking not found</h1>
      <p>We could not find that booking. Check your email link or contact support.</p>
      <a class="btn btn-primary" href="<?= e(app_url('index.html')) ?>">Back home</a>
    </div></section>
    <?php
    render_layout('Booking not found', ob_get_clean());
    exit;
}

// Reconcile if still awaiting payment confirmation (webhook is source of truth;
// this is a best-effort unlock if the buyer returns before the webhook lands).
if (in_array($booking['status'], ['pending', 'processing'], true) && !empty($booking['bachs_checkout_id'])) {
    try {
        $session = bachs_get_checkout((string) $booking['bachs_checkout_id']);
        if (bachs_session_is_paid($session)) {
            [$amount, $currency] = bachs_extract_amount_currency($session);
            fulfill_paid_booking($booking, (string) $booking['bachs_checkout_id'], $amount, $currency);
            $booking = booking_find_by_id((int) $booking['id']);
        } elseif (strtolower((string) ($session['status'] ?? '')) === 'expired') {
            if (!in_array($booking['status'], ['paid', 'refunded'], true)) {
                booking_set_status((int) $booking['id'], 'expired');
            }
        }
    } catch (Throwable $e) {
        // Keep pending UI; webhook may still arrive
    }
}

$booking = booking_find_by_id((int) $booking['id']) ?: $booking;
$isPaid = ($booking['status'] ?? '') === 'paid' && !empty($booking['ticket_code']);

ob_start();
?>
<section class="app-shell">
  <div class="app-card">
    <?php if ($isPaid): ?>
      <p class="eyebrow">Confirmed</p>
      <h1>Payment successful</h1>
      <p>Your spot is locked. Download your QR ticket and keep it handy for the gate.</p>
      <p><strong>Reference:</strong> <?= e($booking['reference']) ?></p>
      <p><strong>Ticket:</strong> <?= e($booking['ticket_title']) ?><?= $booking['price_label'] ? ' · ' . e($booking['price_label']) : '' ?></p>
      <p class="price-preview">₦<?= e(number_format((float) $booking['amount'], 2)) ?></p>

      <div class="qr-box">
        <img src="<?= e(qr_image_src(ticket_qr_url($booking))) ?>" alt="Your ticket QR code" />
      </div>
      <div class="form-actions" style="justify-content:center;">
        <a class="btn btn-primary" href="<?= e(qr_download_endpoint($booking)) ?>">Download QR</a>
        <a class="btn btn-ghost" href="<?= e(app_url('index.html')) ?>">Back home</a>
      </div>
      <p style="margin-top:1rem;color:rgba(247,239,217,0.7);font-size:0.9rem;">
        A confirmation email was sent to <?= e($booking['email']) ?> with a link back to this page.
      </p>
    <?php else: ?>
      <p class="eyebrow">Payment</p>
      <h1>Confirming your payment…</h1>
      <div class="alert">
        We’re verifying your payment with Bachs. This page will unlock your QR ticket once payment is confirmed.
        Status: <span class="status-pill"><?= e($booking['status']) ?></span>
      </div>
      <p>Reference: <?= e($booking['reference']) ?></p>
      <div class="form-actions">
        <a class="btn btn-primary" href="<?= e(app_url('thank-you.php?ref=' . urlencode($ref) . '&token=' . urlencode($token))) ?>">Refresh status</a>
        <a class="btn btn-ghost" href="<?= e(app_url('index.html')) ?>">Home</a>
      </div>
      <script>
        setTimeout(function () { window.location.reload(); }, 8000);
      </script>
    <?php endif; ?>
  </div>
</section>
<?php
render_layout('Thank you', ob_get_clean());
