<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\LoginThrottle;
use PDO;

final readonly class PdoLoginThrottle implements LoginThrottle
{
    public function __construct(private PDO $pdo) {}

    public function recentFailures(string $username, int $windowSeconds): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) AS failures FROM login_failures
              WHERE username = ? AND failed_at > CURRENT_TIMESTAMP(3) - INTERVAL ? SECOND',
        );
        $statement->execute([$username, $windowSeconds]);
        $row = Rows::first($statement);

        return $row === null ? 0 : Rows::int($row, 'failures');
    }

    public function recordFailure(string $username, int $windowSeconds): void
    {
        $this->pdo->prepare('DELETE FROM login_failures WHERE failed_at <= CURRENT_TIMESTAMP(3) - INTERVAL ? SECOND')
            ->execute([$windowSeconds]);
        $this->pdo->prepare('INSERT INTO login_failures (username) VALUES (?)')->execute([$username]);
    }

    public function clear(string $username): void
    {
        $this->pdo->prepare('DELETE FROM login_failures WHERE username = ?')->execute([$username]);
    }
}
