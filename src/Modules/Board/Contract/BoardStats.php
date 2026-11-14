<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract;

use App\Modules\Card\Contract\CardSummary;
use App\Modules\Label\Contract\Label;
use DateTimeImmutable;

/**
 * Numbers for a board's dashboard, worked out in one pass over the cards
 * the board page already loads (archived cards are left out).
 */
final class BoardStats
{
    public const TREND_DAYS = 14;

    /**
     * @param array<int, array{name: string, open: int, done: int}> $perList
     * @param array<int, array{open: int, overdue: int, done: int}> $perPerson user id => counts
     * @param list<array{label: Label, count: int}> $perLabel
     * @param array<string, int> $doneByDay Y-m-d => cards completed that day, oldest first
     */
    private function __construct(
        public readonly int $total,
        public readonly int $open,
        public readonly int $done,
        public readonly int $overdue,
        public readonly int $dueToday,
        public readonly int $dueSoon,
        public readonly int $noDueDate,
        public readonly int $unassigned,
        public readonly array $perList,
        public readonly array $perPerson,
        public readonly array $perLabel,
        public readonly array $doneByDay,
    ) {
    }

    /**
     * @param list<ListSummary> $lists
     * @param array<int, list<CardSummary>> $cardsByList
     * @param array<int, list<Label>> $cardLabels
     * @param array<int, list<int>> $cardMembers
     */
    public static function from(array $lists, array $cardsByList, array $cardLabels, array $cardMembers, DateTimeImmutable $now): self
    {
        $today = $now->format('Y-m-d');
        $soon = $now->modify('+7 days')->format('Y-m-d');
        $doneByDay = [];
        for ($i = self::TREND_DAYS - 1; $i >= 0; $i--) {
            $doneByDay[$now->modify("-$i days")->format('Y-m-d')] = 0;
        }

        $total = $done = $overdue = $dueToday = $dueSoon = $noDue = $unassigned = 0;
        $perList = [];
        $perPerson = [];
        $labelCounts = [];
        $labelsById = [];

        foreach ($lists as $list) {
            $perList[$list->id] = ['name' => $list->name, 'open' => 0, 'done' => 0];
            foreach ($cardsByList[$list->id] ?? [] as $card) {
                $total++;
                $isDone = $card->isComplete();
                $isLate = $card->isOverdue($now);
                $perList[$list->id][$isDone ? 'done' : 'open']++;

                if ($isDone) {
                    $done++;
                    $day = substr((string) $card->completedAt, 0, 10);
                    if (isset($doneByDay[$day])) {
                        $doneByDay[$day]++;
                    }
                } else {
                    $due = $card->dueDate();
                    match (true) {
                        $due === null => $noDue++,
                        $isLate       => $overdue++,
                        $due === $today => $dueToday++,
                        $due <= $soon => $dueSoon++,
                        default       => null,
                    };
                }

                $members = $cardMembers[$card->id] ?? [];
                if ($members === [] && !$isDone) {
                    $unassigned++;
                }
                foreach ($members as $userId) {
                    $perPerson[$userId] ??= ['open' => 0, 'overdue' => 0, 'done' => 0];
                    $perPerson[$userId][$isDone ? 'done' : 'open']++;
                    if ($isLate) {
                        $perPerson[$userId]['overdue']++;
                    }
                }
                foreach ($cardLabels[$card->id] ?? [] as $label) {
                    $labelsById[$label->id] = $label;
                    $labelCounts[$label->id] = ($labelCounts[$label->id] ?? 0) + 1;
                }
            }
        }

        uasort($perPerson, static fn (array $a, array $b) => ($b['open'] + $b['done']) <=> ($a['open'] + $a['done']));
        arsort($labelCounts);

        return new self(
            $total,
            $total - $done,
            $done,
            $overdue,
            $dueToday,
            $dueSoon,
            $noDue,
            $unassigned,
            $perList,
            $perPerson,
            array_map(static fn (int $id) => ['label' => $labelsById[$id], 'count' => $labelCounts[$id]], array_keys($labelCounts)),
            $doneByDay,
        );
    }

    public function percentDone(): int
    {
        return $this->total === 0 ? 0 : (int) round($this->done * 100 / $this->total);
    }
}
