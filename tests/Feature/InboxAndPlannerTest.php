<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\UploadedFile;
use App\Modules\Access\Application\Gate;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Attachment\Contract\AttachmentFiles;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Application\ViewSettings;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Personal\Application\Inbox;
use App\Modules\Personal\Application\Planner;
use App\Validation\ValidationException;
use PDO;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

/** Batch 6: the personal inbox, the planner, the calendar link, the workspace calendar, table and dashboard settings. */
final class InboxAndPlannerTest extends TestCase
{
    private BoardFixture $f;
    private CardSummary $card;

    public function setUp(): void
    {
        // The test clock: Tuesday 2026-01-20 10:00.
        $this->f = new BoardFixture(new App());
        $this->card = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Write launch post', ['due_date' => '2026-01-23']);
        $this->cards()->assign($this->f->owner, $this->card, $this->f->owner);
    }

    /* ---------- inbox ---------- */

    public function testQuickCaptureAddsAPrivateItemThatMovesToABoardWithItsDetailsAndFiles(): void
    {
        $response = $this->f->app->post('/inbox', ['title' => 'Call the printer'], true);
        $this->assertSame(200, $response->status);
        $this->assertTrue(str_contains($response->body, '"count":1'));
        $this->f->app->post('/inbox', ['title' => 'Order stickers', 'description' => "Two sizes\nBlue and pink", 'due_date' => '2026-02-01']);
        $items = $this->inbox()->items($this->f->owner);
        $this->assertSame(['Order stickers', 'Call the printer'], array_column($items, 'title'));
        $this->assertTrue(str_contains($this->f->app->page('/inbox'), 'Order stickers'));

        $dir = App::scratch('inbox-files');
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/quote.txt', 'Quote: 400');
        $sticker = $items[0]['public_id'];
        $this->assertSame(302, $this->f->app->upload('/inbox/' . $sticker . '/files', ['file' => UploadedFile::local($dir . '/quote.txt', 'quote.txt')])->status);
        $file = $this->inbox()->items($this->f->owner)[0]['files'][0];
        $this->assertSame('quote.txt', $file['name']);
        $download = $this->f->app->get('/inbox-files/' . $file['public_id']);
        $this->assertSame([200, 'Quote: 400'], [$download->status, $download->body]);
        $this->assertTrue(str_contains((string) $download->header('Content-Security-Policy'), 'sandbox'));

        // Nobody else sees or changes it, not even in the same company.
        $this->f->colleague('mona@example.com');
        $this->f->app->loginAs('mona@example.com');
        $this->assertTrue(!str_contains($this->f->app->page('/inbox'), 'Order stickers'));
        $this->assertSame(404, $this->f->app->get('/inbox-files/' . $file['public_id'])->status);
        $this->assertSame(404, $this->f->app->post('/inbox/' . $sticker, ['title' => 'Hacked'])->status);
        $this->assertSame(404, $this->f->app->post('/inbox/' . $sticker . '/move', ['to' => $this->f->board->publicId . ':' . $this->f->todo->id])->status);
        $this->f->app->loginAs('sara@example.com');

        $this->assertInvalid(fn () => $this->inbox()->move($this->f->owner, $sticker, $this->f->board->publicId . ':999999'), 'move_to');
        $response = $this->f->app->post('/inbox/' . $sticker . '/move', ['to' => $this->f->board->publicId . ':' . $this->f->doing->id]);
        $this->assertSame(302, $response->status);
        $card = $this->findCard('Order stickers');
        $this->assertSame([$this->f->doing->id, "Two sizes\nBlue and pink", '2026-02-01'], [$card->listId, $card->description, $card->dueDate()]);
        $attachments = $this->f->app->container->get(AttachmentFiles::class)->forCard($card->id);
        $this->assertSame(['quote.txt'], array_map(static fn ($a) => $a->name, $attachments));
        $this->assertSame(200, $this->f->app->get('/attachments/' . $attachments[0]->publicId . '?download=1')->status, 'The file moved with the item.');
        $this->assertSame(['Call the printer'], array_column($this->inbox()->items($this->f->owner), 'title'));
    }

