<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Http\HttpException;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Workspace\Application\WorkspaceService;
use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Validation\ValidationException;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class WorkspaceMembersTest extends TestCase
{
    private BoardFixture $f;
    private int $admin;
    private int $member;

    public function setUp(): void
    {
        $this->f = new BoardFixture(new App());
        $this->admin = $this->f->colleague('admin@example.com', WorkspaceRole::Admin);
        $this->member = $this->f->colleague('member@example.com');
    }

    public function testOwnersGiveAnyRoleAndAdminsOnlyMemberOrGuest(): void
    {
        $this->service()->changeRole($this->admin, $this->f->workspaceId, $this->member, 'guest');
        $this->assertSame(WorkspaceRole::Guest, $this->service()->roleOf($this->member, $this->f->workspaceId));

        $this->assertThrows(ValidationException::class, fn () => $this->service()->changeRole($this->admin, $this->f->workspaceId, $this->member, 'admin'));
        $this->assertSame(403, $this->status(fn () => $this->service()->changeRole($this->admin, $this->f->workspaceId, $this->f->owner, 'member')));
        $this->assertSame(403, $this->status(fn () => $this->service()->changeRole($this->member, $this->f->workspaceId, $this->admin, 'guest')));

        $this->service()->changeRole($this->f->owner, $this->f->workspaceId, $this->member, 'admin');
        $this->assertSame(WorkspaceRole::Admin, $this->service()->roleOf($this->member, $this->f->workspaceId));
    }

    public function testNobodyChangesTheirOwnRoleAndTheLastOwnerStays(): void
    {
        $this->assertThrows(ValidationException::class, fn () => $this->service()->changeRole($this->f->owner, $this->f->workspaceId, $this->f->owner, 'member'));
        $this->assertThrows(ValidationException::class, fn () => $this->service()->leave($this->f->owner, $this->f->workspaceId));

        $this->service()->changeRole($this->f->owner, $this->f->workspaceId, $this->admin, 'owner');
        $this->service()->leave($this->f->owner, $this->f->workspaceId);
        $this->assertSame(null, $this->service()->roleOf($this->f->owner, $this->f->workspaceId));
    }

    public function testRemovingSomeoneTakesAwayTheirBoardsToo(): void
    {
        $this->f->app->container->get(BoardMembers::class)->add($this->f->board->id, $this->member, BoardRole::Member);
        $gate = $this->f->app->container->get(AccessGate::class);
        $this->assertSame(BoardRole::Member, $gate->roleOn($this->member, $this->f->board->id));

        $this->service()->removeMember($this->admin, $this->f->workspaceId, $this->member);

        $this->assertSame(null, $this->service()->roleOf($this->member, $this->f->workspaceId));
        $this->assertSame(null, $this->f->app->container->get(BoardMembers::class)->role($this->f->board->id, $this->member));
        $this->assertSame(null, $gate->roleOn($this->member, $this->f->board->id));
    }

    private function status(callable $action): int
    {
        try {
            $action();
        } catch (HttpException $e) {
            return $e->status;
        }

        return 0;
    }

    private function service(): WorkspaceService
    {
        return $this->f->app->container->get(WorkspaceService::class);
    }
}
