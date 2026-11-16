<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\I18n\Translator;
use App\Security\RateLimited;
use App\Security\RateLimiter;
use App\Security\RateLimitResult;
use App\Security\RateLimits;
use App\Session\Session;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\View\View;

/**
 * Applies the limits in RateLimits before anything else runs. Over the
 * limit, the request ends with HTTP 429 and a Retry-After header: JSON for
 * the API and fetch() calls, the error page for forms.
 */
final class ThrottleRequests implements Middleware
{
    public function __construct(
        private RateLimits $limits,
        private RateLimiter $limiter,
        private Session $session,
        private EventBus $events,
        private Clock $clock,
        private Translator $translator,
        private View $view,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $tightest = null;

        foreach ($this->limits->matching($request->method, $request->path) as $rule) {
            $result = $this->limiter->attempt($rule->name . '|' . $this->key($rule->by, $request), $rule->max, $rule->seconds);

            if (!$result->allowed) {
                return $this->refuse($request, $rule->name, $result);
            }
            if ($tightest === null || $result->remaining < $tightest->remaining) {
                $tightest = $result;
            }
        }

        $response = $next($request);

        // API clients can pace themselves with these.
        if ($tightest !== null && str_starts_with($request->path, '/api/')) {
            $response = $response
                ->withHeader('X-RateLimit-Limit', (string) $tightest->limit)
                ->withHeader('X-RateLimit-Remaining', (string) $tightest->remaining);
        }

        return $response;
    }

    private function key(string $by, Request $request): string
    {
        $userId = $this->session->get('user_id');

        return match (true) {
            $by === 'user' && is_int($userId) => 'user:' . $userId,
            $by === 'token' && preg_match('/^Bearer\s+(\S+)$/i', (string) $request->header('authorization'), $m) === 1 => 'token:' . $m[1],
            str_starts_with($by, 'field:') => 'field:' . mb_strtolower(trim((string) ($request->body()[substr($by, 6)] ?? ''))),
            default => 'ip:' . $request->ip,
        };
    }

    private function refuse(Request $request, string $rule, RateLimitResult $result): Response
    {
        $userId = $this->session->get('user_id');
        $this->events->publish(new RateLimited($rule, is_int($userId) ? $userId : null, $request->ip, $this->clock->now()));

        $message = $this->translator->t('Too many requests. Please wait :seconds seconds and try again.', ['seconds' => (string) $result->retryAfter]);

        $response = $request->wantsJson()
            ? Response::json(['ok' => false, 'error' => 'rate_limited', 'message' => $message, 'retry_after' => $result->retryAfter], 429)
            : new Response($this->view->render('errors/error', [
                'title'        => '429',
                'bodyClass'    => 'page-error',
                'inlineErrors' => true,
                'status'       => 429,
                'message'      => $message,
            ]), 429);

        return $response
            ->withHeader('Retry-After', (string) $result->retryAfter)
            ->withHeader('X-RateLimit-Limit', (string) $result->limit)
            ->withHeader('X-RateLimit-Remaining', '0');
    }
}
