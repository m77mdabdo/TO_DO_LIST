<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Card\Application\CardService;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Checklist\Application\ChecklistService;
use App\Modules\Label\Contract\LabelCatalog;
use App\Modules\Workspace\Application\WorkspaceService;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

/** Batch 2: search everywhere, saved searches, card templates and copies, bulk actions, list colors, card display, editing automations. */
final class DailyUseTest extends TestCase
{
    private BoardFixture $f;

    public function setUp(): void
    {
        // The test clock is 2026-01-20.
        $this->f = new BoardFixture(new App(), BoardVisibility::Workspace);
    }

    /* ---------- search ---------- */

    public function testSearchCoversEveryWorkspaceOrJustOne(): void
    {
        $this->card('Logo ideas');
        $first = $this->f->app->container->get(WorkspaceDirectory::class)->current($this->f->owner);
        $second = $this->f->app->container->get(WorkspaceService::class)->create($this->f->owner, ['company_name' => 'Side project']);
        $board = $this->boards()->createBoard($this->f->owner, $second->id, 'Shop', BoardVisibility::Private);
        $list = $this->boards()->addList($this->f->owner, $board->id, 'Ideas');
        $this->cards()->createCard($this->f->owner, $board->id, $list->id, 'Logo for the shop');

        $all = $this->f->app->page('/search?q=logo');
        $this->assertTrue(str_contains($all, 'Logo ideas'));
        $this->assertTrue(str_contains($all, 'Logo for the shop'));
        $this->assertTrue(str_contains($all, 'Side project'), 'Each result names its workspace.');

        $one = $this->f->app->page('/search?q=logo&workspace=' . $first->publicId);
        $this->assertTrue(str_contains($one, 'Logo ideas'));
        $this->assertFalse(str_contains($one, 'Logo for the shop'));

        // Someone outside the second workspace gets nothing from it, even when they name it.
        $this->f->colleague('mona@example.com');
        $outsider = $this->f->app->page('/search?q=logo&workspace=' . $second->publicId . '&board=' . $board->publicId);
        $this->assertFalse(str_contains($outsider, 'Logo for the shop'));
        $this->assertFalse(str_contains($outsider, 'Side project'));
    }

    public function testFiltersWorkWithoutTextAndLinksCarryOnlyKnownValues(): void
    {
        $mine = $this->card('Write the post', '2026-01-10');
        $this->cards()->toggleMember($this->f->owner, $mine, $this->f->owner);
        $this->card('Book the room', '2026-02-10');
        $label = $this->labels()->ensureLabel($this->f->owner, $this->f->board->id, 'Urgent', 'red');
        $this->labels()->attach($this->f->owner, $this->card('Call the printer')->id, $label->id);

        $this->assertSame(['Write the post'], $this->titles('/search?member=me'));
        $this->assertSame(['Write the post'], $this->titles('/search?due=overdue'));
        $this->assertSame(['Call the printer'], $this->titles('/search?label=urgent'));
        $this->assertSame(['Write the post'], $this->titles('/search?board=' . $this->f->board->publicId . '&list=' . $this->f->todo->id . '&member=me'));

        $page = $this->f->app->page('/search?member=me&due=overdue&evil=%3Cscript%3E&board=nope');
        $this->assertTrue(str_contains($page, '/search?member=me&amp;due=overdue'), 'The share link keeps only checked values.');
        $this->assertFalse(str_contains($page, 'evil'));
    }

    public function testSavedSearchesBelongToTheirOwner(): void
    {
        $this->assertSame(302, $this->f->app->post('/search/saved', ['name' => 'Nothing', 'query' => ''])->status);
        $this->assertSame(0, $this->count('saved_searches'), 'An empty search is not saved.');

        $this->f->app->post('/search/saved', ['name' => 'My overdue', 'query' => 'member=me&due=overdue&junk=1']);
        $row = $this->f->app->pdo()->query('SELECT public_id, name, query FROM saved_searches')->fetch();
        $this->assertSame(['My overdue', 'member=me&due=overdue'], [$row['name'], $row['query']]);
        $this->assertTrue(str_contains($this->f->app->page('/search'), 'My overdue'));

        $this->f->app->post('/search/saved/' . $row['public_id'], ['name' => 'Late and mine', 'query' => 'member=me&due=week']);
        $this->assertSame(['Late and mine', 'member=me&due=week'], array_values($this->f->app->pdo()->query('SELECT name, query FROM saved_searches')->fetch(\PDO::FETCH_ASSOC)));
        $this->f->app->post('/search/saved/' . $row['public_id'], ['name' => 'Renamed only']);
        $this->assertSame('member=me&due=week', $this->f->app->pdo()->query('SELECT query FROM saved_searches')->fetchColumn(), 'Renaming keeps the filters.');

        $this->f->colleague('mona@example.com');
        $this->assertFalse(str_contains($this->f->app->page('/search'), 'Renamed only'));
        $this->assertSame(404, $this->f->app->post('/search/saved/' . $row['public_id'], ['name' => 'Mine now'])->status);
        $this->assertSame(404, $this->f->app->post('/search/saved/' . $row['public_id'] . '/delete')->status);

        $this->f->app->loginAs('sara@example.com');
        $this->f->app->post('/search/saved/' . $row['public_id'] . '/delete');
        $this->assertSame(0, $this->count('saved_searches'));
    }

