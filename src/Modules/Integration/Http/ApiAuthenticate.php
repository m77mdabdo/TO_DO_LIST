<?php

declare(strict_types=1);

namespace App\Modules\Integration\Http;

use App\Http\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Integration\Application\ApiTokens;

/**
 * For /api/ routes: "Authorization: Bearer tdb_..." only. The session
 * cookie is ignored here, so a web page can't make the browser call the API.
 */
final class ApiAuthenticate implements Middleware
{
    public function __construct(private ApiTokens $tokens, private UserDirectory $users)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $header = (string) $request->header('authorization');
        $secret = preg_match('/^Bearer\s+(\S+)$/i', $header, $m) ? $m[1] : null;
        $token = $this->tokens->authenticate($secret);
        $user = $token !== null ? $this->users->find($token->userId) : null;

        if ($token === null || $user === null) {
            return Response::json(['ok' => false, 'message' => 'A valid API token is required.'], 401)
                ->withHeader('WWW-Authenticate', 'Bearer');
        }

        return $next($request->withAttribute('apiToken', $token)->withAttribute('user', $user));
    }
}
