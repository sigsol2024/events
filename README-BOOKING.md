# Booking backend setup

## 1. Database
1. Create a MySQL database in phpMyAdmin.
2. Import `sql/schema.sql` (includes default tickets/prices).

## 2. Config
1. Copy `config/config.example.php` → `config/config.php`.
2. Fill DB credentials, `app_url`, Bachs keys, `setup_key`, `app_secret`, admin notify email.

## 3. Admin account
1. Copy `setup-admin.example.php` → `setup-admin.php` on the server (gitignored).
2. Open `/setup-admin.php`, enter setup key + email/password.
3. **Delete `setup-admin.php`** from the server.
4. Sign in at `/admin/login.php`.
5. Generate a **Gate PIN** under Admin → Gate PIN.

## 4. Bachs webhook
Point Bachs webhook URL to:
`https://YOUR_DOMAIN/api/webhooks/bachs.php`

Subscribe at least to:
- `collection.succeeded` (primary fulfilment signal)
- `checkout.completed` (fulfil only when `payment_status=paid`; ignore `no_payment_required`)
- `checkout.expired`
- `refund.paid`

Do **not** treat `collection.failed` as a terminal booking failure — Bachs keeps the checkout open for retry.

### Database auto-updates
Pending migrations in `database/auto-migrations/` apply automatically on app bootstrap (booking, gate, admin — locked + idempotent).
Signed-in admin pages also show a green banner when updates ran in that request. Manual SQL under `sql/patches/` is optional fallback only.

Gate flow: scan QR → enter staff PIN → review guest → tap **Admit guest** (saved once). Re-scans still work and show **Already admitted**; the QR does not expire.

Booking age check: guests confirm **I am 18 or older** (checkbox). Date of birth is no longer collected.

## 5. Incomplete bookings
- Starting checkout emails the buyer a **Complete payment** link.
- Cancel/return from Bachs lands on `resume.php` for that booking.
- Rebooking the same ticket with the same email **resumes** the open booking (no duplicate).
- Schedule a cron hit every 10–15 minutes:
  `https://YOUR_DOMAIN/api/cron/booking-maintenance.php?key=YOUR_SETUP_KEY`
  It expires stale checkouts and sends reminder emails.

## 6. Landing page
`index.php` renders tickets from the database; booking CTAs go to `book.php`.
