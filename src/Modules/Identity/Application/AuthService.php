<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Contract\Authenticator;
use App\Modules\Identity\Contract\Events\UserLoggedIn;
use App\Modules\Identity\Contract\Events\UserRegistered;
use App\Modules\Identity\Contract\User;
use App\Modules\Identity\Domain\LoginThrottle;
use App\Modules\Identity\Domain\PasswordHasher;
use App\Modules\Identity\Domain\UserRepository;
use App\Session\Session;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use App\Validation\Validator;

final class AuthService implements Authenticator
{
    private const SESSION_KEY = 'user_id';
    // Sessions from before auth versions existed hold none: they count as version 1.
    private const VERSION_KEY = 'auth_version';
    /** This browser's secret in user_sessions. */
    private const DEVICE_KEY = 'device';
    /** The password was right; the second step (two-step sign-in) is next. */
    private const PENDING_KEY = 'two_factor_login';
    private const PENDING_SECONDS = 300;
    private const PENDING_TRIES = 5;

    private ?User $user = null;

    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private LoginThrottle $throttle,
        private Session $session,
        private Validator $validator,
        private EventBus $events,
        private Clock $clock,
        private DeviceSessions $devices,
        private TwoFactor $twoFactor,
        private \App\I18n\Translator $translator,
    ) {
    }

    public function user(): ?User
    {
        $id = $this->session->get(self::SESSION_KEY);

        if (!is_int($id)) {
            return null;
        }

        if ($this->user !== null) {
            return $this->user;
        }
        $version = $this->session->get(self::VERSION_KEY);
        $this->user = $this->users->findForSession($id, is_int($version) ? $version : 1);

        $device = $this->session->get(self::DEVICE_KEY);
        if ($this->user !== null && is_string($device) && !$this->devices->check($id, $device)) {
            // Signed out from another device's list.
            $this->user = null;
        } elseif ($this->user !== null && !is_string($device)) {
            // A session from before the device list: it joins the list now.
            $this->session->put(self::DEVICE_KEY, $this->devices->start($id, '', ''));
        }

        // The password changed since this session started (here or on another device).
        if ($this->user === null) {
            $this->session->forget(self::SESSION_KEY);
            $this->session->forget(self::DEVICE_KEY);
        }

        return $this->user;
    }

    /** Forget the cached user, e.g. after the email was confirmed during this request. */
    public function refresh(): void
    {
        $this->user = null;
    }

    /**
     * Signs $userId in on this device (used after a password reset). With
     * two-step sign-in on, the code is still asked for: null means "next step".
     */
    public function signIn(int $userId, string $ip = '', string $agent = ''): ?User
    {
        // Ends sessions that never joined the device list too.
        $this->devices->endAll($userId);
        if ($this->twoFactor->enabled($userId)) {
            $this->session->regenerate();
            $this->session->put(self::PENDING_KEY, ['user' => $userId, 'until' => $this->clock->now()->getTimestamp() + self::PENDING_SECONDS, 'tries' => 0]);

            return null;
        }

        return $this->startSession($userId, $ip, $agent);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Null means the password was right and the second step is next
     * (completeTwoFactor), when the account has two-step sign-in on.
     *
     * @param array<string, mixed> $input email and password
     * @throws ValidationException when the form is invalid or the login fails
     */
    public function login(array $input, string $ip, string $agent = ''): ?User
    {
        if ($this->throttle->tooManyAttempts($ip, (string) ($input['email'] ?? ''))) {
            $this->fail('login', 'Too many attempts. Please wait 15 minutes and try again.');
        }

        $this->assertValid($input, [
            'email'    => ['Email', 'required|email'],
            'password' => ['Password', 'required'],
        ]);

        $email = (string) $input['email'];
        $password = (string) $input['password'];
        $credentials = $this->users->credentials($email);

        if (!$this->hasher->verify($password, $credentials?->passwordHash)) {
            $this->throttle->recordFailure($ip, $email);
            $this->fail('login', "That email and password don't match.");
        }

        $this->throttle->clear($email);

        if ($this->hasher->needsRehash($credentials->passwordHash)) {
            $this->users->updatePassword($credentials->userId, $this->hasher->hash($password));
        }

        if ($this->twoFactor->enabled($credentials->userId)) {
            $this->session->regenerate();
            $this->session->put(self::PENDING_KEY, ['user' => $credentials->userId, 'until' => $this->clock->now()->getTimestamp() + self::PENDING_SECONDS, 'tries' => 0]);

            return null;
        }

        $user = $this->startSession($credentials->userId, $ip, $agent);
        $this->events->publish(new UserLoggedIn($user->id, $user->name, $this->clock->now()));

        return $user;
    }

    /** Is someone between the password and the second step? */
    public function pendingTwoFactor(): bool
    {
        return $this->pending() !== null;
    }

    /** The second step: a code from the app or a recovery code. */
    public function completeTwoFactor(string $code, string $ip, string $agent = ''): User
    {
        $pending = $this->pending() ?? $this->fail('code', 'Please sign in again.');
        if (!$this->twoFactor->verify($pending['user'], $code)) {
            $pending['tries']++;
            if ($pending['tries'] >= self::PENDING_TRIES) {
                $this->session->forget(self::PENDING_KEY);
                $this->fail('code', 'Too many wrong codes. Please sign in again.');
            }
            $this->session->put(self::PENDING_KEY, $pending);
            $this->fail('code', 'That code is not right. Try the newest code from your app, or a recovery code.');
        }
        $this->session->forget(self::PENDING_KEY);
        $user = $this->startSession($pending['user'], $ip, $agent);
        $this->events->publish(new UserLoggedIn($user->id, $user->name, $this->clock->now()));

        return $user;
    }

    /** The user id waiting for the second step, for the security log. */
    public function pendingUserId(): ?int
    {
        return $this->pending()['user'] ?? null;
    }

    public function cancelTwoFactor(): void
    {
        $this->session->forget(self::PENDING_KEY);
    }

    /** This browser's place in the device list (null before sign-in). */
    public function deviceSecret(): ?string
    {
        $device = $this->session->get(self::DEVICE_KEY);

        return is_string($device) ? $device : null;
    }

    /** Signs out every other browser; this one stays signed in. @return int how many were signed out */
    public function signOutOtherDevices(int $userId): int
    {
        // A new auth version also ends sessions that never joined the device list.
        $this->users->bumpAuthVersion($userId);
        $this->session->put(self::VERSION_KEY, $this->users->authVersion($userId));
        $this->user = null;

        return $this->devices->endAll($userId, $this->deviceSecret());
    }

    /** Signs out every browser, this one too. */
    public function signOutEverywhere(int $userId): void
    {
        $this->users->bumpAuthVersion($userId);
        $this->devices->endAll($userId);
        $this->logout();
    }

    /** @return array{user: int, until: int, tries: int}|null */
    private function pending(): ?array
    {
        $pending = $this->session->get(self::PENDING_KEY);
        if (!is_array($pending) || !is_int($pending['user'] ?? null) || (int) ($pending['until'] ?? 0) < $this->clock->now()->getTimestamp()) {
            return null;
        }

        return ['user' => $pending['user'], 'until' => (int) $pending['until'], 'tries' => (int) ($pending['tries'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input name, email, password, password_confirmation
     */
    public function register(array $input): User
    {
        $input['email'] = mb_strtolower(trim((string) ($input['email'] ?? '')));

        $result = $this->validator->validate($input, [
            'name'     => ['Name', 'required|text|min:2|max:60'],
            'email'    => ['Email', 'required|email|max:190'],
            'password' => ['Password', 'required|min:8|max:72'],
        ]);

        $errors = $result->errors();

        if (!isset($errors['password']) && ($input['password'] ?? null) !== ($input['password_confirmation'] ?? null)) {
            $result = $result->with('password_confirmation', "Passwords don't match.");
        }

        if (!isset($errors['email']) && $this->users->emailExists($input['email'])) {
            $result = $result->with('email', 'This email already has an account.');
        }

        if ($result->fails()) {
            throw new ValidationException($result);
        }

        $user = $this->users->create(
            trim((string) $input['name']),
            $input['email'],
            $this->hasher->hash((string) $input['password']),
        );

        $this->events->publish(new UserRegistered($user->id, $user->name, $user->email, $this->clock->now()));

        return $this->startSession($user->id);
    }

    public function logout(): void
    {
        $device = $this->deviceSecret();
        if ($device !== null) {
            $this->devices->endSecret($device);
        }
        $this->user = null;
        $this->session->invalidate();
    }

    private function startSession(int $userId, string $ip = '', string $agent = ''): User
    {
        $this->session->regenerate();
        $this->session->put(self::SESSION_KEY, $userId);
        $this->session->put(self::VERSION_KEY, $this->users->authVersion($userId));
        $this->session->put(self::DEVICE_KEY, $this->devices->start($userId, $ip, $agent));
        $locale = $this->users->locale($userId);
        if ($locale !== null) {
            $this->translator->setLocale($locale);
        }
        $this->user = null;

        return $this->user();
    }

    /** @param array<string, array{0: string, 1: string}> $fields */
    private function assertValid(array $input, array $fields): void
    {
        $result = $this->validator->validate($input, $fields);

        if ($result->fails()) {
            throw new ValidationException($result);
        }
    }

    private function fail(string $field, string $message): never
    {
        throw new ValidationException(new ValidationResult([$field => $message]));
    }
}
