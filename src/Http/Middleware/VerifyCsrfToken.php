<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\HttpException;
use App\Http\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;

final class VerifyCsrfToken implements Middleware
{
    public function __construct(private Csrf $csrf)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        // The API is called with a bearer token, never with the session cookie, so there is nothing to forge.
        if ($request->isPost() && !str_starts_with($request->path, '/api/')) {
            $token = $request->body()['_token'] ?? $request->header('x-csrf-token');

            if (!$this->csrf->isValid($token)) {
                throw new HttpException(419, 'Your session expired. Refresh the page and try again.');
            }
        }

        return $next($request);
    }
}
