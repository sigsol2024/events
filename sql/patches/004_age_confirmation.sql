-- Optional manual patch. Prefer letting auto-migrations apply this on first page load.
-- Safe to skip if database/auto-migrations already ran.

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'bookings' AND column_name = 'age_confirmed'
);
SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `bookings` ADD COLUMN `age_confirmed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `gender`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `bookings`
  MODIFY COLUMN `date_of_birth` DATE NULL;
