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

    public function updatePassword(int $id, string $passwordHash): void;
}
