<?php

declare(strict_types=1);

namespace App\Http;

/**
 * A response read from a file on disk as it is sent, so a large download
 * (a ZIP export) is never held in memory. A temporary file can be removed
 * once it has been sent.
 */
final class FileResponse extends Response
{
    /** @param array<string, string> $headers */
    public function __construct(public readonly string $path, array $headers, private bool $deleteAfterSend = false)
    {
        parent::__construct('', 200, $headers + ['Content-Length' => (string) (is_file($path) ? filesize($path) : 0)]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers() as $name => $value) {
            header("$name: $value");
        }
        readfile($this->path);
        if ($this->deleteAfterSend) {
            @unlink($this->path);
        }
    }
}
