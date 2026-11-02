<?php

declare(strict_types=1);

// Applies pending SQL migrations. Run on every container start (idempotent), or manually:
//   docker compose exec api php bin/migrate.php

use App\Infrastructure\Database;
use App\Infrastructure\Migrator;

require __DIR__ . '/../vendor/autoload.php';

$database = Database::fromEnvironment();

// The database container may still be starting; retry the connection for up to ~30 s.
for ($attempt = 1; ; ++$attempt) {
    try {
        $pdo = $database->connect();
        break;
    } catch (PDOException $e) {
        if ($attempt >= 30) {
            fwrite(STDERR, 'Database unreachable: ' . $e->getMessage() . PHP_EOL);
            exit(1);
        }
        sleep(1);
    }
}

$ran = (new Migrator($pdo, __DIR__ . '/../migrations'))->migrate();
echo $ran === [] ? 'Schema up to date.' . PHP_EOL : 'Applied: ' . implode(', ', $ran) . PHP_EOL;
