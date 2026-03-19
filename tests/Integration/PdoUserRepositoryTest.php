<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Auth\PdoUserRepository;
use App\Support\FrozenClock;
use Tests\Support\Database;
use Tests\TestCase;

final class PdoUserRepositoryTest extends TestCase
{
    public function testStoresEmailsInLowerCaseAndFindsThemAnyCase(): void
    {
        $users = new PdoUserRepository(Database::fresh(), new FrozenClock('2026-01-20'));

        $user = $users->create('Omar Hassan', 'Omar@Example.COM', 'hash');

        $this->assertSame('omar@example.com', $user->email);
        $this->assertTrue($users->emailExists('OMAR@example.com'));
        $this->assertSame($user->id, $users->credentials(' omar@example.com ')?->userId);
        $this->assertNull($users->credentials('nobody@example.com'));
        $this->assertSame('Omar', $user->firstName());
        $this->assertSame('O', $user->initial());
    }

    public function testUpdatesThePasswordHash(): void
    {
        $users = new PdoUserRepository(Database::fresh(), new FrozenClock());
        $user = $users->create('Mona', 'mona@example.com', 'old');

        $users->updatePassword($user->id, 'new');

        $this->assertSame('new', $users->credentials('mona@example.com')?->passwordHash);
    }
}
