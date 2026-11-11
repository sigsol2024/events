<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$ref = trim((string) ($_GET['ref'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$cancelled = isset($_GET['cancelled']);
$booking = $ref !== '' ? booking_find_by_reference($ref) : null;
$error = null;

if (!$booking || $token === '' || !hash_equals((string) $booking['access_token'], $token)) {
    ob_start();
    ?>
    <section class="app-shell"><div class="app-card">
      <h1>Booking not found</h1>
      <p>That resume link is invalid or expired.</p>
      <a class="btn btn-primary" href="<?= e(app_url('index.php#tickets')) ?>">View tickets</a>
    </div></section>
    <?php
    render_layout('Booking not found', ob_get_clean());
    exit;
}

$booking = booking_expire_if_stale($booking);

if (($booking['status'] ?? '') === 'paid') {
    redirect(app_url(
        'thank-you.php?ref=' . urlencode((string) $booking['reference'])
        . '&token=' . urlencode((string) $booking['access_token'])
    ));
}

if (request_method() === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
    $action = post_string('action');
    try {
        if ($action === 'pay') {
            $booking = booking_expire_if_stale($booking);
            $needsFresh = (($booking['status'] ?? '') === 'expired')
                || (($booking['status'] ?? '') === 'failed')
                || !booking_is_checkout_open($booking);

            if ($needsFresh) {
                $booking = booking_start_or_refresh_checkout($booking, true);
                if (empty($booking['incomplete_notified_at'])) {
                    send_incomplete_booking_email($booking, 'initial');
                }
            }

            $url = (string) ($booking['bachs_checkout_url'] ?? '');
            if ($url === '') {
                $booking = booking_start_or_refresh_checkout($booking, true);
                $url = (string) ($booking['bachs_checkout_url'] ?? '');
            }
            if ($url === '') {
                throw new RuntimeException('No payment link available. Try again.');
            }
            redirect($url);
        }

        if ($action === 'restart') {
            if (in_array(($booking['status'] ?? ''), ['pending', 'processing'], true)) {
                booking_set_status((int) $booking['id'], 'expired');
            }
            redirect(app_url('book.php?ticket_id=' . (int) $booking['ticket_id']));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $booking = booking_find_by_id((int) $booking['id']) ?: $booking;
    }
}

$isOpen = booking_is_checkout_open($booking);
$status = (string) ($booking['status'] ?? '');

ob_start();
?>
<section class="app-shell">
  <div class="app-card">
    <p class="eyebrow">Resume booking</p>
    <h1><?= e((string) $booking['reference']) ?></h1>
    <p>Continue the booking you already started, or start over for this ticket.</p>

    <?php if ($cancelled): ?>
      <div class="alert">Checkout was cancelled. You can continue payment below whenever you’re ready.</div>
    <?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

    <div class="booking-review-columns" style="margin-top:1.2rem;">
      <article class="booking-card">
        <header class="booking-card-head">
          <span class="booking-card-icon" aria-hidden="true">G</span>
          <div>
            <p class="booking-card-label">Guest</p>
            <h3><?= e((string) $booking['full_name']) ?></h3>
          </div>
        </header>
        <div class="booking-card-body booking-card-body-2">
          <div><span>Email</span><strong><?= e((string) $booking['email']) ?></strong></div>
          <div><span>Phone</span><strong><?= e((string) $booking['phone']) ?></strong></div>
        </div>
      </article>
      <article class="booking-card booking-card-ticket">
        <header class="booking-card-head">
          <span class="booking-card-icon" aria-hidden="true">T</span>
          <div>
            <p class="booking-card-label">Ticket</p>
            <h3><?= e((string) $booking['ticket_title']) ?></h3>
          </div>
        </header>
        <div class="booking-card-body booking-card-body-2">
          <div><span>Option</span><strong><?= e((string) ($booking['price_label'] ?: 'Standard')) ?></strong></div>
          <div><span>Status</span><strong><?= e($status) ?></strong></div>
        </div>
      </article>
    </div>

    <div class="booking-total-card" style="margin-top:1rem;">
      <div class="booking-total-card-copy">
        <span>Amount due</span>
        <p><?= $isOpen ? 'Your checkout link is still available' : 'Checkout ended — we can create a fresh payment link' ?></p>
      </div>
      <strong>₦<?= e(number_format((float) $booking['amount'], 2)) ?></strong>
    </div>

    <form method="post" class="form-actions" style="margin-top:1.25rem;">
      <?= csrf_field() ?>
      <?php if (in_array($status, ['pending', 'processing', 'expired', 'failed'], true)): ?>
        <button class="btn btn-primary" type="submit" name="action" value="pay">
          <?= $isOpen ? 'Continue payment' : 'Create new payment link' ?>
        </button>
        <button class="btn btn-ghost" type="submit" name="action" value="restart"
          onclick="return confirm('Start a completely new booking for this ticket?')">
          Start over
        </button>
      <?php else: ?>
        <a class="btn btn-primary" href="<?= e(app_url('index.php#tickets')) ?>">Back to tickets</a>
      <?php endif; ?>
    </form>
  </div>
</section>
<?php
render_layout('Resume booking', ob_get_clean());
