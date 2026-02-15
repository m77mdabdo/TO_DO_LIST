<?php

declare(strict_types=1);

namespace App\Auth;

interface LoginThrottle
{
    public function tooManyAttempts(string $ip): bool;

    public function recordFailure(string $ip, string $email): void;

    public function clear(string $ip): void;
}
