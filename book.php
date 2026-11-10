<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$ticketId = (int) ($_GET['ticket_id'] ?? $_POST['ticket_id'] ?? 0);
$ticket = $ticketId ? ticket_find($ticketId, true) : null;
$error = null;
$cancelled = isset($_GET['cancelled']);

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
            if (!is_adult($dob, (int) config('min_age', 18))) {
                throw new InvalidArgumentException('You must be 18 or older to book.');
            }

            $phoneNormalized = normalize_phone_e164($phone);
            $phoneDigits = preg_replace('/\D+/', '', $phoneNormalized) ?? '';
            if (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
                throw new InvalidArgumentException('Enter a valid phone number (e.g. 08012345678).');
            }

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
      <form method="post" class="form-grid" style="margin-top:1.25rem;">
        <?= csrf_field() ?>
        <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>" />

        <div class="form-grid two">
          <label>Full name
            <input type="text" name="full_name" required value="<?= e(post_string('full_name')) ?>" />
          </label>
          <label>Email
            <input type="email" name="email" required value="<?= e(post_string('email')) ?>" />
          </label>
        </div>

        <div class="form-grid two">
          <label>Phone
            <input type="tel" name="phone" required value="<?= e(post_string('phone')) ?>" />
          </label>
          <label>Date of birth
            <input type="date" name="date_of_birth" required value="<?= e(post_string('date_of_birth')) ?>" />
          </label>
        </div>

        <div class="form-grid two">
          <label>Gender
            <select name="gender" required>
              <option value="">Select</option>
              <option value="male" <?= post_string('gender') === 'male' ? 'selected' : '' ?>>Male</option>
              <option value="female" <?= post_string('gender') === 'female' ? 'selected' : '' ?>>Female</option>
            </select>
          </label>

          <?php if ($ticket['pricing_mode'] === 'tiered'): ?>
            <label>Ticket option
              <select name="price_id" id="price_id" required>
                <option value="">Select</option>
                <?php foreach ($ticket['prices'] as $price): ?>
                  <option
                    value="<?= (int) $price['id'] ?>"
                    data-amount="<?= e(format_ngn($price['amount'])) ?>"
                    <?= (string) ($_POST['price_id'] ?? '') === (string) $price['id'] ? 'selected' : '' ?>
                  >
                    <?= e($price['label']) ?> — <?= e(format_ngn($price['amount'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          <?php else: ?>
            <div>
              <div style="font-size:0.85rem;color:rgba(247,239,217,0.75);">Amount</div>
              <div class="price-preview"><?= e(format_ngn($ticket['prices'][0]['amount'] ?? 0)) ?></div>
            </div>
          <?php endif; ?>
        </div>

        <div class="form-actions">
          <button class="btn btn-primary" type="submit">Pay &amp; Reserve</button>
          <a class="btn btn-ghost" href="<?= e(app_url('index.html#tickets')) ?>">Back to tickets</a>
        </div>
      </form>
    <?php else: ?>
      <div class="form-actions">
        <a class="btn btn-primary" href="<?= e(app_url('index.html#tickets')) ?>">View tickets</a>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php
$content = ob_get_clean();
render_layout('Book your spot', $content);
