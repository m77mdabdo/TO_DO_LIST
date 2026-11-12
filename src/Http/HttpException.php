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

    public static function notFound(string $message = "This page doesn't exist."): self
    {
        return new self(404, $message);
    }
}
