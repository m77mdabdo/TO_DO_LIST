<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Support\NotFound;
use App\View\View;
use Closure;
use Throwable;

/**
 * Turns any exception into a friendly error page (or JSON for fetch calls).
 * Unexpected errors are logged and only described in debug mode.
 */
final class HandleErrors implements Middleware
{
    public function __construct(private View $view, private bool $debug = false)
    {
    }

    public function process(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (HttpException $e) {
            return $this->render($request, $e->status, $e->getMessage());
        } catch (NotFound $e) {
            return $this->render($request, 404, $e->getMessage());
        } catch (Throwable $e) {
            error_log((string) $e);

            return $this->render($request, 500, $this->debug ? $e->getMessage() : 'Please try again in a moment.');
        }
    }

    private function render(Request $request, int $status, string $message): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'message' => $message], $status);
        }

        $html = $this->view->render('error', [
            'title'        => (string) $status,
            'bodyClass'    => 'page-error',
            'inlineErrors' => true,
            'status'       => $status,
            'message'      => $message,
        ]);

        return Response::html($html, $status);
    }
}