    /* ---------- lists and card display ---------- */

    public function testListsCanHaveAColorOnlyFromThePalette(): void
    {
        $path = '/boards/' . $this->f->board->publicId . '/lists/' . $this->f->todo->id;
        $this->f->app->post($path, ['name' => 'To do', 'color' => 'mint']);
        $this->assertTrue(str_contains($this->f->app->page('/boards/' . $this->f->board->publicId), 'list-color tile-mint'));

        $this->f->app->post($path, ['name' => 'To do', 'color' => 'url(evil)']);
        $this->assertSame('mint', $this->f->app->pdo()->query('SELECT color FROM lists WHERE id = ' . $this->f->todo->id)->fetchColumn());

        $this->f->app->post($path, ['name' => 'To do', 'color' => '']);
        $this->assertNull($this->f->app->pdo()->query('SELECT color FROM lists WHERE id = ' . $this->f->todo->id)->fetchColumn());

        $this->f->colleague('mona@example.com');
        $this->members()->add($this->f->board->id, $this->f->app->loginAs('mona@example.com'), BoardRole::Viewer);
        $this->assertSame(403, $this->f->app->post($path, ['name' => 'To do', 'color' => 'coral'])->status);
    }

    public function testEachPersonChoosesWhatTheCardTilesShow(): void
    {
        $this->card('Dated', '2026-02-01');
        $board = '/boards/' . $this->f->board->publicId;
        $this->assertTrue(str_contains($this->f->app->page($board), '2026-02-01'));

        $this->f->app->post($board . '/display', ['show' => ['labels', 'members']]);
        $this->assertFalse(str_contains($this->f->app->page($board), '2026-02-01'));

        $this->f->colleague('mona@example.com');
        $this->assertTrue(str_contains($this->f->app->page($board), '2026-02-01'), 'Only the person who chose it sees less.');

        $this->f->app->loginAs('sara@example.com');
        $this->f->app->post($board . '/display', ['reset' => '1']);
        $this->assertTrue(str_contains($this->f->app->page($board), '2026-02-01'));
    }

    /* ---------- templates and copies ---------- */

    public function testCopyingACardKeepsOnlyWhatWasChosen(): void
    {
        $card = $this->card('Weekly report', '2026-02-01', 'Numbers and notes');
        $label = $this->labels()->ensureLabel($this->f->owner, $this->f->board->id, 'Report', 'blue');
        $this->labels()->attach($this->f->owner, $card->id, $label->id);
        $checklists = $this->f->app->container->get(ChecklistService::class);
        $checklists->addItem($this->f->owner, $checklists->addChecklist($this->f->owner, $card, 'Steps'), 'Collect numbers');
        $this->cards()->toggleMember($this->f->owner, $card, $this->f->owner);

        $other = $this->boards()->createBoard($this->f->owner, $this->f->workspaceId, 'Archive board', BoardVisibility::Private);
        $inbox = $this->boards()->addList($this->f->owner, $other->id, 'Inbox');

        $this->f->app->post('/cards/' . $card->publicId . '/copy', [
            'title' => 'Report copy', 'target' => $other->publicId . ':' . $inbox->id, 'keep' => ['labels', 'description'],
        ]);
        $copy = $this->find('Report copy');
        $this->assertSame([$other->id, $inbox->id], [$copy->boardId, $copy->listId]);
        $this->assertSame('Numbers and notes', $copy->description);
        $this->assertNull($copy->dueAt);
        $this->assertSame(['Report'], array_map(static fn ($l) => $l->name, $this->labels()->forCards([$copy->id])[$copy->id] ?? []));
        $this->assertSame([], $checklists->forCard($copy->id));
        $this->assertSame([], $this->cards()->membersOf($copy));
        $this->assertSame('Weekly report', $this->find('Weekly report')->title, 'The original is untouched.');

        // A member who can't edit the target board can't copy into it.
        $mona = $this->f->colleague('mona@example.com');
        $this->assertSame(404, $this->f->app->post('/cards/' . $card->publicId . '/copy', ['target' => $other->publicId . ':' . $inbox->id])->status);
        $this->assertSame(1, (int) $this->f->app->pdo()->query("SELECT COUNT(*) FROM cards WHERE board_id = {$other->id}")->fetchColumn(), "Only the owner's copy is there (mona $mona).");
    }

