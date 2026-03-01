<?php

declare(strict_types=1);

namespace App\Validation;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    public function __construct(public readonly ValidationResult $result)
    {
        parent::__construct('The given data was invalid.');
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->result->errors();
    }
}
