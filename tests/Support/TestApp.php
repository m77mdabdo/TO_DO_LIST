<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Bootstrap\Services;
use App\Http\Kernel;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Session\ArraySession;
use App\Session\Session;
use App\Support\Clock;
use App\Support\Container;
use App\Support\FrozenClock;

/**
 * The whole app wired like production, but with an in-memory database,
 * an in-memory session and a frozen clock. Requests go straight to the Kernel.
 */
final class TestApp
{
    public readonly Container $container;
    public readonly ArraySession $session;
    public readonly FrozenClock $clock;

    public function __construct(string $now = '2026-02-10 09:00:00')
    {
        $this->container = new Container();
        $this->session = new ArraySession();
        $this->clock = new FrozenClock($now);

        Services::register($this->container, [
            'app_name' => 'Todo Board',
            'timezone' => 'UTC',
            'debug'    => true,
            'db'       => ['driver' => 'sqlite', 'sqlite_path' => ':memory:'],
        ], BASE_PATH);

        $this->container->instance(Session::class, $this->session);
        $this->container->instance(Clock::class, $this->clock);
    }

    public function get(string $path, bool $json = false): Response
    {
        return $this->send(new Request('GET', $path, headers: $this->headers($json)));
    }

    /** @param array<string, string> $body the CSRF token is added for you */
    public function post(string $path, array $body = [], bool $json = false): Response
    {
        $body += ['_token' => $this->container->get(Csrf::class)->token()];

        return $this->send(new Request('POST', $path, body: $body, headers: $this->headers($json)));
    }

    public function send(Request $request): Response
    {
        return $this->container->get(Kernel::class)->handle($request);
    }

    /** Signs up a user and leaves them logged in. */
    public function signUp(string $name = 'Sara Ali', string $email = 'sara@example.com'): void
    {
        $this->post('/register', [
            'name'                  => $name,
            'email'                 => $email,
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ]);
        $this->session->pull('success');
    }

    /** @return array<string, string> */
    private function headers(bool $json): array
    {
        return $json ? ['accept' => 'application/json'] : [];
    }
}
