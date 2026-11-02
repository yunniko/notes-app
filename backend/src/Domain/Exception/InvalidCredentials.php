<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

/** Wrong username or password. Deliberately does not say which. */
final class InvalidCredentials extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Wrong username or password.');
    }
}
