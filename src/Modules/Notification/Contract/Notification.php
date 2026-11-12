<?php

declare(strict_types=1);

namespace App\Modules\Notification\Contract;

/** One notification in someone's bell. */
final class Notification
{
    /** @param array<string, string> $data names copied in when it happened (list, role, excerpt...) */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly ?int $actorId,
        public readonly string $subject,
        public readonly array $data,
        public readonly string $link,
        public readonly string $createdAt,
        public readonly ?string $readAt,
    ) {
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }
}
