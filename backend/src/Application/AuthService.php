<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\CredentialsValidator;
use App\Domain\Exception\InvalidCredentials;
use App\Domain\Exception\TooManyLoginAttempts;
use App\Domain\Exception\UsernameTaken;
use App\Domain\Exception\ValidationFailed;
use App\Domain\LoginThrottle;
use App\Domain\PasswordHasher;
use App\Domain\SessionRepository;
use App\Domain\User;
use App\Domain\UserRepository;

/**
 * Registration, login and sessions (D011, D012). The browser holds a random token; the
 * database holds only its SHA-256, so the token itself is never stored.
 */
final readonly class AuthService
{
    public const int SESSION_LIFETIME_SECONDS = 30 * 24 * 3600;
    public const int MAX_FAILED_LOGINS = 10;
    public const int FAILED_LOGIN_WINDOW_SECONDS = 15 * 60;

    public function __construct(
        private UserRepository $users,
        private SessionRepository $sessions,
        private LoginThrottle $throttle,
        private CredentialsValidator $validator,
        private PasswordHasher $hasher,
    ) {}

    /**
     * Creates the account and logs it in.
     *
     * @throws ValidationFailed also when the username is taken
     */
    public function register(mixed $input): AuthenticatedSession
    {
        $credentials = $this->validator->forRegistration($input);
        try {
            $user = $this->users->create($credentials->username, $this->hasher->hash($credentials->password));
        } catch (UsernameTaken) {
            throw new ValidationFailed(['username' => 'This username is taken.']);
        }

        return $this->openSession($user);
    }

    /** @throws ValidationFailed|InvalidCredentials|TooManyLoginAttempts */
    public function login(mixed $input): AuthenticatedSession
    {
        $credentials = $this->validator->forLogin($input);

        if (!$this->validator->isWellFormedUsername($credentials->username)) {
            // No such account can exist. Still spend the hashing time, so this answer is as slow as any other.
            $this->hasher->verify($credentials->password, null);

            throw new InvalidCredentials();
        }

        $key = strtolower($credentials->username);
        if ($this->throttle->recentFailures($key, self::FAILED_LOGIN_WINDOW_SECONDS) >= self::MAX_FAILED_LOGINS) {
            throw new TooManyLoginAttempts(self::FAILED_LOGIN_WINDOW_SECONDS);
        }

        $stored = $this->users->findByUsername($credentials->username);
        if ($stored === null || !$this->hasher->verify($credentials->password, $stored->passwordHash)) {
            $this->throttle->recordFailure($key, self::FAILED_LOGIN_WINDOW_SECONDS);

            throw new InvalidCredentials();
        }

        $this->throttle->clear($key);
        if ($this->hasher->needsRehash($stored->passwordHash)) {
            $this->users->updatePasswordHash($stored->user->id, $this->hasher->hash($credentials->password));
        }

        return $this->openSession($stored->user);
    }

    public function logout(?string $token): void
    {
        if ($token !== null) {
            $this->sessions->delete(self::hashToken($token));
        }
    }

    public function userForToken(?string $token): ?User
    {
        return $token === null ? null : $this->sessions->findUser(self::hashToken($token));
    }

    private function openSession(User $user): AuthenticatedSession
    {
        $this->sessions->deleteExpired();
        $token = bin2hex(random_bytes(32));
        $this->sessions->create(self::hashToken($token), $user->id, self::SESSION_LIFETIME_SECONDS);

        return new AuthenticatedSession($user, $token);
    }

    private static function hashToken(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token, true);
    }
}
