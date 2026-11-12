<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Modules\Workspace\Domain\WorkspaceRepository;
use App\Validation\ValidationException;
use Tests\Support\App;
use Tests\TestCase;

final class BoardAccessTest extends TestCase
{
    private App $app;
    private int $owner;
    private int $workspaceId;

    public function setUp(): void
    {
        $this->app = new App();
        $this->owner = $this->app->signUp();
        $this->workspaceId = $this->app->container->get(WorkspaceDirectory::class)->current($this->owner)->id;
    }

    public function testTheCreatorManagesTheNewBoard(): void
    {
        $board = $this->boards()->createBoard($this->owner, $this->workspaceId, 'Marketing');

        $this->assertSame(BoardRole::Manager, $this->gate()->roleOn($this->owner, $board->id));
        $this->assertSame(BoardVisibility::Private, $board->visibility);
        $this->assertSame(26, strlen($board->publicId));
    }

    public function testOutsidersCannotCreateBoardsInSomeoneElsesWorkspace(): void
    {
        $stranger = $this->app->signUp('Omar', 'omar@example.com');

        $this->assertThrows(AccessDenied::class, fn () => $this->boards()->createBoard($stranger, $this->workspaceId, 'Sneaky'));
    }

    public function testPrivateBoardsAreInvisibleToOtherCompaniesAndPlainMembers(): void
    {
        $board = $this->boards()->createBoard($this->owner, $this->workspaceId, 'Secret');
        $stranger = $this->app->signUp('Omar', 'omar@example.com');
        $colleague = $this->app->signUp('Mona', 'mona@example.com');
        $this->app->container->get(WorkspaceRepository::class)->addMember($this->workspaceId, $colleague, WorkspaceRole::Member);

        $this->assertNull($this->gate()->roleOn($stranger, $board->id));
        $this->assertNull($this->gate()->roleOn($colleague, $board->id));
        $this->assertSame([], $this->boards()->boardsInWorkspace($colleague, $this->workspaceId));
        $this->assertThrows(AccessDenied::class, fn () => $this->boards()->boardFor($stranger, $board->publicId));
    }

    public function testWorkspaceVisibleBoardsGiveMembersViewOnly(): void
    {
        $board = $this->boards()->createBoard($this->owner, $this->workspaceId, 'Roadmap', BoardVisibility::Workspace);
        $colleague = $this->app->signUp('Mona', 'mona@example.com');
        $this->app->container->get(WorkspaceRepository::class)->addMember($this->workspaceId, $colleague, WorkspaceRole::Member);

        $this->assertSame(BoardRole::Viewer, $this->gate()->roleOn($colleague, $board->id));
        $this->assertCount(1, $this->boards()->boardsInWorkspace($colleague, $this->workspaceId));
        $this->assertTrue($this->gate()->can($colleague, $board->id, Permission::Comment));
        $this->assertThrows(AccessDenied::class, fn () => $this->boards()->addList($colleague, $board->id, 'Hijack'));
    }

    public function testGuestsSeeOnlyBoardsTheyWereAddedTo(): void
    {
        $shared = $this->boards()->createBoard($this->owner, $this->workspaceId, 'Client board', BoardVisibility::Workspace);
        $this->boards()->createBoard($this->owner, $this->workspaceId, 'Internal', BoardVisibility::Workspace);
        $guest = $this->app->signUp('Client', 'client@example.com');
        $this->app->container->get(WorkspaceRepository::class)->addMember($this->workspaceId, $guest, WorkspaceRole::Guest);
        $this->app->container->get(BoardMembers::class)->add($shared->id, $guest, BoardRole::Member);

        $visible = $this->boards()->boardsInWorkspace($guest, $this->workspaceId);

        $this->assertSame(['Client board'], array_map(static fn ($b) => $b->name, $visible));
        $this->assertSame(BoardRole::Member, $this->gate()->roleOn($guest, $shared->id));
    }

    public function testListsAreAppendedAndMovedWithOneWrite(): void
    {
        $board = $this->boards()->createBoard($this->owner, $this->workspaceId, 'Flow');
        $todo = $this->boards()->addList($this->owner, $board->id, 'To do');
        $doing = $this->boards()->addList($this->owner, $board->id, 'Doing');
        $done = $this->boards()->addList($this->owner, $board->id, 'Done');

        $this->boards()->moveList($this->owner, $board, $done->id, null);
        $this->boards()->moveList($this->owner, $board, $todo->id, $doing->id);

        $names = array_map(static fn ($l) => $l->name, $this->app->container->get(\App\Modules\Board\Contract\BoardDirectory::class)->openLists($board->id));
        $this->assertSame(['Done', 'Doing', 'To do'], $names);
    }

    public function testAListFromAnotherBoardCannotBeTouched(): void
    {
        $mine = $this->boards()->createBoard($this->owner, $this->workspaceId, 'Mine');
        $theirsOwner = $this->app->signUp('Omar', 'omar@example.com');
        $theirWorkspace = $this->app->container->get(WorkspaceDirectory::class)->current($theirsOwner)->id;
        $theirs = $this->boards()->createBoard($theirsOwner, $theirWorkspace, 'Theirs');
        $theirList = $this->boards()->addList($theirsOwner, $theirs->id, 'Private');

        $this->assertThrows(AccessDenied::class, fn () => $this->boards()->renameList($this->owner, $mine, $theirList->id, 'Pwned'));
        $this->assertThrows(AccessDenied::class, fn () => $this->boards()->archiveList($this->owner, $mine, $theirList->id));
    }

    public function testArchivedBoardsAreReadOnlyUntilRestored(): void
    {
        $board = $this->boards()->createBoard($this->owner, $this->workspaceId, 'Old');
        $this->boards()->archiveBoard($this->owner, $board);
        $archived = $this->app->container->get(\App\Modules\Board\Contract\BoardDirectory::class)->findByPublicId($board->publicId);

        $this->assertTrue($archived->archived);
        $this->assertThrows(AccessDenied::class, fn () => $this->boards()->addList($this->owner, $board->id, 'Late'));
        $this->assertCount(1, $this->boards()->boardsInWorkspace($this->owner, $this->workspaceId, archived: true));
    }

    public function testBoardNamesAreValidated(): void
    {
        $this->assertThrows(ValidationException::class, fn () => $this->boards()->createBoard($this->owner, $this->workspaceId, '  '));
        $this->assertThrows(ValidationException::class, fn () => $this->boards()->createBoard($this->owner, $this->workspaceId, 'Ok', BoardVisibility::Private, 'neon'));
    }

    private function boards(): BoardService
    {
        return $this->app->container->get(BoardService::class);
    }

    private function gate(): AccessGate
    {
        return $this->app->container->get(AccessGate::class);
    }
}
