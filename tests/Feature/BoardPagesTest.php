<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Workspace\Contract\WorkspaceRole;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

/** The board screens over HTTP, including the security cases from the spec. */
final class BoardPagesTest extends TestCase
{
    private BoardFixture $f;

    public function setUp(): void
    {
        $this->f = new BoardFixture(new App());
    }

    public function testHomeListsBoardsAndCreatesNewOnes(): void
    {
        $this->assertTrue(str_contains($this->f->app->page('/'), 'Launch'));

        $response = $this->f->app->post('/boards', ['name' => 'Hiring', 'color' => 'mint', 'visibility' => 'private']);

        $this->assertTrue(str_starts_with((string) $response->header('Location'), '/boards/'));
        $this->assertTrue(str_contains($this->f->app->page((string) $response->header('Location')), 'Hiring'));
    }

    public function testTheBoardPageShowsListsCardsAndActivity(): void
    {
        $board = '/boards/' . $this->f->board->publicId;
        $this->f->app->post($board . '/cards', ['list_id' => (string) $this->f->todo->id, 'title' => 'Write the brief']);

        $html = $this->f->app->page($board);

        $this->assertTrue(str_contains($html, 'Doing'));
        $this->assertTrue(str_contains($html, 'Write the brief'));
        $this->assertTrue(str_contains($html, 'added <bdi class="activity-name">Write the brief</bdi>'));
    }

    public function testDragAndDropMoveOverJson(): void
    {
        $board = '/boards/' . $this->f->board->publicId;
        $this->f->app->post($board . '/cards', ['list_id' => (string) $this->f->todo->id, 'title' => 'A']);
        $card = $this->openCards()[$this->f->todo->id][0];

        $response = $this->f->app->post('/cards/' . $card->publicId . '/move', ['list_id' => (string) $this->f->done->id, 'after_id' => ''], json: true);

        $this->assertSame(200, $response->status);
        $this->assertSame('A', $this->openCards()[$this->f->done->id][0]->title);
    }

    public function testCardPageEditsTheCard(): void
    {
        $this->f->app->post('/boards/' . $this->f->board->publicId . '/cards', ['list_id' => (string) $this->f->todo->id, 'title' => 'Draft']);
        $card = $this->openCards()[$this->f->todo->id][0];
        $path = '/cards/' . $card->publicId;

        $this->f->app->post($path, ['title' => 'Final', 'description' => 'Notes', 'due_date' => '2026-02-10']);
        $this->f->app->post('/boards/' . $this->f->board->publicId . '/labels', ['name' => 'Urgent', 'color' => 'red', 'card' => $card->publicId]);

        $html = $this->f->app->page($path);
        $this->assertTrue(str_contains($html, 'value="Final"'));
        $this->assertTrue(str_contains($html, 'Urgent'));
        $this->assertTrue(str_contains($this->f->app->page('/boards/' . $this->f->board->publicId), 'label-red'));
    }

    public function testAssigningSomeoneShowsThemOnTheCardAndTheBoard(): void
    {
        $this->f->app->post('/boards/' . $this->f->board->publicId . '/cards', ['list_id' => (string) $this->f->todo->id, 'title' => 'Draft']);
        $card = $this->openCards()[$this->f->todo->id][0];

        $this->f->app->post('/cards/' . $card->publicId . '/members', ['user_id' => (string) $this->f->owner]);

        $this->assertTrue(str_contains($this->f->app->page('/cards/' . $card->publicId), 'Remove from card'));
        $this->assertTrue(str_contains($this->f->app->page('/boards/' . $this->f->board->publicId), 'avatar-xs'));
    }

    public function testDescriptionIsRenderedAsSafeMarkdown(): void
    {
        $this->f->app->post('/boards/' . $this->f->board->publicId . '/cards', ['list_id' => (string) $this->f->todo->id, 'title' => 'Draft']);
        $card = $this->openCards()[$this->f->todo->id][0];

        $this->f->app->post('/cards/' . $card->publicId, ['title' => 'Draft', 'description' => "**Scope**\n<script>alert(1)</script>"]);

        $html = $this->f->app->page('/cards/' . $card->publicId);
        $this->assertTrue(str_contains($html, '<strong>Scope</strong>'));
        $this->assertFalse(str_contains($html, '<script>alert'));
    }

    public function testChecklistShowsOnTheCardAndItsProgressOnTheBoard(): void
    {
        $this->f->app->post('/boards/' . $this->f->board->publicId . '/cards', ['list_id' => (string) $this->f->todo->id, 'title' => 'Draft']);
        $card = $this->openCards()[$this->f->todo->id][0];

        $this->f->app->post('/cards/' . $card->publicId . '/checklists', ['title' => 'Steps']);
        $html = $this->f->app->page('/cards/' . $card->publicId);
        $this->assertTrue(str_contains($html, 'Steps'));
        preg_match('#/checklists/(\d+)/items#', $html, $m);

        $this->f->app->post('/checklists/' . $m[1] . '/items', ['text' => 'Write intro']);
        $this->f->app->post('/checklists/' . $m[1] . '/items', ['text' => 'Proofread']);
        preg_match('#/checklist-items/(\d+)/toggle#', $this->f->app->page('/cards/' . $card->publicId), $item);
        $toggle = $this->f->app->post('/checklist-items/' . $item[1] . '/toggle', [], json: true);

        $this->assertSame(200, $toggle->status);
        $this->assertTrue(str_contains($this->f->app->page('/boards/' . $this->f->board->publicId), '1/2'));
    }

