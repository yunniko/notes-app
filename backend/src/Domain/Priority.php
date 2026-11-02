<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Note priority. The string values are the wire and storage format;
 * the database column is ENUM('low','medium','high') in the same order.
 */
enum Priority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn(self $p): string => $p->value, self::cases());
    }

    /** Position when sorting by priority: high first. */
    public function rank(): int
    {
        return match ($this) {
            self::High => 0,
            self::Medium => 1,
            self::Low => 2,
        };
    }
}
