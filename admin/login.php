<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

if (admin_user()) {
    redirect(app_url('admin/index.php'));
}

$error = null;
if (request_method() === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session.';
    } elseif (attempt_admin_login(post_string('email'), (string) ($_POST['password'] ?? ''))) {
        redirect(app_url('admin/index.php'));
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= e(asset_url('admin/admin.css')) ?>" />
</head>
<body class="admin-body">
  <div class="admin-login-shell">
    <div class="admin-login-card">
      <p class="admin-brand-kicker">Tee’s Beach House</p>
      <h1>Admin sign in</h1>
      <p>Manage tickets, bookings, and gate access.</p>
      <?php if ($error): ?><div class="admin-alert admin-alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="admin-form">
        <?= csrf_field() ?>
        <label class="admin-label">Email
          <input type="email" name="email" required value="<?= e(post_string('email')) ?>" autocomplete="username" />
        </label>
        <label class="admin-label">Password
          <input type="password" name="password" required autocomplete="current-password" />
        </label>
        <button class="admin-btn admin-btn-primary" type="submit" style="background:#dc2626;">Sign in</button>
      </form>
    </div>
  </div>
</body>
</html>
