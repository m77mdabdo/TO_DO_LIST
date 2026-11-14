<?php

declare(strict_types=1);

namespace App\Modules\Automation\Domain;

use App\Modules\Automation\Contract\AutomationRun;

interface RunLog
{
    /** False when $dedupeKey was already used, i.e. this run already happened. */
    public function add(int $automationId, int $boardId, ?int $cardId, ?int $actorId, string $status, string $detail, ?string $dedupeKey = null): bool;

    public function hasRun(string $dedupeKey): bool;

    /** @return list<AutomationRun> newest first */
    public function latest(int $boardId, int $limit = 50): array;

    /** Deletes runs older than the date (Y-m-d H:i:s); returns how many. */
    public function pruneBefore(string $before): int;
}
