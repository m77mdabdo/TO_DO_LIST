<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use Closure;

/**
 * Every POST must carry the session's CSRF token, either as the _token
 * form field or the X-CSRF-Token header (used by fetch() calls).
 */
final class VerifyCsrfToken implements Middleware
{
    public function __construct(private Csrf $csrf)
    {
    }

    public function process(Request $request, Closure $next): Response
    {
        if ($request->isPost()) {
            $token = $request->body()[Csrf::FIELD] ?? $request->header(Csrf::HEADER);

            if (!$this->csrf->matches($token)) {
                throw HttpException::tokenMismatch();
            }
        }

        return $next($request);
    }
}
