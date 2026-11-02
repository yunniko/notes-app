<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class TooManyLoginAttempts extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct(sprintf(
            'Too many failed logins for this username. Try again in %d minutes.',
            intdiv($retryAfterSeconds + 59, 60),
        ));
    }
}
