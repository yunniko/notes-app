<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;
use RuntimeException;

/**
 * Applies `migrations/NNN_name.sql` files in filename order, once each, recording them
 * in `schema_migrations`. Deliberately tiny (see D003).
 * A file may hold several statements, each ending with `;` at the end of a line; `--` comment
 * lines are dropped. MariaDB DDL commits implicitly, so a failed migration is not rolled back:
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
            foreach (self::statements($sql) as $statement) {
                $this->pdo->exec($statement);
            }
            $this->pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]);
            $ran[] = $name;
        }

        return $ran;
    }

    /** @return list<string> */
    private static function statements(string $sql): array
    {
        $withoutComments = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $parts = preg_split('/;\s*$/m', $withoutComments) ?: [];

        return array_values(array_filter(array_map(trim(...), $parts), static fn(string $s): bool => $s !== ''));
    }
}
