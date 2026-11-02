<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Exception\UsernameTaken;
use App\Domain\StoredUser;
use App\Domain\User;
use App\Domain\UserRepository;
use DateTimeImmutable;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, StoredUser> lower-cased username => user */
    private array $users = [];

    public function create(string $username, string $passwordHash): User
    {
        $key = strtolower($username);
        if (isset($this->users[$key])) {
            throw new UsernameTaken($username);
        }
        $user = new User(count($this->users) + 1, $username, new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $this->users[$key] = new StoredUser($user, $passwordHash);

        return $user;
    }

    public function findByUsername(string $username): ?StoredUser
    {
        return $this->users[strtolower($username)] ?? null;
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        foreach ($this->users as $key => $stored) {
            if ($stored->user->id === $userId) {
                $this->users[$key] = new StoredUser($stored->user, $passwordHash);
            }
        }
    }

    public function byId(int $id): ?User
    {
        foreach ($this->users as $stored) {
            if ($stored->user->id === $id) {
                return $stored->user;
            }
        }

        return null;
    }
}
