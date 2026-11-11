<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$code = trim((string) ($_GET['code'] ?? $_POST['code'] ?? ''));
$booking = $code !== '' ? booking_find_by_ticket_code($code) : null;
$error = null;
$notice = null;
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
    $action = post_string('action') ?: 'unlock';

    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session. Try again.';
    } elseif ($action === 'admit') {
        if (!$unlocked) {
            $error = 'Unlock with the gate PIN before admitting a guest.';
        } else {
            try {
                $didAdmit = booking_admit($booking);
                $booking = booking_find_by_id((int) $booking['id']) ?? $booking;
                $notice = $didAdmit
                    ? 'Guest admitted. Entry is recorded for this ticket.'
                    : 'This guest was already admitted earlier.';
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
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

$admitted = booking_is_admitted($booking);

ob_start();
?>
<section class="app-shell">
  <div class="app-card">
    <p class="eyebrow">Gate verification</p>
    <?php if (!$unlocked): ?>
      <h1>Enter staff PIN</h1>
      <p>Ticket details are protected. Only event staff with the current gate PIN can view or admit this guest.</p>
      <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="form-grid" style="margin-top:1rem;max-width:320px;">
        <?= csrf_field() ?>
        <input type="hidden" name="code" value="<?= e($code) ?>" />
        <input type="hidden" name="action" value="unlock" />
        <label>PIN
          <input type="password" name="pin" inputmode="numeric" pattern="[0-9]*" required autocomplete="one-time-code" />
        </label>
        <button class="btn btn-magenta" type="submit">Unlock details</button>
      </form>
    <?php else: ?>
      <h1>Guest ticket</h1>

      <?php if ($admitted): ?>
        <div class="alert error" role="status">
          <strong>Already admitted</strong><br />
          This QR was used for entry at <?= e((string) $booking['admitted_at']) ?>.
          Do not treat this as a fresh unused ticket. If the same guest stepped out, staff may still allow re-entry after confirming identity.
        </div>
      <?php else: ?>
        <div class="alert" role="status">
          Not admitted yet. Confirm the guest matches these details, then tap <strong>Admit guest</strong>.
        </div>
      <?php endif; ?>

      <?php if ($notice): ?><div class="alert success"><?= e($notice) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

      <table class="data">
        <tr><th>Full name</th><td><?= e($booking['full_name']) ?></td></tr>
        <tr><th>Email</th><td><?= e($booking['email']) ?></td></tr>
        <tr><th>Phone</th><td><?= e($booking['phone']) ?></td></tr>
        <tr><th>Age</th><td><?= e(booking_age_label($booking)) ?></td></tr>
        <tr><th>Gender</th><td><?= e(ucfirst($booking['gender'])) ?></td></tr>
        <tr><th>Ticket</th><td><?= e($booking['ticket_title']) ?><?= $booking['price_label'] ? ' · ' . e($booking['price_label']) : '' ?></td></tr>
        <tr><th>Amount paid</th><td>₦<?= e(number_format((float) $booking['amount'], 2)) ?> <?= e($booking['currency']) ?></td></tr>
        <tr><th>Paid at</th><td><?= e((string) $booking['paid_at']) ?></td></tr>
        <tr><th>Reference</th><td><?= e($booking['reference']) ?></td></tr>
        <tr><th>Payment</th><td><span class="status-pill"><?= e($booking['status']) ?></span></td></tr>
        <tr>
          <th>Admission</th>
          <td>
            <?php if ($admitted): ?>
              <span class="status-pill status-pill-admitted">Admitted</span>
              <div style="margin-top:0.35rem;opacity:.8;"><?= e((string) $booking['admitted_at']) ?></div>
            <?php else: ?>
              <span class="status-pill">Not admitted</span>
            <?php endif; ?>
          </td>
        </tr>
      </table>

      <?php if (!$admitted): ?>
        <form method="post" class="form-grid" style="margin-top:1.25rem;max-width:360px;">
          <?= csrf_field() ?>
          <input type="hidden" name="code" value="<?= e($code) ?>" />
          <input type="hidden" name="action" value="admit" />
          <button class="btn btn-primary" type="submit">Admit guest</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php
render_layout('Ticket verification', ob_get_clean());
