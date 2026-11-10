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
<section class="app-shell">
  <div class="app-card">
    <?php include __DIR__ . '/_nav.php'; ?>
    <p class="eyebrow">Security</p>
    <h1>Gate verification PIN</h1>
    <p>Staff enter this PIN after scanning a guest QR to unlock booking details. Buyers never receive this PIN.</p>
    <p>Status: <?= gate_pin_hash() ? 'A PIN is currently set.' : 'No PIN set yet.' ?></p>

    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($newPin): ?>
      <div class="alert" style="font-size:1.6rem;letter-spacing:0.2em;text-align:center;"><?= e($newPin) ?></div>
      <p style="color:rgba(247,239,217,0.75);">Share this with gate staff only. Generating again replaces the old PIN immediately.</p>
    <?php endif; ?>

    <form method="post" class="form-actions">
      <?= csrf_field() ?>
      <button class="btn btn-magenta" type="submit" onclick="return confirm('Generate a new PIN? The previous PIN will stop working.')">
        Generate new PIN
      </button>
    </form>
  </div>
</section>
<?php
render_layout('Gate PIN', ob_get_clean(), ['show_nav' => false]);
