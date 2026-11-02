<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Bootstrap;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

/** The whole stack: Slim routing, actions, error handler, service, PDO repository, MariaDB. */
final class NotesApiTest extends DatabaseTestCase
{
    /** @var App<\Psr\Container\ContainerInterface> */
    private App $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = Bootstrap::app(Bootstrap::container([PDO::class => self::$pdo]));
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

    public function testUnicodeRoundTripsUnchanged(): void
    {
        $text = "Příliš žluťoučký kůň 🐎\n<script>alert(1)</script>";
        $note = $this->data($this->request('POST', '/api/notes', ['title' => 'Čeština', 'content' => $text, 'priority' => 'low']));

        self::assertSame($text, $this->data($this->request('GET', $this->url($note)))['content']);
    }

    /** @param array<string, mixed>|string|null $body array = JSON-encoded, string = sent raw */
    private function request(string $method, string $uri, array|string|null $body = null, string $contentType = 'application/json'): ResponseInterface
    {
        $request = new ServerRequestFactory()->createServerRequest($method, $uri);
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        $request = $request->withQueryParams($query);
        if ($body !== null) {
            $request->getBody()->write(is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR));
            $request = $request->withHeader('Content-Type', $contentType);
        }

        return $this->app->handle($request);
    }

    /** @return array<mixed> */
    private function json(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /** @return array<mixed> */
    private function data(ResponseInterface $response): array
    {
        $data = $this->json($response)['data'] ?? null;
        self::assertIsArray($data);

        return $data;
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