    public function testTemplatesMakeNewCardsFromTheListMenu(): void
    {
        $template = $this->card('Bug report', null, 'Steps to reproduce:');
        $checklists = $this->f->app->container->get(ChecklistService::class);
        $checklists->addItem($this->f->owner, $checklists->addChecklist($this->f->owner, $template, 'Triage'), 'Add a severity');
        $this->f->app->post('/cards/' . $template->publicId . '/template', ['on' => '1']);
        $this->assertTrue($this->find('Bug report')->isTemplate);

        $board = $this->f->app->page('/boards/' . $this->f->board->publicId);
        $this->assertTrue(str_contains($board, 'tile-template'));
        $this->assertTrue(str_contains($board, 'New card from a template'));

        $this->f->app->post('/boards/' . $this->f->board->publicId . '/cards', ['list_id' => (string) $this->f->doing->id, 'template' => $template->publicId, 'title' => 'Login fails']);
        $made = $this->find('Login fails');
        $this->assertSame($this->f->doing->id, $made->listId);
        $this->assertFalse($made->isTemplate);
        $this->assertSame('Steps to reproduce:', $made->description);
        $this->assertSame(['Triage'], array_map(static fn ($c) => $c->title, $checklists->forCard($made->id)));

        // A template card of another board, or a plain card, can't be used this way.
        $plain = $this->card('Plain');
        $this->assertSame(404, $this->f->app->post('/boards/' . $this->f->board->publicId . '/cards', ['list_id' => (string) $this->f->todo->id, 'template' => $plain->publicId])->status);
    }

    /* ---------- bulk actions ---------- */

    public function testBulkActionsChangeEveryChosenCardOrNone(): void
    {
        $a = $this->card('Alpha');
        $b = $this->card('Beta');
        $c = $this->card('Gamma');
        $bulk = '/boards/' . $this->f->board->publicId . '/cards/bulk';

        $this->f->app->post($bulk, ['cards' => [$a->publicId, $b->publicId], 'action' => 'move', 'value_move' => (string) $this->f->done->id]);
        $this->assertSame([$this->f->done->id, $this->f->done->id, $this->f->todo->id], [$this->find('Alpha')->listId, $this->find('Beta')->listId, $this->find('Gamma')->listId]);

        $this->f->app->post($bulk, ['cards' => [$a->publicId, $c->publicId], 'action' => 'assign', 'value_assign' => (string) $this->f->owner]);
        $this->assertSame([[$this->f->owner], [], [$this->f->owner]], [$this->cards()->membersOf($a), $this->cards()->membersOf($b), $this->cards()->membersOf($c)]);

        $this->f->app->post($bulk, ['cards' => [$a->publicId, $b->publicId, $c->publicId], 'action' => 'due', 'value_due' => '2026-03-01']);
        $this->assertSame('2026-03-01', $this->find('Gamma')->dueDate());

        // One card from another board spoils the whole request.
        $other = $this->boards()->createBoard($this->f->owner, $this->f->workspaceId, 'Elsewhere', BoardVisibility::Private);
        $foreign = $this->cards()->createCard($this->f->owner, $other->id, $this->boards()->addList($this->f->owner, $other->id, 'L')->id, 'Foreign');
        $this->f->app->post($bulk, ['cards' => [$a->publicId, $foreign->publicId], 'action' => 'complete']);
        $this->assertFalse($this->find('Alpha')->isComplete());
        $this->assertFalse($this->find('Foreign')->isComplete());

        // Deleting needs the count typed in.
        $this->f->app->post($bulk, ['cards' => [$a->publicId, $b->publicId], 'action' => 'delete', 'confirm' => '1']);
        $this->assertTrue(null !== $this->find('Alpha'));
        $this->f->app->post($bulk, ['cards' => [$a->publicId, $b->publicId], 'action' => 'delete', 'confirm' => '2']);
        $this->assertNull($this->find('Alpha'));
        $this->assertNull($this->find('Beta'));
        $this->assertTrue(null !== $this->find('Gamma'));
    }

