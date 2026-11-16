<?php

declare(strict_types=1);

namespace App\Modules\Board\Domain;

/** A change counter per board: raised on every change, read by open board pages. */
interface BoardVersions
{
    public function bump(int $boardId, ?int $actorId): void;

    /** @return array{version: int, actorId: ?int, changedAt: ?string} version 0 when nothing changed yet */
    public function current(int $boardId): array;
}
