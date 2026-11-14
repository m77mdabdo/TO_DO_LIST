<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Read-only access to config.php (or config.local.php) with dot keys: get('db.driver').
 * Values in a .env file are loaded into the environment first.
 */
final class Config
{
    /** @param array<string, mixed> $items */
    public function __construct(private array $items)
    {
    }

    public static function load(string $dir): self
    {
        // Secrets (database and mail passwords) can live in .env, which git ignores.
        DotEnv::load($dir . '/.env');
        $local = $dir . '/config.local.php';

        return new self(require is_file($local) ? $local : $dir . '/config.php');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
