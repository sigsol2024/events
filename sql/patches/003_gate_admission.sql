-- Run once on existing databases for gate admission tracking.

ALTER TABLE `bookings`
  ADD COLUMN `admitted_at` DATETIME NULL AFTER `paid_at`,
  ADD KEY `bookings_admitted_at` (`admitted_at`);
