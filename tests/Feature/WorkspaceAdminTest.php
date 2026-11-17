<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Access\Contract\AccessGate;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceRole;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

/** Suspending members, the workspace rules (who may invite), and handing over ownership. */
final class WorkspaceAdminTest extends TestCase
{
    public function testASuspendedMemberLosesEveryAccessUntilTurnedBackOn(): void
    {
        $f = new BoardFixture(new App(), BoardVisibility::Workspace);
        $app = $f->app;
        $mona = $f->colleague('mona@example.com');
        $ws = $app->container->get(WorkspaceDirectory::class)->current($f->owner);
        $members = '/workspaces/' . $ws->publicId . '/members';

        $app->loginAs('mona@example.com');
        $this->assertSame(200, $app->get('/boards/' . $f->board->publicId)->status);

        $app->loginAs('sara@example.com');
        $app->post($members . '/' . $mona . '/suspend', ['on' => '1']);
        $app->container->get(AccessGate::class)->forget($mona, $f->board->id);
        $page = $app->page($members);
        $this->assertTrue(str_contains($page, 'Suspended members'));
        $this->assertTrue(str_contains($page, 'Turn back on'));

        $app->loginAs('mona@example.com');
        $this->assertSame(404, $app->get('/boards/' . $f->board->publicId)->status, 'A suspended member sees nothing.');
        $this->assertSame(404, $app->get($members)->status);

        $app->loginAs('sara@example.com');
        $app->post($members . '/' . $mona . '/suspend', ['on' => '0']);
        $app->container->get(AccessGate::class)->forget($mona, $f->board->id);
        $app->loginAs('mona@example.com');
        $this->assertSame(200, $app->get('/boards/' . $f->board->publicId)->status);

        $actions = $app->pdo()->query("SELECT action FROM audit_log WHERE action LIKE 'member.%' ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertTrue(in_array('member.suspended', $actions, true) && in_array('member.reactivated', $actions, true));
    }

    public function testMembersCannotSuspendAndTheLastOwnerCannotBeSuspended(): void
    {
        $f = new BoardFixture(new App());
        $app = $f->app;
        $f->colleague('mona@example.com');
        $admin = $f->colleague('adam@example.com', WorkspaceRole::Admin);
        $ws = $app->container->get(WorkspaceDirectory::class)->current($f->owner);
        $members = '/workspaces/' . $ws->publicId . '/members';

        $app->loginAs('mona@example.com');
        $this->assertFalse(in_array($app->post($members . '/' . $admin . '/suspend', ['on' => '1'])->status, [200, 302], true));

        $app->loginAs('adam@example.com');
        $app->post($members . '/' . $f->owner . '/suspend', ['on' => '1']);
        $this->assertFalse($app->container->get(WorkspaceDirectory::class)->isSuspended($f->owner, $f->workspaceId), 'An admin cannot suspend an owner.');
    }

    public function testOnlyOwnersChangeTheRulesAndMembersInviteWithApproval(): void
    {
        $f = new BoardFixture(new App());
        $app = $f->app;
        $f->colleague('mona@example.com');
        $f->colleague('adam@example.com', WorkspaceRole::Admin);
        $ws = $app->container->get(WorkspaceDirectory::class)->current($f->owner);
        $base = '/workspaces/' . $ws->publicId;

        $app->loginAs('adam@example.com');
        $this->assertSame(403, $app->post($base . '/rules', ['members_invite' => '1'])->status);

        $app->loginAs('sara@example.com');
        $this->assertTrue(str_contains($app->page($base . '/members'), 'Workspace rules'));
        $app->post($base . '/rules', ['members_invite' => '1', 'invite_role' => 'member', 'invite_approval' => '1']);
        $policy = $app->container->get(WorkspaceDirectory::class)->policy($f->workspaceId);
        $this->assertTrue($policy->membersInvite);
        $this->assertSame(WorkspaceRole::Member, $policy->inviteRole);
        $this->assertFalse($policy->publicLinks);

        // Mona asks for "admin": she gets the role the owners chose, and nothing is emailed yet.
        $app->loginAs('mona@example.com');
        $this->assertTrue(str_contains($app->page($base . '/members'), 'Send invitation'));
        $sent = count($app->sentMail());
        $app->post($base . '/invitations', ['email' => 'omar@example.com', 'role' => 'admin', 'board_role' => 'member']);
        $row = $app->pdo()->query("SELECT public_id, role, awaiting_approval FROM invitations WHERE email = 'omar@example.com'")->fetch();
        $this->assertSame('member', (string) $row['role']);
        $this->assertSame(1, (int) $row['awaiting_approval']);
        $this->assertCount($sent, $app->sentMail());
        $this->assertTrue(str_contains($app->page($base . '/members'), 'waiting for approval'));
        $this->assertSame(404, $app->post('/invitations/' . $row['public_id'] . '/approve')->status, 'Only owners and admins approve.');

        $app->loginAs('adam@example.com');
        $this->assertTrue(str_contains($app->page($base . '/members'), 'Approve and send'));
        $app->post('/invitations/' . $row['public_id'] . '/approve', ['back' => $base . '/members']);
        $this->assertSame(0, (int) $app->pdo()->query("SELECT awaiting_approval FROM invitations WHERE email = 'omar@example.com'")->fetchColumn());
        $this->assertTrue($app->linkFromLastEmail('omar@example.com', '/invitations/') !== null);
    }

    public function testMembersCannotInviteWhenTheRulesSayNo(): void
    {
        $f = new BoardFixture(new App());
        $f->colleague('mona@example.com');
        $ws = $f->app->container->get(WorkspaceDirectory::class)->current($f->owner);

        $f->app->loginAs('mona@example.com');
        $this->assertSame(403, $f->app->post('/workspaces/' . $ws->publicId . '/invitations', ['email' => 'x@example.com', 'role' => 'member'])->status);
    }

    public function testOwnershipIsHandedToAnActiveMember(): void
    {
        $f = new BoardFixture(new App());
        $app = $f->app;
        $mona = $f->colleague('mona@example.com');
        $guest = $f->colleague('guest@example.com', WorkspaceRole::Guest);
        $ws = $app->container->get(WorkspaceDirectory::class)->current($f->owner);
        $base = '/workspaces/' . $ws->publicId;

        $app->loginAs('mona@example.com');
        $this->assertSame(403, $app->post($base . '/owner', ['user_id' => (string) $mona])->status);

        $app->loginAs('sara@example.com');
        $app->post($base . '/owner', ['user_id' => (string) $guest]);
        $directory = $app->container->get(WorkspaceDirectory::class);
        $this->assertSame(WorkspaceRole::Guest, $directory->roleOf($guest, $f->workspaceId), 'A guest cannot be made owner.');

        $app->post($base . '/owner', ['user_id' => (string) $mona]);
        $this->assertSame(WorkspaceRole::Owner, $directory->roleOf($mona, $f->workspaceId));
        $this->assertSame(WorkspaceRole::Admin, $directory->roleOf($f->owner, $f->workspaceId));
    }
}
