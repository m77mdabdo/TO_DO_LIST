<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Builds links from the app root, so the app works the same at
 * http://localhost:8000/ and inside a sub folder like http://localhost/to_do_list/.
 */
final class UrlGenerator
{
    private string $basePath;

    public function __construct(string $basePath = '', private string $publicDir = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Works out the folder the app is served from.
     *
     * When the project root forwards requests to public/ (XAMPP), the script
     * lives in ".../public" but the browser never sees that part of the URL.
     *
     * @param array<string, mixed> $server
     */
    public static function detectBasePath(array $server): string
    {
        $dir = str_replace('\\', '/', dirname((string) ($server['SCRIPT_NAME'] ?? '/')));
        $dir = rtrim($dir, '/.');
        $uri = (string) parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        if (str_ends_with($dir, '/public') && !str_starts_with($uri, $dir . '/') && $uri !== $dir) {
            $dir = substr($dir, 0, -strlen('/public'));
        }

        return $dir;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function to(string $path = '/'): string
    {
        return $this->basePath . '/' . ltrim($path, '/');
    }

    /** Asset URL with the file's modified time, so browsers pick up changes. */
    public function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = rtrim($this->publicDir, '/') . '/assets/' . $path;
        $version = $this->publicDir !== '' && is_file($file) ? filemtime($file) : 1;

        return $this->to('assets/' . $path) . '?v=' . $version;
    }
}
