<?php

declare(strict_types=1);

namespace App\Http;

use App\Session\Session;

/**
 * The page someone wanted before they were asked to sign in, so signing in
 * (or creating an account) brings them back to it, e.g. an invitation link.
 * Only local paths are kept, so this can't be used to redirect elsewhere.
 */
final class Intended
{
    private const KEY = 'intended_path';

    public static function remember(Session $session, string $path): void
    {
        if (self::isLocal($path)) {
            $session->put(self::KEY, $path);
        }
    }

    public static function pull(Session $session, string $default = '/'): string
    {
        $path = $session->get(self::KEY);
        $session->forget(self::KEY);

        return is_string($path) && self::isLocal($path) ? $path : $default;
    }

    private static function isLocal(string $path): bool
    {
        return preg_match('#^/(?![/\\\\])[A-Za-z0-9/_.\-]*$#', $path) === 1;
    }
}
