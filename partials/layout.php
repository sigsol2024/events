<?php
/** @var string $title */
/** @var string $content */
/** @var string $bodyClass */
/** @var bool $showNav */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($title) ?> — <?= e((string) config('app_name', "Tee's Beach House")) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= e(app_url('styles.css')) ?>" />
  <link rel="stylesheet" href="<?= e(app_url('app.css')) ?>" />
</head>
<body class="<?= e($bodyClass) ?>">
<?php if ($showNav): ?>
  <header class="site-nav is-scrolled">
    <a class="nav-brand" href="<?= e(app_url('index.html')) ?>">Tee's Beach House</a>
    <nav class="nav-links" aria-label="Primary">
      <a href="<?= e(app_url('index.html#tickets')) ?>">Tickets</a>
      <a class="nav-cta" href="<?= e(app_url('index.html#tickets')) ?>">Book</a>
    </nav>
  </header>
<?php endif; ?>
  <main class="app-main">
    <?= $content ?>
  </main>
  <footer class="site-footer">
    <p>Temi Presents · Tee's Beach House Edition</p>
    <p>Love Beach, Eleko Lagos</p>
  </footer>
</body>
</html>
