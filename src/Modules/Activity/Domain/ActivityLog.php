<?php

declare(strict_types=1);

namespace App\Modules\Activity\Domain;

use App\Modules\Activity\Contract\ActivityFeed;

interface ActivityLog extends ActivityFeed
{
    /** @param array<string, string|int|null> $data */
    public function record(int $boardId, ?int $cardId, int $actorId, string $type, string $subject, array $data = []): void;
}
