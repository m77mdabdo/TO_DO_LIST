<?php

declare(strict_types=1);

namespace App\Modules\Integration\Http;

use App\Http\HttpException;

/** An API failure with a stable machine-readable code and per-field messages. */
final class ApiError extends HttpException
{
    /** @param array<string, string> $fields */
    public function __construct(int $status, public readonly string $errorCode, string $message, public readonly array $fields = [])
    {
        parent::__construct($status, $message);
    }

    public function code(): string
    {
        return $this->errorCode;
    }

    public function fields(): array
    {
        return $this->fields;
    }
}
