<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\CredentialsValidator;
use App\Domain\Exception\ValidationFailed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CredentialsValidatorTest extends TestCase
{
    private CredentialsValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new CredentialsValidator();
    }

    public function testValidRegistrationKeepsBothFieldsExactly(): void
    {
        $credentials = $this->validator->forRegistration(['username' => 'Jan.Novak-2', 'password' => ' spaces count ']);

        self::assertSame('Jan.Novak-2', $credentials->username);
        self::assertSame(' spaces count ', $credentials->password);
    }

    /** @return iterable<string, array{mixed, list<string>}> */
    public static function invalidRegistrations(): iterable
    {
        yield 'empty object' => [[], ['username', 'password']];
        yield 'not an object' => [['alice', 'secret12'], ['body']];
        yield 'too short username' => [['username' => 'al', 'password' => 'long enough'], ['username']];
        yield 'too long username' => [['username' => str_repeat('a', 33), 'password' => 'long enough'], ['username']];
        yield 'space in username' => [['username' => 'al ice', 'password' => 'long enough'], ['username']];
        yield 'non-ASCII username' => [['username' => 'žofie', 'password' => 'long enough'], ['username']];
        yield 'trailing newline' => [['username' => "alice\n", 'password' => 'long enough'], ['username']];
        yield 'short password' => [['username' => 'alice', 'password' => '1234567'], ['password']];
        yield 'long password' => [['username' => 'alice', 'password' => str_repeat('p', 129)], ['password']];
        yield 'number password' => [['username' => 'alice', 'password' => 12345678], ['password']];
    }

    /** @param list<string> $fields */
    #[DataProvider('invalidRegistrations')]
    public function testInvalidRegistrationNamesEveryBadField(mixed $input, array $fields): void
    {
        try {
            $this->validator->forRegistration($input);
            self::fail('Expected ValidationFailed.');
        } catch (ValidationFailed $e) {
            self::assertSame($fields, array_keys($e->errors));
        }
    }

    public function testLimitsAreInclusiveAndCountCharacters(): void
    {
        $credentials = $this->validator->forRegistration(['username' => str_repeat('a', 32), 'password' => str_repeat('ř', 128)]);
        self::assertSame(32, strlen($credentials->username));

        $this->validator->forRegistration(['username' => 'abc', 'password' => 'čččččččč']);
        $this->addToAssertionCount(1);
    }

    public function testLoginOnlyRequiresBothFields(): void
    {
        $credentials = $this->validator->forLogin(['username' => 'x', 'password' => 'y']);
        self::assertSame(['x', 'y'], [$credentials->username, $credentials->password]);

        $this->expectException(ValidationFailed::class);
        $this->validator->forLogin(['username' => '', 'password' => 'y']);
    }
}