    public function testInboxChecksItsInputAndOnlyMovesToBoardsWhereThePersonAddsCards(): void
    {
        $this->assertInvalid(fn () => $this->inbox()->capture($this->f->owner, '   '), 'title');
        $this->assertInvalid(fn () => $this->inbox()->capture($this->f->owner, str_repeat('x', 201)), 'title');
        $this->assertInvalid(fn () => $this->inbox()->capture($this->f->owner, 'Ok', '', '2026-02-30'), 'due_date');
        $this->assertSame(422, $this->f->app->post('/inbox', ['title' => ''], true)->status);

        // A viewer on the board may not add cards there, so it is not a choice.
        $mona = $this->onBoard('mona@example.com', BoardRole::Viewer);
        $id = $this->inbox()->capture($mona, 'Idea');
        $this->assertSame([], $this->inbox()->targets($mona, $this->workspaces($mona)));
        $this->assertInvalid(fn () => $this->inbox()->move($mona, $id, $this->f->board->publicId . ':' . $this->f->todo->id), 'move_to');
        $this->assertTrue(str_contains($this->f->app->page('/inbox'), 'You cannot add cards to any board yet'));
    }

    /* ---------- planner ---------- */

    public function testPlannedTimeIsPersonalAndNeverChangesTheDueDate(): void
    {
        $page = $this->f->app->page('/planner');
        $this->assertTrue(str_contains($page, 'data-card="' . $this->card->publicId . '"'), 'The task waits in "Not planned yet".');

        $this->f->app->post('/planner/sessions', ['card' => $this->card->publicId, 'day' => '2026-01-21', 'hour' => '9', 'minutes' => '90']);
        $sessions = $this->planner()->between($this->f->owner, '2026-01-19', '2026-01-25');
        $this->assertSame([['2026-01-21', 9, 90]], array_map(static fn ($s) => [$s['day'], $s['hour'], $s['minutes']], $sessions));
        $this->assertSame('2026-01-23', $this->reload()->dueDate(), 'The due date stays.');
        $this->assertSame([], $this->planner()->unplanned($this->f->owner));

        $id = $sessions[0]['id'];
        $this->f->app->post('/planner/sessions/' . $id, ['day' => '2026-01-24', 'hour' => '']);
        $moved = $this->planner()->between($this->f->owner, '2026-01-19', '2026-01-25')[0];
        $this->assertSame(['2026-01-24', null, 90], [$moved['day'], $moved['hour'], $moved['minutes']]);
        $this->assertSame('2026-01-23', $this->reload()->dueDate());
        $page = $this->f->app->page('/planner?week=2026-01-24');
        $this->assertTrue(str_contains($page, 'Due before this day: 2026-01-23'), 'A plan after the due date is pointed out.');
        $this->assertTrue(str_contains($this->f->app->page('/cards/' . $this->card->publicId), 'Plan work time'));

        $this->assertInvalid(fn () => $this->planner()->move($this->f->owner, $id, '2026-13-01', ''), 'day');
        $this->assertInvalid(fn () => $this->planner()->move($this->f->owner, $id, '2026-01-22', '24'), 'hour');
        $this->assertInvalid(fn () => $this->planner()->move($this->f->owner, $id, '2026-01-22', '', '45'), 'minutes');

        // Someone else can neither see nor touch it, and may not plan a card they cannot open.
        $mona = $this->f->colleague('mona@example.com');
        $this->f->app->loginAs('mona@example.com');
        $this->assertSame(404, $this->f->app->post('/planner/sessions/' . $id . '/delete')->status);
        $this->assertInvalid(fn () => $this->planner()->plan($mona, $this->card->publicId, '2026-01-21', '', '60'), 'card');
        $this->assertSame([], $this->planner()->between($mona, '2026-01-19', '2026-01-25'));
        $this->f->app->loginAs('sara@example.com');

        $this->f->app->post('/planner/sessions/' . $id . '/delete', ['week' => '2026-01-19']);
        $this->assertSame([], $this->planner()->between($this->f->owner, '2026-01-01', '2026-02-28'));
    }

    public function testSessionsOnCardsThePersonCanNoLongerSeeAreHidden(): void
    {
        $mona = $this->onBoard('mona@example.com', BoardRole::Member);
        $this->cards()->assign($this->f->owner, $this->card, $mona);
        $this->planner()->plan($mona, $this->card->publicId, '2026-01-22', '10', '60');
        $this->assertCount(1, $this->planner()->between($mona, '2026-01-19', '2026-01-25'));

        $this->f->app->container->get(BoardMembers::class)->remove($this->f->board->id, $mona);
        $this->f->app->container->get(Gate::class)->forget($mona, $this->f->board->id);
        $this->assertSame([], $this->planner()->between($mona, '2026-01-19', '2026-01-25'));
        $this->assertTrue(!str_contains($this->f->app->page('/planner'), 'Write launch post'));
    }

    /* ---------- calendar link ---------- */

