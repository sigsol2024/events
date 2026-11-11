<?php

declare(strict_types=1);

/**
 * Idempotent DB auto-migrator (Bloombit-style).
 * Runs pending PHP migrations from database/auto-migrations/ when an admin loads any admin page.
 */

class DatabaseAutoMigrate
{
    private static bool $ranThisRequest = false;
    /** @var array<string, mixed>|null */
    private static ?array $lastResult = null;

    private PDO $pdo;
    private string $dir;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'auto-migrations';
    }

    public function run(?int $appliedBy = null): array
    {
        // Return the real prior result (not an empty stub) so admin flash still works
        // when bootstrap already ran migrations earlier in this request.
        if (self::$ranThisRequest) {
            return self::$lastResult ?? [
                'ran' => false,
                'applied' => [],
                'failed' => [],
                'skipped' => 0,
                'errors' => [],
            ];
        }
        self::$ranThisRequest = true;

        $result = [
            'ran' => false,
            'applied' => [],
            'failed' => [],
            'skipped' => 0,
            'errors' => [],
        ];

        if (!$this->acquireLock()) {
            self::$lastResult = $result;
            return $result;
        }
        $result['ran'] = true;

        try {
            $this->ensureTrackingTable();
            $migrations = $this->discoverMigrations();
            $appliedIds = $this->getSuccessfullyAppliedIds();

            foreach ($migrations as $migration) {
                $id = (string) $migration['id'];
                $description = (string) ($migration['description'] ?? $id);
                if (isset($appliedIds[$id])) {
                    $result['skipped']++;
                    continue;
                }

                try {
                    if (!is_callable($migration['up'] ?? null)) {
                        throw new RuntimeException('Migration up() is not callable');
                    }
                    ($migration['up'])($this->pdo);
                    $this->record($id, $description, 'success', null, $appliedBy);
                    $result['applied'][] = ['id' => $id, 'description' => $description];
                } catch (Throwable $e) {
                    $msg = $e->getMessage();
                    $this->record($id, $description, 'failed', $msg, $appliedBy);
                    $result['failed'][] = ['id' => $id, 'description' => $description, 'error' => $msg];
                    $result['errors'][] = "{$id}: {$msg}";
                    error_log("Events auto-migration failed [{$id}]: {$msg}");
                    // Stop so later migrations cannot run against a half-updated schema.
                    break;
                }
            }
        } catch (Throwable $e) {
            $result['errors'][] = 'Migrator bootstrap failed: ' . $e->getMessage();
            error_log('Events auto-migrator bootstrap error: ' . $e->getMessage());
        } finally {
            $this->releaseLock();
        }

        self::$lastResult = $result;
        return $result;
    }

    private function acquireLock(): bool
    {
        try {
            // Wait briefly so concurrent booking/admin requests don't skip schema
            // updates that another request is still applying.
            $stmt = $this->pdo->query("SELECT GET_LOCK(CONCAT('auto_migrate_', DATABASE()), 5)");
            return (int) $stmt->fetchColumn() === 1;
        } catch (Throwable $e) {
            error_log('Events auto-migrator could not acquire lock: ' . $e->getMessage());
            return false;
        }
    }

    private function releaseLock(): void
    {
        try {
            $this->pdo->query("SELECT RELEASE_LOCK(CONCAT('auto_migrate_', DATABASE()))");
        } catch (Throwable $e) {
            // ignore
        }
    }

    private function ensureTrackingTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `auto_migrations` (
                `id` VARCHAR(191) NOT NULL,
                `description` VARCHAR(255) DEFAULT NULL,
                `status` ENUM('success','failed') NOT NULL DEFAULT 'success',
                `error_message` TEXT DEFAULT NULL,
                `applied_by` INT UNSIGNED DEFAULT NULL,
                `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @return array<string, true> */
    private function getSuccessfullyAppliedIds(): array
    {
        $ids = [];
        $stmt = $this->pdo->query("SELECT id FROM auto_migrations WHERE status = 'success'");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $ids[(string) $id] = true;
        }
        return $ids;
    }

    private function discoverMigrations(): array
    {
        if (!is_dir($this->dir)) {
            return [];
        }
        $files = glob($this->dir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);

        $migrations = [];
        foreach ($files as $file) {
            if (basename($file) === 'index.php') {
                continue;
            }
            $data = include $file;
            if (!is_array($data) || empty($data['id']) || !isset($data['up'])) {
                throw new RuntimeException('Invalid migration file: ' . basename($file));
            }
            $migrations[] = $data;
        }
        return $migrations;
    }

    private function record(string $id, string $description, string $status, ?string $error, ?int $appliedBy): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO auto_migrations (id, description, status, error_message, applied_by, applied_at)
                 VALUES (?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                    description = VALUES(description),
                    status = VALUES(status),
                    error_message = VALUES(error_message),
                    applied_by = VALUES(applied_by),
                    applied_at = IF(VALUES(status) = 'success', NOW(), applied_at)"
            );
            $stmt->execute([$id, $description, $status, $error, $appliedBy]);
        } catch (Throwable $e) {
            error_log("Events auto-migrator could not record [{$id}]: " . $e->getMessage());
        }
    }

    private static function ident(string $name): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9_]/', '', $name);
    }

    public static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([self::ident($table)]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([self::ident($table), self::ident($column)]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function indexExists(PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
        );
        $stmt->execute([self::ident($table), self::ident($index)]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function ensureColumn(PDO $pdo, string $table, string $column, string $definitionSql): bool
    {
        if (self::columnExists($pdo, $table, $column)) {
            return false;
        }
        $pdo->exec('ALTER TABLE `' . self::ident($table) . '` ADD COLUMN ' . $definitionSql);
        return true;
    }

    public static function ensureIndex(PDO $pdo, string $table, string $index, string $definitionSql): bool
    {
        if (self::indexExists($pdo, $table, $index)) {
            return false;
        }
        $pdo->exec('ALTER TABLE `' . self::ident($table) . '` ADD ' . $definitionSql);
        return true;
    }

    public static function dropIndexIfExists(PDO $pdo, string $table, string $index): void
    {
        if (!self::indexExists($pdo, $table, $index)) {
            return;
        }
        $pdo->exec('ALTER TABLE `' . self::ident($table) . '` DROP INDEX `' . self::ident($index) . '`');
    }
}

function run_admin_database_auto_migrations(?int $adminUserId = null): ?array
{
    try {
        return (new DatabaseAutoMigrate(db()))->run($adminUserId);
    } catch (Throwable $e) {
        error_log('Events auto-migrator: database unavailable: ' . $e->getMessage());
        return null;
    }
}
