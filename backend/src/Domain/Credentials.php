<?php

declare(strict_types=1);

namespace App\Domain;

/** A username and password that passed CredentialsValidator. */
final readonly class Credentials
{
    public function __construct(
        public string $username,
        #[\SensitiveParameter]
        public string $password,
    ) {}
}
