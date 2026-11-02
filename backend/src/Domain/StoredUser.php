<?php

declare(strict_types=1);

namespace App\Domain;

/** A user together with their password hash, as loaded for a login check. */
final readonly class StoredUser
{
    public function __construct(
        public User $user,
        public string $passwordHash,
    ) {}
}
