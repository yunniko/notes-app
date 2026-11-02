<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class UsernameTaken extends RuntimeException
{
    public function __construct(public readonly string $username)
    {
        parent::__construct(sprintf('The username "%s" is taken.', $username));
    }
}
