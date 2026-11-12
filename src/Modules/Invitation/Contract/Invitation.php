<?php

declare(strict_types=1);

namespace App\Modules\Invitation\Contract;

use App\Modules\Access\Contract\BoardRole;
use App\Modules\Workspace\Contract\WorkspaceRole;
use DateTimeImmutable;

/**
 * An invitation to join a workspace, and optionally some of its boards.
 * The link's secret is not part of it: only its hash is ever stored.
 */
final class Invitation
{
    public const PENDING = 'pending';
    public const EXPIRED = 'expired';
    public const ACCEPTED = 'accepted';
    public const REVOKED = 'revoked';

    /** @param list<int> $boardIds */
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $workspaceId,
        public readonly string $email,
        public readonly WorkspaceRole $role,
        public readonly BoardRole $boardRole,
        public readonly array $boardIds,
        public readonly int $invitedBy,
        public readonly string $sentAt,
        public readonly string $expiresAt,
        public readonly ?string $acceptedAt,
        public readonly ?string $revokedAt,
    ) {
    }

    public function state(DateTimeImmutable $now): string
    {
        return match (true) {
            $this->acceptedAt !== null                       => self::ACCEPTED,
            $this->revokedAt !== null                        => self::REVOKED,
            $this->expiresAt <= $now->format('Y-m-d H:i:s') => self::EXPIRED,
            default                                          => self::PENDING,
        };
    }

    public function isPending(DateTimeImmutable $now): bool
    {
        return $this->state($now) === self::PENDING;
    }

    /** Emails are compared case-insensitively; they are stored lower-cased. */
    public function isFor(string $email): bool
    {
        return hash_equals($this->email, mb_strtolower(trim($email)));
    }
}
