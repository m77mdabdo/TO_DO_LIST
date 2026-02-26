<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Validation rules for the login and sign-up forms.
 */
final class AuthRules
{
    public const NAME_MAX     = 60;
    public const EMAIL_MAX    = 190;
    public const PASSWORD_MIN = 8;
    public const PASSWORD_MAX = 72; // bcrypt ignores anything longer

    /** @return array<string, array{0: string, 1: string}> */
    public static function login(): array
    {
        return [
            'email'    => ['Email', 'required|email'],
            'password' => ['Password', 'required'],
        ];
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function register(): array
    {
        return [
            'name'     => ['Name', 'required|text|min:2|max:' . self::NAME_MAX],
            'email'    => ['Email', 'required|email|max:' . self::EMAIL_MAX],
            'password' => ['Password', 'required|min:' . self::PASSWORD_MIN . '|max:' . self::PASSWORD_MAX],
        ];
    }
}
