<?php

declare(strict_types=1);

namespace App\Modules\Audit\Contract;

/** One line of a workspace's audit log. */
final class AuditEntry
{
    /** @param array<string, string> $details */
    public function __construct(
        public readonly int $id,
        public readonly ?int $actorId,
        public readonly string $action,
        public readonly string $target,
        public readonly array $details,
        public readonly string $createdAt,
    ) {
    }
}
