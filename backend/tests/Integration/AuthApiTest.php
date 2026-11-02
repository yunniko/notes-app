<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\AuthService;

/** Accounts, sessions, throttling and the cross-site guard, through HTTP against MariaDB. */
final class AuthApiTest extends ApiTestCase
{
    public function testRegisterReturnsTheUserAndAHardenedSessionCookie(): void
    {
        $response = $this->register('Alice');

        $user = $this->data($response);
        self::assertSame('Alice', $user['username']);
        self::assertArrayNotHasKey('passwordHash', $user);
        $cookie = $response->getHeaderLine('Set-Cookie');
        self::assertMatchesRegularExpression('/^notes_session=[0-9a-f]{64}; Path=\/api; Max-Age=2592000; HttpOnly; SameSite=Lax$/', $cookie);

        $me = $this->request('GET', '/api/auth/me');
        self::assertSame(200, $me->getStatusCode());
        self::assertSame($user, $this->data($me));
    }

    public function testTheCookieIsSecureBehindAnHttpsProxy(): void
    {
        $response = $this->request(
            'POST',
            '/api/auth/register',
            ['username' => 'alice', 'password' => self::PASSWORD],
            headers: ['X-Forwarded-Proto' => 'https'],
        );

        self::assertStringEndsWith('; Secure', $response->getHeaderLine('Set-Cookie'));
    }

    public function testTakenUsernameAndInvalidFieldsAreFieldErrors(): void
    {
        $this->register('alice');
        $this->session = null;

        $taken = $this->request('POST', '/api/auth/register', ['username' => 'ALICE', 'password' => 'other password']);
        self::assertSame(422, $taken->getStatusCode());
        self::assertSame(['username' => 'This username is taken.'], $this->json($taken)['errors']);
        self::assertNull($this->session);

        $invalid = $this->request('POST', '/api/auth/register', ['username' => 'a b', 'password' => 'short']);
        self::assertSame(422, $invalid->getStatusCode());
        $errors = $this->json($invalid)['errors'];
        self::assertIsArray($errors);
        self::assertSame(['username', 'password'], array_keys($errors));
    }

    public function testLoginAndLogout(): void
    {
        $this->register('alice');
        $this->request('POST', '/api/auth/logout');
        self::assertNull($this->session);
        self::assertSame(401, $this->request('GET', '/api/auth/me')->getStatusCode());

        $wrong = $this->request('POST', '/api/auth/login', ['username' => 'alice', 'password' => 'not it at all']);
        self::assertSame(401, $wrong->getStatusCode());
        self::assertSame('Wrong username or password.', $this->json($wrong)['detail']);
        self::assertNull($this->session);

        $ok = $this->request('POST', '/api/auth/login', ['username' => 'Alice', 'password' => self::PASSWORD]);
        self::assertSame(200, $ok->getStatusCode());
        self::assertSame('alice', $this->data($ok)['username']);
        self::assertSame(200, $this->request('GET', '/api/auth/me')->getStatusCode());
    }

    public function testLogoutEndsTheSessionOnTheServer(): void
    {
        $this->register('alice');
        $token = $this->session;

        $logout = $this->request('POST', '/api/auth/logout');
        self::assertSame(204, $logout->getStatusCode());
        self::assertStringStartsWith('notes_session=; Path=/api; Max-Age=0', $logout->getHeaderLine('Set-Cookie'));

        // A copy of the old cookie no longer works either.
        $this->session = $token;
        self::assertSame(401, $this->request('GET', '/api/auth/me')->getStatusCode());
        self::assertSame(204, $this->request('POST', '/api/auth/logout')->getStatusCode());
    }

    public function testAnExpiredSessionIsRejected(): void
    {
        $this->register('alice');
        self::$pdo->exec('UPDATE sessions SET expires_at = CURRENT_TIMESTAMP(3) - INTERVAL 1 SECOND');

        $response = $this->request('GET', '/api/notes');
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Authentication required', $this->json($response)['title']);
    }

    public function testMalformedOrForgedCookiesAreRejected(): void
    {
        $this->register('alice');

        foreach (['', 'abc', str_repeat('0', 64), strtoupper((string) $this->session)] as $forged) {
            $this->session = $forged;
            self::assertSame(401, $this->request('GET', '/api/auth/me')->getStatusCode(), "cookie '{$forged}'");
        }
    }

    public function testRepeatedFailuresAreThrottledPerUsername(): void
    {
        $this->register('alice');
        $this->session = null;
        for ($i = 0; $i < AuthService::MAX_FAILED_LOGINS; ++$i) {
            $this->request('POST', '/api/auth/login', ['username' => 'alice', 'password' => "guess {$i}"]);
        }

        $blocked = $this->request('POST', '/api/auth/login', ['username' => 'ALICE', 'password' => self::PASSWORD]);
        self::assertSame(429, $blocked->getStatusCode());
        self::assertSame('900', $blocked->getHeaderLine('Retry-After'));
        self::assertNull($this->session);

        // Another account is unaffected.
        $this->register('bob');
    }

    public function testCrossSiteWritesAreRefused(): void
    {
        $body = ['username' => 'alice', 'password' => self::PASSWORD];

        foreach (['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://evil.example'] as $header => $value) {
            $response = $this->request('POST', '/api/auth/register', $body, headers: [$header => $value, 'Host' => 'notes.example']);
            self::assertSame(403, $response->getStatusCode(), $header);
        }
        self::assertSame(403, $this->request('POST', '/api/auth/register', $body, headers: ['Sec-Fetch-Site' => 'same-site'])->getStatusCode());

        $sameOrigin = ['Sec-Fetch-Site' => 'same-origin', 'Origin' => 'https://notes.example', 'Host' => 'notes.example'];
        self::assertSame(201, $this->request('POST', '/api/auth/register', $body, headers: $sameOrigin)->getStatusCode());
        // Reads are not guarded.
        self::assertSame(200, $this->request('GET', '/api/auth/me', headers: ['Sec-Fetch-Site' => 'cross-site'])->getStatusCode());
    }

    public function testOriginWithoutFetchMetadataMustMatchTheHost(): void
    {
        $body = ['username' => 'alice', 'password' => self::PASSWORD];
        $response = $this->request('POST', '/api/auth/register', $body, headers: ['Origin' => 'http://localhost:8088', 'Host' => 'localhost:8088']);

        self::assertSame(201, $response->getStatusCode());
    }
}
