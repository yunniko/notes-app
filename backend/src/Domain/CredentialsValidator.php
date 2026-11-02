<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exception\ValidationFailed;

/** Rules for usernames and passwords. Pure, like NoteValidator. */
final class CredentialsValidator
{
    public const int USERNAME_MIN_LENGTH = 3;
    public const int USERNAME_MAX_LENGTH = 32;
    public const int PASSWORD_MIN_LENGTH = 8;
    /** Bounds the hashing work one request can cause. */
    public const int PASSWORD_MAX_LENGTH = 128;

    private const string USERNAME_PATTERN = '/^[A-Za-z0-9._-]+$/D';

    /**
     * The full rules, for a new account. Every broken rule is reported at once.
     *
     * @throws ValidationFailed
     */
    public function forRegistration(mixed $input): Credentials
    {
        [$username, $password] = $this->fields($input);
        $errors = [];

        if ($username === null || $username === '') {
            $errors['username'] = 'Username is required.';
        } elseif (!$this->isWellFormedUsername($username)) {
            $errors['username'] = sprintf(
                'Username must be %d to %d characters: letters, digits, dot, dash or underscore.',
                self::USERNAME_MIN_LENGTH,
                self::USERNAME_MAX_LENGTH,
            );
        }

        if ($password === null || $password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (mb_strlen($password) < self::PASSWORD_MIN_LENGTH) {
            $errors['password'] = sprintf('Password must be at least %d characters.', self::PASSWORD_MIN_LENGTH);
        } elseif (mb_strlen($password) > self::PASSWORD_MAX_LENGTH) {
            $errors['password'] = sprintf('Password must be at most %d characters.', self::PASSWORD_MAX_LENGTH);
        }

        if ($errors !== [] || $username === null || $password === null) {
            throw new ValidationFailed($errors ?: ['body' => 'Invalid credentials.']);
        }

        return new Credentials($username, $password);
    }

    /**
     * Login only needs both fields present. Whether they are right is decided by the login
     * itself, without repeating the registration rules.
     *
     * @throws ValidationFailed
     */
    public function forLogin(mixed $input): Credentials
    {
        [$username, $password] = $this->fields($input);
        if ($username !== null && $username !== '' && $password !== null && $password !== '') {
            return new Credentials($username, $password);
        }

        $errors = [];
        if ($username === null || $username === '') {
            $errors['username'] = 'Username is required.';
        }
        if ($password === null || $password === '') {
            $errors['password'] = 'Password is required.';
        }

        throw new ValidationFailed($errors ?: ['body' => 'Invalid credentials.']);
    }

    public function isWellFormedUsername(string $username): bool
    {
        $length = strlen($username);

        return $length >= self::USERNAME_MIN_LENGTH
            && $length <= self::USERNAME_MAX_LENGTH
            && preg_match(self::USERNAME_PATTERN, $username) === 1;
    }

    /**
     * @return array{?string, ?string} username and password; null when missing or not a string
     *
     * @throws ValidationFailed
     */
    private function fields(mixed $input): array
    {
        if (!is_array($input) || array_is_list($input) && $input !== []) {
            throw new ValidationFailed(['body' => 'Request body must be a JSON object.']);
        }
        $username = $input['username'] ?? null;
        $password = $input['password'] ?? null;

        return [is_string($username) ? $username : null, is_string($password) ? $password : null];
    }
}
