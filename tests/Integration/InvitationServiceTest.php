<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Http\HttpException;
use App\Mail\Mailer;
use App\Mail\MemoryMailer;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Invitation\Application\InvitationService;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Support\Clock;
use App\Support\FrozenClock;
use App\Validation\ValidationException;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class InvitationServiceTest extends TestCase
{
    private BoardFixture $f;

    public function setUp(): void
    {
        $this->f = new BoardFixture(new App('2026-11-12 10:00:00'));
    }

    public function testAnInvitationIsEmailedWithALinkThatWorksOnceForThatEmail(): void
    {
        [$invitation, $link] = $this->invite('Omar@Example.com', 'member', [$this->f->board->id]);

        $this->assertSame('omar@example.com', $invitation->email);
        $mail = $this->mailer()->sent[0];
        $this->assertSame('omar@example.com', $mail->to);
        $this->assertTrue(str_contains($mail->body, $link));
        $this->assertSame(1, preg_match('#^https://todo\.test/invitations/[A-Za-z0-9_-]{43}$#', $link));
        // Only the hash is stored.
        $this->assertSame(0, (int) $this->f->app->pdo()->query("SELECT COUNT(*) FROM invitations WHERE token_hash = '" . basename($link) . "'")->fetchColumn());

        $omar = $this->f->app->signUp('Omar', 'omar@example.com');
        $other = $this->f->app->signUp('Laila', 'laila@example.com');

        $this->assertThrows(ValidationException::class, fn () => $this->service()->accept($this->user($other), basename($link)));

        $workspace = $this->service()->accept($this->user($omar), basename($link));
        $this->assertSame($this->f->workspaceId, $workspace->id);
        $this->assertSame(WorkspaceRole::Member, $this->directory()->roleOf($omar, $this->f->workspaceId));
        $this->assertSame(BoardRole::Member, $this->f->app->container->get(BoardMembers::class)->role($this->f->board->id, $omar));
        $this->assertSame($this->f->workspaceId, $this->directory()->current($omar)->id);

        // Used once: a second click fails and changes nothing.
        $this->assertThrows(ValidationException::class, fn () => $this->service()->accept($this->user($omar), basename($link)));
    }

    public function testExpiredRevokedAndResentLinksStopWorking(): void
    {
        [$first, $oldLink] = $this->invite('omar@example.com');
        $newLink = $this->service()->resend($this->f->owner, $first->publicId);
        $omar = $this->f->app->signUp('Omar', 'omar@example.com');

        $this->assertThrows(ValidationException::class, fn () => $this->service()->accept($this->user($omar), basename($oldLink)));

        $clock = $this->f->app->container->get(Clock::class);
        assert($clock instanceof FrozenClock);
        $clock->travel('+7 days +1 second');
        $this->assertSame('expired', $this->service()->lookup(basename($newLink))['state']);
        $this->assertThrows(ValidationException::class, fn () => $this->service()->accept($this->user($omar), basename($newLink)));

        [$second, $link] = $this->invite('laila@example.com');
        $this->service()->revoke($this->f->owner, $second->publicId);
        $this->assertSame('revoked', $this->service()->lookup(basename($link))['state']);
        $this->assertSame([], array_values(array_filter(
            $this->service()->openInvitations($this->f->owner, $this->f->workspaceId),
            static fn ($i) => $i->email === 'laila@example.com',
        )));
    }

    public function testAnInvitationCannotGiveMoreThanTheInviterMay(): void
    {
        $admin = $this->f->colleague('admin@example.com', WorkspaceRole::Admin);
        $member = $this->f->colleague('member@example.com');
        $elsewhere = $this->f->app->signUp('Laila', 'laila@example.com');
        $otherWorkspace = $this->directory()->current($elsewhere)->id;
        $otherBoard = $this->f->app->container->get(\App\Modules\Board\Application\BoardService::class)
            ->createBoard($elsewhere, $otherWorkspace, 'Secret');

        $this->assertSame(403, $this->status(fn () => $this->invite('x@example.com', 'member', [], $member)));
        $this->assertSame('role', $this->firstError(fn () => $this->invite('x@example.com', 'admin', [], $admin)));
        $this->assertSame('role', $this->firstError(fn () => $this->invite('x@example.com', 'owner')));
        $this->assertSame('boards', $this->firstError(fn () => $this->invite('x@example.com', 'guest')));
        $this->assertSame('boards', $this->firstError(fn () => $this->invite('x@example.com', 'member', [$otherBoard->id])));
        $this->assertSame('email', $this->firstError(fn () => $this->invite('member@example.com')));

        $this->invite('x@example.com');
        $this->assertSame('email', $this->firstError(fn () => $this->invite('X@example.com')));
    }

    public function testAGuestSeesOnlyTheInvitedBoardAndAnExistingRoleIsNeverLowered(): void
    {
        $boards = $this->f->app->container->get(\App\Modules\Board\Application\BoardService::class);
        $other = $boards->createBoard($this->f->owner, $this->f->workspaceId, 'Other', \App\Modules\Board\Contract\BoardVisibility::Workspace);

        [, $link] = $this->invite('guest@example.com', 'guest', [$this->f->board->id], null, 'viewer');
        $guest = $this->f->app->signUp('Guest', 'guest@example.com');
        $this->service()->accept($this->user($guest), basename($link));

        $gate = $this->f->app->container->get(AccessGate::class);
        $this->assertSame(WorkspaceRole::Guest, $this->directory()->roleOf($guest, $this->f->workspaceId));
        $this->assertSame(BoardRole::Viewer, $gate->roleOn($guest, $this->f->board->id));
        $this->assertSame(null, $gate->roleOn($guest, $other->id));

        // A second invitation to the same board as a viewer doesn't take a manager role away.
        $this->f->app->container->get(BoardMembers::class)->add($other->id, $guest, BoardRole::Manager);
        [, $again] = $this->invite('guest@example.com', 'guest', [$other->id], null, 'viewer');
        $this->service()->accept($this->user($guest), basename($again));
        $this->assertSame(BoardRole::Manager, $this->f->app->container->get(BoardMembers::class)->role($other->id, $guest));
    }

    public function testSendingIsRateLimited(): void
    {
        for ($i = 0; $i < InvitationService::HOURLY_LIMIT; $i++) {
            $this->invite("person$i@example.com");
        }

        $this->assertSame('email', $this->firstError(fn () => $this->invite('one-more@example.com')));
    }

    /**
     * @param list<int> $boards
     * @return array{0: \App\Modules\Invitation\Contract\Invitation, 1: string}
     */
    private function invite(string $email, string $role = 'member', array $boards = [], ?int $actor = null, string $boardRole = 'member'): array
    {
        return $this->service()->invite($actor ?? $this->f->owner, $this->f->workspaceId, [
            'email' => $email, 'role' => $role, 'board_role' => $boardRole, 'boards' => array_map('strval', $boards),
        ]);
    }

    private function firstError(callable $action): string
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return (string) array_key_first($e->errors());
        }

        return '';
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

    private function user(int $id): \App\Modules\Identity\Contract\User
    {
        return $this->f->app->container->get(UserDirectory::class)->find($id);
    }

    private function mailer(): MemoryMailer
    {
        $mailer = $this->f->app->container->get(Mailer::class);
        assert($mailer instanceof MemoryMailer);

        return $mailer;
    }

    private function directory(): WorkspaceDirectory
    {
        return $this->f->app->container->get(WorkspaceDirectory::class);
    }

    private function service(): InvitationService
    {
        return $this->f->app->container->get(InvitationService::class);
    }
}
