<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Access\Application\Gate;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Application\RecurringCards;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Checklist\Application\ChecklistService;
use App\Modules\Notification\Application\DueReminders;
use App\Modules\Notification\Application\NotificationCenter;
use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Support\Clock;
use App\Support\FrozenClock;
use App\Validation\ValidationException;
use PDO;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

/** Batch 5: checklists, repeating dates, reminders, scheduled copies, collapsed and sorted lists, board groups. */
final class ChecklistsAndRepeatsTest extends TestCase
{
    private BoardFixture $f;
    private CardSummary $card;

    public function setUp(): void
    {
        // The test clock: Tuesday 2026-01-20 10:00.
        $this->f = new BoardFixture(new App());
        $this->card = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Launch post');
    }

    /* ---------- checklists ---------- */

    public function testPastingSeveralLinesAddsOneItemEachOrNothing(): void
    {
        $id = $this->checklists()->addChecklist($this->f->owner, $this->card, 'Steps');
        $this->f->app->post('/checklists/' . $id . '/items', ['text' => "Write draft\r\n\n  Review  \nPublish\n"]);
        $this->assertSame(['Write draft', 'Review', 'Publish'], $this->itemTexts());

        $this->assertInvalid(fn () => $this->checklists()->addItem($this->f->owner, $id, "Fine\n" . str_repeat('x', 501)), 'text');
        $this->assertCount(3, $this->itemTexts(), 'One bad line adds nothing.');

        $this->assertInvalid(fn () => $this->checklists()->addItem($this->f->owner, $id, implode("\n", array_fill(0, 198, 'More'))), 'text');
        $this->assertCount(3, $this->itemTexts(), 'Lines that would pass 200 items add nothing.');
    }

    public function testCopyingAChecklistFromAnotherCardOfTheBoard(): void
    {
        $other = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Template-ish');
        $source = $this->checklists()->addChecklist($this->f->owner, $other, 'Release steps');
        $this->checklists()->addItem($this->f->owner, $source, "Tag\nDeploy");
        $items = $this->checklists()->forCard($other->id)[0]->items;
        $this->checklists()->toggleItem($this->f->owner, $items[0]->id);
        $this->checklists()->updateItem($this->f->owner, $items[1]->id, ['text' => 'Deploy', 'assignee_id' => (string) $this->f->owner, 'due_date' => '2026-02-01']);

        $page = $this->f->app->page('/cards/' . $this->card->publicId);
        $this->assertTrue(str_contains($page, 'Template-ish › Release steps'));

        $this->f->app->post('/cards/' . $this->card->publicId . '/checklists/copy', ['from' => (string) $source]);
        $copy = $this->checklists()->forCard($this->card->id)[0];
        $this->assertSame('Release steps', $copy->title);
        $this->assertSame(['Tag', 'Deploy'], array_map(static fn ($i) => $i->text, $copy->items));
        $this->assertFalse($copy->items[0]->isDone(), 'Copies start unticked.');
        $this->assertSame([$this->f->owner, '2026-02-01'], [$copy->items[1]->assigneeId, $copy->items[1]->dueDate()]);

        // Not from another board, even one the person can see.
        $boards = $this->f->app->container->get(BoardService::class);
        $elsewhere = $boards->createBoard($this->f->owner, $this->f->workspaceId, 'Elsewhere');
        $list = $boards->addList($this->f->owner, $elsewhere->id, 'Inbox');
        $far = $this->cards()->createCard($this->f->owner, $elsewhere->id, $list->id, 'Far');
        $farList = $this->checklists()->addChecklist($this->f->owner, $far, 'Far list');
        $this->assertInvalid(fn () => $this->checklists()->copyChecklist($this->f->owner, $this->card, $farList), 'copy_from');
    }

