<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Modules\Identity\Contract\User;

interface UserRepository
{
    public function find(int $id): ?User;

    public function emailExists(string $email): bool;

    /** The stored password hash for an email, or null when there's no such user. */
    public function credentials(string $email): ?Credentials;

    public function create(string $name, string $email, string $passwordHash): User;

    /** Same hash, new format (rehash on login): sessions stay signed in. */
    public function updatePassword(int $id, string $passwordHash): void;

    /** A new password: bumps auth_version, which signs out every other session. */
    public function changePassword(int $id, string $passwordHash): void;

    /** The user, only while the session's auth version is still current. */
    public function findForSession(int $id, int $authVersion): ?User;

    public function authVersion(int $id): int;

    public function findByEmail(string $email): ?User;

    public function markEmailVerified(int $id): void;
}
