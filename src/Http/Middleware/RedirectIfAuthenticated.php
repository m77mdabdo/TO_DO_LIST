<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identity\Contract\Authenticator;
use App\Http\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;

/** Keeps signed-in users away from the login and sign-up pages. */
final class RedirectIfAuthenticated implements Middleware
{
    public function __construct(private Authenticator $auth, private Url $url)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        return $this->auth->check() ? Response::redirect($this->url->to('/')) : $next($request);
    }
}
