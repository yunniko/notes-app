<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Validated user input for creating or replacing a note. Only NoteValidator builds one,
 * so holding a NoteDraft means the title/content/priority rules already passed.
 */
final readonly class NoteDraft
{
    public function __construct(
        public string $title,
        public string $content,
        public Priority $priority,
    ) {}
}
