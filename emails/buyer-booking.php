<?php
/** @var array $booking */
$thankYou = app_url(
    'thank-you.php?ref=' . urlencode((string)$booking['reference'])
    . '&token=' . urlencode((string)$booking['access_token'])
);
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Your ticket</title></head>
<body style="margin:0;padding:0;background:#07111f;font-family:Arial,Helvetica,sans-serif;color:#f7efd9;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#07111f;padding:32px 12px;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#1a1208;border:1px solid rgba(240,196,58,0.35);">
        <tr><td style="padding:28px 28px 12px;">
          <div style="color:#f0c43a;font-size:12px;letter-spacing:3px;text-transform:uppercase;">Temi Presents</div>
          <h1 style="margin:10px 0 0;font-size:28px;color:#fff;">You're booked for Beach House</h1>
          <p style="margin:10px 0 0;color:rgba(247,239,217,0.8);">Payment confirmed. Keep this email and download your QR ticket.</p>
        </td></tr>
        <tr><td style="padding:8px 28px 28px;">
          <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
            <tr><td style="color:#ff6eb5;">Reference</td><td style="color:#f0c43a;"><?= e($booking['reference']) ?></td></tr>
            <tr><td style="color:#ff6eb5;">Name</td><td><?= e($booking['full_name']) ?></td></tr>
            <tr><td style="color:#ff6eb5;">Ticket</td><td><?= e($booking['ticket_title']) ?><?= $booking['price_label'] ? ' · ' . e($booking['price_label']) : '' ?></td></tr>
            <tr><td style="color:#ff6eb5;">Amount paid</td><td style="color:#f0c43a;">₦<?= e(number_format((float)$booking['amount'], 2)) ?></td></tr>
          </table>
          <p style="margin:24px 0 0;">
            <a href="<?= e($thankYou) ?>" style="display:inline-block;background:#e91e8c;color:#fff;text-decoration:none;padding:12px 18px;font-weight:bold;">View &amp; download QR ticket</a>
          </p>
          <p style="margin:16px 0 0;font-size:12px;color:rgba(247,239,217,0.65);">Show your QR at the gate. Staff will unlock ticket details with their verification PIN.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
