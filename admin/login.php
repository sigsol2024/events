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

ob_start();
?>
<section class="app-shell">
  <div class="app-card">
    <p class="eyebrow">Admin</p>
    <h1>Sign in</h1>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <label>Email<input type="email" name="email" required value="<?= e(post_string('email')) ?>" /></label>
      <label>Password<input type="password" name="password" required /></label>
      <button class="btn btn-primary" type="submit">Login</button>
    </form>
  </div>
</section>
<?php
render_layout('Admin login', ob_get_clean(), ['show_nav' => false]);
