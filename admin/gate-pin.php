<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
$admin = require_admin();

$newPin = null;
$error = null;
$message = null;

if (request_method() === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } else {
        $newPin = generate_gate_pin();
        $message = 'New gate PIN generated. Copy it now — it will not be shown again.';
    }
}

ob_start();
?>
<div class="admin-page-head">
  <div>
    <h2>Gate PIN</h2>
    <p>Staff enter this PIN after scanning a guest QR to unlock booking details. Buyers never receive it.</p>
  </div>
</div>

<div class="admin-panel">
  <div class="admin-panel-head">
    <h3>Current status</h3>
    <span class="admin-status <?= gate_pin_hash() ? 'admin-status-paid' : 'admin-status-failed' ?>">
      <?= gate_pin_hash() ? 'PIN configured' : 'Not set' ?>
    </span>
  </div>

  <?php if ($error): ?><div class="admin-alert admin-alert-error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($message): ?><div class="admin-alert admin-alert-success"><?= e($message) ?></div><?php endif; ?>

  <?php if ($newPin): ?>
    <div class="admin-pin-display"><?= e($newPin) ?></div>
    <p class="admin-muted" style="margin:0.85rem 0 1.25rem;">Share with gate staff only. Generating again replaces the old PIN immediately.</p>
  <?php else: ?>
    <p class="admin-muted" style="margin:0 0 1.25rem;">Generate a PIN before doors open. Keep it offline with staff — rotating invalidates the previous code.</p>
  <?php endif; ?>

  <form method="post" class="admin-actions">
    <?= csrf_field() ?>
    <button class="admin-btn admin-btn-magenta" type="submit" onclick="return confirm('Generate a new PIN? The previous PIN will stop working.')">
      Generate new PIN
    </button>
  </form>
</div>
<?php
render_admin('Gate PIN', ob_get_clean(), 'gate');
