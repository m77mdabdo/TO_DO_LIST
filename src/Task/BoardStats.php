<?php

declare(strict_types=1);

namespace App\Task;

use DateTimeImmutable;
use JsonSerializable;

final class BoardStats implements JsonSerializable
{
    private function __construct(
        public readonly int $total,
        public readonly int $open,
        public readonly int $done,
        public readonly int $overdue,
        public readonly int $dueToday,
        public readonly int $doneToday,
    ) {
    }

    public static function of(Board $board, DateTimeImmutable $today): self
    {
        $open = $board->open();
        $done = $board->column(Status::Done);

        return new self(
            total: $board->count(),
            open: count($open),
            done: count($done),
            overdue: count(array_filter($open, fn (Task $t) => $t->isOverdue($today))),
            dueToday: count(array_filter($open, fn (Task $t) => $t->isDueOn($today))),
            doneToday: count(array_filter($done, fn (Task $t) => $t->wasCompletedOn($today))),
        );
    }

    public function percent(): int
    {
        return $this->total ? (int) round($this->done / $this->total * 100) : 0;
    }

    public function jsonSerialize(): array
    {
        return [
            'total'      => $this->total,
            'open'       => $this->open,
            'done'       => $this->done,
            'overdue'    => $this->overdue,
            'today'      => $this->dueToday,
            'done_today' => $this->doneToday,
            'percent'    => $this->percent(),
        ];
    }
}
