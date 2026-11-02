<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Exception\NoteNotFound;
use App\Domain\Exception\VersionConflict;
use App\Domain\Note;
use App\Domain\NoteDraft;
use App\Domain\NoteRepository;
use App\Domain\Priority;
use PDO;

/**
 * MariaDB implementation of NoteRepository using PDO prepared statements only.
 * Every statement carries `user_id = ?`, so no query can reach another user's note (D010).
 */
final readonly class PdoNoteRepository implements NoteRepository
{
    private const string COLUMNS = 'id, title, content, priority, version, created_at, updated_at, archived_at';

    public function __construct(private PDO $pdo) {}

    public function findAll(int $ownerId, array $priorities = [], bool $archived = false): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM notes WHERE user_id = ?'
            . ($archived ? ' AND archived_at IS NOT NULL' : ' AND archived_at IS NULL');
        $params = [$ownerId];
        if ($priorities !== []) {
            $sql .= ' AND priority IN (' . implode(', ', array_fill(0, count($priorities), '?')) . ')';
            array_push($params, ...array_map(static fn(Priority $p): string => $p->value, $priorities));
        }
        $sql .= $archived ? ' ORDER BY archived_at DESC, id DESC' : ' ORDER BY updated_at DESC, id DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(self::hydrate(...), Rows::all($statement));
    }

    public function get(int $ownerId, int $id): Note
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM notes WHERE id = ? AND user_id = ?');
        $statement->execute([$id, $ownerId]);
        $row = Rows::first($statement);

        return $row === null ? throw new NoteNotFound($id) : self::hydrate($row);
    }

    public function create(int $ownerId, NoteDraft $draft): Note
    {
        $statement = $this->pdo->prepare('INSERT INTO notes (user_id, title, content, priority) VALUES (?, ?, ?, ?)');
        $statement->execute([$ownerId, $draft->title, $draft->content, $draft->priority->value]);

        return $this->get($ownerId, (int) $this->pdo->lastInsertId());
    }

    public function update(int $ownerId, int $id, NoteDraft $draft, int $expectedVersion): Note
    {
        // Compare-and-set in one statement: the WHERE on version makes a concurrent
        // edit lose cleanly instead of overwriting (D004).
        $statement = $this->pdo->prepare(
            'UPDATE notes
                SET title = ?, content = ?, priority = ?, version = version + 1, updated_at = CURRENT_TIMESTAMP(3)
              WHERE id = ? AND user_id = ? AND version = ?',
        );
        $statement->execute([$draft->title, $draft->content, $draft->priority->value, $id, $ownerId, $expectedVersion]);

        if ($statement->rowCount() === 0) {
            // Either the owner has no such note (get() throws NoteNotFound) or its version moved on.
            throw new VersionConflict($this->get($ownerId, $id), $expectedVersion);
        }

        return $this->get($ownerId, $id);
    }

    public function setArchived(int $ownerId, int $id, bool $archived): Note
    {
        // Matches only when the state actually changes, so repeating the call is a no-op.
        $statement = $this->pdo->prepare($archived
            ? 'UPDATE notes SET archived_at = CURRENT_TIMESTAMP(3), version = version + 1
                WHERE id = ? AND user_id = ? AND archived_at IS NULL'
            : 'UPDATE notes SET archived_at = NULL, version = version + 1
                WHERE id = ? AND user_id = ? AND archived_at IS NOT NULL', );
        $statement->execute([$id, $ownerId]);

        return $this->get($ownerId, $id);
    }

    public function delete(int $ownerId, int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?');
        $statement->execute([$id, $ownerId]);
        if ($statement->rowCount() === 0) {
            throw new NoteNotFound($id);
        }
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): Note
    {
        return new Note(
            id: Rows::int($row, 'id'),
            title: Rows::string($row, 'title'),
            content: Rows::string($row, 'content'),
            priority: Priority::from(Rows::string($row, 'priority')),
            version: Rows::int($row, 'version'),
            createdAt: Rows::utc($row, 'created_at'),
            updatedAt: Rows::utc($row, 'updated_at'),
            archivedAt: ($row['archived_at'] ?? null) === null ? null : Rows::utc($row, 'archived_at'),
        );
    }
}
