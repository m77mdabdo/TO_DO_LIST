<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Auth\AuthService;
use App\Auth\PasswordHasher;
use App\Auth\PdoLoginThrottle;
use App\Auth\PdoUserRepository;
use App\Session\ArraySession;
use App\Support\FrozenClock;
use App\Validation\ValidationException;
use App\Validation\Validator;
use Tests\Support\Database;
use Tests\TestCase;

final class AuthServiceTest extends TestCase
{
    private AuthService $auth;
    private ArraySession $session;
    private FrozenClock $clock;

    public function setUp(): void
    {
        $pdo = Database::fresh();
        $this->clock = new FrozenClock('2026-01-20 10:00:00');
        $this->session = new ArraySession();
        $this->auth = new AuthService(
            new PdoUserRepository($pdo, $this->clock),
            new PasswordHasher(),
            new PdoLoginThrottle($pdo, $this->clock),
            $this->session,
            new Validator(),
        );
    }

    public function testRegisteringLogsTheUserIn(): void
    {
        $user = $this->auth->register($this->signup());

        $this->assertSame('sara@example.com', $user->email);
        $this->assertTrue($this->auth->check());
        $this->assertSame(1, $this->session->regenerated);
    }

    public function testRegistrationRules(): void
    {
        $this->auth->register($this->signup());
        $this->auth->logout();

        $errors = $this->errorsFrom(fn () => $this->auth->register($this->signup(['password_confirmation' => 'different1'])));

        $this->assertSame("Passwords don't match.", $errors['password_confirmation']);
        $this->assertSame('This email already has an account.', $errors['email']);
    }

    public function testLogsInWithTheRightPasswordOnly(): void
    {
        $this->auth->register($this->signup());
        $this->auth->logout();

        $errors = $this->errorsFrom(fn () => $this->auth->login(['email' => 'sara@example.com', 'password' => 'wrong-pass'], '1.1.1.1'));
        $this->assertSame("That email and password don't match.", $errors['login']);
        $this->assertFalse($this->auth->check());

        $this->auth->login(['email' => 'SARA@example.com', 'password' => 'secret123'], '1.1.1.1');
        $this->assertTrue($this->auth->check());
    }

    public function testLocksAnIpOutAfterFiveFailures(): void
    {
        $this->auth->register($this->signup());
        $this->auth->logout();

        for ($i = 0; $i < 5; $i++) {
            $this->errorsFrom(fn () => $this->auth->login(['email' => 'sara@example.com', 'password' => 'nope'], '2.2.2.2'));
        }

        $errors = $this->errorsFrom(fn () => $this->auth->login(['email' => 'sara@example.com', 'password' => 'secret123'], '2.2.2.2'));
        $this->assertTrue(str_starts_with($errors['login'], 'Too many attempts'));

        $this->clock->travel('+16 minutes');
        $this->auth->login(['email' => 'sara@example.com', 'password' => 'secret123'], '2.2.2.2');
        $this->assertTrue($this->auth->check());
    }

    /** @return array<string, string> */
    private function signup(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Sara Ali',
            'email' => 'Sara@Example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];
    }

    /** @return array<string, string> */
    private function errorsFrom(callable $callback): array
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->assertTrue(false, 'Expected a ValidationException.');

        return [];
    }
}
