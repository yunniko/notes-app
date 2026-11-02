<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Application\AuthService;
use App\Domain\CredentialsValidator;
use App\Domain\Exception\InvalidCredentials;
use App\Domain\Exception\TooManyLoginAttempts;
use App\Domain\Exception\ValidationFailed;
use App\Domain\PasswordHasher;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private const array ALICE = ['username' => 'alice', 'password' => 'correct horse'];

    private ManualClock $clock;
    private InMemoryUserRepository $users;
    private InMemorySessionRepository $sessions;
    private AuthService $auth;

    protected function setUp(): void
    {
        $this->clock = new ManualClock();
        $this->users = new InMemoryUserRepository();
        $this->sessions = new InMemorySessionRepository($this->users, $this->clock);
        $this->auth = new AuthService(
            $this->users,
            $this->sessions,
            new InMemoryLoginThrottle($this->clock),
            new CredentialsValidator(),
            new PasswordHasher(),
        );
    }

    public function testRegisterCreatesTheAccountAndLogsItIn(): void
    {
        $session = $this->auth->register(self::ALICE);

        self::assertSame('alice', $session->user->username);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $session->token);
        self::assertEquals($session->user, $this->auth->userForToken($session->token));
    }

    public function testTheTokenItselfIsNeverStored(): void
    {
        $session = $this->auth->register(self::ALICE);

        self::assertArrayNotHasKey($session->token, $this->sessions->sessions);
        self::assertArrayHasKey(hash('sha256', $session->token, true), $this->sessions->sessions);
    }

    public function testUsernamesAreUniqueIgnoringCase(): void
    {
        $this->auth->register(self::ALICE);

        try {
            $this->auth->register(['username' => 'ALICE', 'password' => 'another password']);
            self::fail('Expected ValidationFailed.');
        } catch (ValidationFailed $e) {
            self::assertSame(['username' => 'This username is taken.'], $e->errors);
        }
    }

    public function testLoginWithTheRightPasswordOpensANewSession(): void
    {
        $first = $this->auth->register(self::ALICE);

        $second = $this->auth->login(['username' => 'Alice', 'password' => 'correct horse']);

        self::assertNotSame($first->token, $second->token);
        self::assertSame($first->user->id, $second->user->id);
    }

    public function testWrongPasswordAndUnknownUserFailTheSameWay(): void
    {
        $this->auth->register(self::ALICE);

        foreach ([['alice', 'wrong password'], ['bob', 'correct horse'], ['no spaces allowed', 'x']] as [$username, $password]) {
            try {
                $this->auth->login(['username' => $username, 'password' => $password]);
                self::fail("Expected InvalidCredentials for {$username}.");
            } catch (InvalidCredentials $e) {
                self::assertSame('Wrong username or password.', $e->getMessage());
            }
        }
    }

    public function testTenFailuresLockTheUsernameForTheWindowEvenWithTheRightPassword(): void
    {
        $this->auth->register(self::ALICE);
        for ($i = 0; $i < AuthService::MAX_FAILED_LOGINS; ++$i) {
            $this->expectLoginFailure(InvalidCredentials::class, 'alice', 'wrong password');
        }

        $this->expectLoginFailure(TooManyLoginAttempts::class, 'ALICE', 'correct horse');

        $this->clock->now += AuthService::FAILED_LOGIN_WINDOW_SECONDS;
        self::assertSame('alice', $this->auth->login(self::ALICE)->user->username);
    }

    public function testASuccessfulLoginResetsTheFailureCount(): void
    {
        $this->auth->register(self::ALICE);
        for ($i = 1; $i < AuthService::MAX_FAILED_LOGINS; ++$i) {
            $this->expectLoginFailure(InvalidCredentials::class, 'alice', 'wrong password');
        }
        $this->auth->login(self::ALICE);

        $this->expectLoginFailure(InvalidCredentials::class, 'alice', 'wrong password');
        self::assertSame('alice', $this->auth->login(self::ALICE)->user->username);
    }

    public function testSessionsExpire(): void
    {
        $token = $this->auth->register(self::ALICE)->token;

        $this->clock->now += AuthService::SESSION_LIFETIME_SECONDS - 1;
        self::assertNotNull($this->auth->userForToken($token));
        $this->clock->now += 1;
        self::assertNull($this->auth->userForToken($token));
    }

    public function testLogoutEndsOnlyThatSession(): void
    {
        $phone = $this->auth->register(self::ALICE)->token;
        $laptop = $this->auth->login(self::ALICE)->token;

        $this->auth->logout($phone);

        self::assertNull($this->auth->userForToken($phone));
        self::assertNotNull($this->auth->userForToken($laptop));
        $this->auth->logout(null);
    }

    public function testUnknownTokensFindNobody(): void
    {
        self::assertNull($this->auth->userForToken(null));
        self::assertNull($this->auth->userForToken(str_repeat('0', 64)));
    }

    public function testAnOutdatedHashIsUpgradedOnLogin(): void
    {
        $this->users->create('legacy', password_hash('old password', PASSWORD_BCRYPT));

        $this->auth->login(['username' => 'legacy', 'password' => 'old password']);

        $stored = $this->users->findByUsername('legacy');
        self::assertNotNull($stored);
        self::assertStringStartsWith('$argon2id$', $stored->passwordHash);
        self::assertTrue(password_verify('old password', $stored->passwordHash));
    }

    /** @param class-string<\Throwable> $expected */
    private function expectLoginFailure(string $expected, string $username, string $password): void
    {
        try {
            $this->auth->login(['username' => $username, 'password' => $password]);
            self::fail("Expected {$expected}.");
        } catch (InvalidCredentials|TooManyLoginAttempts $e) {
            self::assertInstanceOf($expected, $e);
        }
    }
}
