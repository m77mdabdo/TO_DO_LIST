<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\User;
use Tests\TestCase;

final class UserTest extends TestCase
{
    public function testFirstNameAndInitial(): void
    {
        $user = User::fromRow([
            'id' => '3', 'name' => ' محمد عبده ', 'email' => 'm@example.com',
            'password' => 'hash', 'created_at' => '2026-01-01 10:00:00',
        ]);

        $this->assertSame(3, $user->id);
        $this->assertSame('محمد', $user->firstName());
        $this->assertSame('م', $user->initial());
    }
}
