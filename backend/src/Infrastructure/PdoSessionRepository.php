<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\SessionRepository;
use App\Domain\User;
use PDO;

final readonly class PdoSessionRepository implements SessionRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(string $tokenHash, int $userId, int $lifetimeSeconds): void
    {
        $this->pdo->prepare(
            'INSERT INTO sessions (token_hash, user_id, expires_at)
             VALUES (?, ?, CURRENT_TIMESTAMP(3) + INTERVAL ? SECOND)',
        )->execute([$tokenHash, $userId, $lifetimeSeconds]);
    }

    public function findUser(string $tokenHash): ?User
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.username, u.created_at
               FROM sessions s JOIN users u ON u.id = s.user_id
              WHERE s.token_hash = ? AND s.expires_at > CURRENT_TIMESTAMP(3)',
        );
        $statement->execute([$tokenHash]);
        $row = Rows::first($statement);

        return $row === null ? null : PdoUserRepository::user($row);
    }

    public function delete(string $tokenHash): void
    {
        $this->pdo->prepare('DELETE FROM sessions WHERE token_hash = ?')->execute([$tokenHash]);
    }

    public function deleteExpired(): void
    {
        $this->pdo->exec('DELETE FROM sessions WHERE expires_at <= CURRENT_TIMESTAMP(3)');
    }
}
