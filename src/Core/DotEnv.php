<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Reads KEY=VALUE lines from a .env file into the environment, so secrets
 * such as the SMTP password live in a file git ignores, never in the code.
 * A variable the server already sets wins over the file.
 */
final class DotEnv
{
    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key) || getenv($key) !== false) {
                continue;
            }

            $quoted = strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0];
            if ($quoted) {
                $value = substr($value, 1, -1);
            } else {
                // An unquoted value ends at a " #" comment.
                $value = trim((string) preg_replace('/\s+#.*$/', '', $value));
            }

            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}
