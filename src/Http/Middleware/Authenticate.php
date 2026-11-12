<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identity\Contract\Authenticator;
use App\Http\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;

/**
 * Lets signed-in users through and hands the User to the controller.
 */
final class Authenticate implements Middleware
{
    public function __construct(private Authenticator $auth, private Url $url)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $request->wantsJson()
                ? Response::json(['ok' => false, 'message' => 'Please log in again.'], 401)
                : Response::redirect($this->url->to('/login'));
        }

        return $next($request->withAttribute('user', $user));
    }
}
