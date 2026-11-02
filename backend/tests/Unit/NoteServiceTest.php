<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Application\NoteService;
use App\Domain\Exception\NoteNotFound;
use App\Domain\Exception\ValidationFailed;
use App\Domain\Exception\VersionConflict;
use App\Domain\Note;
use App\Domain\NoteValidator;
use App\Domain\Priority;
use PHPUnit\Framework\TestCase;

final class NoteServiceTest extends TestCase
{
    private NoteService $service;

    protected function setUp(): void
    {
        $this->service = new NoteService(new InMemoryNoteRepository(), new NoteValidator());
    }

    public function testCreatedNoteStartsAtVersionOne(): void
    {
        $note = $this->service->create(['title' => 'Plan trip', 'content' => 'Prague', 'priority' => 'medium']);

        self::assertSame(1, $note->version);
        self::assertSame(Priority::Medium, $note->priority);
        self::assertEquals($note, $this->service->get($note->id));
    }

    public function testListFiltersByPriorityAndShowsRecentlyUpdatedFirst(): void
    {
        $a = $this->service->create(['title' => 'A', 'priority' => 'high']);
        $this->service->create(['title' => 'B', 'priority' => 'low']);
        $c = $this->service->create(['title' => 'C', 'priority' => 'high']);
        $this->service->update($a->id, ['title' => 'A2', 'priority' => 'high', 'version' => 1]);

        self::assertSame(['A2', 'C'], $this->titles($this->service->list('high')));
        self::assertSame(['A2', 'C', 'B'], $this->titles($this->service->list(null)));
        self::assertSame(['A2', 'C', 'B'], $this->titles($this->service->list('high,low')));
        self::assertSame($c->id, $this->service->list('high')[1]->id);
    }

    public function testUpdateReplacesFieldsAndBumpsVersion(): void
    {
        $note = $this->service->create(['title' => 'Draft', 'content' => 'old', 'priority' => 'low']);

        $updated = $this->service->update($note->id, ['title' => 'Final', 'content' => 'new', 'priority' => 'high', 'version' => 1]);

        self::assertSame(['Final', 'new', Priority::High, 2], [$updated->title, $updated->content, $updated->priority, $updated->version]);
        self::assertEquals($note->createdAt, $updated->createdAt);
    }

    public function testUpdateWithStaleVersionConflictsAndKeepsTheNewerEdit(): void
    {
        $note = $this->service->create(['title' => 'Shared', 'priority' => 'low']);
        $this->service->update($note->id, ['title' => 'Edited in tab 1', 'priority' => 'low', 'version' => 1]);

        try {
            $this->service->update($note->id, ['title' => 'Edited in tab 2', 'priority' => 'low', 'version' => 1]);
            self::fail('Expected VersionConflict.');
        } catch (VersionConflict $e) {
            self::assertSame('Edited in tab 1', $e->current->title);
            self::assertSame(2, $e->current->version);
        }
        self::assertSame('Edited in tab 1', $this->service->get($note->id)->title);
    }

    public function testUpdateReportsDraftAndVersionErrorsTogether(): void
    {
        $note = $this->service->create(['title' => 'x', 'priority' => 'low']);

        try {
            $this->service->update($note->id, ['title' => '', 'priority' => 'low']);
            self::fail('Expected ValidationFailed.');
        } catch (ValidationFailed $e) {
            self::assertSame(['title', 'version'], array_keys($e->errors));
        }
    }

    public function testMissingNoteIsReported(): void
    {
        $this->expectException(NoteNotFound::class);
        $this->service->update(999, ['title' => 'x', 'priority' => 'low', 'version' => 1]);
    }

    /**
     * @param list<Note> $notes
     *
     * @return list<string>
     */
    private function titles(array $notes): array
    {
        return array_map(static fn(Note $n): string => $n->title, $notes);
    }
}