    public function testHidingTickedItemsIsPersonal(): void
    {
        $id = $this->checklists()->addChecklist($this->f->owner, $this->card, 'Steps');
        $this->checklists()->addItem($this->f->owner, $id, "Done thing\nOpen thing");
        $this->checklists()->toggleItem($this->f->owner, $this->checklists()->forCard($this->card->id)[0]->items[0]->id);

        $this->f->app->post('/checklists/' . $id . '/hide-done', ['hide' => '1']);
        $page = $this->f->app->page('/cards/' . $this->card->publicId);
        $this->assertFalse(str_contains($page, $this->itemHtml('Done thing')));
        $this->assertTrue(str_contains($page, $this->itemHtml('Open thing')));
        $this->assertTrue(str_contains($page, 'Show checked items (1)'));

        $this->onBoard('mona@example.com', BoardRole::Viewer);
        $this->assertTrue(str_contains($this->f->app->page('/cards/' . $this->card->publicId), $this->itemHtml('Done thing')), 'Others still see them.');
        $this->assertSame(302, $this->f->app->post('/checklists/' . $id . '/hide-done', ['hide' => '1'])->status, 'Viewers may hide them for themselves.');

        $this->f->app->loginAs('sara@example.com');
        $this->f->app->post('/checklists/' . $id . '/hide-done', ['hide' => '0']);
        $this->assertTrue(str_contains($this->f->app->page('/cards/' . $this->card->publicId), $this->itemHtml('Done thing')));
    }

    public function testAnItemBecomesACardWithLinksBothWays(): void
    {
        $id = $this->checklists()->addChecklist($this->f->owner, $this->card, 'Steps');
        $this->checklists()->addItem($this->f->owner, $id, 'Book the venue');
        $item = $this->checklists()->forCard($this->card->id)[0]->items[0];
        $this->checklists()->updateItem($this->f->owner, $item->id, ['text' => 'Book the venue', 'assignee_id' => (string) $this->f->owner, 'due_date' => '2026-03-05']);

        $boards = $this->f->app->container->get(BoardService::class);
        $events = $boards->createBoard($this->f->owner, $this->f->workspaceId, 'Events');
        $list = $boards->addList($this->f->owner, $events->id, 'To book');

        $this->f->app->post('/checklist-items/' . $item->id . '/convert', ['target' => $events->publicId . ':' . $list->id]);
        $item = $this->checklists()->forCard($this->card->id)[0]->items[0];
        $this->assertTrue($item->cardLinkId !== null);
        $new = $this->f->app->container->get(CardQueries::class)->find((int) $item->cardLinkId);
        $this->assertSame(['Book the venue', $events->id, $list->id, '2026-03-05'], [$new->title, $new->boardId, $new->listId, $new->dueDate()]);
        $this->assertSame([$this->f->owner], $this->cards()->membersOf($new));
        $this->assertTrue(str_contains((string) $new->description, '/cards/' . $this->card->publicId), 'The new card links back.');
        $this->assertTrue(str_contains($this->f->app->page('/cards/' . $this->card->publicId), '/cards/' . $new->publicId), 'The item links to the new card.');

        // Mona is on this board only: she sees the item but not the private card's title.
        $this->onBoard('mona@example.com', BoardRole::Member);
        $page = $this->f->app->page('/cards/' . $this->card->publicId);
        $this->assertFalse(str_contains($page, '/cards/' . $new->publicId));
        $this->assertTrue(str_contains($page, 'Turned into a card you cannot open'));

        // And she can not turn items into cards on a board she can not add to.
        $this->checklists()->addItem($this->f->owner, $id, 'Second');
        $second = $this->checklists()->forCard($this->card->id)[0]->items[1];
        $this->assertInvalid(fn () => $this->checklists()->convertItem($this->monaId(), $second->id, $events->id, $list->id), 'convert_to');
    }

    /* ---------- repeating due dates ---------- */

