<?php

declare(strict_types=1);

namespace App\Auth;

use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use App\Validation\Validator;

/**
 * Signing up and logging in. Every failure is a ValidationException,
 * so the controller only has to send the user back to the form.
 */
final class AuthService
{
    /** Checked when the email doesn't exist, so a wrong email takes as long as a wrong password. */
    private const DUMMY_HASH = '$2y$10$yo7DWutMZbeStohJaVd1Ruj4hq721ZOMsDpHuNtHXwKA2CzjGLFsq';

    public function __construct(
        private UserRepository $users,
        private Authenticator $auth,
        private LoginThrottle $throttle,
        private Validator $validator,
    ) {
    }

    /**
     * @param array{email?: string, password?: string} $input
     */
    public function attempt(array $input, string $ip): User
    {
        if ($this->throttle->tooManyAttempts($ip)) {
            $this->throwIfFailed(new ValidationResult([
                'login' => 'Too many attempts. Please wait 15 minutes and try again.',
            ]));
        }

        $this->throwIfFailed($this->validator->validate($input, AuthRules::login()));

        $email    = (string) $input['email'];
        $password = (string) $input['password'];
        $user     = $this->users->findByEmail($email);

        if (!password_verify($password, $user?->passwordHash ?? self::DUMMY_HASH) || $user === null) {
            $this->throttle->recordFailure($ip, $email);
            $this->throwIfFailed(new ValidationResult(['login' => "That email and password don't match."]));
        }

        $this->throttle->clear($ip);

        if (password_needs_rehash($user->passwordHash, PASSWORD_DEFAULT)) {
            $this->users->updatePassword($user->id, password_hash($password, PASSWORD_DEFAULT));
        }

        $this->auth->login($user);

        return $user;
    }

    /**
     * @param array{name?: string, email?: string, password?: string, password_confirmation?: string} $input
     */
    public function register(array $input): User
    {
        $result = $this->validator->validate($input, AuthRules::register());

        if (!isset($result->errors()['password']) && ($input['password'] ?? '') !== ($input['password_confirmation'] ?? null)) {
            $result = $result->with('password_confirmation', "Passwords don't match.");
        }

        if (!isset($result->errors()['email']) && $this->users->findByEmail((string) $input['email']) !== null) {
            $result = $result->with('email', 'This email already has an account.');
        }

        $this->throwIfFailed($result);

        $user = $this->users->create(
            trim((string) $input['name']),
            (string) $input['email'],
            password_hash((string) $input['password'], PASSWORD_DEFAULT),
        );

        $this->auth->login($user);

        return $user;
    }

    private function throwIfFailed(ValidationResult $result): void
    {
        if ($result->fails()) {
            throw new ValidationException($result);
        }
    }
}
