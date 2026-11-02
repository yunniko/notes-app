<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/** An account. The password hash is not part of it; see StoredUser. */
final readonly class User
{
    public function __construct(
        public int $id,
        public string $username,
        public DateTimeImmutable $createdAt,
    ) {}

    /** @return array{id: int, username: string, createdAt: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
