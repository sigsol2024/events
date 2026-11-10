<?php
/** @var array $booking */
$thankYou = app_url('admin/booking.php?id=' . (int) $booking['id']);
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>New booking</title></head>
<body style="margin:0;padding:0;background:#07111f;font-family:Arial,Helvetica,sans-serif;color:#f7efd9;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#07111f;padding:32px 12px;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#1a1208;border:1px solid rgba(240,196,58,0.35);">
        <tr><td style="padding:28px 28px 12px;">
          <div style="color:#f0c43a;font-size:12px;letter-spacing:3px;text-transform:uppercase;">Tee's Beach House</div>
          <h1 style="margin:10px 0 0;font-size:28px;color:#fff;">New paid booking</h1>
          <p style="margin:10px 0 0;color:rgba(247,239,217,0.8);">A guest completed payment successfully.</p>
        </td></tr>
        <tr><td style="padding:8px 28px 28px;">
          <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
            <tr><td style="color:#ff6eb5;">Reference</td><td style="color:#f0c43a;"><?= e($booking['reference']) ?></td></tr>
            <tr><td style="color:#ff6eb5;">Guest</td><td><?= e($booking['full_name']) ?></td></tr>
            <tr><td style="color:#ff6eb5;">Email</td><td><?= e($booking['email']) ?></td></tr>
            <tr><td style="color:#ff6eb5;">Phone</td><td><?= e($booking['phone']) ?></td></tr>
            <tr><td style="color:#ff6eb5;">Ticket</td><td><?= e($booking['ticket_title']) ?><?= $booking['price_label'] ? ' · ' . e($booking['price_label']) : '' ?></td></tr>
            <tr><td style="color:#ff6eb5;">Amount</td><td style="color:#f0c43a;">₦<?= e(number_format((float)$booking['amount'], 2)) ?></td></tr>
            <tr><td style="color:#ff6eb5;">Paid at</td><td><?= e((string)$booking['paid_at']) ?></td></tr>
          </table>
          <p style="margin:24px 0 0;">
            <a href="<?= e($thankYou) ?>" style="display:inline-block;background:#f0c43a;color:#1a1208;text-decoration:none;padding:12px 18px;font-weight:bold;">Open in admin</a>
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
