<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

final class PasswordHasher
{
    // Checked when the email doesn't exist, so a wrong email takes as long as a wrong password.
    private const DUMMY_HASH = '$2y$10$yo7DWutMZbeStohJaVd1Ruj4hq721ZOMsDpHuNtHXwKA2CzjGLFsq';

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function verify(string $password, ?string $hash): bool
    {
        return password_verify($password, $hash ?? self::DUMMY_HASH) && $hash !== null;
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
