<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\LoginThrottle;

final class InMemoryLoginThrottle implements LoginThrottle
{
    /** @var array<string, list<int>> username => failure times */
    private array $failures = [];

    public function __construct(private readonly ManualClock $clock) {}

    public function recentFailures(string $username, int $windowSeconds): int
    {
        $since = $this->clock->now - $windowSeconds;

        return count(array_filter($this->failures[$username] ?? [], static fn(int $t): bool => $t > $since));
    }

    public function recordFailure(string $username, int $windowSeconds): void
    {
        $this->failures[$username][] = $this->clock->now;
    }

    public function clear(string $username): void
    {
        unset($this->failures[$username]);
    }
}
