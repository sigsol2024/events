<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
$admin = require_admin();

$message = flash('ok');
$error = null;
$formState = null;

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
        $formState = [
            'id' => (int) ($_POST['ticket_id'] ?? 0),
            'title' => post_string('title'),
            'description' => post_string('description'),
            'pricing_mode' => post_string('pricing_mode') === 'tiered' ? 'tiered' : 'single',
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'prices' => [],
        ];
        $postedLabels = $_POST['price_label'] ?? [];
        $postedAmounts = $_POST['price_amount'] ?? [];
        if (!is_array($postedLabels)) {
            $postedLabels = [];
        }
        if (!is_array($postedAmounts)) {
            $postedAmounts = [];
        }
        foreach ($postedAmounts as $i => $amount) {
            $formState['prices'][] = [
                'label' => (string) ($postedLabels[$i] ?? ''),
                'amount' => (string) $amount,
            ];
        }
    }
}

$tickets = tickets_all(false);
$ticketsJson = array_map(static function (array $t): array {
    return [
        'id' => (int) $t['id'],
        'title' => (string) $t['title'],
        'description' => (string) ($t['description'] ?? ''),
        'pricing_mode' => (string) $t['pricing_mode'],
        'sort_order' => (int) $t['sort_order'],
        'is_active' => (int) $t['is_active'],
        'prices' => array_map(static function (array $p): array {
            return [
                'label' => (string) ($p['label'] ?? ''),
                'amount' => money_string($p['amount']),
            ];
        }, $t['prices'] ?? []),
    ];
}, $tickets);

$openModal = $error !== null;
$bootTicket = $formState;

