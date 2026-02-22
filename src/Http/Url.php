<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Builds links from the app's base path, so it works in the web root
 * or inside any XAMPP sub folder like /todolist.
 */
final class Url
{
    public function __construct(private string $basePath, private string $publicDir)
    {
    }

    public static function detectBasePath(): string
    {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));

        return rtrim($dir, '/');
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function to(string $path = '/'): string
    {
        return $this->basePath . '/' . ltrim($path, '/');
    }

    /** Asset link with a version query, so browsers reload changed files. */
    public function asset(string $path): string
    {
        $file = $this->publicDir . '/assets/' . ltrim($path, '/');
        $version = is_file($file) ? filemtime($file) : 1;

        return $this->to('assets/' . ltrim($path, '/')) . '?v=' . $version;
    }
}