    public function testCompletingARepeatingCardMovesItsDatesAndKeepsItOpen(): void
    {
        $this->assertInvalid(fn () => $this->cards()->setSchedule($this->f->owner, $this->card, ['recurrence' => 'weekly']), 'recurrence');

        $this->cards()->update($this->f->owner, $this->card, ['title' => 'Launch post', 'start_date' => '2026-01-18', 'due_date' => '2026-01-20']);
        $this->f->app->post('/cards/' . $this->card->publicId . '/schedule', ['recurrence' => 'weekly', 'remind' => '', 'remind_hour' => '9']);
        $this->assertSame('weekly', $this->reload()->recurrence);

        $this->f->app->post('/cards/' . $this->card->publicId . '/complete', ['done' => '1']);
        $card = $this->reload();
        $this->assertFalse($card->isComplete());
        $this->assertSame(['2026-01-25', '2026-01-27'], [$card->startDate(), $card->dueDate()]);

        // A late card does not come back already overdue.
        $this->cards()->update($this->f->owner, $card, ['title' => 'Launch post', 'start_date' => '', 'due_date' => '2026-01-01']);
        $this->cards()->setComplete($this->f->owner, $this->reload(), true);
        $this->assertSame('2026-01-22', $this->reload()->dueDate());

        $this->assertSame('2026-02-28', CardSummary::nextOccurrence('2026-01-31', 'monthly', '2026-01-01'));
        $this->assertSame('2027-02-28', CardSummary::nextOccurrence('2026-02-28', 'yearly', '2026-01-01'));
        $this->assertSame('2026-01-25', CardSummary::nextOccurrence('2026-01-22', 'sun_thu', '2026-01-01'), 'Thursday to Sunday.');
        $this->assertSame('2026-01-26', CardSummary::nextOccurrence('2026-01-23', 'mon_fri', '2026-01-01'), 'Friday to Monday.');

        // Without a repeat it is simply done.
        $this->cards()->setSchedule($this->f->owner, $this->reload(), ['recurrence' => '', 'remind' => '']);
        $this->cards()->setComplete($this->f->owner, $this->reload(), true);
        $this->assertTrue($this->reload()->isComplete());
    }

    /* ---------- reminders ---------- */

    public function testACardsOwnReminderGoesOutOnceAtItsTimeAndStopsWhenArchived(): void
    {
        $this->cards()->toggleMember($this->f->owner, $this->card, $this->f->owner);
        $this->cards()->update($this->f->owner, $this->card, ['title' => 'Launch post', 'due_date' => '2026-01-22']);
        $this->cards()->setSchedule($this->f->owner, $this->reload(), ['remind' => '2', 'remind_hour' => '11']);
        $reminders = $this->f->app->container->get(DueReminders::class);

        $this->assertSame(0, $reminders->run(), 'Not before 11:00 two days before.');
        $this->clock()->travel('+1 hour');
        $this->assertSame(1, $reminders->run());
        $this->assertSame(0, $reminders->run(), 'Once.');
        $this->assertSame('card.reminder', $this->center()->latest($this->f->owner)[0]->type);

        // "No reminders" means no "due soon" either.
        $quiet = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Quiet', ['due_date' => '2026-01-21']);
        $this->cards()->toggleMember($this->f->owner, $quiet, $this->f->owner);
        $this->cards()->setSchedule($this->f->owner, $quiet, ['remind' => 'none']);
        $this->assertSame(0, $reminders->run());

        // Archived cards get nothing.
        $later = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Later', ['due_date' => '2026-01-24']);
        $this->cards()->toggleMember($this->f->owner, $later, $this->f->owner);
        $this->cards()->setSchedule($this->f->owner, $later, ['remind' => '7', 'remind_hour' => '0']);
        $this->cards()->archive($this->f->owner, $this->f->app->container->get(CardQueries::class)->find($later->id));
        $this->assertSame(0, $reminders->run());
    }

    /* ---------- scheduled copies ---------- */

