<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Board\Contract\ListSummary;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Modules\Workspace\Domain\WorkspaceRepository;

/** Builds a signed-up owner with a board and To do / Doing / Done lists. */
final class BoardFixture
{
    public readonly int $owner;
    public readonly int $workspaceId;
    public readonly BoardSummary $board;
    public readonly ListSummary $todo;
    public readonly ListSummary $doing;
    public readonly ListSummary $done;

    public function __construct(
        public readonly App $app,
        BoardVisibility $visibility = BoardVisibility::Private,
        string $email = 'sara@example.com',
    ) {
        $this->owner = $app->signUp(ucfirst(strtok($email, '@')), $email);
        $this->workspaceId = $app->container->get(WorkspaceDirectory::class)->current($this->owner)->id;
        $boards = $app->container->get(BoardService::class);
        $this->board = $boards->createBoard($this->owner, $this->workspaceId, 'Launch', $visibility);
        $this->todo = $boards->addList($this->owner, $this->board->id, 'To do');
        $this->doing = $boards->addList($this->owner, $this->board->id, 'Doing');
        $this->done = $boards->addList($this->owner, $this->board->id, 'Done');
    }

    /** Signs up another user and puts them in this workspace with $role. */
    public function colleague(string $email, WorkspaceRole $role = WorkspaceRole::Member): int
    {
        $id = $this->app->signUp(ucfirst(strtok($email, '@')), $email);
        $this->app->container->get(WorkspaceRepository::class)->addMember($this->workspaceId, $id, $role);

        return $id;
    }
}
