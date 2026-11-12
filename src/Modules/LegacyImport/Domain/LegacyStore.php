<?php

declare(strict_types=1);

namespace App\Modules\LegacyImport\Domain;

interface LegacyStore
{
    /** @return list<array<string, mixed>> the user's old tasks not imported yet, oldest first */
    public function pendingTasks(int $userId): array;

    /** @return list<int> users who still have old tasks to import */
    public function usersWithPendingTasks(): array;

    /** @return array{boardId: int, todo: int, doing: int, done: int}|null */
    public function boardOf(int $userId): ?array;

    public function rememberBoard(int $userId, int $boardId, int $todo, int $doing, int $done): void;

    public function markImported(int $taskId, int $cardId, int $userId): void;

    /** @return array{tasks: int, imported: int} */
    public function counts(?int $userId = null): array;
}
