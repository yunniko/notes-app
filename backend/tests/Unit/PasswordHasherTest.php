<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\PasswordHasher;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    public function testHashesWithArgon2idAndVerifies(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('correct horse');

        self::assertStringStartsWith('$argon2id$v=19$m=19456,t=2,p=1$', $hash);
        self::assertTrue($hasher->verify('correct horse', $hash));
        self::assertFalse($hasher->verify('Correct horse', $hash));
        self::assertFalse($hasher->needsRehash($hash));
    }

    public function testAnUnknownUserNeverVerifies(): void
    {
        $hasher = new PasswordHasher();

        self::assertFalse($hasher->verify('', null));
        self::assertFalse($hasher->verify('anything', null));
    }

    public function testOtherAlgorithmsAndWeakerParametersNeedARehash(): void
    {
        $hasher = new PasswordHasher();

        self::assertTrue($hasher->needsRehash(password_hash('x', PASSWORD_BCRYPT)));
        self::assertTrue($hasher->needsRehash(password_hash('x', PASSWORD_ARGON2ID, ['memory_cost' => 8192, 'time_cost' => 1])));
    }
}
