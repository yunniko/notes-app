<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Exception\NoteNotFound;
use App\Domain\Exception\VersionConflict;
use App\Domain\Note;
use App\Domain\NoteDraft;
use App\Domain\NoteRepository;
use DateTimeImmutable;

/** Test double honouring the NoteRepository contract, including version checks. */
final class InMemoryNoteRepository implements NoteRepository
{
    /** @var array<int, Note> */
    private array $notes = [];
    private int $nextId = 1;
    private int $clock = 0;

    public function findAll(array $priorities = []): array
    {
        $notes = array_filter(
            $this->notes,
            static fn(Note $n): bool => $priorities === [] || in_array($n->priority, $priorities, true),
        );
        usort($notes, static fn(Note $a, Note $b): int => [$b->updatedAt, $b->id] <=> [$a->updatedAt, $a->id]);

        return $notes;
    }

    public function get(int $id): Note
    {
        return $this->notes[$id] ?? throw new NoteNotFound($id);
    }

    public function create(NoteDraft $draft): Note
    {
        $now = $this->tick();
        $note = new Note($this->nextId++, $draft->title, $draft->content, $draft->priority, 1, $now, $now);

        return $this->notes[$note->id] = $note;
    }

    public function update(int $id, NoteDraft $draft, int $expectedVersion): Note
    {
        $current = $this->get($id);
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
        );
    }

    /** A deterministic clock: every write is one second after the previous one. */
    private function tick(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-01T00:00:00Z')->modify(sprintf('+%d seconds', ++$this->clock));
    }
}
