<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\Mailer;
use App\Mail\MemoryMailer;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceRole;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class InvitationFlowTest extends TestCase
{
    public function testSomeoneWithoutAnAccountFollowsTheLinkSignsUpAndJoins(): void
    {
        $f = new BoardFixture(new App());
        $app = $f->app;
        $workspace = $app->container->get(WorkspaceDirectory::class)->current($f->owner);
        $members = '/workspaces/' . $workspace->publicId . '/members';

        $app->loginAs('sara@example.com');
        $app->post('/workspaces/' . $workspace->publicId . '/invitations', [
            'email' => 'omar@example.com', 'role' => 'member', 'board_role' => 'member', 'boards' => [(string) $f->board->id],
        ]);
        $page = $app->page($members);
        $this->assertTrue(str_contains($page, 'omar@example.com'));
        $this->assertTrue(str_contains($page, 'https://todo.test/invitations/'), 'The new link is shown once.');
        $this->assertFalse(str_contains($app->page($members), 'https://todo.test/invitations/'));

        $mailer = $app->container->get(Mailer::class);
        assert($mailer instanceof MemoryMailer);
        preg_match('#/invitations/[A-Za-z0-9_-]{43}#', $mailer->sent[0]->body, $m);
        $path = $m[0];

        // Signed out: the page explains, then sign-up brings them back to it.
        $app->post('/logout');
        $landing = $app->page($path);
        $this->assertTrue(str_contains($landing, 'Create an account'));
        $this->assertFalse(str_contains($landing, ':company'), 'The company name is filled in.');
        $register = $app->post('/register', ['name' => 'Omar', 'email' => 'omar@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123']);
        $this->assertSame('/' . ltrim($path, '/'), $register->header('Location'));

        $app->post($path . '/accept');
        $omar = (int) $app->session->get('user_id');
        $this->assertSame(WorkspaceRole::Member, $app->container->get(WorkspaceDirectory::class)->roleOf($omar, $f->workspaceId));
        $this->assertTrue(str_contains($app->page('/boards/' . $f->board->publicId), 'Launch'));

        $this->assertTrue(str_contains($app->page($path), 'no longer valid'));
    }

    public function testAnUnknownLinkShowsTheSamePageAsAnExpiredOne(): void
    {
        $app = new App();

        $this->assertTrue(str_contains($app->page('/invitations/' . str_repeat('a', 43)), 'no longer valid'));
        $this->assertSame(404, $app->get('/invitations/short')->status);
    }

    public function testMembersCannotInviteOrSeeInvitations(): void
    {
        $f = new BoardFixture(new App());
        $f->colleague('member@example.com');
        $workspace = $f->app->container->get(WorkspaceDirectory::class)->current($f->owner);

        $f->app->loginAs('member@example.com');
        $response = $f->app->post('/workspaces/' . $workspace->publicId . '/invitations', ['email' => 'x@example.com', 'role' => 'member']);

        $this->assertSame(403, $response->status);
        $this->assertFalse(str_contains($f->app->page('/workspaces/' . $workspace->publicId . '/members'), 'Send invitation'));
    }
}
