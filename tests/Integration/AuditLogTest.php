<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Audit\Domain\AuditLog;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Invitation\Application\InvitationService;
use App\Modules\Workspace\Application\WorkspaceService;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceRole;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class AuditLogTest extends TestCase
{
    public function testInvitationsAndRoleChangesAreRecordedForAdminsOnly(): void
    {
        $f = new BoardFixture(new App());
        $invitations = $f->app->container->get(InvitationService::class);

        [$invitation, $link] = $invitations->invite($f->owner, $f->workspaceId, ['email' => 'omar@example.com', 'role' => 'member', 'boards' => []]);
        $omar = $f->app->signUp('Omar', 'omar@example.com');
        $invitations->accept($f->app->container->get(UserDirectory::class)->find($omar), basename($link));
        $f->app->container->get(WorkspaceService::class)->changeRole($f->owner, $f->workspaceId, $omar, 'admin');
        $f->app->container->get(WorkspaceService::class)->removeMember($f->owner, $f->workspaceId, $omar);

        $actions = array_map(static fn ($e) => $e->action . ' ' . $e->target, $f->app->container->get(AuditLog::class)->forWorkspace($f->workspaceId, null, 10));
        $this->assertSame([
            'member.removed omar@example.com',
            'member.role_changed omar@example.com',
            'invitation.accepted omar@example.com',
            'member.joined omar@example.com',
            'invitation.sent omar@example.com',
        ], $actions);

        $workspace = $f->app->container->get(WorkspaceDirectory::class)->current($f->owner);
        $f->app->loginAs('sara@example.com');
        $this->assertTrue(str_contains($f->app->page('/workspaces/' . $workspace->publicId . '/audit'), 'omar@example.com'));

        $f->colleague('member@example.com', WorkspaceRole::Member);
        $f->app->loginAs('member@example.com');
        $this->assertSame(403, $f->app->get('/workspaces/' . $workspace->publicId . '/audit')->status);
    }
}
