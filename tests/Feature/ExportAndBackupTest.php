<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Database\Backup;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Board\Contract\BoardVisibility;
use App\Modules\Card\Application\CardService;
use App\Modules\Checklist\Application\ChecklistService;
use App\Modules\Discussion\Application\DiscussionService;
use App\Modules\Label\Contract\LabelCatalog;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceRole;
use PDO;
use Tests\Support\App;
use Tests\Support\BoardFixture;
use Tests\TestCase;

final class ExportAndBackupTest extends TestCase
{
    private BoardFixture $f;

    public function setUp(): void
    {
        $this->f = new BoardFixture(new App(), BoardVisibility::Workspace);
        $c = $this->f->app->container;
        $card = $c->get(CardService::class)->createCard($this->f->owner, $this->f->board->id, $this->f->todo->id, 'Plan', ['description' => 'نص عربي', 'due_date' => '2026-02-01']);
        $c->get(CardService::class)->toggleMember($this->f->owner, $card, $this->f->owner);
        $c->get(LabelCatalog::class)->attach($this->f->owner, $card->id, $c->get(LabelCatalog::class)->ensureLabel($this->f->owner, $this->f->board->id, 'Design', 'purple')->id);
        $list = $c->get(ChecklistService::class)->addChecklist($this->f->owner, $card, 'Steps');
        $c->get(ChecklistService::class)->addItem($this->f->owner, $list, 'Sketch');
        $c->get(DiscussionService::class)->post($this->f->owner, $card, 'First idea');
    }

    public function testABoardExportsEverythingAsJson(): void
    {
        $response = $this->f->app->get('/boards/' . $this->f->board->publicId . '/export.json');
        $this->assertSame(200, $response->status);
        $this->assertTrue(str_starts_with((string) $response->header('Content-Disposition'), 'attachment'));

        $data = json_decode($response->body, true);
        $this->assertSame('todo-board/1', $data['format']);
        $card = $data['boards'][0]['cards'][0];
        $this->assertSame(['Plan', 'To do', 'نص عربي', '2026-02-01', ['Sara']], [$card['title'], $card['list'], $card['description'], $card['due'], $card['people']]);
        $this->assertSame([['name' => 'Design', 'color' => 'purple']], $card['labels']);
        $this->assertSame('Sketch', $card['checklists'][0]['items'][0]['text']);
        $this->assertSame('First idea', $card['comments'][0]['body']);
        $this->assertSame(['To do', 'Doing', 'Done'], array_column($data['boards'][0]['lists'], 'name'));
    }

    public function testOnlyPeopleWhoMayExportGetIt(): void
    {
        $viewer = $this->f->colleague('viewer@example.com');
        $this->f->app->container->get(BoardMembers::class)->add($this->f->board->id, $viewer, BoardRole::Viewer);
        $this->f->app->loginAs('viewer@example.com');
        $this->assertSame(403, $this->f->app->get('/boards/' . $this->f->board->publicId . '/export.json')->status);

        $workspace = $this->f->app->container->get(WorkspaceDirectory::class)->current($this->f->owner);
        $this->assertSame(403, $this->f->app->get('/workspaces/' . $workspace->publicId . '/export.json')->status, 'Members do not see every board, so they can not export the workspace.');

        $this->f->colleague('admin@example.com', WorkspaceRole::Admin);
        $this->f->app->loginAs('admin@example.com');
        $data = json_decode($this->f->app->get('/workspaces/' . $workspace->publicId . '/export.json')->body, true);
        $this->assertSame(['Launch'], array_column($data['boards'], 'name'));
    }

    public function testTheDatabaseIsBackedUpAndOldCopiesArePruned(): void
    {
        $dir = App::scratch('backup');
        $backup = new Backup($this->f->app->container->get(PDO::class), $dir);

        $first = $backup->database('20260101-000000-aaaa');
        $copy = new PDO('sqlite:' . $first);
        $this->assertSame('Plan', $copy->query('SELECT title FROM cards')->fetchColumn());

        $backup->database('20260102-000000-bbbb');
        $backup->database('20260103-000000-cccc');
        $this->assertCount(1, $backup->prune(2));
        $this->assertSame(['database-20260102-000000-bbbb.sqlite', 'database-20260103-000000-cccc.sqlite'], array_map('basename', glob($dir . '/database-*') ?: []));
    }
}
