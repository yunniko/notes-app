<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Exception\ValidationFailed;
use App\Domain\NoteValidator;
use App\Domain\Priority;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoteValidatorTest extends TestCase
{
    private NoteValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new NoteValidator();
    }

    public function testValidInputBecomesADraftWithTrimmedTitleAndVerbatimContent(): void
    {
        $draft = $this->validator->draft(['title' => '  Buy milk  ', 'content' => "  two litres\n", 'priority' => 'high']);

        self::assertSame('Buy milk', $draft->title);
        self::assertSame("  two litres\n", $draft->content);
        self::assertSame(Priority::High, $draft->priority);
    }

    public function testContentIsOptional(): void
    {
        self::assertSame('', $this->validator->draft(['title' => 'Call mum', 'priority' => 'low'])->content);
    }

    public function testTitleLimitCountsCharactersNotBytes(): void
    {
        $title = str_repeat('ř', NoteValidator::TITLE_MAX_LENGTH); // 400 bytes, 200 characters

        self::assertSame($title, $this->validator->draft(['title' => $title, 'priority' => 'low'])->title);
    }

    /** @return iterable<string, array{mixed, array<string, string>}> */
    public static function invalidDrafts(): iterable
    {
        yield 'missing title' => [['priority' => 'low'], ['title' => 'Title is required.']];
        yield 'whitespace-only title' => [['title' => " \t\n", 'priority' => 'low'], ['title' => 'Title is required.']];
        yield 'title is a number' => [['title' => 42, 'priority' => 'low'], ['title' => 'Title is required.']];
        yield 'title too long' => [
            ['title' => str_repeat('a', 201), 'priority' => 'low'],
            ['title' => 'Title must be at most 200 characters.'],
        ];
        yield 'content not text' => [
            ['title' => 'x', 'content' => ['a'], 'priority' => 'low'],
            ['content' => 'Content must be text.'],
        ];
        yield 'content too long' => [
            ['title' => 'x', 'content' => str_repeat('a', 20_001), 'priority' => 'low'],
            ['content' => 'Content must be at most 20000 characters.'],
        ];
        yield 'unknown priority' => [
            ['title' => 'x', 'priority' => 'urgent'],
            ['priority' => 'Priority must be one of: low, medium, high.'],
        ];
        yield 'priority wrong case' => [
            ['title' => 'x', 'priority' => 'HIGH'],
            ['priority' => 'Priority must be one of: low, medium, high.'],
        ];
        yield 'every field wrong at once' => [
            ['title' => '', 'content' => 1, 'priority' => null],
            [
                'title' => 'Title is required.',
                'content' => 'Content must be text.',
                'priority' => 'Priority must be one of: low, medium, high.',
            ],
        ];
        yield 'body is a JSON array' => [['a', 'b'], ['body' => 'Request body must be a JSON object.']];
        yield 'body is a string' => ['hello', ['body' => 'Request body must be a JSON object.']];
    }

    /** @param array<string, string> $expected */
    #[DataProvider('invalidDrafts')]
    public function testInvalidDraftReportsEveryBrokenRule(mixed $input, array $expected): void
    {
        try {
            $this->validator->draft($input);
            self::fail('Expected ValidationFailed.');
        } catch (ValidationFailed $e) {
            self::assertSame($expected, $e->errors);
        }
    }

    public function testExpectedVersionMustBeAPositiveInteger(): void
    {
        self::assertSame(3, $this->validator->expectedVersion(['version' => 3]));

        foreach ([[], ['version' => '3'], ['version' => 0], ['version' => 1.5]] as $input) {
            try {
                $this->validator->expectedVersion($input);
                self::fail('Expected ValidationFailed for ' . json_encode($input));
            } catch (ValidationFailed $e) {
                self::assertArrayHasKey('version', $e->errors);
            }
        }
    }

    public function testPriorityFilterParsesADeduplicatedList(): void
    {
        self::assertSame([], $this->validator->priorityFilter(null));
        self::assertSame([], $this->validator->priorityFilter(''));
        self::assertSame([Priority::High], $this->validator->priorityFilter('high'));
        self::assertSame([Priority::High, Priority::Low], $this->validator->priorityFilter('high, low,high'));
    }

    public function testPriorityFilterRejectsUnknownValuesAndArrays(): void
    {
        foreach (['high,urgent', ['high']] as $raw) {
            try {
                $this->validator->priorityFilter($raw);
                self::fail('Expected ValidationFailed.');
            } catch (ValidationFailed $e) {
                self::assertArrayHasKey('priority', $e->errors);
            }
        }
    }

    public function testSearchTermsSplitOnWhitespaceAndDeduplicate(): void
    {
        self::assertSame([], $this->validator->searchTerms(null));
        self::assertSame([], $this->validator->searchTerms(" \t\n"));
        self::assertSame(['budget', 'Q3'], $this->validator->searchTerms("  budget\u{00A0}Q3 budget "));
        self::assertSame(['50%', 'a_b'], $this->validator->searchTerms('50% a_b'), 'wildcards are kept as text');
        self::assertSame([str_repeat('ž', 100)], $this->validator->searchTerms(str_repeat('ž', 100)), 'the limit counts characters');
    }

    public function testSearchRejectsArraysAndOverlongText(): void
    {
        foreach ([['x'], str_repeat('a', 101)] as $raw) {
            try {
                $this->validator->searchTerms($raw);
                self::fail('Expected ValidationFailed.');
            } catch (ValidationFailed $e) {
                self::assertArrayHasKey('q', $e->errors);
            }
        }
    }

    public function testArchivedFilterAcceptsOnlyTrueOrFalse(): void
    {
        self::assertFalse($this->validator->archivedFilter(null));
        self::assertFalse($this->validator->archivedFilter(''));
        self::assertFalse($this->validator->archivedFilter('false'));
        self::assertTrue($this->validator->archivedFilter('true'));

        foreach (['1', 'TRUE', 'yes', ['true']] as $raw) {
            try {
                $this->validator->archivedFilter($raw);
                self::fail('Expected ValidationFailed.');
            } catch (ValidationFailed $e) {
                self::assertArrayHasKey('archived', $e->errors);
            }
        }
    }
}
