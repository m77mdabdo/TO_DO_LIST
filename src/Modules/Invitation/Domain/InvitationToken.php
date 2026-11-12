<?php

declare(strict_types=1);

namespace App\Modules\Invitation\Domain;

/**
 * The secret in an invitation link: 32 random bytes (256 bits), written in
 * URL-safe base64 (43 characters). Only its SHA-256 hash is stored, so the
 * lookup is an exact match on a unique index: O(log n), and nothing to
 * compare in a way that leaks timing.
 */
final class InvitationToken
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
