<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Order of the notes list. "Newest" and "oldest" go by the time that defines the list: the last
 * update for active notes, the archiving time for archived ones. "Priority" puts high first, and
 * the newest first within one priority (D016).
 */
enum NoteSort: string
{
    case Newest = 'newest';
    case Oldest = 'oldest';
    case Priority = 'priority';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn(self $s): string => $s->value, self::cases());
    }
}