    public function testScheduledCopiesAreMadeOncePerTimeEvenWhenTheSchedulerRepeats(): void
    {
        $path = '/cards/' . $this->card->publicId . '/repeat';
        $this->f->app->post($path, ['frequency' => 'weekly', 'hour' => '6', 'list_id' => (string) $this->f->doing->id]);
        $this->assertNull($this->repeats()->forCard($this->card->id), 'Weekly needs a day.');

        $this->f->app->post($path, ['frequency' => 'weekly', 'weekdays' => ['0', '3'], 'hour' => '6', 'list_id' => (string) $this->f->doing->id]);
        $repeat = $this->repeats()->forCard($this->card->id);
        $this->assertSame('2026-01-21 06:00:00', $repeat['next_at'], 'Next Wednesday at 06:00.');

        $this->assertSame(0, $this->repeats()->run(), 'Nothing before its time.');
        $this->clock()->travel('+20 hours');
        $this->assertSame(1, $this->repeats()->run());
        $this->assertSame(0, $this->repeats()->run(), 'A second run makes nothing.');
        $copies = array_values(array_filter($this->f->app->container->get(CardQueries::class)->openCardsByList($this->f->board->id)[$this->f->doing->id] ?? [], fn ($c) => $c->title === 'Launch post'));
        $this->assertCount(1, $copies);
        $repeat = $this->repeats()->forCard($this->card->id);
        $this->assertSame(['ok', '2026-01-25 06:00:00'], [$repeat['last_result'], $repeat['next_at']]);

        // A retried run for a time already made (say the next time was not saved) is refused by the claim.
        $this->pdo()->prepare('UPDATE card_repeats SET next_at = ? WHERE card_id = ?')->execute(['2026-01-21 06:00:00', $this->card->id]);
        $this->assertSame(0, $this->repeats()->run());
        $this->assertSame('2026-01-25 06:00:00', $this->repeats()->forCard($this->card->id)['next_at']);

        // When the person who set it up can no longer add cards, the run says so and nothing is made.
        $mona = $this->onBoard('mona@example.com', BoardRole::Member);
        $this->f->app->post($path, ['frequency' => 'daily', 'hour' => '6', 'list_id' => (string) $this->f->doing->id]);
        $this->f->app->container->get(BoardMembers::class)->remove($this->f->board->id, $mona);
        $this->f->app->container->get(Gate::class)->forget($mona, $this->f->board->id);
        $this->clock()->travel('+1 day');
        $this->assertSame(0, $this->repeats()->run());
        $this->assertSame('The person who set this up can no longer add cards here.', $this->repeats()->forCard($this->card->id)['last_result']);

        // A viewer can not set it up, and "Never" turns it off.
        $this->f->app->loginAs('sara@example.com');
        $this->f->app->post($path, ['frequency' => '']);
        $this->assertNull($this->repeats()->forCard($this->card->id));
    }

    /* ---------- lists ---------- */

    public function testCollapsingIsPersonalAndSortingReordersOnce(): void
    {
        $base = '/boards/' . $this->f->board->publicId;
        $this->f->app->post($base . '/lists/' . $this->f->done->id . '/collapse', ['collapsed' => '1']);
        $page = $this->f->app->page($base);
        $this->assertTrue(str_contains($page, 'id="list-' . $this->f->done->id . '"') && str_contains($page, 'is-collapsed'));

        $this->onBoard('vera@example.com', BoardRole::Viewer);
        $this->assertFalse(str_contains($this->f->app->page($base), 'is-collapsed'), 'Only for the person who folded it.');
        $this->assertSame(404, $this->f->app->post('/boards/' . $this->f->board->publicId . '/lists/999999/collapse', ['collapsed' => '1'])->status);
        $this->assertSame(403, $this->f->app->post($base . '/lists/' . $this->f->todo->id . '/sort', ['by' => 'name'])->status, 'Viewers can not reorder.');

        $this->f->app->loginAs('sara@example.com');
        $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'apple', ['due_date' => '2026-03-01']);
        $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Banana', ['due_date' => '2026-02-01']);
        $titles = fn () => array_map(static fn ($c) => $c->title, $this->f->app->container->get(CardQueries::class)->openCardsByList($this->f->board->id)[$this->f->todo->id]);

