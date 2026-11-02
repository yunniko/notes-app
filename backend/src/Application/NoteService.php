<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Exception\NoteNotFound;
use App\Domain\Exception\ValidationFailed;
use App\Domain\Exception\VersionConflict;
use App\Domain\Note;
use App\Domain\NoteCounts;
use App\Domain\NoteRepository;
use App\Domain\NoteValidator;

/**
 * The use cases of the app. HTTP actions call this and know nothing about storage;
 * the repository knows nothing about request formats. Every call acts for one owner.
 */
final readonly class NoteService
{
    public function __construct(
        private NoteRepository $notes,
        private NoteValidator $validator,
    ) {}

    /**
     * @param array<array-key, mixed> $params the raw query string: `priority`, `archived`, `q`, `sort`
     *
     * @return list<Note>
     *
     * @throws ValidationFailed
     */
    public function list(int $ownerId, array $params = []): array
    {
        return $this->notes->findAll($ownerId, $this->validator->listQuery($params));
    }

    public function counts(int $ownerId): NoteCounts
    {
        return $this->notes->counts($ownerId);
    }

    /** @throws NoteNotFound */
    public function archive(int $ownerId, int $id): Note
    {
        return $this->notes->setArchived($ownerId, $id, true);
    }

    /** @throws NoteNotFound */
    public function restore(int $ownerId, int $id): Note
    {
        return $this->notes->setArchived($ownerId, $id, false);
    }

    /** Permanent; the UI asks for confirmation first. @throws NoteNotFound */
    public function delete(int $ownerId, int $id): void
    {
        $this->notes->delete($ownerId, $id);
    }

    /** @throws NoteNotFound */
    public function get(int $ownerId, int $id): Note
    {
        return $this->notes->get($ownerId, $id);
    }

    /** @throws ValidationFailed */
    public function create(int $ownerId, mixed $input): Note
    {
        return $this->notes->create($ownerId, $this->validator->draft($input));
    }

    /** @throws ValidationFailed|NoteNotFound|VersionConflict */
    public function update(int $ownerId, int $id, mixed $input): Note
    {
        // Validate both parts before failing so the client gets every error in one response.
        $errors = [];
        try {
            $draft = $this->validator->draft($input);
        } catch (ValidationFailed $e) {
            $errors = $e->errors;
        }
        try {
            $expectedVersion = $this->validator->expectedVersion($input);
        } catch (ValidationFailed $e) {
            $errors += $e->errors;
        }
        if ($errors !== [] || !isset($draft, $expectedVersion)) {
            throw new ValidationFailed($errors ?: ['body' => 'Invalid note.']);
        }

        return $this->notes->update($ownerId, $id, $draft, $expectedVersion);
    }
}