    public function testTheCalendarLinkShowsMyDatesUntilItIsReplacedOrTurnedOff(): void
    {
        $this->assertTrue(str_contains($this->f->app->page('/planner'), 'No calendar link is on.'));
        $this->f->app->post('/planner/calendar-link');
        $page = $this->f->app->page('/planner');
        $this->assertSame(1, preg_match('#/calendar/([A-Za-z0-9_-]{43})\.ics#', $page, $m));
        $secret = $m[1];
        $this->assertTrue(!str_contains($this->f->app->page('/planner'), $secret), 'The link is shown once.');
        $this->assertSame(1, (int) $this->pdo()->query('SELECT COUNT(*) FROM calendar_feeds WHERE token_hash = ' . $this->pdo()->quote(hash('sha256', $secret)))->fetchColumn(), 'Only the hash is kept.');

        $this->cards()->update($this->f->owner, $this->card, ['title' => 'Launch; post, v2', 'description' => '', 'start_date' => '', 'due_date' => '2026-01-23']);
        $this->planner()->plan($this->f->owner, $this->card->publicId, '2026-01-21', '9', '120');
        $this->f->app->post('/logout');
        $feed = $this->f->app->get('/calendar/' . $secret . '.ics');
        $this->assertSame(200, $feed->status);
        $this->assertTrue(str_starts_with((string) $feed->header('Content-Type'), 'text/calendar'));
        $this->assertTrue(str_contains($feed->body, "DTSTART;VALUE=DATE:20260123\r\n"));
        $this->assertTrue(str_contains($feed->body, 'SUMMARY:Due: Launch\; post\, v2'), 'Text is escaped.');
        $this->assertTrue(str_contains($feed->body, 'UID:card-' . $this->card->publicId . '-due@'));
        $this->assertTrue(str_contains($feed->body, 'SUMMARY:Planned: Launch\; post\, v2'));
        $this->assertSame(2, substr_count($feed->body, 'BEGIN:VEVENT'));
        $this->assertSame(404, $this->f->app->get('/calendar/' . str_repeat('A', 43) . '.ics')->status);

        // Archiving the board takes its dates out of the feed.
        $this->f->app->container->get(BoardService::class)->archiveBoard($this->f->owner, $this->f->board);
        $this->assertSame(0, substr_count($this->f->app->get('/calendar/' . $secret . '.ics')->body, 'BEGIN:VEVENT'));

        $this->f->app->loginAs('sara@example.com');
        $this->assertTrue(str_contains($this->f->app->page('/planner'), 'Last read by a calendar app'));
        $this->f->app->post('/planner/calendar-link');
        $this->assertSame(404, $this->f->app->get('/calendar/' . $secret . '.ics')->status, 'A new link ends the old one.');
        $this->f->app->post('/planner/calendar-link/delete');
        $this->assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM calendar_feeds')->fetchColumn());
    }

    /* ---------- workspace calendar, table, dashboard ---------- */

    public function testTheWorkspaceCalendarOnlyShowsBoardsThePersonCanOpen(): void
    {
        $boards = $this->f->app->container->get(BoardService::class);
        $secret = $boards->createBoard($this->f->owner, $this->f->workspaceId, 'Secret board');
        $list = $boards->addList($this->f->owner, $secret->id, 'Ideas');
        $this->cards()->createCard($this->f->owner, $secret->id, $list->id, 'Hidden plan', ['due_date' => '2026-01-26']);

        $path = '/workspaces/' . $this->workspace()->publicId . '/calendar?month=2026-01';
        $page = $this->f->app->page($path);
        $this->assertTrue(str_contains($page, 'Write launch post') && str_contains($page, 'Hidden plan'));

        $this->onBoard('mona@example.com', BoardRole::Member);
        $page = $this->f->app->page($path);
        $this->assertTrue(str_contains($page, 'Write launch post'));
        $this->assertTrue(!str_contains($page, 'Hidden plan') && !str_contains($page, 'Secret board'), 'A private board stays hidden.');
    }

