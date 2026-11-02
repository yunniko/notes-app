<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/**
 * A stored note. Immutable: an update produces a new row version in the repository,
 * and `version` increments on every successful update (optimistic locking, see D004).
 */
final readonly class Note
{
    public function __construct(
        public int $id,
        public string $title,
        public string $content,
        public Priority $priority,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        /** Set while the note is archived (D013). */
        public ?DateTimeImmutable $archivedAt = null,
    ) {}

    /** @return array{id: int, title: string, content: string, priority: string, version: int, createdAt: string, updatedAt: string, archivedAt: ?string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'priority' => $this->priority->value,
            'version' => $this->version,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'archivedAt' => $this->archivedAt?->format(DATE_ATOM),
        ];
    }
}
