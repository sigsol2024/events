-- Run once on existing databases for incomplete-booking resume + reminder emails.

ALTER TABLE `bookings`
  ADD COLUMN `bachs_checkout_url` VARCHAR(500) NULL AFTER `bachs_checkout_id`,
  ADD COLUMN `checkout_expires_at` DATETIME NULL AFTER `bachs_reference`,
  ADD COLUMN `incomplete_notified_at` DATETIME NULL AFTER `buyer_notified_at`,
  ADD COLUMN `incomplete_reminded_at` DATETIME NULL AFTER `incomplete_notified_at`,
  ADD KEY `bookings_open_lookup` (`email`, `ticket_id`, `status`);
