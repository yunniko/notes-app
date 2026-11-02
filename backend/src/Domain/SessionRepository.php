<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Server-side login sessions, keyed by a hash of the token the browser holds (D011).
 * Expiry is judged by the database clock, so every API instance agrees on it.
 */
interface SessionRepository
{
    public function create(string $tokenHash, int $userId, int $lifetimeSeconds): void;

    /** The owner of an unexpired session, or null. */
    public function findUser(string $tokenHash): ?User;

    public function delete(string $tokenHash): void;

    public function deleteExpired(): void;
}
