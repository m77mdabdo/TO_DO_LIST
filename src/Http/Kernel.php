<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Container;
use App\Task\TaskNotFound;
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
        private ?\Closure $reportError = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        return $this->pipeline($this->globalMiddleware, $request, function (Request $request): Response {
            try {
                [$route, $params] = $this->router->match($request);

                return $this->pipeline(
                    $route->middleware,
                    $request,
                    fn (Request $request) => $this->callController($route, $request, $params),
                );
            } catch (Throwable $e) {
                return $this->renderError($e, $request);
            }
        });
    }

    /**
     * @param list<class-string<Middleware>> $middleware
     * @param callable(Request): Response $destination
     */
    private function pipeline(array $middleware, Request $request, callable $destination): Response
    {
        $next = $destination;

        foreach (array_reverse($middleware) as $class) {
            $next = fn (Request $request) => $this->container->get($class)->handle($request, $next);
        }

        return $next($request);
    }

    /** @param array<string, int> $params */
    private function callController(Route $route, Request $request, array $params): Response
    {
        [$class, $method] = $route->action;

        return $this->container->get($class)->$method($request, $params);
    }

    private function renderError(Throwable $e, Request $request): Response
    {
        [$status, $message] = match (true) {
            $e instanceof HttpException => [$e->status, $e->getMessage()],
            $e instanceof TaskNotFound  => [404, $e->getMessage()],
            default                     => [500, 'Something broke on our side. Please try again.'],
        };

        if ($status === 500 && $this->reportError) {
            ($this->reportError)($e);
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
