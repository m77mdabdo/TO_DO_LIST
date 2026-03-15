<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

/**
 * Writes unexpected errors to storage/logs/app-YYYY-MM-DD.log.
 */
final class FileLogger implements Logger
{
    public function __construct(private string $directory, private Clock $clock)
    {
    }

    public function error(Throwable $e): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }

        $now = $this->clock->now();
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n\n",
            $now->format('Y-m-d H:i:s'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
        );

        file_put_contents($this->directory . '/app-' . $now->format('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
