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
    private const int OWNER = 1;

    private NoteService $service;

    protected function setUp(): void
    {
        $this->service = new NoteService(new InMemoryNoteRepository(), new NoteValidator());
    }

    public function testCreatedNoteStartsAtVersionOne(): void
    {
        $note = $this->service->create(self::OWNER, ['title' => 'Plan trip', 'content' => 'Prague', 'priority' => 'medium']);

        self::assertSame(1, $note->version);
        self::assertSame(Priority::Medium, $note->priority);
        self::assertEquals($note, $this->service->get(self::OWNER, $note->id));
    }

    public function testListFiltersByPriorityAndShowsRecentlyUpdatedFirst(): void
    {
        $a = $this->service->create(self::OWNER, ['title' => 'A', 'priority' => 'high']);
        $this->service->create(self::OWNER, ['title' => 'B', 'priority' => 'low']);
        $c = $this->service->create(self::OWNER, ['title' => 'C', 'priority' => 'high']);
        $this->service->update(self::OWNER, $a->id, ['title' => 'A2', 'priority' => 'high', 'version' => 1]);

        self::assertSame(['A2', 'C'], $this->titles($this->service->list(self::OWNER, 'high')));
        self::assertSame(['A2', 'C', 'B'], $this->titles($this->service->list(self::OWNER, null)));
        self::assertSame(['A2', 'C', 'B'], $this->titles($this->service->list(self::OWNER, 'high,low')));
        self::assertSame($c->id, $this->service->list(self::OWNER, 'high')[1]->id);
    }

    public function testUpdateReplacesFieldsAndBumpsVersion(): void
    {
        $note = $this->service->create(self::OWNER, ['title' => 'Draft', 'content' => 'old', 'priority' => 'low']);

        $updated = $this->service->update(self::OWNER, $note->id, ['title' => 'Final', 'content' => 'new', 'priority' => 'high', 'version' => 1]);

        self::assertSame(['Final', 'new', Priority::High, 2], [$updated->title, $updated->content, $updated->priority, $updated->version]);
        self::assertEquals($note->createdAt, $updated->createdAt);
    }

    public function testUpdateWithStaleVersionConflictsAndKeepsTheNewerEdit(): void
    {
        $note = $this->service->create(self::OWNER, ['title' => 'Shared', 'priority' => 'low']);
        $this->service->update(self::OWNER, $note->id, ['title' => 'Edited in tab 1', 'priority' => 'low', 'version' => 1]);

        try {
            $this->service->update(self::OWNER, $note->id, ['title' => 'Edited in tab 2', 'priority' => 'low', 'version' => 1]);
            self::fail('Expected VersionConflict.');
        } catch (VersionConflict $e) {
            self::assertSame('Edited in tab 1', $e->current->title);
            self::assertSame(2, $e->current->version);
        }
        self::assertSame('Edited in tab 1', $this->service->get(self::OWNER, $note->id)->title);
    }

    public function testUpdateReportsDraftAndVersionErrorsTogether(): void
    {
        $note = $this->service->create(self::OWNER, ['title' => 'x', 'priority' => 'low']);

        try {
            $this->service->update(self::OWNER, $note->id, ['title' => '', 'priority' => 'low']);
            self::fail('Expected ValidationFailed.');
        } catch (ValidationFailed $e) {
            self::assertSame(['title', 'version'], array_keys($e->errors));
        }
    }

    public function testMissingNoteIsReported(): void
    {
        $this->expectException(NoteNotFound::class);
        $this->service->update(self::OWNER, 999, ['title' => 'x', 'priority' => 'low', 'version' => 1]);
    }

    public function testNotesOfAnotherOwnerAreInvisible(): void
    {
        $mine = $this->service->create(self::OWNER, ['title' => 'Mine', 'priority' => 'low']);
        $this->service->create(2, ['title' => 'Theirs', 'priority' => 'low']);

        self::assertSame(['Mine'], $this->titles($this->service->list(self::OWNER, null)));
        self::assertSame(['Theirs'], $this->titles($this->service->list(2, null)));
        $this->expectException(NoteNotFound::class);
        $this->service->update(2, $mine->id, ['title' => 'Taken over', 'priority' => 'low', 'version' => 1]);
    }

    public function testArchivingMovesANoteBetweenTheTwoListsAndKeepsItsPlace(): void
    {
        $old = $this->service->create(self::OWNER, ['title' => 'Old', 'priority' => 'low']);
        $this->service->create(self::OWNER, ['title' => 'New', 'priority' => 'high']);

        $archived = $this->service->archive(self::OWNER, $old->id);
        self::assertNotNull($archived->archivedAt);
        self::assertSame(2, $archived->version);
        self::assertEquals($old->updatedAt, $archived->updatedAt);
        self::assertSame(['New'], $this->titles($this->service->list(self::OWNER, null)));
        self::assertSame(['Old'], $this->titles($this->service->list(self::OWNER, 'low', 'true')));
        self::assertSame([], $this->titles($this->service->list(self::OWNER, 'high', 'true')));

        self::assertSame(2, $this->service->archive(self::OWNER, $old->id)->version, 'archiving twice changes nothing');

        $restored = $this->service->restore(self::OWNER, $old->id);
        self::assertNull($restored->archivedAt);
        self::assertSame(3, $restored->version);
        self::assertSame(['New', 'Old'], $this->titles($this->service->list(self::OWNER, null, 'false')));
    }

    public function testArchivedListIsMostRecentlyArchivedFirst(): void
    {
        $a = $this->service->create(self::OWNER, ['title' => 'A', 'priority' => 'low']);
        $b = $this->service->create(self::OWNER, ['title' => 'B', 'priority' => 'low']);
        $this->service->archive(self::OWNER, $b->id);
        $this->service->archive(self::OWNER, $a->id);

        self::assertSame(['A', 'B'], $this->titles($this->service->list(self::OWNER, null, 'true')));
    }

    public function testListReportsBothBadFiltersTogether(): void
    {
        try {
            $this->service->list(self::OWNER, 'urgent', 'maybe');
            self::fail('Expected ValidationFailed.');
        } catch (ValidationFailed $e) {
            self::assertSame(['priority', 'archived'], array_keys($e->errors));
        }
    }

    public function testDeleteIsPermanentAndScopedToTheOwner(): void
    {
        $note = $this->service->create(self::OWNER, ['title' => 'Gone soon', 'priority' => 'low']);

        try {
            $this->service->delete(2, $note->id);
            self::fail('Expected NoteNotFound.');
        } catch (NoteNotFound) {
            self::assertSame('Gone soon', $this->service->get(self::OWNER, $note->id)->title);
        }

        $this->service->delete(self::OWNER, $note->id);
        $this->expectException(NoteNotFound::class);
        $this->service->get(self::OWNER, $note->id);
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
