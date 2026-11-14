<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Mail\Mailer;
use App\Mail\MemoryMailer;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Discussion\Application\DiscussionService;
use App\Modules\Notification\Application\DueReminders;
use App\Modules\Notification\Application\NotificationCenter;
use App\Support\Clock;
use App\Support\FrozenClock;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class NotificationTest extends TestCase
{
    private BoardFixture $f;
    private CardSummary $card;
    private int $mona;
    private int $omar;

    public function setUp(): void
    {
        $this->f = new BoardFixture(new App('2026-11-12 10:00:00'));
        $this->card = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Launch plan');
        $this->mona = $this->f->app->signUp('Mona Hassan', 'mona@example.com');
        $this->omar = $this->f->colleague('omar@example.com');
        $this->f->app->container->get(\App\Modules\Workspace\Domain\WorkspaceRepository::class)->addMember($this->f->workspaceId, $this->mona, \App\Modules\Workspace\Contract\WorkspaceRole::Member);
        $members = $this->f->app->container->get(BoardMembers::class);
        $members->add($this->f->board->id, $this->mona, BoardRole::Member);
        $members->add($this->f->board->id, $this->omar, BoardRole::Member);
    }

    public function testAssigningSomeoneNotifiesAndEmailsThemAndTheyWatchTheCard(): void
    {
        $this->cards()->toggleMember($this->f->owner, $this->card, $this->mona);

        $bell = $this->center()->latest($this->mona);
        $this->assertCount(1, $bell);
        $this->assertSame('card.assigned', $bell[0]->type);
        $this->assertSame('/cards/' . $this->card->publicId, $bell[0]->link);
        $this->assertTrue($this->center()->isWatchingCard($this->mona, $this->card->id));
        $this->assertSame('mona@example.com', $this->mailer()->sent[0]->to);
        $this->assertTrue(str_contains($this->mailer()->sent[0]->body, 'https://todo.test/cards/' . $this->card->publicId));

        // Nobody is notified about their own actions.
        $this->assertSame([], $this->center()->latest($this->f->owner));
    }

    public function testAMentionedWatcherGetsOneNotificationAndOtherWatchersGetTheComment(): void
    {
        $this->center()->toggleCard($this->mona, $this->card);
        $this->center()->toggleBoard($this->omar, $this->f->board);

        $this->discussion()->post($this->f->owner, $this->card, 'Can @Mona Hassan check the dates?');

        $this->assertSame(['comment.mentioned'], array_map(static fn ($n) => $n->type, $this->center()->latest($this->mona)));
        $this->assertSame(['comment.posted'], array_map(static fn ($n) => $n->type, $this->center()->latest($this->omar)));
        // The card's creator watches it too, but wrote the comment.
        $this->assertSame([], $this->center()->latest($this->f->owner));
        // A mention is emailed, a plain comment is not.
        $this->assertSame(['mona@example.com'], array_map(static fn ($m) => $m->to, $this->mailer()->sent));
    }

    public function testEmailCanBeTurnedOffAndReadStateIsKept(): void
    {
        $this->center()->setEmailEnabled($this->mona, false);
        $this->cards()->toggleMember($this->f->owner, $this->card, $this->mona);

        $this->assertSame([], $this->mailer()->sent);
        $this->assertSame(1, $this->center()->unreadCount($this->mona));

        $link = $this->center()->open($this->mona, $this->center()->latest($this->mona)[0]->id);
        $this->assertSame('/cards/' . $this->card->publicId, $link);
        $this->assertSame(0, $this->center()->unreadCount($this->mona));
    }

    public function testPeopleWhoLostAccessHearNothing(): void
    {
        $this->center()->toggleBoard($this->omar, $this->f->board);
        $this->f->app->container->get(BoardMembers::class)->remove($this->f->board->id, $this->omar);
        $this->f->app->container->get(\App\Modules\Access\Application\Gate::class)->forget($this->omar, $this->f->board->id);

        $this->discussion()->post($this->f->owner, $this->card, 'Private update');

        $this->assertSame([], $this->center()->latest($this->omar));
    }

    public function testDueRemindersGoOutOncePerDueDate(): void
    {
        $this->cards()->toggleMember($this->f->owner, $this->card, $this->mona);
        $this->cards()->update($this->f->owner, $this->card, ['title' => 'Launch plan', 'due_date' => '2026-11-13']);
        $reminders = $this->f->app->container->get(DueReminders::class);

        $this->assertTrue($reminders->run() >= 1);
        $this->assertSame(0, $reminders->run());
        $types = array_map(static fn ($n) => $n->type, $this->center()->latest($this->mona));
        $this->assertTrue(in_array('card.due_soon', $types, true));

        $clock = $this->f->app->container->get(Clock::class);
        assert($clock instanceof FrozenClock);
        $clock->travel('+3 days');
        $reminders->run();
        $this->assertSame('card.overdue', $this->center()->latest($this->mona)[0]->type);
    }

    public function testTheBellAndTheNotificationsPage(): void
    {
        $this->cards()->toggleMember($this->f->owner, $this->card, $this->mona);
        $this->f->app->loginAs('mona@example.com');

        $board = $this->f->app->page('/boards/' . $this->f->board->publicId);
        $this->assertTrue(str_contains($board, '<span class="bell-count" aria-hidden="true">1</span>'));
        $this->assertTrue(str_contains($board, '/boards/' . $this->f->board->publicId . '/watch'));

        $page = $this->f->app->page('/notifications');
        $this->assertTrue(str_contains($page, 'added you to'));
        $this->assertTrue(str_contains($page, 'Launch plan'));

        $id = $this->center()->latest($this->mona)[0]->id;
        $this->assertSame(404, $this->f->app->post('/notifications/' . ($id + 1000) . '/open')->status);
        $open = $this->f->app->post('/notifications/' . $id . '/open');
        $this->assertSame('/cards/' . $this->card->publicId, $open->header('Location'));
    }

    private function cards(): CardService
    {
        return $this->f->app->container->get(CardService::class);
    }

    private function discussion(): DiscussionService
    {
        return $this->f->app->container->get(DiscussionService::class);
    }

    private function center(): NotificationCenter
    {
        return $this->f->app->container->get(NotificationCenter::class);
    }

    private function mailer(): MemoryMailer
    {
        $mailer = $this->f->app->container->get(Mailer::class);
        assert($mailer instanceof MemoryMailer);

        return $mailer;
    }
}
