<?php

declare(strict_types=1);

namespace App\Auth;

use App\Session\Session;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use App\Validation\Validator;

final class AuthService
{
    private const SESSION_KEY = 'user_id';

    private ?User $user = null;

    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private LoginThrottle $throttle,
        private Session $session,
        private Validator $validator,
    ) {
    }

    public function user(): ?User
    {
        $id = $this->session->get(self::SESSION_KEY);

        if (!is_int($id)) {
            return null;
        }

        return $this->user ??= $this->users->find($id);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @param array<string, mixed> $input email and password
     * @throws ValidationException when the form is invalid or the login fails
     */
    public function login(array $input, string $ip): User
    {
        if ($this->throttle->tooManyAttempts($ip)) {
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

        $this->throttle->clear($ip);

        if ($this->hasher->needsRehash($credentials->passwordHash)) {
            $this->users->updatePassword($credentials->userId, $this->hasher->hash($password));
        }

        return $this->startSession($credentials->userId);
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

        return $this->startSession($user->id);
    }

    public function logout(): void
    {
        $this->user = null;
        $this->session->invalidate();
    }

    private function startSession(int $userId): User
    {
        $this->session->regenerate();
        $this->session->put(self::SESSION_KEY, $userId);
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
