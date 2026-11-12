<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract;

/** Read-only board lookups for other modules. No access checks: callers use the AccessGate. */
interface BoardDirectory
{
    public function find(int $boardId): ?BoardSummary;

    public function findByPublicId(string $publicId): ?BoardSummary;

    /** The list only when it belongs to that board, so a forged list id from another board is rejected. */
    public function listOnBoard(int $listId, int $boardId): ?ListSummary;

    /** @return list<ListSummary> open lists in board order */
    public function openLists(int $boardId): array;

    /** @return list<BoardSummary> every board of the workspace, archived ones included */
    public function allInWorkspace(int $workspaceId): array;
}
