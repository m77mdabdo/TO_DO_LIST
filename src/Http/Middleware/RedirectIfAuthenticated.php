<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\Authenticator;
use App\Http\Redirector;
use App\Http\Request;
use App\Http\Response;
use Closure;

/**
 * Login and sign-up pages send logged-in users straight to their board.
 */
final class RedirectIfAuthenticated implements Middleware
{
    public function __construct(private Authenticator $auth, private Redirector $redirect)
    {
    }

    public function process(Request $request, Closure $next): Response
    {
        return $this->auth->check() ? $this->redirect->to('/') : $next($request);
    }
}
