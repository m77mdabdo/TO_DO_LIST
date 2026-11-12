<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Container;
use App\Support\Logger;
use App\View\View;
use Throwable;

/**
 * Turns a Request into a Response: match the route, run its middleware,
 * call the controller, and render any error as a friendly page.
 */
final class Kernel
{
    /** @param list<class-string<Middleware>> $globalMiddleware run on every request */
    public function __construct(
        private Container $container,
        private Router $router,
        private array $globalMiddleware = [],
        private ?Logger $logger = null,
        private bool $debug = false,
    ) {
    }

    public function handle(Request $request): Response
    {
        return $this->pipeline($this->globalMiddleware, $request, function (Request $request): Response {
            [$route, $params] = $this->router->match($request);

            return $this->pipeline(
                $route->middleware,
                $request,
                fn (Request $request) => $this->callController($route, $request, $params),
            );
        });
    }

    /**
     * @param list<class-string<Middleware>> $middleware
     * @param callable(Request): Response $destination
     */
    private function pipeline(array $middleware, Request $request, callable $destination): Response
    {
        $next = $this->guarded($destination);

        foreach (array_reverse($middleware) as $class) {
            $next = $this->guarded(fn (Request $request) => $this->container->get($class)->handle($request, $next));
        }

        return $next($request);
    }

    /**
     * Turns an exception into an error response right where it happens,
     * so outer middleware (like the security headers) still run.
     *
     * @param callable(Request): Response $step
     * @return callable(Request): Response
     */
    private function guarded(callable $step): callable
    {
        return function (Request $request) use ($step): Response {
            try {
                return $step($request);
            } catch (Throwable $e) {
                return $this->renderError($e, $request);
            }
        };
    }

    /** @param array<string, int|string> $params */
    private function callController(Route $route, Request $request, array $params): Response
    {
        [$class, $method] = $route->action;

        return $this->container->get($class)->$method($request, $params);
    }

    private function renderError(Throwable $e, Request $request): Response
    {
        [$status, $message] = match (true) {
            $e instanceof HttpException => [$e->status, $e->getMessage()],
            default                     => [500, 'Something broke on our side. Please try again.'],
        };

        if ($status === 500) {
            $this->logger?->error($e);

            if ($this->debug) {
                $message = $e::class . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
            }
        }

        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'message' => $message], $status);
        }

        $html = $this->container->get(View::class)->render('errors/error', [
            'title'        => (string) $status,
            'bodyClass'    => 'page-error',
            'inlineErrors' => true,
            'status'       => $status,
            'message'      => $message,
        ]);

        return new Response($html, $status);
    }
}
