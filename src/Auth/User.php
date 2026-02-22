<?php

declare(strict_types=1);

namespace App\Auth;

use DateTimeImmutable;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: (string) $row['name'],
            email: (string) $row['email'],
            passwordHash: (string) $row['password'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
        );
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0];
    }

    /** The letter shown in the avatar circle. */
    public function initial(): string
    {
        return mb_strtoupper(mb_substr(trim($this->name), 0, 1));
    }
}
