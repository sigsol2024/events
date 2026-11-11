<?php

declare(strict_types=1);

return [
    'id' => '2026_03_11_bachs_checkout_unique',
    'description' => 'Enforce unique Bachs checkout id on bookings',
    'up' => function (PDO $pdo): void {
        if (!DatabaseAutoMigrate::tableExists($pdo, 'bookings')) {
            return;
        }
        DatabaseAutoMigrate::dropIndexIfExists($pdo, 'bookings', 'bookings_checkout');
        DatabaseAutoMigrate::ensureIndex(
            $pdo,
            'bookings',
            'bookings_checkout_unique',
            'UNIQUE KEY `bookings_checkout_unique` (`bachs_checkout_id`)'
        );
    },
];
