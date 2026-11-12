<?php

declare(strict_types=1);

namespace App\Modules\Board\Domain;

use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Board\Contract\ListSummary;

interface BoardRepository
{
    public function create(int $workspaceId, string $name, string $color, BoardVisibility $visibility, int $createdBy): BoardSummary;

    public function find(int $id): ?BoardSummary;

    public function findByPublicId(string $publicId): ?BoardSummary;

    /** @param array{name?: string, description?: ?string, color?: string, visibility?: string} $fields */
    public function update(int $id, array $fields): void;

    public function setArchived(int $id, bool $archived): void;

    /**
     * Boards in a workspace the user may see, in one query.
     *
     * @param list<int> $memberOf boards the user was added to
     * @return list<BoardSummary>
     */
    public function visibleInWorkspace(int $workspaceId, bool $seesAll, bool $seesWorkspaceBoards, array $memberOf, bool $archived = false): array;

    public function createList(int $boardId, string $name, string $position): ListSummary;

    public function findList(int $listId, int $boardId): ?ListSummary;

    /** @param array{name?: string, position?: string} $fields */
    public function updateList(int $listId, array $fields): void;

    public function setListArchived(int $listId, bool $archived): void;

    /** @return list<ListSummary> */
    public function lists(int $boardId, bool $archived = false): array;

    public function listPositionAfter(int $boardId, ?int $afterListId, ?int $movingId = null): string;

    public function listPositionAtEnd(int $boardId): string;
}
