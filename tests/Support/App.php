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

    public function __construct(string $now = '2026-01-20 10:00:00', string $locale = 'en')
    {
        $boot = require BASE_PATH . '/bootstrap/app.php';
        $this->session = new ArraySession();
        $this->container = $boot(new Config([
            'app_name' => 'Todo Board',
            'timezone' => 'Asia/Riyadh',
            'locale'   => $locale,
            'url'      => 'https://todo.test',
            'mail'     => ['driver' => 'memory', 'from_address' => 'team@todo.test', 'from_name' => 'To Do List'],
            'db'       => ['driver' => 'sqlite', 'sqlite_path' => ':memory:'],
            'attachments' => ['path' => self::scratch('attachments')],
            'backups'     => ['path' => self::scratch('backups')],
        ]), $this->session);
        $this->container->singleton(Clock::class, fn () => new FrozenClock($now));
    }

    /** A fresh folder under the system temp dir, removed when the test run ends. */
    public static function scratch(string $what): string
    {
        $dir = sys_get_temp_dir() . '/todo-tests-' . getmypid() . '/' . $what . '-' . bin2hex(random_bytes(4));
        static $registered = false;
        if (!$registered) {
            $registered = true;
            $root = sys_get_temp_dir() . '/todo-tests-' . getmypid();
            register_shutdown_function(static function () use ($root): void {
                if (!is_dir($root)) {
                    return;
                }
                $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($items as $item) {
                    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
                }
                rmdir($root);
            });
        }

        return $dir;
    }

    /** @param array<string, string> $body */
    public function post(string $path, array $body = [], bool $json = false): Response
    {
        $body['_token'] ??= $this->container->get(Csrf::class)->token();

        return $this->send(new Request('POST', $path, [], $body, $json ? ['accept' => 'application/json'] : [], '10.0.0.1'));
    }

    /**
     * A multipart POST with files, as a browser sends a file input.
     *
     * @param array<string, string> $body
     * @param array<string, \App\Http\UploadedFile> $files
     */
    public function upload(string $path, array $files, array $body = []): Response
    {
        $body['_token'] ??= $this->container->get(Csrf::class)->token();

        return $this->send(new Request('POST', $path, [], $body, [], '10.0.0.1', [], $files));
    }

    public function get(string $path): Response
    {
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);

        return $this->send(new Request('GET', (string) parse_url($path, PHP_URL_PATH), $query, [], [], '10.0.0.1'));
    }

    public function send(Request $request): Response
    {
        return $this->container->get(Kernel::class)->handle($request);
    }

    /** Registers through the real form and returns the new user's id. */
    public function signUp(string $name = 'Sara Ali', string $email = 'sara@example.com'): int
    {
        $this->post('/logout');
        $this->post('/register', [
            'name' => $name,
            'email' => $email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        return (int) $this->session->get('user_id');
    }

    /** Switches the session to another (already registered) user. */
    public function loginAs(string $email): int
    {
        $this->post('/logout');
        $this->post('/login', ['email' => $email, 'password' => 'secret123']);

        return (int) $this->session->get('user_id');
    }

    /** The body of a GET, failing loudly on anything but 200. */
    public function page(string $path): string
    {
        $response = $this->get($path);

        if ($response->status !== 200) {
            throw new \RuntimeException("GET $path gave {$response->status}, expected 200.");
        }

        return $response->body;
    }

    public function pdo(): PDO
    {
        return $this->container->get(PDO::class);
    }
}