ob_start();
?>
<div class="admin-page-head">
  <div>
    <h2>Tickets</h2>
    <p>These drive the landing-page ticket cards and booking prices.</p>
  </div>
  <div class="admin-actions">
    <button class="admin-btn admin-btn-primary" type="button" id="add-ticket-btn">Add ticket</button>
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
              <button
                class="admin-btn admin-btn-secondary admin-btn-sm"
                type="button"
                data-edit-ticket="<?= (int) $t['id'] ?>"
              >Edit</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="admin-modal <?= $openModal ? 'is-open' : '' ?>" id="ticket-modal" aria-hidden="<?= $openModal ? 'false' : 'true' ?>">
  <button class="admin-modal-backdrop" type="button" id="ticket-modal-backdrop" aria-label="Close"></button>
  <div class="admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ticket-modal-title">
    <div class="admin-modal-head">
      <h3 id="ticket-modal-title">Add ticket</h3>
      <button class="admin-btn admin-btn-ghost admin-btn-sm" type="button" id="ticket-modal-close">Close</button>
    </div>

    <form method="post" class="admin-form" id="ticket-form">
      <?= csrf_field() ?>
      <input type="hidden" name="ticket_id" id="ticket_id" value="0" />

      <label class="admin-label">Title
        <input type="text" name="title" id="ticket_title" required />
      </label>
      <label class="admin-label">Description
        <textarea name="description" id="ticket_description"></textarea>
      </label>

      <div class="admin-form-grid two">
        <label class="admin-label">Pricing mode
          <select name="pricing_mode" id="pricing_mode">
            <option value="single">Single amount</option>
            <option value="tiered">Tiered (Guys/Girls/etc.)</option>
          </select>
        </label>
        <label class="admin-label">Sort order
          <input type="number" name="sort_order" id="ticket_sort_order" value="0" />
        </label>
      </div>

      <label class="admin-check">
        <input type="checkbox" name="is_active" id="ticket_is_active" value="1" checked />
        Active on landing page
      </label>

      <div>
        <div class="admin-stat-label" style="margin-bottom:0.55rem;" id="prices-heading">Price</div>
        <div class="admin-tier-rows" id="tier-rows" data-mode="single"></div>
        <button type="button" class="admin-btn admin-btn-ghost" id="add-tier" style="margin-top:0.75rem;" hidden>Add price tier</button>
      </div>

      <div class="admin-actions">
        <button class="admin-btn admin-btn-primary" type="submit">Save ticket</button>
        <button class="admin-btn admin-btn-danger" type="submit" name="action" value="delete" id="ticket-delete-btn" hidden onclick="return confirm('Remove/deactivate this ticket?')">Delete</button>
        <button class="admin-btn admin-btn-ghost" type="button" id="ticket-cancel-btn">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var catalog = <?= json_encode($ticketsJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var bootTicket = <?= json_encode($bootTicket, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var openOnBoot = <?= $openModal ? 'true' : 'false' ?>;

  var modal = document.getElementById('ticket-modal');
  var rows = document.getElementById('tier-rows');
  var modeSelect = document.getElementById('pricing_mode');
  var addTier = document.getElementById('add-tier');
  var pricesHeading = document.getElementById('prices-heading');
  var titleEl = document.getElementById('ticket-modal-title');
  var idInput = document.getElementById('ticket_id');
  var deleteBtn = document.getElementById('ticket-delete-btn');

  function escapeAttr(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;');
  }

  function setModal(open) {
    modal.classList.toggle('is-open', open);
    modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.classList.toggle('admin-modal-open', open);
  }

  function singleRowHtml(amount) {
    return '<div class="admin-tier-row is-single">' +
      '<label class="admin-label">Amount (NGN)' +
      '<input type="number" step="0.01" min="0" name="price_amount[]" value="' + escapeAttr(amount || '') + '" required /></label>' +
      '</div>';
  }

  function tierRowHtml(label, amount) {
    return '<div class="admin-tier-row is-tiered">' +
      '<label class="admin-label">Label' +
      '<input type="text" name="price_label[]" value="' + escapeAttr(label || '') + '" placeholder="Guys" required /></label>' +
      '<label class="admin-label">Amount (NGN)' +
      '<input type="number" step="0.01" min="0" name="price_amount[]" value="' + escapeAttr(amount || '') + '" required /></label>' +
      '<button type="button" class="admin-btn admin-btn-ghost remove-tier">Remove</button>' +
      '</div>';
  }

  function renderPrices(mode, prices) {
    var single = mode === 'single';
    rows.setAttribute('data-mode', mode);
    addTier.hidden = single;
    pricesHeading.textContent = single ? 'Price' : 'Prices';

    if (single) {
      var amount = prices && prices[0] ? prices[0].amount : '';
      rows.innerHTML = singleRowHtml(amount);
      return;
    }

    var list = Array.isArray(prices) && prices.length
      ? prices
      : [{ label: 'Guys', amount: '100000' }, { label: 'Girls', amount: '70000' }];
    rows.innerHTML = list.map(function (row) {
      return tierRowHtml(row.label || '', row.amount || '');
    }).join('');
  }

  function fillForm(ticket) {
    var isEdit = !!(ticket && ticket.id);
    titleEl.textContent = isEdit ? 'Edit ticket' : 'Add ticket';
    idInput.value = isEdit ? String(ticket.id) : '0';
    document.getElementById('ticket_title').value = ticket && ticket.title ? ticket.title : '';
    document.getElementById('ticket_description').value = ticket && ticket.description ? ticket.description : '';
    document.getElementById('ticket_sort_order').value = ticket && ticket.sort_order != null ? String(ticket.sort_order) : '0';
    document.getElementById('ticket_is_active').checked = !ticket || ticket.is_active == null ? true : Number(ticket.is_active) === 1;
    deleteBtn.hidden = !isEdit;

    var mode = ticket && ticket.pricing_mode === 'tiered' ? 'tiered' : 'single';
    modeSelect.value = mode;
    renderPrices(mode, ticket && ticket.prices ? ticket.prices : (mode === 'single' ? [{ amount: '' }] : null));
  }

  function openCreate() {
    fillForm(null);
    setModal(true);
    document.getElementById('ticket_title').focus();
  }

  function openEdit(id) {
    var ticket = catalog.find(function (row) { return Number(row.id) === Number(id); });
    if (!ticket) return;
    fillForm(ticket);
    setModal(true);
  }

  function closeModal() {
    setModal(false);
  }

  document.getElementById('add-ticket-btn').addEventListener('click', openCreate);
  document.getElementById('ticket-modal-close').addEventListener('click', closeModal);
  document.getElementById('ticket-cancel-btn').addEventListener('click', closeModal);
  document.getElementById('ticket-modal-backdrop').addEventListener('click', closeModal);

  document.querySelectorAll('[data-edit-ticket]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openEdit(btn.getAttribute('data-edit-ticket'));
    });
  });

  modeSelect.addEventListener('change', function () {
    var mode = modeSelect.value;
    var amounts = [];
    rows.querySelectorAll('input[name="price_amount[]"]').forEach(function (input) {
      amounts.push(input.value);
    });

    if (mode === 'single') {
      renderPrices('single', [{ amount: amounts[0] || '' }]);
      return;
    }

    var labels = [];
    rows.querySelectorAll('input[name="price_label[]"]').forEach(function (input) {
      labels.push(input.value);
    });

    if (!labels.length) {
      renderPrices('tiered', [
        { label: 'Guys', amount: amounts[0] || '100000' },
        { label: 'Girls', amount: '70000' }
      ]);
      return;
    }

    renderPrices('tiered', amounts.map(function (amount, i) {
      return { label: labels[i] || '', amount: amount || '' };
    }));
  });

  addTier.addEventListener('click', function () {
    if (modeSelect.value === 'single') return;
    rows.insertAdjacentHTML('beforeend', tierRowHtml('', ''));
  });

  rows.addEventListener('click', function (e) {
    if (!e.target.classList.contains('remove-tier')) return;
    if (modeSelect.value === 'single') return;
    var items = rows.querySelectorAll('.admin-tier-row');
    if (items.length > 1) e.target.closest('.admin-tier-row').remove();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) {
      closeModal();
    }
  });

  if (openOnBoot && bootTicket) {
    fillForm(bootTicket);
    setModal(true);
  } else {
    fillForm(null);
  }
})();
</script>
<?php
render_admin('Tickets', ob_get_clean(), 'tickets');
