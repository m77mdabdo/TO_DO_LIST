<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Hashes passwords with Argon2id when PHP has it (memory-hard, resists GPU
 * cracking), bcrypt otherwise. Old bcrypt hashes keep working and are
 * upgraded on the next successful login through needsRehash().
 */
final class PasswordHasher
{
    // Verified when the email doesn't exist, so a wrong email takes as long as a wrong password.
    private const DUMMY_ARGON2ID = '$argon2id$v=19$m=65536,t=4,p=1$YXRlL05ZU3JHUFkwVE0vMg$+BEao+/8rjiST/NQ5o4VYY7KmQtatakU1FiQedi7boQ';
    private const DUMMY_BCRYPT = '$2y$10$yo7DWutMZbeStohJaVd1Ruj4hq721ZOMsDpHuNtHXwKA2CzjGLFsq';

    public function hash(string $password): string
    {
        return password_hash($password, self::algorithm());
    }

    public function verify(string $password, ?string $hash): bool
    {
        return password_verify($password, $hash ?? self::dummyHash()) && $hash !== null;
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::algorithm());
    }

    private static function algorithm(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    private static function dummyHash(): string
    {
        return defined('PASSWORD_ARGON2ID') ? self::DUMMY_ARGON2ID : self::DUMMY_BCRYPT;
    }
}
