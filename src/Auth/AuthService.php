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
    public function __construct(
        private UserRepository $users,
        private Authenticator $auth,
        private Validator $validator,
    ) {
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
