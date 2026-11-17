<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/** Ends the request with an HTTP status; modules may subclass it for their own failures. */
class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message, $status);
    }

    /** A stable code for JSON clients ("not_found", "forbidden", ...). */
    public function code(): string
    {
        return match ($this->status) {
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            419 => 'expired',
            422 => 'invalid',
            429 => 'rate_limited',
            default => $this->status >= 500 ? 'server_error' : 'error',
        };
    }

    /** @return array<string, string> per-field messages, if any */
    public function fields(): array
    {
        return [];
    }

    public static function notFound(string $message = "This page doesn't exist."): self
    {
        return new self(404, $message);
    }
}
