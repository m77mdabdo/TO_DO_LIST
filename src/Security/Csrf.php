<?php

declare(strict_types=1);

namespace App\Security;

use App\Session\Session;

final class Csrf
{
    private const KEY = '_csrf';

    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->put(self::KEY, $token);
        }

        return $token;
    }

    public function isValid(mixed $token): bool
    {
        return is_string($token) && hash_equals($this->token(), $token);
    }
}
