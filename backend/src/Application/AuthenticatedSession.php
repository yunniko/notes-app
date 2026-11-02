<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\User;

/** A freshly opened session: the user, and the token to hand to the browser (once). */
final readonly class AuthenticatedSession
{
    public function __construct(
        public User $user,
        #[\SensitiveParameter]
        public string $token,
    ) {}
}
