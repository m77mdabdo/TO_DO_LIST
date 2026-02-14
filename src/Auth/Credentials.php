<?php

declare(strict_types=1);

namespace App\Auth;

final class Credentials
{
    public function __construct(
        public readonly int $userId,
        public readonly string $passwordHash,
    ) {
    }
}
