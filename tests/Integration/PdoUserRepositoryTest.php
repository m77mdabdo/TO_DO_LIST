<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Auth\PdoUserRepository;
use App\Support\FrozenClock;
use PDOException;
use Tests\Support\Database;
use Tests\TestCase;

final class PdoUserRepositoryTest extends TestCase
{
    private PdoUserRepository $users;

    public function setUp(): void
    {
        $this->users = new PdoUserRepository(Database::fresh(), new FrozenClock('2026-02-01 08:30:00'));
    }

    public function testCreatesAUserWithALowerCasedEmail(): void
    {
        $user = $this->users->create('Sara Ali', 'Sara@Example.com ', 'hash');

        $this->assertSame('sara@example.com', $user->email);
        $this->assertSame('2026-02-01 08:30:00', $user->createdAt->format('Y-m-d H:i:s'));
        $this->assertSame($user->id, $this->users->findByEmail('SARA@example.com')?->id);
        $this->assertNull($this->users->findByEmail('omar@example.com'));
    }

    public function testEmailsAreUnique(): void
    {
        $this->users->create('Sara', 'sara@example.com', 'hash');

        $this->assertThrows(PDOException::class, fn () => $this->users->create('Other', 'sara@example.com', 'hash'));
    }

    public function testUpdatesThePassword(): void
    {
        $user = $this->users->create('Sara', 'sara@example.com', 'old');
        $this->users->updatePassword($user->id, 'new');

        $this->assertSame('new', $this->users->find($user->id)?->passwordHash);
    }
}
