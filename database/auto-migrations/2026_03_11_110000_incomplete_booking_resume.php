<?php

declare(strict_types=1);

return [
    'id' => '2026_03_11_incomplete_booking_resume',
    'description' => 'Add incomplete-booking resume columns and open-booking lookup index',
    'up' => function (PDO $pdo): void {
        if (!DatabaseAutoMigrate::tableExists($pdo, 'bookings')) {
            return;
        }

        DatabaseAutoMigrate::ensureColumn(
            $pdo,
            'bookings',
            'bachs_checkout_url',
            '`bachs_checkout_url` VARCHAR(500) NULL AFTER `bachs_checkout_id`'
        );
        DatabaseAutoMigrate::ensureColumn(
            $pdo,
            'bookings',
            'checkout_expires_at',
            '`checkout_expires_at` DATETIME NULL AFTER `bachs_reference`'
        );
        DatabaseAutoMigrate::ensureColumn(
            $pdo,
            'bookings',
            'incomplete_notified_at',
            '`incomplete_notified_at` DATETIME NULL AFTER `buyer_notified_at`'
        );
        DatabaseAutoMigrate::ensureColumn(
            $pdo,
            'bookings',
            'incomplete_reminded_at',
            '`incomplete_reminded_at` DATETIME NULL AFTER `incomplete_notified_at`'
        );
        DatabaseAutoMigrate::ensureIndex(
            $pdo,
            'bookings',
            'bookings_open_lookup',
            'KEY `bookings_open_lookup` (`email`, `ticket_id`, `status`)'
        );
    },
];
