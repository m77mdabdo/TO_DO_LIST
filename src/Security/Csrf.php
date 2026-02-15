<?php

declare(strict_types=1);

namespace App\Security;

use App\Session\Session;

/**
 * One random token per session. Every form that changes data sends it back,
 * so other sites can't post to the app on the user's behalf.
 */
final class Csrf
{
    public const FIELD  = '_token';
    public const HEADER = 'X-CSRF-Token';

    private const KEY = '_csrf';

    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }

        return $token;
    }

    public function matches(mixed $token): bool
    {
        return is_string($token) && hash_equals($this->token(), $token);
    }

    /** Called after login and logout so an old token can't be reused. */
    public function refresh(): void
    {
        $this->session->forget(self::KEY);
    }
}
