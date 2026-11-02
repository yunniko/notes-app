<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exception\NoteNotFound;
use App\Domain\Exception\VersionConflict;

/**
 * Persistence port. The application layer depends on this interface only;
 * PdoNoteRepository implements it for MariaDB, tests use an in-memory fake.
 *
 * Every method is scoped to one owner: another user's note behaves exactly like a missing
 * one (NoteNotFound), so ids reveal nothing about other accounts (D010).
 */
interface NoteRepository
{
    /**
     * The owner's notes matching $query: active ones most recently updated first, or archived
     * ones most recently archived first. A search term matches as a substring of the title or
     * the content, ignoring letter case and accents (D014).
     *
     * @return list<Note>
     */
    public function findAll(int $ownerId, NoteQuery $query = new NoteQuery()): array;

    /** @throws NoteNotFound */
    public function get(int $ownerId, int $id): Note;

    public function create(int $ownerId, NoteDraft $draft): Note;

    /**
     * Replaces the note's fields if its stored version still equals $expectedVersion.
     *
     * @throws NoteNotFound    when the owner has no note with this id
     * @throws VersionConflict when the note was changed since $expectedVersion was read
     */
    public function update(int $ownerId, int $id, NoteDraft $draft, int $expectedVersion): Note;

    /**
     * Archives or restores the note. Idempotent; the version increments only when the state
     * changes, and `updatedAt` is left alone because the content did not change (D013).
     *
     * @throws NoteNotFound
     */
    public function setArchived(int $ownerId, int $id, bool $archived): Note;

    /** @throws NoteNotFound */
    public function delete(int $ownerId, int $id): void;
}
