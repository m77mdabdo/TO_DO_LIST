<?php

declare(strict_types=1);

namespace App\Modules\Integration\Contract;

/**
 * A personal API token. It acts as its owner, but only on the boards it was
 * given and only for its scope, and never more than the owner may do.
 * Only a hash of the secret is kept.
 */
final class ApiToken
{
    public const SCOPES = ['read', 'write'];

    /** @param list<int> $boardIds */
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $userId,
        public readonly string $name,
        public readonly string $scope,
        public readonly array $boardIds,
        public readonly ?string $expiresAt,
        public readonly ?string $lastUsedAt,
        public readonly string $createdAt,
        public readonly ?string $revokedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['public_id'],
            (int) $row['user_id'],
            (string) $row['name'],
            (string) $row['scope'],
            array_values(array_map('intval', (array) json_decode((string) $row['board_ids'], true))),
            $row['expires_at'] !== null ? (string) $row['expires_at'] : null,
            $row['last_used_at'] !== null ? (string) $row['last_used_at'] : null,
            (string) $row['created_at'],
            $row['revoked_at'] !== null ? (string) $row['revoked_at'] : null,
        );
    }

    public function canWrite(): bool
    {
        return $this->scope === 'write';
    }

    public function isActive(string $now): bool
    {
        return $this->revokedAt === null && ($this->expiresAt === null || $this->expiresAt > $now);
    }
}
