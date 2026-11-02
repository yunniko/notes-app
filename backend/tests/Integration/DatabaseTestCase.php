<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Infrastructure\Database;
use App\Infrastructure\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Runs against the real MariaDB named by DB_NAME (the `notes_test` database in Docker),
 * with migrations applied and the notes table emptied before every test.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        $database = Database::fromEnvironment();
        if ($database->name === 'notes') {
            self::fail('Refusing to run integration tests against the app database "notes"; set DB_NAME=notes_test.');
        }
        self::$pdo = $database->connect();
        new Migrator(self::$pdo, __DIR__ . '/../../migrations')->migrate();
    }

    protected function setUp(): void
    {
        // TRUNCATE is refused on a table that a foreign key points at, even an empty one.
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['notes', 'sessions', 'login_failures', 'users'] as $table) {
            self::$pdo->exec("TRUNCATE TABLE {$table}");
        }
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
