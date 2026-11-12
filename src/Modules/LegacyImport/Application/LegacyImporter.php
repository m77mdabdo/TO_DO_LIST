<?php

declare(strict_types=1);

namespace App\Modules\LegacyImport\Application;

use App\Database\Transactions;
use App\I18n\Translator;
use App\Modules\Board\Contract\BoardCatalog;
use App\Modules\Card\Contract\CardWriter;
use App\Modules\Label\Contract\Label;
use App\Modules\Label\Contract\LabelCatalog;
use App\Modules\LegacyImport\Domain\LegacyStore;
use App\Modules\Workspace\Contract\WorkspaceProvisioning;
use Throwable;

/**
 * Moves the old single-board tasks into the new model, per user:
 *
 *   tasks of a user  -> cards on their "My Board" (in their own workspace)
 *   status           -> list: To do / Doing / Done
 *   category         -> a label with that name
 *   priority         -> "High priority" / "Low priority" labels (normal gets none)
 *   notes, due date, completed and created times -> kept on the card
 *
 * Each task is recorded in legacy_task_map, so the import is idempotent,
 * every old row stays traceable, and the counts can be checked afterwards.
 * The old tasks table is left untouched.
 *
 * All of a user's tasks go in one transaction: they all arrive or none do.
 * Labels are looked up once per name and cached in a hash map, so N tasks
 * cost O(N) card inserts plus one label lookup per distinct name.
 */
final class LegacyImporter
{
    private const STATUS_LIST = ['todo' => 'todo', 'doing' => 'doing', 'done' => 'done'];

    public function __construct(
        private LegacyStore $store,
        private WorkspaceProvisioning $workspaces,
        private BoardCatalog $boards,
        private CardWriter $cards,
        private LabelCatalog $labels,
        private Transactions $transactions,
        private Translator $translator,
    ) {
    }

    public function importFor(int $userId, string $userName): ImportReport
    {
        $tasks = $this->store->pendingTasks($userId);
        $problems = [];
        $imported = 0;

        if ($tasks !== []) {
            try {
                $imported = $this->transactions->run(fn (): int => $this->import($userId, $userName, $tasks));
            } catch (Throwable $e) {
                $problems[] = "User $userId: " . $e->getMessage();
            }
        }

        $counts = $this->store->counts($userId);

        return new ImportReport($userId, $imported, $counts['tasks'], $counts['imported'], $problems);
    }

    /** @return list<int> */
    public function usersWithPendingTasks(): array
    {
        return $this->store->usersWithPendingTasks();
    }

    /** @return array{tasks: int, imported: int} */
    public function totals(): array
    {
        return $this->store->counts();
    }

    /** @param list<array<string, mixed>> $tasks */
    private function import(int $userId, string $userName, array $tasks): int
    {
        $board = $this->store->boardOf($userId) ?? $this->createBoard($userId, $userName);
        $labelCache = [];

        foreach ($tasks as $task) {
            $status = self::STATUS_LIST[(string) $task['status']] ?? 'todo';

            $card = $this->cards->createCard($userId, $board['boardId'], $board[$status], (string) $task['title'], [
                'description'  => $task['notes'] !== null && $task['notes'] !== '' ? (string) $task['notes'] : null,
                'due_date'     => $task['due_date'] !== null && $task['due_date'] !== '' ? substr((string) $task['due_date'], 0, 10) : null,
                'completed_at' => $task['completed_at'] !== null ? (string) $task['completed_at'] : null,
                'created_at'   => (string) $task['created_at'],
            ]);

            foreach ($this->labelsFor($task) as [$name, $color]) {
                $labelCache[$name] ??= $this->labels->ensureLabel($userId, $board['boardId'], $name, $color);
                $this->labels->attach($userId, $card->id, $labelCache[$name]->id);
            }

            $this->store->markImported((int) $task['id'], $card->id, $userId);
        }

        return count($tasks);
    }

    /** @return array{boardId: int, todo: int, doing: int, done: int} */
    private function createBoard(int $userId, string $userName): array
    {
        $workspaceId = $this->workspaces->ensureWorkspaceFor($userId, $userName);
        $board = $this->boards->createBoard($userId, $workspaceId, $this->translator->t('My Board'));
        $todo = $this->boards->addList($userId, $board->id, $this->translator->t('To do'))->id;
        $doing = $this->boards->addList($userId, $board->id, $this->translator->t('Doing'))->id;
        $done = $this->boards->addList($userId, $board->id, $this->translator->t('Done'))->id;
        $this->store->rememberBoard($userId, $board->id, $todo, $doing, $done);

        return ['boardId' => $board->id, 'todo' => $todo, 'doing' => $doing, 'done' => $done];
    }

    /**
     * @param array<string, mixed> $task
     * @return list<array{0: string, 1: string}> label name and color
     */
    private function labelsFor(array $task): array
    {
        $labels = [];
        $category = trim((string) ($task['category'] ?? ''));

        if ($category !== '') {
            $name = mb_substr($category, 0, 60);
            // The same category always gets the same color.
            $labels[] = [$name, Label::COLORS[crc32(mb_strtolower($name)) % count(Label::COLORS)]];
        }

        $priority = match ((string) $task['priority']) {
            'high'  => [$this->translator->t('High priority'), 'red'],
            'low'   => [$this->translator->t('Low priority'), 'slate'],
            default => null,
        };

        if ($priority !== null) {
            $labels[] = $priority;
        }

        return $labels;
    }
}
