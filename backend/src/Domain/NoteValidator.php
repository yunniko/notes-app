<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exception\ValidationFailed;

/**
 * Turns untrusted request data into domain values, collecting every broken rule at once
 * so a form can show all errors in one round trip. Pure: no I/O, unit-tested directly.
 */
final class NoteValidator
{
    public const int TITLE_MAX_LENGTH = 200;
    public const int CONTENT_MAX_LENGTH = 20_000;
    public const int SEARCH_MAX_LENGTH = 100;

    /** @throws ValidationFailed */
    public function draft(mixed $input): NoteDraft
    {
        $data = $this->object($input);
        $errors = [];

        $title = $data['title'] ?? null;
        if (!is_string($title) || trim($title) === '') {
            $errors['title'] = 'Title is required.';
        } elseif (mb_strlen(trim($title)) > self::TITLE_MAX_LENGTH) {
            $errors['title'] = sprintf('Title must be at most %d characters.', self::TITLE_MAX_LENGTH);
        }

        // Content may be empty (a title-only note is useful); it is stored verbatim, not trimmed.
        $content = $data['content'] ?? '';
        if (!is_string($content)) {
            $errors['content'] = 'Content must be text.';
        } elseif (mb_strlen($content) > self::CONTENT_MAX_LENGTH) {
            $errors['content'] = sprintf('Content must be at most %d characters.', self::CONTENT_MAX_LENGTH);
        }

        $priority = is_string($data['priority'] ?? null) ? Priority::tryFrom($data['priority']) : null;
        if ($priority === null) {
            $errors['priority'] = sprintf('Priority must be one of: %s.', implode(', ', Priority::values()));
        }

        if ($errors !== [] || !is_string($title) || !is_string($content) || $priority === null) {
            throw new ValidationFailed($errors ?: ['body' => 'Invalid note.']);
        }

        return new NoteDraft(trim($title), $content, $priority);
    }

    /**
     * The version the client last read; required on update for optimistic locking (D004).
     *
     * @throws ValidationFailed
     */
    public function expectedVersion(mixed $input): int
    {
        $version = $this->object($input)['version'] ?? null;
        if (!is_int($version) || $version < 1) {
            throw new ValidationFailed(['version' => 'Version must be the positive integer you last received for this note.']);
        }

        return $version;
    }

    /**
     * Parses the `priority` list filter, e.g. "high" or "high,medium". Blank means no filter.
     *
     * @return list<Priority>
     *
     * @throws ValidationFailed
     */
    public function priorityFilter(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (!is_string($raw)) {
            throw new ValidationFailed(['priority' => 'Priority filter must be a comma-separated list.']);
        }

        $priorities = [];
        foreach (explode(',', $raw) as $part) {
            $priority = Priority::tryFrom(trim($part));
            if ($priority === null) {
                throw new ValidationFailed(['priority' => sprintf(
                    'Unknown priority "%s"; use one of: %s.',
                    trim($part),
                    implode(', ', Priority::values()),
                )]);
            }
            $priorities[$priority->value] = $priority;
        }

        return array_values($priorities);
    }

    /**
     * Parses the `archived` list filter: "true" lists archived notes, "false" or blank active ones.
     *
     * @throws ValidationFailed
     */
    public function archivedFilter(mixed $raw): bool
    {
        return match ($raw) {
            null, '', 'false' => false,
            'true' => true,
            default => throw new ValidationFailed(['archived' => 'Archived must be "true" or "false".']),
        };
    }

    /**
     * Parses the `q` search: words separated by whitespace, each of which must occur in the note.
     * Blank means no search.
     *
     * @return list<string>
     *
     * @throws ValidationFailed
     */
    public function searchTerms(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }
        if (!is_string($raw)) {
            throw new ValidationFailed(['q' => 'Search must be text.']);
        }
        $raw = trim($raw);
        if (mb_strlen($raw) > self::SEARCH_MAX_LENGTH) {
            throw new ValidationFailed(['q' => sprintf('Search must be at most %d characters.', self::SEARCH_MAX_LENGTH)]);
        }

        return $raw === '' ? [] : array_values(array_unique(preg_split('/\s+/u', $raw) ?: []));
    }

    /**
     * Parses the list endpoint's query string, reporting every invalid parameter at once.
     *
     * @param array<array-key, mixed> $params
     *
     * @throws ValidationFailed
     */
    public function listQuery(array $params): NoteQuery
    {
        $errors = [];
        $priorities = [];
        $archived = false;
        $terms = [];
        try {
            $priorities = $this->priorityFilter($params['priority'] ?? null);
        } catch (ValidationFailed $e) {
            $errors += $e->errors;
        }
        try {
            $archived = $this->archivedFilter($params['archived'] ?? null);
        } catch (ValidationFailed $e) {
            $errors += $e->errors;
        }
        try {
            $terms = $this->searchTerms($params['q'] ?? null);
        } catch (ValidationFailed $e) {
            $errors += $e->errors;
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new NoteQuery($priorities, $archived, $terms);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationFailed
     */
    private function object(mixed $input): array
    {
        if (!is_array($input) || array_is_list($input) && $input !== []) {
            throw new ValidationFailed(['body' => 'Request body must be a JSON object.']);
        }

        /** @var array<string, mixed> $input */
        return $input;
    }
}
