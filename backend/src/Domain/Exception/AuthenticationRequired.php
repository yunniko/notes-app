<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

/** No valid session: the cookie is missing, unknown or expired. */
final class AuthenticationRequired extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Log in to continue.');
    }
}
