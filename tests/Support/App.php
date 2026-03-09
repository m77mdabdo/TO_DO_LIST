<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Config;
use App\Core\Container;
use App\Http\Kernel;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Session\ArraySession;
use App\Support\Clock;
use App\Support\FrozenClock;
use PDO;

/**
 * Boots the real application with an in-memory database and session,
 * so tests can send requests through the whole stack.
 */
final class App
{
    public readonly Container $container;
    public readonly ArraySession $session;

    public function __construct(string $now = '2026-01-20 10:00:00')
    {
        $boot = require BASE_PATH . '/bootstrap/app.php';
        $this->session = new ArraySession();
        $this->container = $boot(new Config([
            'app_name' => 'Todo Board',
            'timezone' => 'Asia/Riyadh',
            'db'       => ['driver' => 'sqlite', 'sqlite_path' => ':memory:'],
        ]), $this->session);
        $this->container->singleton(Clock::class, fn () => new FrozenClock($now));
    }

    /** @param array<string, string> $body */
    public function post(string $path, array $body = [], bool $json = false): Response
    {
        $body['_token'] ??= $this->container->get(Csrf::class)->token();

        return $this->send(new Request('POST', $path, [], $body, $json ? ['accept' => 'application/json'] : [], '10.0.0.1'));
    }

    public function get(string $path): Response
    {
        return $this->send(new Request('GET', $path, [], [], [], '10.0.0.1'));
    }

    public function send(Request $request): Response
    {
        return $this->container->get(Kernel::class)->handle($request);
    }

    public function pdo(): PDO
    {
        return $this->container->get(PDO::class);
    }
}
