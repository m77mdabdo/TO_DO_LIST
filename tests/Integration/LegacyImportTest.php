<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Board\Application\BoardService;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Label\Contract\LabelCatalog;
use App\Modules\LegacyImport\Application\LegacyImporter;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use PDO;
use Tests\Support\App;
use Tests\TestCase;

final class LegacyImportTest extends TestCase
{
    private App $app;

    public function setUp(): void
    {
        $this->app = new App();
    }

    public function testOldTasksBecomeCardsOnMyBoardWhenTheUserLogsIn(): void
    {
        $userId = $this->oldUser();
        $this->task($userId, 'Pay rent', 'todo', 'high', 'Home', '2026-01-25');
        $this->task($userId, 'Write report', 'doing', 'normal', 'Work', null, 'Two pages');
        $this->task($userId, 'Gym', 'done', 'low', null, null, null, '2026-01-10 09:00:00');

        $this->app->loginAs('old@example.com');

        $workspace = $this->app->container->get(WorkspaceDirectory::class)->current($userId);
        $boards = $this->app->container->get(BoardService::class)->boardsInWorkspace($userId, $workspace->id);
        $this->assertSame(['My Board'], array_map(static fn ($b) => $b->name, $boards));

        $lists = $this->app->container->get(BoardService::class)->listsOf($boards[0]);
        $this->assertSame(['To do', 'Doing', 'Done'], array_map(static fn ($l) => $l->name, $lists));

        $byList = $this->app->container->get(CardQueries::class)->openCardsByList($boards[0]->id);
        [$todo, $doing, $done] = [$byList[$lists[0]->id][0], $byList[$lists[1]->id][0], $byList[$lists[2]->id][0]];
        $this->assertSame('Pay rent', $todo->title);
        $this->assertSame('2026-01-25', $todo->dueDate());
        $this->assertSame('Two pages', $doing->description);
        $this->assertSame('2026-01-10 09:00:00', $done->completedAt);

        $labels = $this->app->container->get(LabelCatalog::class)->forCards([$todo->id, $doing->id, $done->id]);
        $this->assertSame(['High priority', 'Home'], array_map(static fn ($l) => $l->name, $labels[$todo->id]));
        $this->assertSame(['Work'], array_map(static fn ($l) => $l->name, $labels[$doing->id]));
        $this->assertSame(['Low priority'], array_map(static fn ($l) => $l->name, $labels[$done->id]));
    }

    public function testTheImportRunsOnceAndPicksUpOnlyNewTasks(): void
    {
        $userId = $this->oldUser();
        $this->task($userId, 'One', 'todo');
        $importer = $this->app->container->get(LegacyImporter::class);

        $first = $importer->importFor($userId, 'Old');
        $this->task($userId, 'Two', 'todo');
        $second = $importer->importFor($userId, 'Old');
        $third = $importer->importFor($userId, 'Old');

        $this->assertSame([1, 1, 0], [$first->importedNow, $second->importedNow, $third->importedNow]);
        $this->assertTrue($third->isComplete());
        $this->assertSame(['tasks' => 2, 'imported' => 2], $importer->totals());
        $this->assertSame(1, (int) $this->app->pdo()->query('SELECT COUNT(*) FROM boards')->fetchColumn());
    }

    public function testEachUsersTasksStayInTheirOwnWorkspace(): void
    {
        $a = $this->oldUser('a@example.com');
        $b = $this->oldUser('b@example.com');
        $this->task($a, 'Mine', 'todo');
        $this->task($b, 'Theirs', 'todo');
        $importer = $this->app->container->get(LegacyImporter::class);

        foreach ($importer->usersWithPendingTasks() as $userId) {
            $importer->importFor($userId, 'User');
        }

        $workspaceOfCard = $this->app->pdo()->query('SELECT c.title, b.workspace_id, wm.user_id FROM cards c
            JOIN boards b ON b.id = c.board_id JOIN workspace_members wm ON wm.workspace_id = b.workspace_id ORDER BY c.title')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame([['Mine', $a], ['Theirs', $b]], array_map(static fn ($r) => [$r['title'], (int) $r['user_id']], $workspaceOfCard));
        $this->assertSame([], $importer->usersWithPendingTasks());
    }

    private function oldUser(string $email = 'old@example.com'): int
    {
        // Created straight in the database, like an account from before the upgrade (no workspace yet).
        $this->app->pdo()->prepare('INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, ?)')
            ->execute(['Old', $email, password_hash('secret123', PASSWORD_BCRYPT), '2025-12-01 10:00:00']);

        return (int) $this->app->pdo()->lastInsertId();
    }

    private function task(int $userId, string $title, string $status, string $priority = 'normal', ?string $category = null, ?string $due = null, ?string $notes = null, ?string $completed = null): void
    {
        $this->app->pdo()->prepare('INSERT INTO tasks (user_id, title, notes, status, priority, category, due_date, completed_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$userId, $title, $notes, $status, $priority, $category, $due, $completed, '2026-01-05 10:00:00', '2026-01-05 10:00:00']);
    }
}
