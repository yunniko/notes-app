<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Exception\NoteNotFound;
use App\Domain\Exception\VersionConflict;
use App\Domain\Note;
use App\Domain\NoteDraft;
use App\Domain\NoteRepository;
use DateTimeImmutable;

/** Test double honouring the NoteRepository contract, including owner scoping and version checks. */
final class InMemoryNoteRepository implements NoteRepository
{
    /** @var array<int, Note> */
    private array $notes = [];
    /** @var array<int, int> note id => owner id */
    private array $owners = [];
    private int $nextId = 1;
    private int $clock = 0;

    public function findAll(int $ownerId, array $priorities = [], bool $archived = false): array
    {
        $notes = array_filter(
            $this->notes,
            fn(Note $n): bool => $this->owners[$n->id] === $ownerId
                && ($n->archivedAt !== null) === $archived
                && ($priorities === [] || in_array($n->priority, $priorities, true)),
        );
        usort($notes, static fn(Note $a, Note $b): int => $archived
            ? [$b->archivedAt, $b->id] <=> [$a->archivedAt, $a->id]
            : [$b->updatedAt, $b->id] <=> [$a->updatedAt, $a->id]);

        return $notes;
    }

    public function get(int $ownerId, int $id): Note
    {
        $note = $this->notes[$id] ?? null;

        return $note !== null && $this->owners[$id] === $ownerId ? $note : throw new NoteNotFound($id);
    }

    public function create(int $ownerId, NoteDraft $draft): Note
    {
        $now = $this->tick();
        $note = new Note($this->nextId++, $draft->title, $draft->content, $draft->priority, 1, $now, $now);
        $this->owners[$note->id] = $ownerId;

        return $this->notes[$note->id] = $note;
    }

    public function update(int $ownerId, int $id, NoteDraft $draft, int $expectedVersion): Note
    {
        $current = $this->get($ownerId, $id);
        if ($current->version !== $expectedVersion) {
            throw new VersionConflict($current, $expectedVersion);
        }

        return $this->notes[$id] = new Note(
            $id,
            $draft->title,
            $draft->content,
            $draft->priority,
            $current->version + 1,
            $current->createdAt,
            $this->tick(),
            $current->archivedAt,
        );
    }

    public function setArchived(int $ownerId, int $id, bool $archived): Note
    {
        $current = $this->get($ownerId, $id);
        if (($current->archivedAt !== null) === $archived) {
            return $current;
        }

        return $this->notes[$id] = new Note(
            $id,
            $current->title,
            $current->content,
            $current->priority,
            $current->version + 1,
            $current->createdAt,
            $current->updatedAt,
            $archived ? $this->tick() : null,
        );
    }

    public function delete(int $ownerId, int $id): void
    {
        $this->get($ownerId, $id);
        unset($this->notes[$id], $this->owners[$id]);
    }

    /** A deterministic clock: every write is one second after the previous one. */
    private function tick(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-01T00:00:00Z')->modify(sprintf('+%d seconds', ++$this->clock));
    }
}