        $this->f->app->post($base . '/lists/' . $this->f->todo->id . '/sort', ['by' => 'name', 'dir' => 'asc']);
        $this->assertSame(['apple', 'Banana', 'Launch post'], $titles());
        $this->f->app->post($base . '/lists/' . $this->f->todo->id . '/sort', ['by' => 'due', 'dir' => 'desc']);
        $this->assertSame(['apple', 'Banana', 'Launch post'], $titles(), 'Latest first, undated last.');
        $this->f->app->post($base . '/lists/' . $this->f->todo->id . '/sort', ['by' => 'created', 'dir' => 'asc']);
        $this->assertSame(['Launch post', 'apple', 'Banana'], $titles());
        $this->assertInvalid(fn () => $this->cards()->sortList($this->f->owner, $this->f->board->id, $this->f->todo->id, 'colour'), 'sort');
    }

    /* ---------- board groups ---------- */

    public function testBoardGroupsAreForAdminsAndShowOnlyBoardsPeopleCanOpen(): void
    {
        $boards = $this->f->app->container->get(BoardService::class);
        $secret = $boards->createBoard($this->f->owner, $this->f->workspaceId, 'Secret plans', BoardVisibility::Private);
        $open = $boards->createBoard($this->f->owner, $this->f->workspaceId, 'Open board', BoardVisibility::Workspace);

        $this->f->app->post('/board-groups', ['name' => 'Marketing']);
        $this->f->app->post('/board-groups', ['name' => 'Ops']);
        $groups = $this->groups()->inWorkspace($this->f->workspaceId);
        $this->assertSame(['Marketing', 'Ops'], array_column($groups, 'name'));
        [$marketing, $ops] = array_column($groups, 'id');

        $this->f->app->post('/boards/' . $secret->publicId . '/group', ['group' => (string) $marketing]);
        $this->f->app->post('/boards/' . $open->publicId . '/group', ['group' => (string) $marketing]);
        $this->f->app->post('/board-groups/' . $ops . '/move', ['up' => '1']);
        $this->assertSame(['Ops', 'Marketing'], array_column($this->groups()->inWorkspace($this->f->workspaceId), 'name'));

        $home = $this->f->app->page('/');
        $this->assertTrue(str_contains($home, 'Marketing') && str_contains($home, 'Secret plans') && str_contains($home, 'Other boards'));

        // A member sees the group with the boards they can open, and can not manage groups.
        $this->f->colleague('mona@example.com', WorkspaceRole::Member);
        $this->f->app->loginAs('mona@example.com');
        $home = $this->f->app->page('/');
        $this->assertTrue(str_contains($home, 'Open board') && str_contains($home, 'Marketing'));
        $this->assertFalse(str_contains($home, 'Secret plans'));
        $this->assertFalse(str_contains($home, 'Ops'), 'An empty group is not shown to members.');
        $this->assertSame(403, $this->f->app->post('/board-groups', ['name' => 'Mine'])->status);
        $this->assertSame(403, $this->f->app->post('/board-groups/' . $marketing . '/delete')->status);
        $this->assertSame(403, $this->f->app->post('/boards/' . $open->publicId . '/group', ['group' => ''])->status, 'Placing a board needs its settings.');

        $this->f->app->loginAs('sara@example.com');
        $this->assertInvalid(fn () => $this->groups()->create($this->f->owner, $this->f->workspaceId, '   '), 'group_name');
        $this->f->app->post('/board-groups/' . $marketing . '/delete');
        $this->assertSame(['Ops'], array_column($this->groups()->inWorkspace($this->f->workspaceId), 'name'));
        $this->assertTrue(str_contains($this->f->app->page('/'), 'Secret plans'), 'Boards of a deleted group stay.');
    }

    /* ---------- helpers ---------- */

    private function assertInvalid(callable $action, string $field): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());

            return;
        }
        $this->assertTrue(false, "Expected a validation error on $field.");
    }

    private function itemHtml(string $text): string
    {
        return '<span class="item-text" dir="auto">' . $text;
    }

    /** @return list<string> */
    private function itemTexts(): array
    {
        return array_map(static fn ($i) => $i->text, $this->checklists()->forCard($this->card->id)[0]->items ?? []);
    }

    private function reload(): CardSummary
    {
        return $this->f->app->container->get(CardQueries::class)->find($this->card->id) ?? $this->card;
    }

    private function onBoard(string $email, BoardRole $role): int
    {
        $id = $this->f->colleague($email);
        $this->f->app->container->get(BoardMembers::class)->add($this->f->board->id, $id, $role);
        $this->f->app->container->get(Gate::class)->forget($id, $this->f->board->id);
        $this->f->app->loginAs($email);

        return $id;
    }

    private function monaId(): int
    {
        return (int) $this->pdo()->query("SELECT id FROM users WHERE email = 'mona@example.com'")->fetchColumn();
    }

    private function clock(): FrozenClock
    {
        $clock = $this->f->app->container->get(Clock::class);
        assert($clock instanceof FrozenClock);

        return $clock;
    }

    private function pdo(): PDO
    {
        return $this->f->app->container->get(PDO::class);
    }

    private function cards(): CardService
    {
        return $this->f->app->container->get(CardService::class);
    }

    private function checklists(): ChecklistService
    {
        return $this->f->app->container->get(ChecklistService::class);
    }

    private function repeats(): RecurringCards
    {
        return $this->f->app->container->get(RecurringCards::class);
    }

    private function groups(): \App\Modules\Board\Application\BoardGroups
    {
        return $this->f->app->container->get(\App\Modules\Board\Application\BoardGroups::class);
    }

    private function center(): NotificationCenter
    {
        return $this->f->app->container->get(NotificationCenter::class);
    }
}
