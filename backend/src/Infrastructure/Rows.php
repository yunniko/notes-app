<?php

declare(strict_types=1);

namespace App\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use PDOStatement;
use UnexpectedValueException;

/** Typed access to PDO result rows, failing loudly when a row is not the expected shape. */
final class Rows
{
    /** @return list<array<string, mixed>> */
    public static function all(PDOStatement $statement): array
    {
        // The connection's default fetch mode is FETCH_ASSOC (Database::connect).
        /** @var list<array<string, mixed>> $result */
        $result = $statement->fetchAll();

        return $result;
    }

    /** @return array<string, mixed>|null the first row, or null when there is none */
    public static function first(PDOStatement $statement): ?array
    {
        return self::all($statement)[0] ?? null;
    }

    /** @param array<string, mixed> $row */
    public static function int(array $row, string $column): int
    {
        return (int) self::scalar($row, $column);
    }

    /** @param array<string, mixed> $row */
    public static function string(array $row, string $column): string
    {
        return (string) self::scalar($row, $column);
    }

    /**
     * A DATETIME column; the connection runs in UTC (see Database::connect).
     *
     * @param array<string, mixed> $row
     */
    public static function utc(array $row, string $column): DateTimeImmutable
    {
        return new DateTimeImmutable(self::string($row, $column), new DateTimeZone('UTC'));
    }

    /** @param array<string, mixed> $row */
    private static function scalar(array $row, string $column): int|float|string
    {
        $value = $row[$column] ?? null;
        if (!is_scalar($value) || is_bool($value)) {
            throw new UnexpectedValueException(sprintf('Column "%s" is missing or not scalar.', $column));
        }

        return $value;
    }
}
