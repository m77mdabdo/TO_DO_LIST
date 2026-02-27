<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Validation\ValidationException;
use Tests\Support\Auth;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    private Auth $app;

    public function setUp(): void
    {
        $this->app = new Auth();
        $this->app->users->create('Sara', 'sara@example.com', password_hash('secret123', PASSWORD_DEFAULT));
    }

    public function testLogsInWithTheRightPassword(): void
    {
        $user = $this->app->service->attempt(['email' => 'Sara@Example.com', 'password' => 'secret123'], '1.1.1.1');

        $this->assertSame('sara@example.com', $user->email);
        $this->assertTrue($this->app->auth->check());
    }

    public function testWrongPasswordAndUnknownEmailGiveTheSameMessage(): void
    {
        $wrongPassword = $this->errorsFor(['email' => 'sara@example.com', 'password' => 'nope']);
        $unknownEmail  = $this->errorsFor(['email' => 'omar@example.com', 'password' => 'secret123']);

        $this->assertSame(['login' => "That email and password don't match."], $wrongPassword);
        $this->assertSame($wrongPassword, $unknownEmail);
        $this->assertFalse($this->app->auth->check());
    }

    public function testLocksOutAfterFiveFailures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->errorsFor(['email' => 'sara@example.com', 'password' => 'nope']);
        }

        $errors = $this->errorsFor(['email' => 'sara@example.com', 'password' => 'secret123']);

        $this->assertSame('Too many attempts. Please wait 15 minutes and try again.', $errors['login'] ?? null);
    }

    public function testRehashesOldPasswordHashes(): void
    {
        $this->app->users->create('Omar', 'omar@example.com', password_hash('secret123', PASSWORD_BCRYPT, ['cost' => 4]));

        $user = $this->app->service->attempt(['email' => 'omar@example.com', 'password' => 'secret123'], '1.1.1.1');

        $this->assertFalse(password_needs_rehash((string) $this->app->users->find($user->id)?->passwordHash, PASSWORD_DEFAULT));
    }

    /** @return array<string, string> */
    private function errorsFor(array $input): array
    {
        try {
            $this->app->service->attempt($input, '1.1.1.1');
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    }
}