    public function testTableSettingsAreSavedPerPerson(): void
    {
        $path = '/boards/' . $this->f->board->publicId . '/table';
        $this->f->app->post($path . '/settings', ['columns' => ['due', 'list'], 'sort' => 'due', 'dir' => 'desc', 'group' => 'member']);
        $layout = $this->settings()->table($this->f->owner, $this->f->board->id);
        $this->assertSame([['due', 'list'], 'due', true, 'member'], [$layout->columns, $layout->sort, $layout->desc, $layout->group]);

        $page = $this->f->app->page($path);
        $this->assertTrue(str_contains($page, 'class="group-row"'));
        $this->assertTrue(str_contains($page, 'Nobody assigned') || str_contains($page, 'Sara'));
        $this->assertTrue(!str_contains($page, '>Labels</th>'), 'Hidden columns are left out.');
        $this->assertTrue(strpos($page, '>Due') < strpos($page, '>List'), 'Columns keep their order.');

        $this->assertInvalid(fn () => $this->settings()->saveTable($this->f->owner, $this->f->board->id, ['columns' => ['secret'], 'sort' => 'due']), 'columns');
        $this->assertInvalid(fn () => $this->settings()->saveTable($this->f->owner, $this->f->board->id, ['columns' => [], 'group' => 'mood']), 'group');

        $mona = $this->onBoard('mona@example.com', BoardRole::Viewer);
        $this->assertTrue(!$this->settings()->table($mona, $this->f->board->id)->saved, 'Others keep the usual table.');
        $this->assertTrue(str_contains($this->f->app->page($path), '>Labels</th>'));
        $this->f->app->loginAs('sara@example.com');
        $this->f->app->post($path . '/settings', ['reset' => '1']);
        $this->assertTrue(!$this->settings()->table($this->f->owner, $this->f->board->id)->saved);
    }

    public function testDashboardWidgetsCanBeRemovedAddedAndReorderedPerPerson(): void
    {
        $path = '/boards/' . $this->f->board->publicId . '/dashboard';
        $this->f->app->post($path . '/widgets', ['action' => 'remove', 'widget' => 'labels']);
        $this->f->app->post($path . '/widgets', ['action' => 'up', 'widget' => 'trend']);
        $this->assertSame(['progress', 'summary', 'lists', 'trend', 'people'], $this->settings()->dashboard($this->f->owner, $this->f->board->id)->widgets);
        $page = $this->f->app->page($path);
        $this->assertTrue(!str_contains($page, 'id="widget-labels"'));
        $this->assertTrue(strpos($page, 'id="widget-trend"') < strpos($page, 'id="widget-people"'));
        $this->assertTrue(str_contains($page, 'How many open (not archived) cards carry each label') === false && str_contains($page, 'Where this comes from'));

        $this->f->app->post($path . '/widgets', ['action' => 'add', 'widget' => 'labels']);
        $this->assertSame('labels', array_slice($this->settings()->dashboard($this->f->owner, $this->f->board->id)->widgets, -1)[0]);
        $this->assertSame(302, $this->f->app->post($path . '/widgets', ['action' => 'add', 'widget' => 'secrets'])->status);
        $this->assertCount(6, $this->settings()->dashboard($this->f->owner, $this->f->board->id)->widgets);

        $mona = $this->onBoard('mona@example.com', BoardRole::Viewer);
        $this->assertSame(['progress', 'summary', 'lists', 'people', 'labels', 'trend'], $this->settings()->dashboard($mona, $this->f->board->id)->widgets);
        $this->f->app->loginAs('sara@example.com');
        $this->f->app->post($path . '/widgets', ['action' => 'reset']);
        $this->assertTrue(!$this->settings()->dashboard($this->f->owner, $this->f->board->id)->saved);
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

    private function onBoard(string $email, BoardRole $role): int
    {
        $id = $this->f->colleague($email);
        $this->f->app->container->get(BoardMembers::class)->add($this->f->board->id, $id, $role);
        $this->f->app->container->get(Gate::class)->forget($id, $this->f->board->id);
        $this->f->app->loginAs($email);

        return $id;
    }

    /** @return list<\App\Modules\Workspace\Contract\WorkspaceSummary> */
    private function workspaces(int $userId): array
    {
        return $this->f->app->container->get(\App\Modules\Workspace\Contract\WorkspaceDirectory::class)->workspacesOf($userId);
    }

    private function workspace(): \App\Modules\Workspace\Contract\WorkspaceSummary
    {
        return $this->workspaces($this->f->owner)[0];
    }

    private function findCard(string $title): CardSummary
    {
        $id = (int) $this->pdo()->query('SELECT id FROM cards WHERE title = ' . $this->pdo()->quote($title))->fetchColumn();

        return $this->f->app->container->get(CardQueries::class)->find($id) ?? throw new \RuntimeException('No card ' . $title);
    }

    private function reload(): CardSummary
    {
        return $this->f->app->container->get(CardQueries::class)->find($this->card->id) ?? $this->card;
    }

    private function pdo(): PDO
    {
        return $this->f->app->container->get(PDO::class);
    }

    private function cards(): CardService
    {
        return $this->f->app->container->get(CardService::class);
    }

    private function inbox(): Inbox
    {
        return $this->f->app->container->get(Inbox::class);
    }

    private function planner(): Planner
    {
        return $this->f->app->container->get(Planner::class);
    }

    private function settings(): ViewSettings
    {
        return $this->f->app->container->get(ViewSettings::class);
    }
}
