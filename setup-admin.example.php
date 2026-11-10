<?php
/**
 * TEMPLATE — copy to setup-admin.php on the server (do not commit setup-admin.php).
 * After creating/resetting your admin account, DELETE setup-admin.php from the server.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$message = null;
$error = null;

if (request_method() === 'POST') {
    $setupKey = post_string('setup_key');
    $email = post_string('email');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    $configuredKey = (string) config('setup_key', '');
    if ($configuredKey === '' || $setupKey === '' || !hash_equals($configuredKey, $setupKey)) {
        $error = 'Invalid setup key.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid admin email.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            create_or_reset_admin($email, $password);
            $message = 'Admin account created/updated for ' . $email . '. Delete this file from the server now, then sign in at /admin/login.php';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

ob_start();
?>
<section class="app-shell">
  <div class="app-card">
    <p class="eyebrow">Bootstrap</p>
    <h1>Create / reset admin</h1>
    <p>Temporary page. Upload as <code>setup-admin.php</code>, use once, then delete.</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <form method="post" class="form-grid">
      <label>Setup key (from config.php)
        <input type="password" name="setup_key" required />
      </label>
      <label>Admin email
        <input type="email" name="email" required />
      </label>
      <label>Password
        <input type="password" name="password" required minlength="8" />
      </label>
      <label>Confirm password
        <input type="password" name="password_confirm" required minlength="8" />
      </label>
      <button class="btn btn-primary" type="submit">Save admin account</button>
    </form>
  </div>
</section>
<?php
render_layout('Setup admin', ob_get_clean(), ['show_nav' => false]);
