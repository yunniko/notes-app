<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * What the notes list should contain: already validated (NoteValidator::listQuery), so a
 * repository can apply it without checking anything.
 */
final readonly class NoteQuery
{
    /**
     * @param list<Priority> $priorities empty = every priority
     * @param list<string>   $terms      every term must occur in the title or the content; empty = no search
     */
    public function __construct(
        public array $priorities = [],
        public bool $archived = false,
        public array $terms = [],
        public NoteSort $sort = NoteSort::Newest,
    ) {}
}
