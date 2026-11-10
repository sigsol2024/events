<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$code = trim((string) ($_GET['code'] ?? $_POST['code'] ?? ''));
$booking = $code !== '' ? booking_find_by_ticket_code($code) : null;
$error = null;
$unlocked = false;

if (!$booking || ($booking['status'] ?? '') !== 'paid' || empty($booking['ticket_code'])) {
    ob_start();
    ?>
    <section class="app-shell"><div class="app-card">
      <h1>Invalid ticket</h1>
      <p>This QR code is invalid, refunded, or the booking is not paid.</p>
    </div></section>
    <?php
    render_layout('Invalid ticket', ob_get_clean());
    exit;
}

$sessionKey = 'gate_unlocked_' . $booking['ticket_code'];
if (!empty($_SESSION[$sessionKey])) {
    $unlocked = true;
}

if (request_method() === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session. Try again.';
    } elseif (pin_attempts_blocked($code)) {
        $error = 'Too many attempts. Wait 15 minutes and try again.';
    } else {
        $pin = post_string('pin');
        record_pin_attempt($code);
        if (!gate_pin_hash()) {
            $error = 'Gate PIN is not configured. Ask the event admin to generate one.';
        } elseif (verify_gate_pin($pin)) {
            $_SESSION[$sessionKey] = true;
            $unlocked = true;
        } else {
            $error = 'Incorrect PIN.';
        }
    }
}

ob_start();
?>
<section class="app-shell">
  <div class="app-card">
    <p class="eyebrow">Gate verification</p>
    <?php if (!$unlocked): ?>
      <h1>Enter staff PIN</h1>
      <p>Ticket details are protected. Only event staff with the current gate PIN can view this guest.</p>
      <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="form-grid" style="margin-top:1rem;max-width:320px;">
        <?= csrf_field() ?>
        <input type="hidden" name="code" value="<?= e($code) ?>" />
        <label>PIN
          <input type="password" name="pin" inputmode="numeric" pattern="[0-9]*" required autocomplete="one-time-code" />
        </label>
        <button class="btn btn-magenta" type="submit">Unlock details</button>
      </form>
    <?php else: ?>
      <h1>Guest ticket</h1>
      <table class="data">
        <tr><th>Full name</th><td><?= e($booking['full_name']) ?></td></tr>
        <tr><th>Email</th><td><?= e($booking['email']) ?></td></tr>
        <tr><th>Phone</th><td><?= e($booking['phone']) ?></td></tr>
        <tr><th>Date of birth</th><td><?= e($booking['date_of_birth']) ?></td></tr>
        <tr><th>Gender</th><td><?= e(ucfirst($booking['gender'])) ?></td></tr>
        <tr><th>Ticket</th><td><?= e($booking['ticket_title']) ?><?= $booking['price_label'] ? ' · ' . e($booking['price_label']) : '' ?></td></tr>
        <tr><th>Amount paid</th><td>₦<?= e(number_format((float) $booking['amount'], 2)) ?> <?= e($booking['currency']) ?></td></tr>
        <tr><th>Paid at</th><td><?= e((string) $booking['paid_at']) ?></td></tr>
        <tr><th>Reference</th><td><?= e($booking['reference']) ?></td></tr>
        <tr><th>Status</th><td><span class="status-pill"><?= e($booking['status']) ?></span></td></tr>
      </table>
    <?php endif; ?>
  </div>
</section>
<?php
render_layout('Ticket verification', ob_get_clean());
