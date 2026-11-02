<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class NoteNotFound extends RuntimeException
{
    public function __construct(public readonly int $noteId)
    {
        parent::__construct(sprintf('Note %d does not exist.', $noteId));
    }
}
