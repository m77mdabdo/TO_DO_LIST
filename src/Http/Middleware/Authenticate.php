<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\Authenticator;
use App\Http\Redirector;
use App\Http\Request;
use App\Http\Response;
use Closure;

/**
 * Only logged-in users get through. The user is added to the request
 * as the "user" attribute, so controllers never look it up again.
 */
final class Authenticate implements Middleware
{
    public function __construct(private Authenticator $auth, private Redirector $redirect)
    {
    }

    public function process(Request $request, Closure $next): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $request->wantsJson()
                ? Response::json(['ok' => false, 'message' => 'Please log in again.'], 401)
                : $this->redirect->to('/login');
        }

        return $next($request->withAttribute('user', $user));
    }
}