    public function testBulkActionsNeedThePermissionForThatAction(): void
    {
        $card = $this->card('Alpha');
        $bulk = '/boards/' . $this->f->board->publicId . '/cards/bulk';
        $this->f->colleague('mona@example.com');
        $mona = $this->f->app->loginAs('mona@example.com');

        // A workspace member can see a workspace board but is only a viewer on it.
        $this->assertSame(403, $this->f->app->post($bulk, ['cards' => [$card->publicId], 'action' => 'archive'])->status);
        $this->members()->add($this->f->board->id, $mona, BoardRole::Member);
        $this->f->app->container->get(\App\Modules\Access\Application\Gate::class)->forget($mona, $this->f->board->id);
        $this->assertSame(302, $this->f->app->post($bulk, ['cards' => [$card->publicId], 'action' => 'complete'])->status);
        $this->assertTrue($this->find('Alpha')->isComplete());
        $this->assertTrue(str_contains($this->f->app->page('/boards/' . $this->f->board->publicId . '?select=1'), 'form="bulk-form"'));
    }

    /* ---------- automations ---------- */

    public function testAutomationsCanBeEditedAfterAPreviewAndDuplicated(): void
    {
        $base = '/boards/' . $this->f->board->publicId . '/automation';
        $this->f->app->post($base, ['kind' => 'rule', 'trigger' => 'card.moved:' . $this->f->done->id, 'actions' => [['do' => 'card.complete']]]);
        $id = (string) $this->f->app->pdo()->query('SELECT public_id FROM automations')->fetchColumn();

        $form = $this->f->app->page('/automations/' . $id . '/edit');
        $this->assertTrue(str_contains($form, 'value="card.moved:' . $this->f->done->id . '" selected'), 'The form shows what it does now.');

        $changes = ['kind' => 'rule', 'trigger' => 'card.moved:' . $this->f->doing->id, 'actions' => [['do' => 'card.complete'], ['do' => 'card.archive']]];
        $preview = $this->f->app->post('/automations/' . $id, $changes);
        $this->assertSame(200, $preview->status);
        $this->assertTrue(str_contains($preview->body, 'When a card is moved into “Doing”: mark the card complete, then archive the card.'));
        $this->assertSame('card.moved', $this->f->app->pdo()->query('SELECT trigger_type FROM automations')->fetchColumn());
        $this->assertSame((string) $this->f->done->id, (string) $this->f->app->pdo()->query('SELECT trigger_value FROM automations')->fetchColumn(), 'A preview saves nothing.');

        $this->f->app->post('/automations/' . $id, $changes + ['confirm' => '1']);
        $this->assertSame((string) $this->f->doing->id, (string) $this->f->app->pdo()->query('SELECT trigger_value FROM automations')->fetchColumn());

        $this->f->app->post('/automations/' . $id . '/duplicate');
        $rows = $this->f->app->pdo()->query('SELECT name, enabled FROM automations ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertSame(2, count($rows));
        $this->assertSame(0, (int) $rows[1]['enabled'], 'The copy starts paused.');
        $this->assertTrue(str_ends_with($rows[1]['name'], '(copy)'));

        // Only the board's managers.
        $this->f->colleague('mona@example.com');
        $this->assertSame(403, $this->f->app->post('/automations/' . $id, $changes + ['confirm' => '1'])->status);
        $this->assertSame(403, $this->f->app->get('/automations/' . $id . '/edit')->status);
    }

    public function testTheLastFailureReasonIsShown(): void
    {
        $gone = $this->boards()->addList($this->f->owner, $this->f->board->id, 'Old');
        $this->f->app->post('/boards/' . $this->f->board->publicId . '/automation', ['kind' => 'rule', 'trigger' => 'card.moved:' . $this->f->done->id, 'actions' => [['do' => 'card.move:' . $gone->id]]]);
        $this->boards()->archiveList($this->f->owner, $this->f->board, $gone->id);
        $this->cards()->move($this->f->owner, $this->card('Fragile'), $this->f->done->id, null);

        $page = $this->f->app->page('/boards/' . $this->f->board->publicId . '/automation');
        $this->assertTrue(str_contains($page, 'Failed 1 time(s) in a row.'));
        $this->assertTrue(str_contains($page, 'That list is not available.'));
    }

    /* ---------- helpers ---------- */

    private function card(string $title, ?string $due = null, ?string $description = null): CardSummary
    {
        return $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, $title, array_filter(['due_date' => $due, 'description' => $description]));
    }

    private function find(string $title): ?CardSummary
    {
        $stmt = $this->f->app->pdo()->prepare('SELECT id FROM cards WHERE title = ?');
        $stmt->execute([$title]);
        $id = $stmt->fetchColumn();

        return $id !== false ? $this->f->app->container->get(CardQueries::class)->find((int) $id) : null;
    }

    /** @return list<string> card titles in the search results */
    private function titles(string $path): array
    {
        preg_match_all('#class="result-title"[^>]*>([^<]+)<#', $this->f->app->page($path), $m);

        return array_map('html_entity_decode', $m[1]);
    }

    private function count(string $table): int
    {
        return (int) $this->f->app->pdo()->query("SELECT COUNT(*) FROM $table")->fetchColumn();
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
}
