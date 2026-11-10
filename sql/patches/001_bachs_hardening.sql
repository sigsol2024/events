-- Run once on existing databases created before Bachs hardening.
-- Safe to re-run: duplicate-key / duplicate-index errors can be ignored.

-- One booking per Bachs checkout session (NULLs remain allowed / unique).
ALTER TABLE `bookings`
  DROP INDEX `bookings_checkout`,
  ADD UNIQUE KEY `bookings_checkout_unique` (`bachs_checkout_id`);
