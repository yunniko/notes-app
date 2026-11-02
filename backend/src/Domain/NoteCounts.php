<?php

declare(strict_types=1);

namespace App\Domain;

/** How many notes an owner has in each list, regardless of any filter or search (D016). */
final readonly class NoteCounts
{
    public function __construct(
        public int $active,
        public int $archived,
    ) {}

    /** @return array{active: int, archived: int} */
    public function toArray(): array
    {
        return ['active' => $this->active, 'archived' => $this->archived];
    }
}
