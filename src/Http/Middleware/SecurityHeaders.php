<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Middleware;
use App\Http\Request;
use App\Http\Response;

final class SecurityHeaders implements Middleware
{
    private const HEADERS = [
        'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
            . "connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'",
        'X-Frame-Options'        => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy'        => 'same-origin',
        'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=()',
    ];

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        // A response may set a stricter policy of its own (e.g. a sandbox for downloads).
        foreach (self::HEADERS as $name => $value) {
            if ($response->header($name) === null) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}
