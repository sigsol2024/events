<?php

declare(strict_types=1);

return [
    'id' => '2026_03_11_age_confirmation',
    'description' => 'Replace required DOB with age confirmation checkbox support',
    'up' => function (PDO $pdo): void {
        if (!DatabaseAutoMigrate::tableExists($pdo, 'bookings')) {
            return;
        }

        DatabaseAutoMigrate::ensureColumn(
            $pdo,
            'bookings',
            'age_confirmed',
            '`age_confirmed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `gender`'
        );

        // Only rewrite the column when it is still NOT NULL.
        $stmt = $pdo->prepare(
            "SELECT IS_NULLABLE
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'bookings'
               AND column_name = 'date_of_birth'
             LIMIT 1"
        );
        $stmt->execute();
        $nullable = $stmt->fetchColumn();
        if ($nullable === 'NO') {
            $pdo->exec('ALTER TABLE `bookings` MODIFY COLUMN `date_of_birth` DATE NULL');
        }
    },
];
