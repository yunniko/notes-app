<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exception\UsernameTaken;

interface UserRepository
{
    /** @throws UsernameTaken when the name is in use, compared case-insensitively */
    public function create(string $username, string $passwordHash): User;

    /** Case-insensitive lookup. */
    public function findByUsername(string $username): ?StoredUser;

    public function updatePasswordHash(int $userId, string $passwordHash): void;
}
