<?php

declare(strict_types=1);

namespace App\Domain;

/** Counts failed logins per username, so password guessing is slowed down (D012). */
interface LoginThrottle
{
    public function recentFailures(string $username, int $windowSeconds): int;

    /** Records a failure, and forgets failures older than the window for every username. */
    public function recordFailure(string $username, int $windowSeconds): void;

    public function clear(string $username): void;
}
