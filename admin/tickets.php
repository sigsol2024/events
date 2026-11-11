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
if ($editId <= 0 && request_method() === 'POST') {
    $editId = (int) ($_POST['ticket_id'] ?? 0);
}
$edit = $editId > 0 ? ticket_find($editId) : null;
$tickets = tickets_all(false);
$openModal = $edit !== null || $error !== null || isset($_GET['new']);
$mode = post_string('pricing_mode') !== ''
    ? (post_string('pricing_mode') === 'tiered' ? 'tiered' : 'single')
    : (($edit['pricing_mode'] ?? 'tiered') === 'single' ? 'single' : 'tiered');

$activeChecked = true;
if (request_method() === 'POST') {
    $activeChecked = isset($_POST['is_active']);
} elseif ($edit) {
    $activeChecked = (int) ($edit['is_active'] ?? 1) === 1;
}

if ($edit && ($edit['pricing_mode'] ?? '') === 'single' && !empty($edit['prices'])) {
    $seedPrices = [['label' => '', 'amount' => $edit['prices'][0]['amount']]];
} elseif ($edit && !empty($edit['prices'])) {
    $seedPrices = $edit['prices'];
} elseif ($mode === 'single') {
    $seedPrices = [['label' => '', 'amount' => post_string('price_amount') ?: '']];
} else {
    $seedPrices = [
        ['label' => 'Guys', 'amount' => '100000'],
        ['label' => 'Girls', 'amount' => '70000'],
    ];
}

ob_start();
?>
<div class="admin-page-head">
  <div>
    <h2>Tickets</h2>
    <p>These drive the landing-page ticket cards and booking prices.</p>
  </div>
  <div class="admin-actions">
    <button class="admin-btn admin-btn-primary" type="button" data-ticket-open>Add ticket</button>
  </div>
</div>

