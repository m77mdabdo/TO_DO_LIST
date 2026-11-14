<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Automation\Application\AutomationEngine;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Checklist\Application\ChecklistService;
use App\Modules\Discussion\Application\DiscussionService;
use App\Modules\Label\Contract\LabelCatalog;
use App\Support\Clock;
use App\Support\FrozenClock;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class AutomationTest extends TestCase
{
    private BoardFixture $f;

    public function setUp(): void
    {
        // The test clock is 2026-01-20 10:00.
        $this->f = new BoardFixture(new App(), BoardVisibility::Private);
    }

    public function testARuleRunsWhenACardIsMovedIntoAList(): void
    {
        $urgent = $this->labels()->ensureLabel($this->f->owner, $this->f->board->id, 'Shipped', 'green');
        $this->save(['kind' => 'rule', 'trigger' => 'card.moved:' . $this->f->done->id, 'actions' => [
            ['do' => 'card.complete'],
            ['do' => 'label.add:' . $urgent->id],
            ['do' => 'comment.post', 'text' => 'Nice work!'],
        ]]);

        $card = $this->card('Write the launch post');
        $this->cards()->move($this->f->owner, $card, $this->f->done->id, null);

        $card = $this->reload($card);
        $this->assertTrue($card->isComplete());
        $this->assertSame(['Shipped'], array_map(static fn ($l) => $l->name, $this->labels()->forCards([$card->id])[$card->id] ?? []));
        $this->assertSame('Nice work!', $this->app()->container->get(DiscussionService::class)->comments($card->id)[0]->body);

        $page = $this->f->app->page('/boards/' . $this->f->board->publicId . '/automation');
        $this->assertTrue(str_contains($page, 'When a card is moved into “Done”: mark the card complete, then add the label “Shipped”, then post a comment: “Nice work!”.'));
        $this->assertSame(1, substr_count($page, 'class="run-ok"'));

        // Moving it somewhere else does nothing.
        $other = $this->card('Plan the webinar');
        $this->cards()->move($this->f->owner, $other, $this->f->doing->id, null);
        $this->assertFalse($this->reload($other)->isComplete());
    }

    public function testConditionsDecideAndTheTriggeringPersonCanBeAssigned(): void
    {
        $this->save(['kind' => 'rule', 'trigger' => 'card.created:' . $this->f->todo->id, 'conditions' => ['no_due'], 'actions' => [
            ['do' => 'card.assign:trigger'],
            ['do' => 'card.due_in:3'],
        ]]);

        $plain = $this->card('No date yet');
        $dated = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Has a date', ['due_date' => '2026-03-01']);
        $elsewhere = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->doing->id, 'In doing');

        $this->assertSame('2026-01-23', $this->reload($plain)->dueDate());
        $this->assertSame([$this->f->owner], $this->cards()->membersOf($plain));
        $this->assertSame('2026-03-01', $this->reload($dated)->dueDate());
        $this->assertSame([], $this->cards()->membersOf($dated));
        $this->assertNull($this->reload($elsewhere)->dueDate());
    }

    public function testRulesThatSetEachOtherOffStop(): void
    {
        $this->save(['kind' => 'rule', 'trigger' => 'card.moved:' . $this->f->doing->id, 'actions' => [['do' => 'card.move:' . $this->f->done->id]]]);
        $this->save(['kind' => 'rule', 'trigger' => 'card.moved:' . $this->f->done->id, 'actions' => [['do' => 'card.move:' . $this->f->doing->id]]]);

        $card = $this->card('Ping pong');
        $this->cards()->move($this->f->owner, $card, $this->f->doing->id, null);

        $statuses = $this->f->app->pdo()->query('SELECT status, detail FROM automation_runs ORDER BY id')->fetchAll();
        $this->assertTrue(count($statuses) < 10, 'The chain stopped quickly.');
        $this->assertTrue(in_array('skipped', array_column($statuses, 'status'), true));
        $this->assertTrue(str_contains(implode(' ', array_column($statuses, 'detail')), 'avoid a loop'));
    }

    public function testAFailingRuleUndoesItsOwnChangesButNotTheUsers(): void
    {
        $gone = $this->boards()->addList($this->f->owner, $this->f->board->id, 'Old');
        $this->save(['kind' => 'rule', 'trigger' => 'card.moved:' . $this->f->done->id, 'actions' => [
            ['do' => 'card.complete'],
            ['do' => 'card.move:' . $gone->id],
        ]]);
        $this->boards()->archiveList($this->f->owner, $this->f->board, $gone->id);

        $card = $this->card('Fragile');
        $this->cards()->move($this->f->owner, $card, $this->f->done->id, null);

        $card = $this->reload($card);
        $this->assertSame($this->f->done->id, $card->listId, "The person's own move stays.");
        $this->assertFalse($card->isComplete(), "The rule's first step was undone with the rest.");
        $run = $this->f->app->pdo()->query('SELECT status, detail FROM automation_runs')->fetch();
        $this->assertSame('failed', $run['status']);
        $this->assertTrue(str_contains($run['detail'], 'That list is not available.'));

        // Five failures in a row pause it.
        for ($i = 0; $i < 4; $i++) {
            $this->cards()->move($this->f->owner, $this->reload($card), $this->f->doing->id, null);
            $this->cards()->move($this->f->owner, $this->reload($card), $this->f->done->id, null);
        }
        $this->assertSame(0, (int) $this->f->app->pdo()->query('SELECT enabled FROM automations')->fetchColumn());
        $this->assertTrue(str_contains($this->f->app->page('/boards/' . $this->f->board->publicId . '/automation'), 'Paused after 5 failures in a row.'));
    }

    public function testCardButtonsMoveTheCardAndTellTheManagers(): void
    {
        $this->save(['kind' => 'card_button', 'name' => 'Send for review', 'actions' => [
            ['do' => 'card.move:' . $this->f->doing->id],
            ['do' => 'notify.managers', 'text' => 'Please review this.'],
        ]]);
        $button = $this->f->app->pdo()->query('SELECT public_id FROM automations')->fetchColumn();
        $card = $this->card('Pricing page');

        $mona = $this->f->colleague('mona@example.com');
        $this->members()->add($this->f->board->id, $mona, BoardRole::Member);
        $this->f->app->loginAs('mona@example.com');
        $this->assertTrue(str_contains($this->f->app->page('/cards/' . $card->publicId), 'Send for review'));

        $this->assertSame(302, $this->f->app->post('/cards/' . $card->publicId . '/buttons/' . $button)->status);
        $this->assertSame($this->f->doing->id, $this->reload($card)->listId);
        $notice = $this->f->app->pdo()->query("SELECT user_id, data FROM notifications WHERE type = 'automation.notice'")->fetch();
        $this->assertSame($this->f->owner, (int) $notice['user_id']);
        $this->assertTrue(str_contains($notice['data'], 'Please review this.'));

        // Viewers can't press buttons, and nobody else can see them.
        $viewer = $this->f->colleague('vera@example.com');
        $this->members()->add($this->f->board->id, $viewer, BoardRole::Viewer);
        $this->f->app->loginAs('vera@example.com');
        $this->assertFalse(str_contains($this->f->app->page('/cards/' . $card->publicId), 'Send for review'));
        $this->assertSame(403, $this->f->app->post('/cards/' . $card->publicId . '/buttons/' . $button)->status);
        $this->assertSame(403, $this->f->app->get('/boards/' . $this->f->board->publicId . '/automation')->status, 'Only managers set automations up.');
        $this->f->app->signUp('Eve', 'eve@example.com');
        $this->assertSame(404, $this->f->app->post('/cards/' . $card->publicId . '/buttons/' . $button)->status);
    }

    public function testBoardButtonsWorkOnTheCardsThatMatch(): void
    {
        $this->save(['kind' => 'board_button', 'name' => 'Clean up Done', 'scope' => (string) $this->f->done->id, 'conditions' => ['complete'], 'actions' => [['do' => 'card.archive']]]);
        $button = $this->f->app->pdo()->query('SELECT public_id FROM automations')->fetchColumn();

        $finished = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->done->id, 'Finished');
        $this->cards()->setComplete($this->f->owner, $finished, true);
        $open = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->done->id, 'Not quite');
        $elsewhere = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Elsewhere');
        $this->cards()->setComplete($this->f->owner, $elsewhere, true);

        $this->assertTrue(str_contains($this->f->app->page('/boards/' . $this->f->board->publicId), 'Clean up Done'));
        $this->f->app->post('/automations/' . $button . '/run');

        $this->assertTrue($this->reload($finished)->archived);
        $this->assertFalse($this->reload($open)->archived);
        $this->assertFalse($this->reload($elsewhere)->archived);
    }

    public function testSchedulesRunOncePerTimeAndNeverForTimesBeforeTheyExisted(): void
    {
        $this->save(['kind' => 'schedule', 'every' => 'day', 'hour' => '9', 'scope' => (string) $this->f->todo->id, 'actions' => [['do' => 'list.sort_due']]]);
        $later = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Later', ['due_date' => '2026-02-10']);
        $sooner = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Sooner', ['due_date' => '2026-01-25']);
        $this->card('Someday');

        $this->assertSame(0, $this->engine()->tick(), "Today's 09:00 was before it was made.");

        $this->clock()->travel('+1 day');
        $this->assertSame(1, $this->engine()->tick());
        $this->assertSame(0, $this->engine()->tick(), 'Once per time.');
        $titles = array_map(static fn ($c) => $c->title, $this->app()->container->get(CardQueries::class)->openCardsByList($this->f->board->id)[$this->f->todo->id]);
        $this->assertSame(['Sooner', 'Later', 'Someday'], $titles);
        unset($later, $sooner);
    }

    public function testDueDateAutomationsRunOnTheRightDayOncePerCard(): void
    {
        $label = $this->labels()->ensureLabel($this->f->owner, $this->f->board->id, 'Due tomorrow', 'orange');
        $this->save(['kind' => 'due', 'when' => 'before', 'days' => '1', 'actions' => [['do' => 'label.add:' . $label->id], ['do' => 'notify.members', 'text' => 'Due tomorrow']]]);
        $card = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Report', ['due_date' => '2026-01-22']);
        $done = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Already done', ['due_date' => '2026-01-22']);
        $this->cards()->setComplete($this->f->owner, $done, true);

        $this->assertSame(0, $this->engine()->tick());
        $this->clock()->travel('+1 day');
        $this->assertSame(1, $this->engine()->tick(), 'Only the unfinished card.');
        $this->assertSame(0, $this->engine()->tick());
        $this->assertSame(['Due tomorrow'], array_map(static fn ($l) => $l->name, $this->labels()->forCards([$card->id])[$card->id] ?? []));
    }

    public function testChecklistsAndBadInputAreHandled(): void
    {
        $this->save(['kind' => 'rule', 'trigger' => 'checklist.done', 'actions' => [['do' => 'card.move:' . $this->f->done->id]]]);
        $this->save(['kind' => 'card_button', 'name' => 'Add steps', 'actions' => [['do' => 'checklist.add', 'text' => 'Steps | Draft | Review']]]);
        $button = $this->f->app->pdo()->query("SELECT public_id FROM automations WHERE kind = 'card_button'")->fetchColumn();
        $card = $this->card('Article');

        $this->f->app->post('/cards/' . $card->publicId . '/buttons/' . $button);
        $checklists = $this->app()->container->get(ChecklistService::class);
        $items = $checklists->forCard($card->id)[0]->items;
        $this->assertSame(['Draft', 'Review'], array_map(static fn ($i) => $i->text, $items));

        foreach ($items as $item) {
            $checklists->toggleItem($this->f->owner, $item->id);
        }
        $this->assertSame($this->f->done->id, $this->reload($card)->listId);

        // A list from another board, an action without its text, a button without a name.
        $other = (new BoardFixture($this->f->app, BoardVisibility::Private, 'sam@example.com'));
        $this->f->app->loginAs('sara@example.com');
        $before = (int) $this->f->app->pdo()->query('SELECT COUNT(*) FROM automations')->fetchColumn();
        $this->save(['kind' => 'rule', 'trigger' => 'card.moved:' . $other->done->id, 'actions' => [['do' => 'card.complete']]]);
        $this->save(['kind' => 'rule', 'trigger' => 'card.completed', 'actions' => [['do' => 'card.move:' . $other->done->id]]]);
        $this->save(['kind' => 'rule', 'trigger' => 'card.completed', 'actions' => [['do' => 'comment.post', 'text' => '']]]);
        $this->save(['kind' => 'card_button', 'name' => '', 'actions' => [['do' => 'card.complete']]]);
        $this->save(['kind' => 'card_button', 'name' => 'Sort', 'actions' => [['do' => 'list.sort_due']]]);
        $this->assertSame($before, (int) $this->f->app->pdo()->query('SELECT COUNT(*) FROM automations')->fetchColumn());
    }

    /** @param array<string, mixed> $input */
    private function save(array $input): void
    {
        $this->f->app->post('/boards/' . $this->f->board->publicId . '/automation', $input);
    }

    private function card(string $title): CardSummary
    {
        return $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, $title);
    }

    private function reload(CardSummary $card): CardSummary
    {
        return $this->app()->container->get(CardQueries::class)->find($card->id);
    }

    private function app(): App
    {
        return $this->f->app;
    }

    private function cards(): CardService
    {
        return $this->f->app->container->get(CardService::class);
    }

    private function boards(): BoardService
    {
        return $this->f->app->container->get(BoardService::class);
    }

    private function labels(): LabelCatalog
    {
        return $this->f->app->container->get(LabelCatalog::class);
    }

    private function members(): BoardMembers
    {
        return $this->f->app->container->get(BoardMembers::class);
    }

    private function engine(): AutomationEngine
    {
        return $this->f->app->container->get(AutomationEngine::class);
    }

    private function clock(): FrozenClock
    {
        /** @var FrozenClock $clock */
        $clock = $this->f->app->container->get(Clock::class);

        return $clock;
    }
}
