<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Exception\UsernameTaken;
use App\Domain\StoredUser;
use App\Domain\User;
use App\Domain\UserRepository;
use PDO;
use PDOException;
use UnexpectedValueException;

/** Usernames compare case-insensitively through the column's collation. */
final readonly class PdoUserRepository implements UserRepository
{
    private const int DUPLICATE_KEY = 1062;

    public function __construct(private PDO $pdo) {}

    public function create(string $username, string $passwordHash): User
    {
        try {
            $this->pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')
                ->execute([$username, $passwordHash]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === self::DUPLICATE_KEY) {
                throw new UsernameTaken($username);
            }

            throw $e;
        }

        $statement = $this->pdo->prepare('SELECT id, username, created_at FROM users WHERE id = ?');
        $statement->execute([(int) $this->pdo->lastInsertId()]);
        $row = Rows::first($statement) ?? throw new UnexpectedValueException('The new user row is missing.');

        return self::user($row);
    }

    public function findByUsername(string $username): ?StoredUser
    {
        $statement = $this->pdo->prepare('SELECT id, username, created_at, password_hash FROM users WHERE username = ?');
        $statement->execute([$username]);
        $row = Rows::first($statement);

        return $row === null ? null : new StoredUser(self::user($row), Rows::string($row, 'password_hash'));
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$passwordHash, $userId]);
    }

    /** @param array<string, mixed> $row */
    public static function user(array $row, string $prefix = ''): User
    {
        return new User(
            Rows::int($row, $prefix . 'id'),
            Rows::string($row, $prefix . 'username'),
            Rows::utc($row, $prefix . 'created_at'),
        );
    }
}
