<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Bootstrap;
use App\Http\SessionCookie;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Requests through the whole Slim app against MariaDB. Acts like one browser: the session
 * cookie from the last register/login is sent with every request until it is replaced.
 */
abstract class ApiTestCase extends DatabaseTestCase
{
    protected const string PASSWORD = 'correct horse';

    /** @var App<ContainerInterface> */
    protected App $app;
    protected ?string $session = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = Bootstrap::app(Bootstrap::container([PDO::class => self::$pdo]));
        $this->session = null;
    }

    /**
     * @param array<string, mixed>|string|null $body    array = JSON-encoded, string = sent raw
     * @param array<string, string>            $headers
     */
    protected function request(
        string $method,
        string $uri,
        array|string|null $body = null,
        string $contentType = 'application/json',
        array $headers = [],
    ): ResponseInterface {
        $request = new ServerRequestFactory()->createServerRequest($method, $uri);
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        $request = $request->withQueryParams($query);
        if ($body !== null) {
            $request->getBody()->write(is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR));
            $request = $request->withHeader('Content-Type', $contentType);
        }
        if ($this->session !== null) {
            $request = $request->withHeader('Cookie', SessionCookie::NAME . '=' . $this->session);
        }
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $response = $this->app->handle($request);
        $set = self::sessionFrom($response);
        if ($set !== null) {
            $this->session = $set === '' ? null : $set;
        }

        return $response;
    }

    /** Registers an account and keeps its session for the following requests. */
    protected function register(string $username = 'alice'): ResponseInterface
    {
        $response = $this->request('POST', '/api/auth/register', ['username' => $username, 'password' => self::PASSWORD]);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getBody());

        return $response;
    }

    /** The session token a response sets, or null when it sets none. A cleared cookie gives ''. */
    protected static function sessionFrom(ResponseInterface $response): ?string
    {
        foreach ($response->getHeader('Set-Cookie') as $cookie) {
            if (preg_match('/^' . SessionCookie::NAME . '=([^;]*)/', $cookie, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    /** @return array<mixed> */
    protected function json(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /** @return array<mixed> */
    protected function data(ResponseInterface $response): array
    {
        $data = $this->json($response)['data'] ?? null;
        self::assertIsArray($data);

        return $data;
    }
}
