<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
$admin = require_admin();

$message = flash('ok');
$error = null;

if (request_method() === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
    $action = post_string('action');
    try {
        if ($action === 'delete') {
            ticket_delete((int) ($_POST['ticket_id'] ?? 0));
            flash('ok', 'Ticket removed or deactivated.');
            redirect(app_url('admin/tickets.php'));
        }

        $id = (int) ($_POST['ticket_id'] ?? 0) ?: null;
        $title = post_string('title');
        $description = post_string('description');
        $pricingMode = post_string('pricing_mode') === 'tiered' ? 'tiered' : 'single';
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '') {
            throw new InvalidArgumentException('Title is required.');
        }

        $labels = $_POST['price_label'] ?? [];
        $amounts = $_POST['price_amount'] ?? [];
        $rows = [];
        if (!is_array($labels)) {
            $labels = [];
        }
        if (!is_array($amounts)) {
            $amounts = [];
        }

        if ($pricingMode === 'single') {
            $amount = (float) ($amounts[0] ?? 0);
            if ($amount <= 0) {
                throw new InvalidArgumentException('Enter a valid amount.');
            }
            $rows[] = ['label' => '', 'amount' => $amount];
        } else {
            foreach ($amounts as $i => $amountRaw) {
                $amount = (float) $amountRaw;
                $label = trim((string) ($labels[$i] ?? ''));
                if ($amount <= 0 && $label === '') {
                    continue;
                }
                if ($label === '' || $amount <= 0) {
                    throw new InvalidArgumentException('Each tier needs a label and amount.');
                }
                $rows[] = ['label' => $label, 'amount' => $amount];
            }
            if (!$rows) {
                throw new InvalidArgumentException('Add at least one price tier.');
            }
        }

        ticket_save([
            'title' => $title,
            'description' => $description,
            'pricing_mode' => $pricingMode,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ], $rows, $id);

        flash('ok', 'Ticket saved.');
        redirect(app_url('admin/tickets.php'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? ticket_find($editId) : null;
$tickets = tickets_all(false);

ob_start();
?>
<div class="admin-page-head">
  <div>
    <h2>Tickets</h2>
    <p>These drive the landing-page ticket cards and booking prices.</p>
  </div>
  <div class="admin-actions">
    <a class="admin-btn admin-btn-secondary" href="#ticket-form"><?= $edit ? 'Editing below' : 'Add ticket' ?></a>
  </div>
</div>

<?php if ($message): ?><div class="admin-alert admin-alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-panel">
  <div class="admin-panel-head">
    <h3>Catalog</h3>
  </div>
  <?php if (!$tickets): ?>
    <div class="admin-empty">No tickets yet. Create one below.</div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>Title</th><th>Mode</th><th>Prices</th><th>Active</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($tickets as $t): ?>
          <tr>
            <td><?= e($t['title']) ?></td>
            <td><?= e($t['pricing_mode']) ?></td>
            <td>
              <?php foreach ($t['prices'] as $p): ?>
                <?= e($p['label'] ? $p['label'] . ': ' : '') ?><?= e(format_ngn($p['amount'])) ?><br />
              <?php endforeach; ?>
            </td>
            <td>
              <span class="admin-status <?= (int) $t['is_active'] ? 'admin-status-paid' : 'admin-status-failed' ?>">
                <?= (int) $t['is_active'] ? 'Yes' : 'No' ?>
              </span>
            </td>
            <td><a href="?edit=<?= (int) $t['id'] ?>#ticket-form">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="admin-panel" id="ticket-form">
  <div class="admin-panel-head">
    <h3><?= $edit ? 'Edit ticket' : 'Add ticket' ?></h3>
  </div>

  <form method="post" class="admin-form">
    <?= csrf_field() ?>
    <input type="hidden" name="ticket_id" value="<?= $edit ? (int) $edit['id'] : 0 ?>" />

    <label class="admin-label">Title
      <input type="text" name="title" required value="<?= e($edit['title'] ?? post_string('title')) ?>" />
    </label>
    <label class="admin-label">Description
      <textarea name="description"><?= e($edit['description'] ?? post_string('description')) ?></textarea>
    </label>

    <div class="admin-form-grid two">
      <label class="admin-label">Pricing mode
        <select name="pricing_mode" id="pricing_mode">
          <?php $mode = $edit['pricing_mode'] ?? post_string('pricing_mode') ?: 'tiered'; ?>
          <option value="single" <?= $mode === 'single' ? 'selected' : '' ?>>Single amount</option>
          <option value="tiered" <?= $mode === 'tiered' ? 'selected' : '' ?>>Tiered (Guys/Girls/etc.)</option>
        </select>
      </label>
      <label class="admin-label">Sort order
        <input type="number" name="sort_order" value="<?= e((string) ($edit['sort_order'] ?? '0')) ?>" />
      </label>
    </div>

    <label class="admin-check">
      <input type="checkbox" name="is_active" value="1" <?= !isset($edit) || (int) ($edit['is_active'] ?? 1) === 1 ? 'checked' : '' ?> />
      Active on landing page
    </label>

    <div>
      <div class="admin-stat-label" style="margin-bottom:0.55rem;">Prices</div>
      <div class="admin-tier-rows" id="tier-rows">
        <?php
        $seedPrices = $edit['prices'] ?? [['label' => 'Guys', 'amount' => '100000'], ['label' => 'Girls', 'amount' => '70000']];
        if (($edit['pricing_mode'] ?? '') === 'single' && $edit['prices']) {
            $seedPrices = [['label' => '', 'amount' => $edit['prices'][0]['amount']]];
        }
        foreach ($seedPrices as $p):
        ?>
          <div class="admin-tier-row">
            <label class="admin-label">Label<input type="text" name="price_label[]" value="<?= e((string) $p['label']) ?>" placeholder="Guys" /></label>
            <label class="admin-label">Amount (NGN)<input type="number" step="0.01" min="0" name="price_amount[]" value="<?= e((string) $p['amount']) ?>" required /></label>
            <button type="button" class="admin-btn admin-btn-ghost remove-tier">Remove</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="admin-btn admin-btn-ghost" id="add-tier" style="margin-top:0.75rem;">Add price tier</button>
    </div>

    <div class="admin-actions">
      <button class="admin-btn admin-btn-primary" type="submit">Save ticket</button>
      <?php if ($edit): ?>
        <button class="admin-btn admin-btn-danger" type="submit" name="action" value="delete" onclick="return confirm('Remove/deactivate this ticket?')">Delete</button>
        <a class="admin-btn admin-btn-ghost" href="<?= e(app_url('admin/tickets.php')) ?>">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<script>
(function () {
  var rows = document.getElementById('tier-rows');
  document.getElementById('add-tier').addEventListener('click', function () {
    var div = document.createElement('div');
    div.className = 'admin-tier-row';
    div.innerHTML = '<label class="admin-label">Label<input type="text" name="price_label[]" placeholder="Label" /></label>' +
      '<label class="admin-label">Amount (NGN)<input type="number" step="0.01" min="0" name="price_amount[]" required /></label>' +
      '<button type="button" class="admin-btn admin-btn-ghost remove-tier">Remove</button>';
    rows.appendChild(div);
  });
  rows.addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-tier')) {
      var items = rows.querySelectorAll('.admin-tier-row');
      if (items.length > 1) e.target.closest('.admin-tier-row').remove();
    }
  });
})();
</script>
<?php
render_admin('Tickets', ob_get_clean(), 'tickets');
