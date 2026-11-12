<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Identity\Domain\PasswordHasher;
use Tests\TestCase;

final class PasswordHasherTest extends TestCase
{
    public function testHashesWithArgon2idWhenAvailable(): void
    {
        $hash = (new PasswordHasher())->hash('secret123');

        $this->assertTrue(str_starts_with($hash, defined('PASSWORD_ARGON2ID') ? '$argon2id$' : '$2y$'));
    }

    public function testVerifiesOnlyTheRightPassword(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('secret123');

        $this->assertTrue($hasher->verify('secret123', $hash));
        $this->assertFalse($hasher->verify('secret124', $hash));
        $this->assertFalse($hasher->verify('secret123', null));
    }

    public function testOldBcryptHashesStillWorkAndAskToBeUpgraded(): void
    {
        $hasher = new PasswordHasher();
        $old = password_hash('secret123', PASSWORD_BCRYPT);

        $this->assertTrue($hasher->verify('secret123', $old));
        $this->assertSame(defined('PASSWORD_ARGON2ID'), $hasher->needsRehash($old));
    }
}
