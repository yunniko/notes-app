<?php

declare(strict_types=1);

namespace App\Tests\Integration;

/** The whole stack: Slim routing, actions, error handler, service, PDO repository, MariaDB. */
final class NotesApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->register();
    }
    public function testHealth(): void
    {
        $response = $this->request('GET', '/api/health');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['status' => 'ok'], $this->json($response));
    }

    public function testCreateThenFetch(): void
    {
        $created = $this->request('POST', '/api/notes', ['title' => 'Dentist', 'content' => 'Tue 9:00 — bring card', 'priority' => 'high']);

        self::assertSame(201, $created->getStatusCode());
        $note = $this->data($created);
        self::assertSame($this->url($note), $created->getHeaderLine('Location'));
        self::assertSame(['Dentist', 'Tue 9:00 — bring card', 'high', 1], [$note['title'], $note['content'], $note['priority'], $note['version']]);
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+00:00$/', $this->string($note['createdAt']));

        $fetched = $this->request('GET', $this->url($note));
        self::assertSame(200, $fetched->getStatusCode());
        self::assertSame($note, $this->data($fetched));
        self::assertNull($note['archivedAt']);
    }

    public function testArchiveAndRestore(): void
    {
        $note = $this->data($this->request('POST', '/api/notes', ['title' => 'Old idea', 'priority' => 'low']));
        $this->request('POST', '/api/notes', ['title' => 'Current', 'priority' => 'high']);
        $url = $this->url($note);

        $archived = $this->request('POST', "{$url}/archive");
        self::assertSame(200, $archived->getStatusCode());
        self::assertIsString($this->data($archived)['archivedAt']);
        self::assertSame(2, $this->data($archived)['version']);
        self::assertSame($note['updatedAt'], $this->data($archived)['updatedAt']);
        self::assertSame(['Current'], array_column($this->list(''), 'title'));
        self::assertSame(['Old idea'], array_column($this->list('?archived=true'), 'title'));
        self::assertSame(['Old idea'], array_column($this->list('?archived=true&priority=low'), 'title'));
        self::assertSame([], $this->list('?archived=true&priority=high'));
        self::assertSame(2, $this->data($this->request('POST', "{$url}/archive"))['version'], 'idempotent');

        // An archived note can still be opened and edited.
        self::assertSame(200, $this->request('PUT', $url, ['title' => 'Old idea, revised', 'priority' => 'low', 'version' => 2])->getStatusCode());

        $restored = $this->request('POST', "{$url}/restore");
        self::assertSame(200, $restored->getStatusCode());
        self::assertNull($this->data($restored)['archivedAt']);
        self::assertSame(4, $this->data($restored)['version']);
        self::assertSame([], $this->list('?archived=true'));
        self::assertSame(422, $this->request('GET', '/api/notes?archived=yes')->getStatusCode());
    }

    public function testDeleteIsPermanent(): void
    {
        $note = $this->data($this->request('POST', '/api/notes', ['title' => 'Delete me', 'priority' => 'medium']));
        $url = $this->url($note);

        $deleted = $this->request('DELETE', $url);
        self::assertSame(204, $deleted->getStatusCode());
        self::assertSame('', (string) $deleted->getBody());
        self::assertSame(404, $this->request('GET', $url)->getStatusCode());
        self::assertSame(404, $this->request('DELETE', $url)->getStatusCode());
        self::assertSame([], $this->list(''));
    }

    public function testAnotherUserCannotArchiveRestoreOrDelete(): void
    {
        $url = $this->url($this->data($this->request('POST', '/api/notes', ['title' => 'Mine', 'priority' => 'low'])));

        $this->register('mallory');
        foreach ([['POST', "{$url}/archive"], ['POST', "{$url}/restore"], ['DELETE', $url]] as [$method, $uri]) {
            self::assertSame(404, $this->request($method, $uri)->getStatusCode(), "{$method} {$uri}");
        }

        $this->request('POST', '/api/auth/login', ['username' => 'alice', 'password' => self::PASSWORD]);
        self::assertSame([null, 1], [$this->data($this->request('GET', $url))['archivedAt'], $this->data($this->request('GET', $url))['version']]);
    }

    public function testListFiltersByOneOrSeveralPriorities(): void
    {
        foreach (['low', 'medium', 'high', 'high'] as $i => $priority) {
            $this->request('POST', '/api/notes', ['title' => "Note $i", 'priority' => $priority]);
        }

        self::assertCount(4, $this->list(''));
        self::assertSame(['high', 'high'], array_column($this->list('?priority=high'), 'priority'));
        self::assertEqualsCanonicalizing(['low', 'high', 'high'], array_column($this->list('?priority=high,low'), 'priority'));
        self::assertSame(['medium'], array_column($this->list('?priority=medium,medium'), 'priority'));
    }

    public function testSearchIgnoresCaseAndAccentsAndTreatsWildcardsAsText(): void
    {
        $this->request('POST', '/api/notes', ['title' => 'Café list', 'content' => 'Oat milk, ŽLUTÝ cukr', 'priority' => 'low']);
        $this->request('POST', '/api/notes', ['title' => 'Discount', 'content' => 'Save 50% on file_names', 'priority' => 'high']);
        $this->request('POST', '/api/notes', ['title' => 'Plain', 'content' => 'Save 500 on filenames!', 'priority' => 'high']);

        $search = fn(string $q, string $extra = ''): array => array_column($this->list('?q=' . rawurlencode($q) . $extra), 'title');

        self::assertSame(['Café list'], $search('cafe'));
        self::assertSame(['Café list'], $search('zluty CUKR'));
        self::assertSame(['Café list'], $search('oat café'));
        self::assertSame(['Discount'], $search('50%'), '% is not a wildcard');
        self::assertSame(['Discount'], $search('file_n'), '_ is not a wildcard');
        self::assertSame(['Plain'], $search('names!'), 'the escape character is matched as text');
        self::assertSame([], $search('cafe save'), 'every term must match');
        self::assertSame(['Plain', 'Discount'], $search('save', '&priority=high'));
        self::assertSame([], $search('save', '&archived=true'));
        self::assertSame(['Plain', 'Discount', 'Café list'], $search('   '));

        $tooLong = $this->request('GET', '/api/notes?q=' . str_repeat('a', 101));
        self::assertSame(422, $tooLong->getStatusCode());
        self::assertArrayHasKey('q', (array) $this->json($tooLong)['errors']);
    }

    public function testSearchNeverReachesAnotherUsersNotes(): void
    {
        $this->request('POST', '/api/notes', ['title' => 'Alice private budget', 'priority' => 'low']);
        $this->register('bob');

        self::assertSame([], $this->list('?q=budget'));
    }

    public function testListShowsMostRecentlyUpdatedFirst(): void
    {
        $first = $this->data($this->request('POST', '/api/notes', ['title' => 'First', 'priority' => 'low']));
        $this->request('POST', '/api/notes', ['title' => 'Second', 'priority' => 'low']);
        usleep(5_000); // timestamps have millisecond precision
        $this->request('PUT', $this->url($first), ['title' => 'First, edited', 'priority' => 'low', 'version' => 1]);

        self::assertSame(['First, edited', 'Second'], array_column($this->list(''), 'title'));
    }

    public function testUpdateBumpsVersionAndStaleUpdateConflicts(): void
    {
        $note = $this->data($this->request('POST', '/api/notes', ['title' => 'Groceries', 'priority' => 'low']));
        $url = $this->url($note);

        $ok = $this->request('PUT', $url, ['title' => 'Groceries + bread', 'content' => '', 'priority' => 'medium', 'version' => 1]);
        self::assertSame(200, $ok->getStatusCode());
        self::assertSame([2, 'medium'], [$this->data($ok)['version'], $this->data($ok)['priority']]);

        $stale = $this->request('PUT', $url, ['title' => 'Overwrite?', 'priority' => 'low', 'version' => 1]);
        self::assertSame(409, $stale->getStatusCode());
        $problem = $this->json($stale);
        self::assertSame('Edit conflict', $problem['title']);
        self::assertIsArray($problem['current']);
        self::assertSame('Groceries + bread', $problem['current']['title']);
        self::assertSame('Groceries + bread', $this->data($this->request('GET', $url))['title']);
    }

    public function testValidationErrorsAreProblemDetailsWithFieldMessages(): void
    {
        $response = $this->request('POST', '/api/notes', ['title' => '   ', 'priority' => 'urgent']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        $problem = $this->json($response);
        self::assertSame(422, $problem['status']);
        self::assertIsArray($problem['errors']);
        self::assertSame(['title', 'priority'], array_keys($problem['errors']));
        self::assertSame([], $this->list(''));
    }

    public function testUnknownPriorityFilterIsRejected(): void
    {
        self::assertSame(422, $this->request('GET', '/api/notes?priority=urgent')->getStatusCode());
    }

    public function testMissingNoteIs404ForReadAndUpdate(): void
    {
        self::assertSame(404, $this->request('GET', '/api/notes/12345')->getStatusCode());
        self::assertSame(404, $this->request('PUT', '/api/notes/12345', ['title' => 'x', 'priority' => 'low', 'version' => 1])->getStatusCode());
        self::assertSame(404, $this->request('GET', '/api/notes/abc')->getStatusCode());
        self::assertSame(404, $this->request('GET', '/api/nothing-here')->getStatusCode());
        // Ids that cannot exist: zero, a leading zero, beyond INT UNSIGNED, beyond the route's 10 digits.
        foreach (['0', '01', '4294967296', '99999999999'] as $id) {
            self::assertSame(404, $this->request('GET', "/api/notes/{$id}")->getStatusCode(), "id {$id}");
        }
    }

    public function testMalformedRequestsGetPreciseStatuses(): void
    {
        self::assertSame(400, $this->request('POST', '/api/notes', '{"title": ')->getStatusCode());
        self::assertSame(415, $this->request('POST', '/api/notes', 'title=x', 'application/x-www-form-urlencoded')->getStatusCode());
        self::assertSame(422, $this->request('POST', '/api/notes', '[1, 2]')->getStatusCode());
        self::assertSame(405, $this->request('DELETE', '/api/notes')->getStatusCode());
    }

    public function testNotesBelongToTheirAuthor(): void
    {
        $note = $this->data($this->request('POST', '/api/notes', ['title' => 'Alice only', 'priority' => 'high']));
        $url = $this->url($note);

        $this->register('bob');
        self::assertSame([], $this->list(''));
        self::assertSame(404, $this->request('GET', $url)->getStatusCode());
        self::assertSame(404, $this->request('PUT', $url, ['title' => 'Bob was here', 'priority' => 'low', 'version' => 1])->getStatusCode());

        $this->session = null;
        self::assertSame(401, $this->request('GET', '/api/notes')->getStatusCode());
        self::assertSame(401, $this->request('GET', $url)->getStatusCode());
        self::assertSame(401, $this->request('POST', '/api/notes', ['title' => 'Anonymous', 'priority' => 'low'])->getStatusCode());

        $this->request('POST', '/api/auth/login', ['username' => 'alice', 'password' => self::PASSWORD]);
        self::assertSame('Alice only', $this->data($this->request('GET', $url))['title']);
    }

    public function testUnicodeRoundTripsUnchanged(): void
    {
        $text = "Příliš žluťoučký kůň 🐎\n<script>alert(1)</script>";
        $note = $this->data($this->request('POST', '/api/notes', ['title' => 'Čeština', 'content' => $text, 'priority' => 'low']));

        self::assertSame($text, $this->data($this->request('GET', $this->url($note)))['content']);
    }

    /** @return list<array<mixed>> */
    private function list(string $query): array
    {
        $response = $this->request('GET', '/api/notes' . $query);
        self::assertSame(200, $response->getStatusCode());
        $data = $this->data($response);
        self::assertTrue(array_is_list($data));

        /** @var list<array<mixed>> $data */
        return $data;
    }

    /** @param array<mixed> $note */
    private function url(array $note): string
    {
        self::assertIsInt($note['id']);

        return '/api/notes/' . $note['id'];
    }

    private function string(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
