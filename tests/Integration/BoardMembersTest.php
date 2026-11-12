<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Access\Application\BoardMemberService;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Activity\Contract\ActivityFeed;
use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Validation\ValidationException;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class BoardMembersTest extends TestCase
{
    private BoardFixture $f;

    public function setUp(): void
    {
        $this->f = new BoardFixture(new App());
    }

    public function testManagersAddChangeAndRemoveWorkspacePeople(): void
    {
        $mona = $this->f->colleague('mona@example.com');
        $gate = $this->f->app->container->get(AccessGate::class);

        $this->service()->set($this->f->owner, $this->f->board, $mona, 'member');
        $this->assertSame(BoardRole::Member, $gate->roleOn($mona, $this->f->board->id));

        $this->service()->set($this->f->owner, $this->f->board, $mona, 'viewer');
        $this->assertSame(BoardRole::Viewer, $gate->roleOn($mona, $this->f->board->id));

        $this->service()->remove($this->f->owner, $this->f->board, $mona);
        $this->assertSame(null, $gate->roleOn($mona, $this->f->board->id));

        $types = array_map(static fn ($e) => $e->type, $this->f->app->container->get(ActivityFeed::class)->forBoard($this->f->board->id, null, 3));
        $this->assertSame(['board.member_removed', 'board.member_role', 'board.member_added'], $types);
    }

    public function testOnlyManagersChangeMembersAndOutsidersOrGuestManagersAreRefused(): void
    {
        $mona = $this->f->colleague('mona@example.com');
        $guest = $this->f->colleague('guest@example.com', WorkspaceRole::Guest);
        $stranger = $this->f->app->signUp('Laila', 'laila@example.com');

        $this->service()->set($this->f->owner, $this->f->board, $mona, 'member');
        $this->assertThrows(AccessDenied::class, fn () => $this->service()->set($mona, $this->f->board, $guest, 'viewer'));
        $this->assertThrows(ValidationException::class, fn () => $this->service()->set($this->f->owner, $this->f->board, $stranger, 'viewer'));
        $this->assertThrows(ValidationException::class, fn () => $this->service()->set($this->f->owner, $this->f->board, $guest, 'manager'));
        $this->assertThrows(ValidationException::class, fn () => $this->service()->set($this->f->owner, $this->f->board, $this->f->owner, 'viewer'));
    }

    public function testTheMembersPageListsPeopleAndHidesFormsFromNonManagers(): void
    {
        $mona = $this->f->colleague('mona@example.com');
        $this->service()->set($this->f->owner, $this->f->board, $mona, 'viewer');
        $this->f->colleague('omar@example.com');
        $path = '/boards/' . $this->f->board->publicId . '/members';

        $this->f->app->loginAs('sara@example.com');
        $this->assertTrue(str_contains($this->f->app->page($path), 'Add someone from the workspace'));

        $this->f->app->loginAs('mona@example.com');
        $page = $this->f->app->page($path);
        $this->assertTrue(str_contains($page, 'Mona'));
        $this->assertFalse(str_contains($page, 'Add someone from the workspace'));
        $this->assertSame(403, $this->f->app->post($path, ['user_id' => (string) $mona, 'role' => 'manager'])->status);
    }

    private function service(): BoardMemberService
    {
        return $this->f->app->container->get(BoardMemberService::class);
    }
}
