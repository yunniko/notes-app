<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Exception\NoteNotFound;
use App\Domain\Exception\VersionConflict;
use App\Domain\Note;
use App\Domain\NoteDraft;
use App\Domain\NoteRepository;
use App\Domain\Priority;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use UnexpectedValueException;

/**
 * MariaDB implementation of NoteRepository using PDO prepared statements only.
 * The connection runs in UTC (see Database::connect), so stored timestamps are UTC.
 */
final readonly class PdoNoteRepository implements NoteRepository
{
    private const string COLUMNS = 'id, title, content, priority, version, created_at, updated_at';

    public function __construct(private PDO $pdo) {}

    public function findAll(array $priorities = []): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM notes';
        $params = [];
        if ($priorities !== []) {
            $sql .= ' WHERE priority IN (' . implode(', ', array_fill(0, count($priorities), '?')) . ')';
            $params = array_map(static fn(Priority $p): string => $p->value, $priorities);
        }
        $sql .= ' ORDER BY updated_at DESC, id DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map($this->hydrate(...), $this->rows($statement->fetchAll()));
    }

    public function get(int $id): Note
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM notes WHERE id = ?');
        $statement->execute([$id]);
        $rows = $this->rows($statement->fetchAll());

        return $rows === [] ? throw new NoteNotFound($id) : $this->hydrate($rows[0]);
    }

    public function create(NoteDraft $draft): Note
    {
        $statement = $this->pdo->prepare('INSERT INTO notes (title, content, priority) VALUES (?, ?, ?)');
        $statement->execute([$draft->title, $draft->content, $draft->priority->value]);

        return $this->get((int) $this->pdo->lastInsertId());
    }

    public function update(int $id, NoteDraft $draft, int $expectedVersion): Note
    {
        // Compare-and-set in one statement: the WHERE on version makes a concurrent
        // edit lose cleanly instead of overwriting (D004).
        $statement = $this->pdo->prepare(
            'UPDATE notes
                SET title = ?, content = ?, priority = ?, version = version + 1, updated_at = CURRENT_TIMESTAMP(3)
              WHERE id = ? AND version = ?',
        );
        $statement->execute([$draft->title, $draft->content, $draft->priority->value, $id, $expectedVersion]);

        if ($statement->rowCount() === 0) {
            // Either the note is gone (get() throws NoteNotFound) or its version moved on.
            throw new VersionConflict($this->get($id), $expectedVersion);
        }

        return $this->get($id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(mixed $result): array
    {
        if (!is_array($result)) {
            throw new UnexpectedValueException('PDO fetchAll did not return an array.');
        }

        /** @var list<array<string, mixed>> $result */
        return $result;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Note
    {
        $utc = new DateTimeZone('UTC');

        return new Note(
            id: (int) self::scalar($row, 'id'),
            title: (string) self::scalar($row, 'title'),
            content: (string) self::scalar($row, 'content'),
            priority: Priority::from((string) self::scalar($row, 'priority')),
            version: (int) self::scalar($row, 'version'),
            createdAt: new DateTimeImmutable((string) self::scalar($row, 'created_at'), $utc),
            updatedAt: new DateTimeImmutable((string) self::scalar($row, 'updated_at'), $utc),
        );
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
