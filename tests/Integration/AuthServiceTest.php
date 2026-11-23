<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Identity\Application\AuthService;
use App\Modules\Identity\Domain\PasswordHasher;
use App\Modules\Identity\Infrastructure\PdoLoginThrottle;
use App\Modules\Identity\Infrastructure\PdoUserRepository;
use App\Modules\Identity\Contract\Events\UserLoggedIn;
use App\Modules\Identity\Contract\Events\UserRegistered;
use App\Session\ArraySession;
use App\Shared\Events\RecordingEventBus;
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
    private RecordingEventBus $events;

    public function setUp(): void
    {
        $pdo = Database::fresh();
        $this->clock = new FrozenClock('2026-01-20 10:00:00');
        $this->session = new ArraySession();
        $this->events = new RecordingEventBus();
        $users = new PdoUserRepository($pdo, $this->clock);
        $this->auth = new AuthService(
            $users,
            new PasswordHasher(),
            new PdoLoginThrottle($pdo, $this->clock),
            $this->session,
            new Validator(),
            $this->events,
            $this->clock,
            new \App\Modules\Identity\Application\DeviceSessions($pdo, $this->clock),
            new \App\Modules\Identity\Application\TwoFactor($users, new \App\Security\Secrets(''), $this->session, $this->clock),
            new \App\I18n\Translator(dirname(__DIR__, 2) . '/lang', 'en', $this->session),
        );
    }

    public function testRegisteringLogsTheUserIn(): void
    {
        $user = $this->auth->register($this->signup());

        $this->assertSame('sara@example.com', $user->email);
        $this->assertTrue($this->auth->check());
        $this->assertSame(1, $this->session->regenerated);
    }

    public function testRegisteringAnnouncesTheNewUser(): void
    {
        $user = $this->auth->register($this->signup());

        [$event] = $this->events->published(UserRegistered::name());
        $this->assertSame($user->id, $event->userId);
        $this->assertSame('sara@example.com', $event->email);
    }

    public function testLoggingInIsAnnounced(): void
    {
        $user = $this->auth->register($this->signup());
        $this->auth->logout();

        $this->auth->login(['email' => 'sara@example.com', 'password' => 'secret123'], '10.0.0.1');

        [$event] = $this->events->published(UserLoggedIn::name());
        $this->assertSame($user->id, $event->userId);
    }

    public function testAFailedSignupAnnouncesNothing(): void
    {
        $this->errorsFrom(fn () => $this->auth->register($this->signup(['email' => 'nope'])));

        $this->assertCount(0, $this->events->published());
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

    public function testLocksAnAccountOnAnIpAfterFiveFailures(): void
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

    /**
     * Regression: a correct login used to clear every failure from the IP, so an
     * attacker could fail 4 times on someone else's account, sign in to their own,
     * and start over with unlimited guesses.
     */
    public function testSigningInToAnotherAccountDoesNotResetTheCount(): void
    {
        $this->registerUsers('sara@example.com', 'omar@example.com');

        for ($i = 0; $i < 4; $i++) {
            $this->failLogin('sara@example.com', '3.3.3.3');
        }
        $this->auth->login(['email' => 'omar@example.com', 'password' => 'secret123'], '3.3.3.3');
        $this->auth->logout();
        $this->failLogin('sara@example.com', '3.3.3.3');

        $errors = $this->errorsFrom(fn () => $this->auth->login(['email' => 'sara@example.com', 'password' => 'secret123'], '3.3.3.3'));
        $this->assertTrue(str_starts_with($errors['login'] ?? '', 'Too many attempts'));
        $this->assertFalse($this->auth->check());
    }

    public function testPeopleSharingAnIpAreNotLockedOutBySomeoneElsesMistakes(): void
    {
        $this->registerUsers('sara@example.com', 'omar@example.com');

        for ($i = 0; $i < 5; $i++) {
            $this->failLogin('sara@example.com', '10.0.0.1');
        }

        $this->auth->login(['email' => 'omar@example.com', 'password' => 'secret123'], '10.0.0.1');
        $this->assertTrue($this->auth->check());
    }

    public function testAnAccountAttackedFromManyIpsIsLocked(): void
    {
        $this->registerUsers('sara@example.com', 'omar@example.com');

        for ($i = 1; $i <= 20; $i++) {
            $this->failLogin('sara@example.com', "4.4.4.$i");
        }

        $errors = $this->errorsFrom(fn () => $this->auth->login(['email' => 'sara@example.com', 'password' => 'secret123'], '5.5.5.5'));
        $this->assertTrue(str_starts_with($errors['login'] ?? '', 'Too many attempts'));

        $this->auth->login(['email' => 'omar@example.com', 'password' => 'secret123'], '5.5.5.5');
        $this->assertTrue($this->auth->check(), 'other accounts are not affected');
    }

    public function testAnIpTryingManyAccountsIsLocked(): void
    {
        $this->registerUsers('sara@example.com');

        for ($i = 1; $i <= 50; $i++) {
            $this->failLogin("guess$i@example.com", '6.6.6.6');
        }

        $errors = $this->errorsFrom(fn () => $this->auth->login(['email' => 'sara@example.com', 'password' => 'secret123'], '6.6.6.6'));
        $this->assertTrue(str_starts_with($errors['login'] ?? '', 'Too many attempts'));

        $this->auth->login(['email' => 'sara@example.com', 'password' => 'secret123'], '7.7.7.7');
        $this->assertTrue($this->auth->check(), 'the same account works from another IP');
    }

    public function testACorrectLoginClearsOnlyThatAccountsFailures(): void
    {
        $this->registerUsers('sara@example.com');

        for ($i = 0; $i < 4; $i++) {
            $this->failLogin('sara@example.com', '8.8.8.8');
        }
        $this->auth->login(['email' => 'Sara@Example.com', 'password' => 'secret123'], '8.8.8.8');
        $this->auth->logout();

        for ($i = 0; $i < 4; $i++) {
            $this->failLogin('sara@example.com', '8.8.8.8');
        }
        $this->auth->login(['email' => 'sara@example.com', 'password' => 'secret123'], '8.8.8.8');
        $this->assertTrue($this->auth->check(), 'four new failures after a correct login are still under the limit');
    }

    public function testFailuresAreCountedForUnknownEmailsWithTheSameMessage(): void
    {
        $errors = $this->errorsFrom(fn () => $this->auth->login(['email' => 'nobody@example.com', 'password' => 'whatever'], '9.9.9.9'));
        $this->assertSame("That email and password don't match.", $errors['login']);
    }

    private function registerUsers(string ...$emails): void
    {
        foreach ($emails as $email) {
            $this->auth->register($this->signup(['email' => $email]));
            $this->auth->logout();
        }
    }

    private function failLogin(string $email, string $ip): void
    {
        $this->errorsFrom(fn () => $this->auth->login(['email' => $email, 'password' => 'wrong-password'], $ip));
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
