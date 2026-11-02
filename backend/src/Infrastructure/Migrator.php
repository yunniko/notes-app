<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;
use RuntimeException;

/**
 * Applies `migrations/NNN_name.sql` files in filename order, once each, recording them
 * in `schema_migrations`. Deliberately tiny: the schema is one table (see D003).
 * Note: MariaDB DDL commits implicitly, so a migration is not rolled back on failure;
 * keep each file to one logical change.
 */
final readonly class Migrator
{
    public function __construct(private PDO $pdo, private string $directory) {}

    /** @return list<string> the migrations applied by this run */
    public function migrate(): array
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                name VARCHAR(255) NOT NULL PRIMARY KEY,
                applied_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci',
        );

        $done = $this->pdo->query('SELECT name FROM schema_migrations');
        $applied = $done === false ? [] : $done->fetchAll(PDO::FETCH_COLUMN);

        $files = glob($this->directory . '/*.sql') ?: [];
        sort($files);

        $ran = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException(sprintf('Cannot read migration %s.', $file));
            }
            $this->pdo->exec($sql);
            $this->pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]);
            $ran[] = $name;
        }

        return $ran;
    }
}