    public function testCommentsAndVotesShowOnTheCardAndTheBoard(): void
    {
        $this->f->app->post('/boards/' . $this->f->board->publicId . '/cards', ['list_id' => (string) $this->f->todo->id, 'title' => 'Draft']);
        $card = $this->openCards()[$this->f->todo->id][0];
        $mona = $this->f->colleague('mona@example.com');
        $this->f->app->container->get(\App\Modules\Access\Contract\BoardMembers::class)->add($this->f->board->id, $mona, \App\Modules\Access\Contract\BoardRole::Member);
        $this->f->app->loginAs('sara@example.com');

        $this->f->app->post('/cards/' . $card->publicId . '/comments', ['body' => 'Ready for @Mona? <img src=x onerror=alert(1)>']);
        $this->f->app->post('/cards/' . $card->publicId . '/vote');

        $html = $this->f->app->page('/cards/' . $card->publicId);
        $this->assertTrue(str_contains($html, '<span class="mention">@Mona</span>'));
        $this->assertFalse(str_contains($html, '<img src=x'));
        $this->assertTrue(str_contains($html, 'Remove my vote'));

        $board = $this->f->app->page('/boards/' . $this->f->board->publicId);
        $this->assertTrue(str_contains($board, 'title="Comments"'));
        $this->assertTrue(str_contains($board, 'title="Votes"'));
    }

    public function testOtherCompaniesGetNotFoundForBoardsAndCards(): void
    {
        $this->f->app->post('/boards/' . $this->f->board->publicId . '/cards', ['list_id' => (string) $this->f->todo->id, 'title' => 'Secret']);
        $card = $this->openCards()[$this->f->todo->id][0];
        $this->f->app->signUp('Omar', 'omar@example.com');

        $this->assertSame(404, $this->f->app->get('/boards/' . $this->f->board->publicId)->status);
        $this->assertSame(404, $this->f->app->get('/cards/' . $card->publicId)->status);
        $this->assertSame(404, $this->f->app->post('/cards/' . $card->publicId, ['title' => 'Pwned'])->status);
        $this->assertSame(404, $this->f->app->post('/boards/' . $this->f->board->publicId . '/cards', ['list_id' => (string) $this->f->todo->id, 'title' => 'x'])->status);
        $this->assertSame(404, $this->f->app->get('/boards/' . $this->f->board->publicId . '/export')->status);
        $this->assertSame('Secret', $this->f->app->container->get(CardQueries::class)->find($card->id)->title);
    }

    public function testViewersSeeTheBoardButCannotChangeIt(): void
    {
        $this->f->app->post('/boards/' . $this->f->board->publicId . '/cards', ['list_id' => (string) $this->f->todo->id, 'title' => 'Plan']);
        $card = $this->openCards()[$this->f->todo->id][0];
        $viewer = $this->f->colleague('mona@example.com', WorkspaceRole::Member);
        $this->f->app->container->get(BoardMembers::class)->add($this->f->board->id, $viewer, BoardRole::Viewer);

        $html = $this->f->app->page('/boards/' . $this->f->board->publicId);

        $this->assertTrue(str_contains($html, 'Plan'));
        $this->assertFalse(str_contains($html, 'name="list_id"'));
        $this->assertSame(403, $this->f->app->post('/cards/' . $card->publicId . '/move', ['list_id' => (string) $this->f->done->id], json: true)->status);
        $this->assertSame(403, $this->f->app->post('/cards/' . $card->publicId, ['title' => 'Changed'])->status);
        $this->assertSame(403, $this->f->app->post('/boards/' . $this->f->board->publicId . '/lists', ['name' => 'Mine'])->status);
    }

    public function testExportGivesCsvInBoardOrder(): void
    {
        $board = '/boards/' . $this->f->board->publicId;
        $this->f->app->post($board . '/cards', ['list_id' => (string) $this->f->doing->id, 'title' => '=cmd()']);
        $this->f->app->post($board . '/cards', ['list_id' => (string) $this->f->todo->id, 'title' => 'First']);

        $csv = $this->f->app->get($board . '/export')->body;

        $this->assertTrue(strpos($csv, 'First') < strpos($csv, 'cmd'));
        $this->assertTrue(str_contains($csv, "'=cmd()"));
    }

    public function testArchivingAndRestoringACard(): void
    {
        $this->f->app->post('/boards/' . $this->f->board->publicId . '/cards', ['list_id' => (string) $this->f->todo->id, 'title' => 'Old']);
        $card = $this->openCards()[$this->f->todo->id][0];

        $this->f->app->post('/cards/' . $card->publicId . '/archive');
        $this->assertSame([], $this->openCards());
        $this->assertTrue(str_contains($this->f->app->page('/boards/' . $this->f->board->publicId . '/archived'), 'Old'));

        $this->f->app->post('/cards/' . $card->publicId . '/restore');
        $this->assertCount(1, $this->openCards()[$this->f->todo->id]);
    }

    /** @return array<int, list<\App\Modules\Card\Contract\CardSummary>> */
    private function openCards(): array
    {
        return $this->f->app->container->get(CardQueries::class)->openCardsByList($this->f->board->id);
    }
}
