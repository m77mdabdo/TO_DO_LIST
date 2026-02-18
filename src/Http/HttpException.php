<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * An error that maps straight to an HTTP status code and a message
 * that is safe to show to the user.
 */
final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message, $status);
    }

    public static function notFound(string $message = "This page doesn't exist."): self
    {
        return new self(404, $message);
    }

    public static function methodNotAllowed(): self
    {
        return new self(405, 'This action only accepts form submissions.');
    }

    public static function tokenMismatch(): self
    {
        return new self(419, 'Your session expired. Refresh the page and try again.');
    }

    public static function unprocessable(string $message): self
    {
        return new self(422, $message);
    }
}
