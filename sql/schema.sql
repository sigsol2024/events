-- Tee's Beach House — booking schema
-- phpMyAdmin: create an empty database, then Import this file.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `pricing_mode` ENUM('single','tiered') NOT NULL DEFAULT 'single',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tickets_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_prices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `label` VARCHAR(80) NOT NULL DEFAULT '',
  `amount` DECIMAL(12,2) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ticket_prices_ticket` (`ticket_id`),
  CONSTRAINT `ticket_prices_ticket_fk`
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(64) NOT NULL,
  `ticket_id` INT UNSIGNED NOT NULL,
  `ticket_title` VARCHAR(160) NOT NULL,
  `price_id` INT UNSIGNED NULL,
  `price_label` VARCHAR(80) NOT NULL DEFAULT '',
  `full_name` VARCHAR(160) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(40) NOT NULL,
  `date_of_birth` DATE NULL,
  `gender` ENUM('male','female') NOT NULL,
  `age_confirmed` TINYINT(1) NOT NULL DEFAULT 0,
  `amount` DECIMAL(12,2) NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'NGN',
  `status` ENUM('pending','processing','paid','failed','expired','refunded') NOT NULL DEFAULT 'pending',
  `bachs_checkout_id` VARCHAR(120) NULL,
  `bachs_checkout_url` VARCHAR(500) NULL,
  `bachs_reference` VARCHAR(128) NULL,
  `checkout_expires_at` DATETIME NULL,
  `ticket_code` VARCHAR(64) NULL,
  `access_token` VARCHAR(64) NULL,
  `paid_at` DATETIME NULL,
  `admitted_at` DATETIME NULL,
  `admin_notified_at` DATETIME NULL,
  `buyer_notified_at` DATETIME NULL,
  `incomplete_notified_at` DATETIME NULL,
  `incomplete_reminded_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bookings_reference_unique` (`reference`),
  UNIQUE KEY `bookings_ticket_code_unique` (`ticket_code`),
  UNIQUE KEY `bookings_access_token_unique` (`access_token`),
  UNIQUE KEY `bookings_checkout_unique` (`bachs_checkout_id`),
  KEY `bookings_status` (`status`),
  KEY `bookings_ticket_id` (`ticket_id`),
  KEY `bookings_admitted_at` (`admitted_at`),
  KEY `bookings_open_lookup` (`email`, `ticket_id`, `status`),
  CONSTRAINT `bookings_ticket_fk`
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhook_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` VARCHAR(120) NOT NULL,
  `event_type` VARCHAR(80) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `processed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `webhook_events_event_id_unique` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pin_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL,
  `ticket_code` VARCHAR(64) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pin_attempts_lookup` (`ip_address`, `ticket_code`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT IGNORE INTO `tickets` (`id`, `title`, `description`, `pricing_mode`, `sort_order`, `is_active`) VALUES
(1, 'Regular Tickets', 'Access to the Beach House night — food, drinks, music, and vibes included.', 'tiered', 1, 1),
(2, 'VIP Table of 5 · Dome', 'Private table with dome — elevated seating for your crew.', 'single', 2, 1),
(3, 'VIP Table of 5 · Bigger Dome', 'Bigger dome experience — more presence, more room to host.', 'single', 3, 1);

INSERT INTO `ticket_prices` (`ticket_id`, `label`, `amount`, `sort_order`)
SELECT * FROM (
  SELECT 1 AS ticket_id, 'Guys' AS label, 100000.00 AS amount, 1 AS sort_order
  UNION ALL SELECT 1, 'Girls', 70000.00, 2
  UNION ALL SELECT 2, '', 1000000.00, 1
  UNION ALL SELECT 3, '', 1200000.00, 1
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `ticket_prices` LIMIT 1);

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('gate_pin_hash', NULL)
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