<?php if ($message): ?><div class="admin-alert admin-alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-panel">
  <div class="admin-panel-head">
    <h3>Catalog</h3>
  </div>
  <?php if (!$tickets): ?>
    <div class="admin-empty">No tickets yet. Tap “Add ticket” to create one.</div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>Title</th><th>Mode</th><th>Prices</th><th>Active</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($tickets as $t): ?>
          <tr>
            <td data-label="Title"><?= e($t['title']) ?></td>
            <td data-label="Mode"><?= e($t['pricing_mode']) ?></td>
            <td data-label="Prices">
              <?php foreach ($t['prices'] as $p): ?>
                <?= e($p['label'] ? $p['label'] . ': ' : '') ?><?= e(format_ngn($p['amount'])) ?><br />
              <?php endforeach; ?>
            </td>
            <td data-label="Active">
              <span class="admin-status <?= (int) $t['is_active'] ? 'admin-status-paid' : 'admin-status-failed' ?>">
                <?= (int) $t['is_active'] ? 'Yes' : 'No' ?>
              </span>
            </td>
            <td data-label="">
              <a class="admin-btn admin-btn-secondary admin-btn-sm" href="?edit=<?= (int) $t['id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="admin-modal <?= $openModal ? 'is-open' : '' ?>" id="ticket-modal" aria-hidden="<?= $openModal ? 'false' : 'true' ?>">
  <button class="admin-modal-backdrop" type="button" data-ticket-close aria-label="Close"></button>
  <div class="admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ticket-modal-title">
    <div class="admin-modal-head">
      <h3 id="ticket-modal-title"><?= $edit ? 'Edit ticket' : 'Add ticket' ?></h3>
      <a class="admin-btn admin-btn-ghost admin-btn-sm" href="<?= e(app_url('admin/tickets.php')) ?>" data-ticket-close>Close</a>
    </div>

    <form method="post" class="admin-form" id="ticket-form">
      <?= csrf_field() ?>
      <input type="hidden" name="ticket_id" value="<?= $edit ? (int) $edit['id'] : (int) ($_POST['ticket_id'] ?? 0) ?>" />

      <label class="admin-label">Title
        <input type="text" name="title" required value="<?= e($edit['title'] ?? post_string('title')) ?>" />
      </label>
      <label class="admin-label">Description
        <textarea name="description"><?= e($edit['description'] ?? post_string('description')) ?></textarea>
      </label>

      <div class="admin-form-grid two">
        <label class="admin-label">Pricing mode
          <select name="pricing_mode" id="pricing_mode">
            <option value="single" <?= $mode === 'single' ? 'selected' : '' ?>>Single amount</option>
            <option value="tiered" <?= $mode === 'tiered' ? 'selected' : '' ?>>Tiered (Guys/Girls/etc.)</option>
          </select>
        </label>
        <label class="admin-label">Sort order
          <input type="number" name="sort_order" value="<?= e((string) ($edit['sort_order'] ?? ($_POST['sort_order'] ?? '0'))) ?>" />
        </label>
      </div>

      <label class="admin-check">
        <input type="checkbox" name="is_active" value="1" <?= $activeChecked ? 'checked' : '' ?> />
        Active on landing page
      </label>

      <div>
        <div class="admin-stat-label" style="margin-bottom:0.55rem;">Prices</div>
        <div class="admin-tier-rows" id="tier-rows" data-mode="<?= e($mode) ?>">
          <?php foreach ($seedPrices as $p): ?>
            <div class="admin-tier-row <?= $mode === 'single' ? 'is-single' : 'is-tiered' ?>">
              <label class="admin-label tier-label" <?= $mode === 'single' ? 'hidden' : '' ?>>Label
                <input type="text" name="price_label[]" value="<?= e((string) ($mode === 'single' ? '' : $p['label'])) ?>" placeholder="Guys" <?= $mode === 'single' ? 'disabled' : '' ?> />
              </label>
              <label class="admin-label">Amount (NGN)
                <input type="number" step="0.01" min="0" name="price_amount[]" value="<?= e((string) $p['amount']) ?>" required />
              </label>
              <button type="button" class="admin-btn admin-btn-ghost remove-tier" <?= $mode === 'single' ? 'hidden' : '' ?>>Remove</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="admin-btn admin-btn-ghost" id="add-tier" style="margin-top:0.75rem;" <?= $mode === 'single' ? 'hidden' : '' ?>>Add price tier</button>
      </div>

      <div class="admin-actions">
        <button class="admin-btn admin-btn-primary" type="submit">Save ticket</button>
        <?php if ($edit): ?>
          <button class="admin-btn admin-btn-danger" type="submit" name="action" value="delete" onclick="return confirm('Remove/deactivate this ticket?')">Delete</button>
        <?php endif; ?>
        <a class="admin-btn admin-btn-ghost" href="<?= e(app_url('admin/tickets.php')) ?>">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('ticket-modal');
  var rows = document.getElementById('tier-rows');
  var modeSelect = document.getElementById('pricing_mode');
  var addTier = document.getElementById('add-tier');

  function setModal(open) {
    modal.classList.toggle('is-open', open);
    modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.classList.toggle('admin-modal-open', open);
  }

  document.querySelectorAll('[data-ticket-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      window.location.href = <?= json_encode(app_url('admin/tickets.php?new=1')) ?>;
    });
  });

  document.querySelectorAll('[data-ticket-close]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (el.tagName === 'A') return;
      e.preventDefault();
      window.location.href = <?= json_encode(app_url('admin/tickets.php')) ?>;
    });
  });

  if (modal.classList.contains('is-open')) {
    document.body.classList.add('admin-modal-open');
  }

  function tierHtml(label, amount, single) {
    return '<div class="admin-tier-row ' + (single ? 'is-single' : 'is-tiered') + '">' +
      '<label class="admin-label tier-label"' + (single ? ' hidden' : '') + '>Label' +
      '<input type="text" name="price_label[]" value="' + label + '" placeholder="Guys"' + (single ? ' disabled' : '') + ' /></label>' +
      '<label class="admin-label">Amount (NGN)' +
      '<input type="number" step="0.01" min="0" name="price_amount[]" value="' + amount + '" required /></label>' +
      '<button type="button" class="admin-btn admin-btn-ghost remove-tier"' + (single ? ' hidden' : '') + '>Remove</button>' +
      '</div>';
  }

  function applyMode(mode) {
    var single = mode === 'single';
    rows.setAttribute('data-mode', mode);
    addTier.hidden = single;

    if (single) {
      var firstAmount = '';
      var amountInput = rows.querySelector('input[name="price_amount[]"]');
      if (amountInput) firstAmount = amountInput.value;
      rows.innerHTML = tierHtml('', firstAmount, true);
      return;
    }

    var current = [];
    rows.querySelectorAll('.admin-tier-row').forEach(function (row) {
      var label = row.querySelector('input[name="price_label[]"]');
      var amount = row.querySelector('input[name="price_amount[]"]');
      current.push({
        label: label && !label.disabled ? label.value : '',
        amount: amount ? amount.value : ''
      });
    });
    if (!current.length || (current.length === 1 && !current[0].label && current[0].amount === '')) {
      current = [{ label: 'Guys', amount: '100000' }, { label: 'Girls', amount: '70000' }];
    } else if (current.length === 1 && !current[0].label) {
      current = [
        { label: 'Guys', amount: current[0].amount || '100000' },
        { label: 'Girls', amount: '70000' }
      ];
    }
    rows.innerHTML = current.map(function (row) {
      return tierHtml(row.label || '', row.amount || '', false);
    }).join('');
  }

  modeSelect.addEventListener('change', function () {
    applyMode(modeSelect.value);
  });

  addTier.addEventListener('click', function () {
    if (modeSelect.value === 'single') return;
    rows.insertAdjacentHTML('beforeend', tierHtml('', '', false));
  });

  rows.addEventListener('click', function (e) {
    if (!e.target.classList.contains('remove-tier')) return;
    if (modeSelect.value === 'single') return;
    var items = rows.querySelectorAll('.admin-tier-row');
    if (items.length > 1) e.target.closest('.admin-tier-row').remove();
  });
})();
</script>
<?php
render_admin('Tickets', ob_get_clean(), 'tickets');
