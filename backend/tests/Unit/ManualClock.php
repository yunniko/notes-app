<?php

declare(strict_types=1);

namespace App\Tests\Unit;

/** Seconds since an arbitrary start; tests move it forward to expire sessions and failures. */
final class ManualClock
{
    public int $now = 0;
}
