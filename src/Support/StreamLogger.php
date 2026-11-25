<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

/**
 * Writes unexpected errors to a stream, stderr by default. Used in containers,
 * where the runtime collects stderr (`docker compose logs`) and files written
 * inside the container would be lost or never read.
 */
final class StreamLogger implements Logger
{
    /** @param resource|null $stream */
    public function __construct(private Clock $clock, private mixed $stream = null)
    {
    }

    public function error(Throwable $e): void
    {
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n",
            $this->clock->now()->format('Y-m-d H:i:s'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
        );

        $stream = $this->stream ?? fopen('php://stderr', 'wb');
        if (is_resource($stream)) {
            fwrite($stream, $line);
        }
    }
}
