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
      <style>
        .booking-timeline{position:relative;margin:1.35rem 0 .5rem;padding:0 0 .25rem}
        .booking-timeline-line{position:absolute;left:16.66%;right:16.66%;top:18px;height:3px;background:rgba(247,239,217,.2);border-radius:999px;z-index:0;overflow:hidden}
        .booking-timeline-progress{display:block;height:100%;width:0;background:linear-gradient(90deg,#f0c43a,#e91e8c);transition:width .3s ease}
        .booking-timeline-steps{display:flex!important;flex-direction:row!important;align-items:flex-start;justify-content:space-between;gap:0;margin:0;padding:0;list-style:none;position:relative;z-index:1}
        .booking-timeline-step{flex:1 1 0;display:flex!important;flex-direction:column!important;align-items:center;text-align:center;gap:.4rem;color:rgba(247,239,217,.45);min-width:0}
        .booking-timeline-dot{width:36px;height:36px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;border:2px solid rgba(240,196,58,.35);background:#2f1a10;font-size:.8rem;font-weight:700;box-shadow:0 0 0 4px #2f1a10}
        .booking-timeline-step strong{display:block;font-size:.88rem;line-height:1.15;color:inherit}
        .booking-timeline-step small{display:block;font-size:.68rem;letter-spacing:.06em;text-transform:uppercase;opacity:.75}
        .booking-timeline-step.is-active,.booking-timeline-step.is-done{color:#ffe9a0}
        .booking-timeline-step.is-active .booking-timeline-dot,.booking-timeline-step.is-done .booking-timeline-dot{border-color:#f0c43a;background:linear-gradient(135deg,#f0c43a,#e91e8c);color:#1a1208;box-shadow:0 0 0 4px #2f1a10,0 0 0 7px rgba(240,196,58,.16)}
        @media (max-width:560px){.booking-timeline-step small{display:none}.booking-timeline-step strong{font-size:.78rem}.booking-timeline-line{left:14%;right:14%}}
      </style>
      <div class="booking-timeline" id="booking-timeline" data-step="1" aria-label="Booking progress">
        <div class="booking-timeline-line" aria-hidden="true">
          <span class="booking-timeline-progress" id="booking-timeline-progress"></span>
        </div>
        <div class="booking-timeline-steps">
          <div class="booking-timeline-step is-active" data-step-indicator="1">
            <span class="booking-timeline-dot" aria-hidden="true">1</span>
            <strong>Details</strong>
            <small>Your info</small>
          </div>
          <div class="booking-timeline-step" data-step-indicator="2">
            <span class="booking-timeline-dot" aria-hidden="true">2</span>
            <strong>Review</strong>
            <small>Confirm</small>
          </div>
          <div class="booking-timeline-step" data-step-indicator="3">
            <span class="booking-timeline-dot" aria-hidden="true">3</span>
            <strong>Pay</strong>
            <small>Checkout</small>
          </div>
        </div>
      </div>

      <form method="post" class="form-grid" id="booking-form" style="margin-top:1.15rem;">
        <?= csrf_field() ?>
        <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>" />

        <div class="booking-panel is-active" data-booking-step="1">
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
            <button class="btn btn-primary" type="button" id="review-booking-btn">Next: Review</button>
            <a class="btn btn-ghost" href="<?= e(app_url('index.php#tickets')) ?>">Back to tickets</a>
          </div>
        </div>

        <div class="booking-panel" data-booking-step="2" hidden>
          <div class="booking-review">
            <div class="booking-total-card">
              <div class="booking-total-card-copy">
                <span>Total due</span>
                <p>Payable now via Bachs checkout</p>
              </div>
              <strong id="preview-amount"><?= e($singleAmount ?: '—') ?></strong>
            </div>

            <div class="booking-review-columns">
              <article class="booking-card">
                <header class="booking-card-head">
                  <span class="booking-card-icon" aria-hidden="true">G</span>
                  <div>
                    <p class="booking-card-label">Guest</p>
                    <h3 id="preview-name">—</h3>
                  </div>
                </header>
                <div class="booking-card-body booking-card-body-2">
                  <div>
                    <span>Gender</span>
                    <strong id="preview-gender">—</strong>
                  </div>
                  <div>
                    <span>Date of birth</span>
                    <strong id="preview-dob">—</strong>
                  </div>
                </div>
              </article>

              <article class="booking-card booking-card-ticket">
                <header class="booking-card-head">
                  <span class="booking-card-icon" aria-hidden="true">T</span>
                  <div>
                    <p class="booking-card-label">Ticket</p>
                    <h3 id="preview-ticket-title"><?= e($ticket['title']) ?></h3>
                  </div>
                </header>
                <div class="booking-card-body">
                  <div>
                    <span>Option</span>
                    <strong id="preview-tier">Standard</strong>
                  </div>
                </div>
              </article>

              <article class="booking-card">
                <header class="booking-card-head">
                  <span class="booking-card-icon" aria-hidden="true">E</span>
                  <div>
                    <p class="booking-card-label">Email</p>
                    <h3 id="preview-email">—</h3>
                  </div>
                </header>
              </article>

              <article class="booking-card">
                <header class="booking-card-head">
                  <span class="booking-card-icon" aria-hidden="true">P</span>
                  <div>
                    <p class="booking-card-label">Phone</p>
                    <h3 id="preview-phone">—</h3>
                  </div>
                </header>
              </article>
            </div>

            <div class="form-actions booking-review-actions">
              <button class="btn btn-primary" type="submit" id="confirm-pay-btn">Confirm &amp; pay</button>
              <button class="btn btn-ghost" type="button" id="preview-edit-btn">Back to details</button>
            </div>
          </div>
        </div>
      </form>
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
  var reviewBtn = document.getElementById('review-booking-btn');
  var editBtn = document.getElementById('preview-edit-btn');
  var step1 = document.querySelector('[data-booking-step="1"]');
  var step2 = document.querySelector('[data-booking-step="2"]');
  var timeline = document.getElementById('booking-timeline');
  var progress = document.getElementById('booking-timeline-progress');
  var indicators = document.querySelectorAll('[data-step-indicator]');
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
    if (match) priceSelect.value = match.value;
  }

  if (gender) {
    gender.addEventListener('change', syncTierFromGender);
    if (gender.value) syncTierFromGender();
  }

  function openDobPicker() {
    if (!dob) return;
    try {
      if (typeof dob.showPicker === 'function') dob.showPicker();
    } catch (e) {}
  }

  if (dob) {
    dob.addEventListener('keydown', function (e) {
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

  function selectedTier() {
    if (!isTiered || !priceSelect || !priceSelect.selectedOptions.length) return 'Single ticket';
    var opt = priceSelect.selectedOptions[0];
    if (!opt || !opt.value) return 'Select an option';
    return opt.textContent.replace(/\s+—.*$/, '').trim() || 'Ticket option';
  }

  function selectedAmount() {
    if (isTiered && priceSelect && priceSelect.selectedOptions.length) {
      var opt = priceSelect.selectedOptions[0];
      return opt && opt.value ? (opt.getAttribute('data-amount') || '—') : '—';
    }
    return singleAmount || '—';
  }

  function formatDob(value) {
    if (!value) return '—';
    var parts = value.split('-');
    if (parts.length !== 3) return value;
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var m = Number(parts[1]) - 1;
    return parts[2] + ' ' + (months[m] || parts[1]) + ' ' + parts[0];
  }

  function fillPreview() {
    var name = document.getElementById('full_name').value.trim() || 'Guest';
    document.getElementById('preview-name').textContent = name;
    document.getElementById('preview-email').textContent = document.getElementById('email').value.trim() || '—';
    document.getElementById('preview-phone').textContent = document.getElementById('phone').value.trim() || '—';
    document.getElementById('preview-dob').textContent = formatDob(dob.value);
    document.getElementById('preview-gender').textContent = gender.value ? gender.options[gender.selectedIndex].text : '—';
    document.getElementById('preview-ticket-title').textContent = ticketTitle;
    document.getElementById('preview-tier').textContent = selectedTier();
    document.getElementById('preview-amount').textContent = selectedAmount();
  }

  function markTimeline(step) {
    if (timeline) timeline.setAttribute('data-step', String(step));
    indicators.forEach(function (el) {
      var n = Number(el.getAttribute('data-step-indicator'));
      el.classList.toggle('is-active', n === step);
      el.classList.toggle('is-done', n < step);
    });
    // Line fills between centers of steps 1→2→3
    if (progress) {
      var pct = step <= 1 ? 0 : (step === 2 ? 50 : 100);
      progress.style.width = pct + '%';
    }
  }

  markTimeline(1);

  function goToStep(step) {
    var isReview = step >= 2;
    step1.hidden = isReview;
    step2.hidden = !isReview;
    step1.classList.toggle('is-active', !isReview);
    step2.classList.toggle('is-active', isReview);
    markTimeline(step);
    window.scrollTo({ top: 0, behavior: 'smooth' });
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
      goToStep(2);
    });
  }

  if (editBtn) {
    editBtn.addEventListener('click', function () {
      goToStep(1);
    });
  }

  form.addEventListener('submit', function () {
    syncTierFromGender();
    markTimeline(3);
  });
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
render_layout('Book your spot', $content);
