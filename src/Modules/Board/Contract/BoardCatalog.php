<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract;

/**
 * Writing boards and lists on behalf of a user, with the same permission
 * checks as the UI. Used by the legacy import and, later, by templates.
 */
interface BoardCatalog
{
    public function createBoard(int $actorId, int $workspaceId, string $name, BoardVisibility $visibility = BoardVisibility::Private): BoardSummary;

    public function addList(int $actorId, int $boardId, string $name): ListSummary;

    /** @return list<BoardSummary> the boards of the workspace this user can see */
    public function boardsInWorkspace(int $userId, int $workspaceId, bool $archived = false): array;
}
