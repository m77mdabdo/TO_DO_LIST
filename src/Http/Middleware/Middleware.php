<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;
use Closure;

/**
 * A step that runs before (and after) a controller. Call $next($request)
 * to continue, or return a Response to stop early.
 */
interface Middleware
{
    /** @param Closure(Request): Response $next */
    public function process(Request $request, Closure $next): Response;
}
