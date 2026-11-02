<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exception\NoteNotFound;
use App\Domain\Exception\VersionConflict;

/**
 * Persistence port. The application layer depends on this interface only;
 * PdoNoteRepository implements it for MariaDB, tests use an in-memory fake.
 */
interface NoteRepository
{
    /**
     * Notes ordered by most recently updated first.
     *
     * @param list<Priority> $priorities empty list = no filter
     *
     * @return list<Note>
     */
    public function findAll(array $priorities = []): array;

    /** @throws NoteNotFound */
    public function get(int $id): Note;

    public function create(NoteDraft $draft): Note;

    /**
     * Replaces the note's fields if its stored version still equals $expectedVersion.
     *
     * @throws NoteNotFound    when no note has this id
     * @throws VersionConflict when the note was changed since $expectedVersion was read
     */
    public function update(int $id, NoteDraft $draft, int $expectedVersion): Note;
}
