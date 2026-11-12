<?php

declare(strict_types=1);

namespace App\Modules\Board\Application;

use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Contract\ListSummary;
use App\Modules\Board\Domain\BoardRepository;

final class BoardQueries implements BoardDirectory
{
    public function __construct(private BoardRepository $boards)
    {
    }

    public function find(int $boardId): ?BoardSummary
    {
        return $this->boards->find($boardId);
    }

    public function findByPublicId(string $publicId): ?BoardSummary
    {
        return $this->boards->findByPublicId($publicId);
    }

    public function listOnBoard(int $listId, int $boardId): ?ListSummary
    {
        return $this->boards->findList($listId, $boardId);
    }

    public function openLists(int $boardId): array
    {
        return $this->boards->lists($boardId);
    }
}
