<?php

declare(strict_types=1);

namespace App\Auth;

interface UserRepository
{
    public function find(int $id): ?User;

    /** Emails are stored lower-cased, so the lookup ignores case. */
    public function findByEmail(string $email): ?User;

    public function create(string $name, string $email, string $passwordHash): User;

    public function updatePassword(int $id, string $passwordHash): void;
}
