<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Validation\ValidationException;
use Tests\Support\Auth;
use Tests\TestCase;

final class RegistrationTest extends TestCase
{
    private Auth $app;

    public function setUp(): void
    {
        $this->app = new Auth();
    }

    public function testRegistersAndLogsTheUserIn(): void
    {
        $user = $this->app->service->register($this->input());

        $this->assertSame('Sara Ali', $user->name);
        $this->assertTrue(password_verify('secret123', $user->passwordHash));
        $this->assertSame($user->id, $this->app->auth->user()?->id);
    }

    public function testPasswordsMustMatch(): void
    {
        $errors = $this->errorsFor($this->input(['password_confirmation' => 'different']));

        $this->assertSame(["password_confirmation" => "Passwords don't match."], $errors);
    }

    public function testEmailsCanOnlyBeUsedOnce(): void
    {
        $this->app->service->register($this->input());

        $errors = $this->errorsFor($this->input(['email' => 'SARA@example.com']));

        $this->assertSame(['email' => 'This email already has an account.'], $errors);
    }

    public function testValidatesEveryField(): void
    {
        $errors = $this->errorsFor(['name' => '1', 'email' => 'nope', 'password' => 'short']);

        $this->assertSame(['name', 'email', 'password'], array_keys($errors));
    }

    /** @param array<string, string> $overrides */
    private function input(array $overrides = []): array
    {
        return $overrides + [
            'name'                  => 'Sara Ali',
            'email'                 => 'sara@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ];
    }

    /** @return array<string, string> */
    private function errorsFor(array $input): array
    {
        try {
            $this->app->service->register($input);
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    }
}
