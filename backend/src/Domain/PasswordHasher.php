<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Argon2id with OWASP's minimum parameters: 19 MiB, two passes, one lane (D012).
 */
final class PasswordHasher
{
    private const array OPTIONS = ['memory_cost' => 19_456, 'time_cost' => 2, 'threads' => 1];

    /** Hash of a random password, checked against when the user does not exist. */
    private ?string $dummyHash = null;

    public function hash(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, self::OPTIONS);
    }

    /**
     * Checks a password. With no stored hash (unknown user) it still does a full Argon2 run and
     * returns false, so the response time does not reveal whether a username exists.
     */
    public function verify(#[\SensitiveParameter] string $password, ?string $hash): bool
    {
        if ($hash === null) {
            $this->dummyHash ??= $this->hash(bin2hex(random_bytes(16)));
            password_verify($password, $this->dummyHash);

            return false;
        }

        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::OPTIONS);
    }
}
