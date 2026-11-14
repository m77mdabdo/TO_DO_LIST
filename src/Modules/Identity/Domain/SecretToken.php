<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * The secret in a password reset or email confirmation link: 32 random
 * bytes (256 bits) in URL-safe base64. Only its SHA-256 is stored, so a
 * leaked database holds no usable link, and lookups are an exact match
 * on a unique index (nothing compared in a way that leaks timing).
 */
final class SecretToken
{
    private function __construct(public readonly string $value)
    {
    }

    public static function generate(): self
    {
        return new self(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
    }

    public static function fromLink(string $value): self
    {
        return new self($value);
    }

    public function hash(): string
    {
        return hash('sha256', $this->value);
    }
}
