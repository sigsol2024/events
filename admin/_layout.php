<?php
/** @var string $title */
/** @var string $content */
/** @var array $admin */
/** @var string $active */

$nav = [
    ['key' => 'dashboard', 'label' => 'Overview', 'href' => app_url('admin/index.php'), 'section' => 'Main'],
    ['key' => 'bookings', 'label' => 'Bookings', 'href' => app_url('admin/bookings.php'), 'section' => 'Main'],
    ['key' => 'tickets', 'label' => 'Tickets', 'href' => app_url('admin/tickets.php'), 'section' => 'Catalog'],
    ['key' => 'gate', 'label' => 'Gate PIN', 'href' => app_url('admin/gate-pin.php'), 'section' => 'Security'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($title) ?> — Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= e(app_url('admin/admin.css')) ?>" />
</head>
<body class="admin-body">
  <div class="admin-shell" data-admin-shell>
    <button class="admin-overlay" type="button" data-admin-close aria-label="Close menu"></button>

    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
      <div class="admin-sidebar-head">
        <div class="admin-brand">
          <p class="admin-brand-kicker">Admin</p>
          <p class="admin-brand-title">Beach House</p>
        </div>
        <button class="admin-icon-btn admin-sidebar-close" type="button" data-admin-close aria-label="Close menu">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
      </div>

      <nav class="admin-nav-list">
        <?php
        $lastSection = null;
        foreach ($nav as $item):
          if ($lastSection !== $item['section']):
            $lastSection = $item['section'];
        ?>
          <p class="admin-nav-section"><?= e($item['section']) ?></p>
        <?php endif; ?>
          <a
            class="admin-nav-link <?= $active === $item['key'] ? 'is-active' : '' ?>"
            href="<?= e($item['href']) ?>"
          >
            <?php if ($item['key'] === 'dashboard'): ?>
              <svg class="admin-nav-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 10.5L12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
            <?php elseif ($item['key'] === 'bookings'): ?>
              <svg class="admin-nav-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 4h10a2 2 0 0 1 2 2v14l-7-3-7 3V6a2 2 0 0 1 2-2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
            <?php elseif ($item['key'] === 'tickets'): ?>
              <svg class="admin-nav-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 9a2 2 0 0 0 2-2V5h12v2a2 2 0 1 0 0 4v2a2 2 0 1 0 0 4v2H6v-2a2 2 0 1 0 0-4V9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
            <?php else: ?>
              <svg class="admin-nav-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V8a4 4 0 1 1 8 0v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <?php endif; ?>
            <span><?= e($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="admin-sidebar-foot">
        <div class="admin-user">
          <strong><?= e((string) ($admin['email'] ?? 'Admin')) ?></strong>
          <span>Signed in</span>
        </div>
        <a class="admin-btn admin-btn-ghost" style="color:#f7efd9;border-color:rgba(240,196,58,.25);" href="<?= e(app_url('admin/logout.php')) ?>">Log out</a>
        <a class="admin-btn admin-btn-ghost" style="color:#f7efd9;border-color:rgba(240,196,58,.25);" href="<?= e(app_url('index.html')) ?>" target="_blank" rel="noopener">View event site</a>
      </div>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <div class="admin-topbar-left">
          <button class="admin-menu-btn" type="button" data-admin-open aria-controls="admin-sidebar" aria-expanded="false" aria-label="Open menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          </button>
          <h1><?= e($title) ?></h1>
        </div>
        <div class="admin-topbar-actions">
          <a class="admin-btn admin-btn-secondary" href="<?= e(app_url('admin/bookings.php?status=paid')) ?>">Paid bookings</a>
        </div>
      </header>

      <main class="admin-content">
        <?= $content ?>
      </main>
    </div>
  </div>
  <script src="<?= e(app_url('admin/admin.js')) ?>"></script>
</body>
</html>
