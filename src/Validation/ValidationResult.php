<?php

declare(strict_types=1);

namespace App\Validation;

final class ValidationResult
{
    /** @param array<string, string> $errors */
    public function __construct(private array $errors = [])
    {
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /** @return array<string, string> one message per field */
    public function errors(): array
    {
        return $this->errors;
    }

    public function with(string $field, string $message): self
    {
        return new self($this->errors + [$field => $message]);
    }
}
