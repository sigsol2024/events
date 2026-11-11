<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$ticketId = (int) ($_GET['ticket_id'] ?? $_POST['ticket_id'] ?? 0);
$ticket = $ticketId ? ticket_find($ticketId, true) : null;
$error = null;
$cancelled = isset($_GET['cancelled']);
$minAge = (int) config('min_age', 18);
$maxDob = (new DateTimeImmutable('today'))->modify('-' . $minAge . ' years')->format('Y-m-d');
$minDob = (new DateTimeImmutable('today'))->modify('-100 years')->format('Y-m-d');

if (!$ticket) {
    $error = 'That ticket is unavailable. Please choose another from the event page.';
}

if ($ticket && request_method() === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session. Please try again.';
    } else {
        try {
            $fullName = post_string('full_name');
            $email = post_string('email');
            $phone = post_string('phone');
            $dob = post_string('date_of_birth');
            $gender = strtolower(post_string('gender'));
            $priceId = (int) ($_POST['price_id'] ?? 0) ?: null;

            if ($fullName === '' || $email === '' || $phone === '' || $dob === '') {
                throw new InvalidArgumentException('Please complete all required fields.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Enter a valid email address.');
            }
            if (!in_array($gender, ['male', 'female'], true)) {
                throw new InvalidArgumentException('Select your gender.');
            }
            if ($dob > $maxDob) {
                throw new InvalidArgumentException('You must be ' . $minAge . ' or older to book.');
            }
            if (!is_adult($dob, $minAge)) {
                throw new InvalidArgumentException('You must be ' . $minAge . ' or older to book.');
            }

            $phoneNormalized = normalize_phone_e164($phone);
            $phoneDigits = preg_replace('/\D+/', '', $phoneNormalized) ?? '';
            if (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
                throw new InvalidArgumentException('Enter a valid phone number (e.g. 08012345678).');
            }

            // Tiered: male → Guys, female → Girls when those labels exist.
            $priceId = resolve_price_id_for_gender($ticket, $gender, $priceId);

            $resolved = resolve_ticket_amount($ticket, $priceId);
            $booking = booking_create([
                'ticket_id' => (int) $ticket['id'],
                'ticket_title' => $ticket['title'],
                'price_id' => $resolved['price_id'],
                'price_label' => $resolved['price_label'],
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phoneNormalized,
                'date_of_birth' => $dob,
                'gender' => $gender,
                'amount' => $resolved['amount'],
                'currency' => (string) config('currency', 'NGN'),
            ]);

            try {
                $checkout = bachs_create_checkout($booking);
            } catch (Throwable $e) {
                booking_set_status((int) $booking['id'], 'failed');
                throw $e;
            }

            $checkoutId = (string) ($checkout['checkout_id'] ?? '');
            $checkoutUrl = (string) ($checkout['checkout_url'] ?? '');
            if ($checkoutId === '' || $checkoutUrl === '') {
                booking_set_status((int) $booking['id'], 'failed');
                throw new RuntimeException('Payment checkout could not be started. Try again.');
            }

            booking_update_checkout((int) $booking['id'], $checkoutId, (string) $booking['reference']);
            redirect($checkoutUrl);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$isTiered = $ticket && ($ticket['pricing_mode'] ?? '') === 'tiered';
$singleAmount = $ticket ? format_ngn($ticket['prices'][0]['amount'] ?? 0) : '';

ob_start();
?>
<section class="app-shell">
  <div class="app-card">
    <p class="eyebrow">Booking</p>
    <h1><?= $ticket ? e($ticket['title']) : 'Ticket unavailable' ?></h1>
    <?php if ($ticket): ?>
      <p><?= e((string) $ticket['description']) ?></p>
    <?php endif; ?>

    <?php if ($cancelled): ?>
      <div class="alert">Checkout was cancelled. You can try again below.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($ticket): ?>
      <form method="post" class="form-grid" id="booking-form" style="margin-top:1.25rem;">
        <?= csrf_field() ?>
        <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>" />

        <div class="form-grid two">
          <label>Full name
            <input type="text" name="full_name" id="full_name" required autocomplete="name" value="<?= e(post_string('full_name')) ?>" />
          </label>
          <label>Email
            <input type="email" name="email" id="email" required autocomplete="email" value="<?= e(post_string('email')) ?>" />
          </label>
        </div>

        <div class="form-grid two">
          <label>Phone
            <input type="tel" name="phone" id="phone" required autocomplete="tel" inputmode="tel" placeholder="08012345678" value="<?= e(post_string('phone')) ?>" />
          </label>
          <label>Date of birth
            <input
              type="date"
              name="date_of_birth"
              id="date_of_birth"
              required
              min="<?= e($minDob) ?>"
              max="<?= e($maxDob) ?>"
              value="<?= e(post_string('date_of_birth')) ?>"
              class="dob-picker"
              inputmode="none"
            />
            <span class="field-hint">Must be <?= (int) $minAge ?>+. Tap to open the calendar.</span>
          </label>
        </div>

        <div class="form-grid two">
          <label>Gender
            <select name="gender" id="gender" required>
              <option value="">Select</option>
              <option value="male" <?= post_string('gender') === 'male' ? 'selected' : '' ?>>Male</option>
              <option value="female" <?= post_string('gender') === 'female' ? 'selected' : '' ?>>Female</option>
            </select>
          </label>

          <?php if ($isTiered): ?>
            <label>Ticket option
              <select name="price_id" id="price_id" required>
                <option value="">Select</option>
                <?php foreach ($ticket['prices'] as $price): ?>
                  <option
                    value="<?= (int) $price['id'] ?>"
                    data-label="<?= e(strtolower((string) $price['label'])) ?>"
                    data-amount="<?= e(format_ngn($price['amount'])) ?>"
                    data-amount-raw="<?= e(money_string($price['amount'])) ?>"
                    <?= (string) ($_POST['price_id'] ?? '') === (string) $price['id'] ? 'selected' : '' ?>
                  >
                    <?= e($price['label']) ?> — <?= e(format_ngn($price['amount'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="field-hint">Male selects Guys · Female selects Girls automatically.</span>
            </label>
          <?php else: ?>
            <div>
              <div style="font-size:0.85rem;color:rgba(247,239,217,0.75);">Amount</div>
              <div class="price-preview" id="single-amount"><?= e($singleAmount) ?></div>
            </div>
          <?php endif; ?>
        </div>

        <div class="form-actions">
          <button class="btn btn-primary" type="button" id="review-booking-btn">Review &amp; continue</button>
          <a class="btn btn-ghost" href="<?= e(app_url('index.php#tickets')) ?>">Back to tickets</a>
        </div>
      </form>

      <div class="booking-preview" id="booking-preview" hidden>
        <button type="button" class="booking-preview-backdrop" id="preview-close-backdrop" aria-label="Close preview"></button>
        <div class="booking-preview-card" role="dialog" aria-modal="true" aria-labelledby="preview-title">
          <p class="eyebrow">Confirm</p>
          <h2 id="preview-title">Review your booking</h2>
          <p class="booking-preview-lede">Check everything looks right before payment.</p>
          <dl class="booking-preview-list">
            <div><dt>Name</dt><dd id="preview-name">—</dd></div>
            <div><dt>Email</dt><dd id="preview-email">—</dd></div>
            <div><dt>Phone</dt><dd id="preview-phone">—</dd></div>
            <div><dt>Date of birth</dt><dd id="preview-dob">—</dd></div>
            <div><dt>Gender</dt><dd id="preview-gender">—</dd></div>
            <div><dt>Ticket</dt><dd id="preview-ticket">—</dd></div>
            <div><dt>Amount</dt><dd id="preview-amount" class="price-preview" style="font-size:1.6rem;">—</dd></div>
          </dl>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit" form="booking-form" id="confirm-pay-btn">Confirm &amp; pay</button>
            <button class="btn btn-ghost" type="button" id="preview-edit-btn">Edit details</button>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="form-actions">
        <a class="btn btn-primary" href="<?= e(app_url('index.php#tickets')) ?>">View tickets</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($ticket): ?>
<script>
(function () {
  var form = document.getElementById('booking-form');
  var gender = document.getElementById('gender');
  var priceSelect = document.getElementById('price_id');
  var dob = document.getElementById('date_of_birth');
  var preview = document.getElementById('booking-preview');
  var reviewBtn = document.getElementById('review-booking-btn');
  var ticketTitle = <?= json_encode((string) $ticket['title'], JSON_UNESCAPED_UNICODE) ?>;
  var isTiered = <?= $isTiered ? 'true' : 'false' ?>;
  var singleAmount = <?= json_encode($singleAmount, JSON_UNESCAPED_UNICODE) ?>;
  var minAge = <?= (int) $minAge ?>;
  var maxDob = <?= json_encode($maxDob) ?>;

  function syncTierFromGender() {
    if (!priceSelect || !gender) return;
    var g = gender.value;
    var want = g === 'male' ? 'guys' : (g === 'female' ? 'girls' : '');
    if (!want) return;
    var options = Array.prototype.slice.call(priceSelect.options);
    var match = options.find(function (opt) {
      var label = (opt.getAttribute('data-label') || opt.textContent || '').toLowerCase();
      return label === want || label.indexOf(want) !== -1;
    });
    if (match) {
      priceSelect.value = match.value;
    }
  }

  if (gender) {
    gender.addEventListener('change', syncTierFromGender);
    if (gender.value) syncTierFromGender();
  }

  function openDobPicker() {
    if (!dob) return;
    try {
      if (typeof dob.showPicker === 'function') {
        dob.showPicker();
      }
    } catch (e) {}
  }

  if (dob) {
    dob.addEventListener('keydown', function (e) {
      // Block typing; calendar only.
      if (e.key === 'Tab' || e.key === 'Escape') return;
      e.preventDefault();
    });
    dob.addEventListener('click', openDobPicker);
    dob.addEventListener('focus', openDobPicker);
    dob.addEventListener('change', function () {
      if (dob.value && dob.value > maxDob) {
        dob.setCustomValidity('You must be ' + minAge + ' or older.');
        dob.value = maxDob;
      } else {
        dob.setCustomValidity('');
      }
    });
  }

  function selectedTicketLabel() {
    if (!isTiered || !priceSelect || !priceSelect.selectedOptions.length) {
      return ticketTitle;
    }
    var opt = priceSelect.selectedOptions[0];
    if (!opt || !opt.value) return ticketTitle;
    var label = opt.getAttribute('data-label') || '';
    return ticketTitle + (label ? ' · ' + opt.textContent.replace(/\s+—.*$/, '').trim() : '');
  }

  function selectedAmount() {
    if (isTiered && priceSelect && priceSelect.selectedOptions.length) {
      var opt = priceSelect.selectedOptions[0];
      return opt && opt.value ? (opt.getAttribute('data-amount') || '—') : '—';
    }
    return singleAmount || '—';
  }

  function fillPreview() {
    document.getElementById('preview-name').textContent = document.getElementById('full_name').value.trim() || '—';
    document.getElementById('preview-email').textContent = document.getElementById('email').value.trim() || '—';
    document.getElementById('preview-phone').textContent = document.getElementById('phone').value.trim() || '—';
    document.getElementById('preview-dob').textContent = dob.value || '—';
    document.getElementById('preview-gender').textContent = gender.value ? gender.options[gender.selectedIndex].text : '—';
    document.getElementById('preview-ticket').textContent = selectedTicketLabel();
    document.getElementById('preview-amount').textContent = selectedAmount();
  }

  function setPreview(open) {
    preview.hidden = !open;
    document.body.classList.toggle('booking-preview-open', open);
  }

  if (reviewBtn) {
    reviewBtn.addEventListener('click', function () {
      syncTierFromGender();
      if (!form.reportValidity()) return;
      if (dob.value && dob.value > maxDob) {
        dob.setCustomValidity('You must be ' + minAge + ' or older.');
        dob.reportValidity();
        return;
      }
      fillPreview();
      setPreview(true);
    });
  }

  var editBtn = document.getElementById('preview-edit-btn');
  var backdrop = document.getElementById('preview-close-backdrop');
  if (editBtn) editBtn.addEventListener('click', function () { setPreview(false); });
  if (backdrop) backdrop.addEventListener('click', function () { setPreview(false); });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') setPreview(false);
  });

  form.addEventListener('submit', function () {
    syncTierFromGender();
  });
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
render_layout('Book your spot', $content);
