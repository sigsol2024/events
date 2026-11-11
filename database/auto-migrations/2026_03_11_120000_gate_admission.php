<?php

declare(strict_types=1);

return [
    'id' => '2026_03_11_gate_admission',
    'description' => 'Track first gate admission timestamp on paid bookings',
    'up' => function (PDO $pdo): void {
        if (!DatabaseAutoMigrate::tableExists($pdo, 'bookings')) {
            return;
        }

        DatabaseAutoMigrate::ensureColumn(
            $pdo,
            'bookings',
            'admitted_at',
            '`admitted_at` DATETIME NULL AFTER `paid_at`'
        );
        DatabaseAutoMigrate::ensureIndex(
            $pdo,
            'bookings',
            'bookings_admitted_at',
            'KEY `bookings_admitted_at` (`admitted_at`)'
        );
    },
];
