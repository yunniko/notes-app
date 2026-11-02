<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

/** Input broke one or more rules; `errors` maps a field name to a human-readable message. */
final class ValidationFailed extends RuntimeException
{
    /** @param non-empty-array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The request contains invalid fields.');
    }
}
