<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Note;
use RuntimeException;

/**
 * The client edited an outdated copy of the note. Carries the current state so the
 * client can show what changed instead of silently overwriting it (see D004).
 */
final class VersionConflict extends RuntimeException
{
    public function __construct(public readonly Note $current, public readonly int $expectedVersion)
    {
        parent::__construct(sprintf(
            'Note %d was changed by someone else (you edited version %d, current is %d).',
            $current->id,
            $expectedVersion,
            $current->version,
        ));
    }
}
