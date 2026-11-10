<?php
/** @var array $admin */
?>
<div class="admin-nav">
  <a href="<?= e(app_url('admin/index.php')) ?>">Dashboard</a>
  <a href="<?= e(app_url('admin/tickets.php')) ?>">Tickets</a>
  <a href="<?= e(app_url('admin/bookings.php')) ?>">Bookings</a>
  <a href="<?= e(app_url('admin/gate-pin.php')) ?>">Gate PIN</a>
  <a href="<?= e(app_url('admin/logout.php')) ?>">Logout (<?= e($admin['email']) ?>)</a>
</div>
