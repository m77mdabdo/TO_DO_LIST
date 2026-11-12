<?php

declare(strict_types=1);

namespace App\Modules\Card\Contract;

/** Creating cards for a user from other modules (the legacy import, later templates). */
interface CardWriter
{
    /**
     * @param array{description?: ?string, due_date?: ?string, completed_at?: ?string, created_at?: ?string} $extra
     *        due_date is Y-m-d; completed_at and created_at are Y-m-d H:i:s
     */
    public function createCard(int $actorId, int $boardId, int $listId, string $title, array $extra = []): CardSummary;
}
