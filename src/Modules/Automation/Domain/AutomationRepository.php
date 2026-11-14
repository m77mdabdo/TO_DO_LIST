<?php

declare(strict_types=1);

namespace App\Modules\Automation\Domain;

use App\Modules\Automation\Contract\Automation;

interface AutomationRepository
{
    /** @param array<string, mixed> $fields the automations columns (JSON columns already encoded) */
    public function create(array $fields): Automation;

    public function findByPublicId(string $publicId): ?Automation;

    /** @return list<Automation> every automation of the board, oldest first */
    public function forBoard(int $boardId): array;

    public function countForBoard(int $boardId): int;

    /** @return list<Automation> enabled rules of the board listening for $trigger */
    public function rulesFor(int $boardId, string $trigger): array;

    /** @return list<Automation> enabled automations of one kind on every board (the scheduler) */
    public function enabledOfKind(string $kind): array;

    public function setEnabled(int $id, bool $enabled): void;

    /** Records a run: resets the failure count on success, otherwise adds one and returns the new count. */
    public function recordRun(int $id, bool $ok): int;

    public function delete(int $id): void;
}
