<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Board\Application\BoardService;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Card\Application\CardService;
use App\Modules\Checklist\Application\ChecklistService;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class CrossBoardViewsTest extends TestCase
{
    private BoardFixture $f;
    private int $mona;

    public function setUp(): void
    {
        // The test clock is 2026-01-20 10:00.
        $this->f = new BoardFixture(new App(), BoardVisibility::Workspace);
        $this->mona = $this->f->colleague('mona@example.com');
        $cards = $this->cards();

        $late = $cards->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Late report', ['due_date' => '2026-01-15']);
        $cards->toggleMember($this->f->owner, $late, $this->mona);
        $soon = $cards->createCard($this->f->owner, $this->f->board->id, $this->f->doing->id, 'Soon thing', ['due_date' => '2026-01-22']);
        $cards->toggleMember($this->f->owner, $soon, $this->mona);
        $cards->toggleMember($this->f->owner, $soon, $this->f->owner);
        $done = $cards->createCard($this->f->owner, $this->f->board->id, $this->f->done->id, 'Shipped');
        $cards->toggleMember($this->f->owner, $done, $this->f->owner);
        $cards->setComplete($this->f->owner, $done, true);

        $checklists = $this->f->app->container->get(ChecklistService::class);
        $list = $checklists->addChecklist($this->f->owner, $done, 'Steps');
        $checklists->addItem($this->f->owner, $list, 'Ask legal');
        $item = $checklists->forCard($done->id)[0]->items[0];
        $checklists->updateItem($this->f->owner, $item->id, ['text' => 'Ask legal', 'assignee_id' => (string) $this->mona, 'due_date' => '2026-01-20']);

        // Sara's private board: Mona must never see it anywhere.
        $boards = $this->f->app->container->get(BoardService::class);
        $secret = $boards->createBoard($this->f->owner, $this->f->workspaceId, 'Secret', BoardVisibility::Private);
        $secretList = $boards->addList($this->f->owner, $secret->id, 'Ideas');
        $cards->createCard($this->f->owner, $secret->id, $secretList->id, 'Hidden plan', ['due_date' => '2026-01-10']);
    }

    public function testMyTasksGroupsWhatIsMineByWhenItIsDue(): void
    {
        $this->f->app->loginAs('mona@example.com');
        $page = $this->f->app->page('/my-tasks');

        $this->assertSame(1, preg_match('#Overdue.*Late report.*Due today.*Ask legal.*on Shipped.*Due in the next 7 days.*Soon thing#s', $page));
        $this->assertFalse(str_contains($page, 'Hidden plan'));

        // Taken off the board: the card disappears from my tasks.
        $this->f->app->container->get(BoardService::class)->updateBoard($this->f->owner, $this->f->board, ['name' => 'Launch', 'visibility' => 'private', 'description' => '', 'color' => $this->f->board->color]);
        $this->f->app->container->get(\App\Modules\Access\Contract\AccessGate::class)->forget($this->mona, $this->f->board->id); // as a new request would
        $this->assertFalse(str_contains($this->f->app->page('/my-tasks'), 'Late report'));
    }

    public function testAllCardsShowsEveryVisibleBoardWithFilters(): void
    {
        $ws = $this->workspace();
        $this->f->app->loginAs('sara@example.com');
        $sara = $this->f->app->page('/workspaces/' . $ws . '/cards');
        $this->assertTrue(str_contains($sara, 'Hidden plan'), 'Sara owns the private board.');

        $this->f->app->loginAs('mona@example.com');
        $page = $this->f->app->page('/workspaces/' . $ws . '/cards');
        $this->assertTrue(str_contains($page, 'Late report') && str_contains($page, 'Shipped'));
        $this->assertFalse(str_contains($page, 'Hidden plan'));

        $overdue = $this->f->app->page('/workspaces/' . $ws . '/cards?due=overdue');
        $this->assertTrue(str_contains($overdue, 'Late report'));
        $this->assertFalse(str_contains($overdue, 'Soon thing'));
    }

    public function testTheTeamReportCountsOnlyVisibleBoards(): void
    {
        $ws = $this->workspace();
        $this->f->app->loginAs('mona@example.com');
        $page = $this->f->app->page('/workspaces/' . $ws . '/report?days=7');
        $this->assertTrue(str_contains($page, 'From 2026-01-14 to 2026-01-20'));
        $this->assertTrue(str_contains($page, 'Launch'));
        $this->assertFalse(str_contains($page, 'Secret'));

        $csv = $this->f->app->get('/workspaces/' . $ws . '/report.csv?days=30');
        $this->assertSame(200, $csv->status);
        $this->assertTrue(str_contains($csv->body, "Mona,2,1,1,0,0"), 'Two open, one overdue, one due soon.');
        $this->assertTrue(str_contains($csv->body, "Sara,1,0,1,1,"), 'One open and due soon, one completed.');
        $this->assertTrue(str_contains($csv->body, 'Launch,2,1,1,33%'));

        $this->f->app->signUp('Eve', 'eve@example.com');
        $this->assertSame(404, $this->f->app->get('/workspaces/' . $ws . '/report')->status);
    }

    public function testTheTimelineDrawsBarsFromStartToDue(): void
    {
        $card = $this->cards()->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Campaign');
        $this->cards()->update($this->f->owner, $card, ['title' => 'Campaign', 'description' => '', 'start_date' => '2026-01-21', 'due_date' => '2026-01-24']);

        // Weeks start on Monday in English: 2026-01-19 is the first column.
        $page = $this->f->app->page('/boards/' . $this->f->board->publicId . '/timeline');
        $this->assertSame(1, preg_match('#class="tl-bar tl-c3 tl-s4[^"]*"[^>]*>\s*<span dir="auto">Campaign#', $page));
        $this->assertFalse(str_contains($page, '>Late report</span>'), 'Late report was due before the window.');
        $this->assertTrue(str_contains($page, '1 cards have no start or due date'));

        $earlier = $this->f->app->page('/boards/' . $this->f->board->publicId . '/timeline?from=2025-12-22');
        $this->assertTrue(str_contains($earlier, 'tl-c25 tl-s1'), 'Late report on 2026-01-15.');
    }

    private function workspace(): string
    {
        return $this->f->app->container->get(WorkspaceDirectory::class)->current($this->f->owner)->publicId;
    }

    private function cards(): CardService
    {
        return $this->f->app->container->get(CardService::class);
    }
}
