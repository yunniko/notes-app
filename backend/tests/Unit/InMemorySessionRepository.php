<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\SessionRepository;
use App\Domain\User;

final class InMemorySessionRepository implements SessionRepository
{
    /** @var array<string, array{userId: int, expiresAt: int}> token hash => session */
    public array $sessions = [];

    public function __construct(private readonly InMemoryUserRepository $users, private readonly ManualClock $clock) {}

    public function create(string $tokenHash, int $userId, int $lifetimeSeconds): void
    {
        $this->sessions[$tokenHash] = ['userId' => $userId, 'expiresAt' => $this->clock->now + $lifetimeSeconds];
    }

    public function findUser(string $tokenHash): ?User
    {
        $session = $this->sessions[$tokenHash] ?? null;

        return $session === null || $session['expiresAt'] <= $this->clock->now ? null : $this->users->byId($session['userId']);
    }

    public function delete(string $tokenHash): void
    {
        unset($this->sessions[$tokenHash]);
    }

    public function deleteExpired(): void
    {
        $this->sessions = array_filter($this->sessions, fn(array $s): bool => $s['expiresAt'] > $this->clock->now);
    }
}
