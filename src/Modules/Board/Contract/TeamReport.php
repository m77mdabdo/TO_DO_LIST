<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract;

use App\Modules\Card\Contract\CardSummary;
use DateTimeImmutable;

/**
 * Numbers about a team's work over a period, across the boards the reader
 * can see. They are for following up on work, not for grading people: every
 * number links back to the cards behind it.
 */
final class TeamReport
{
    /**
     * @param array{open: int, done: int, overdue: int, dueSoon: int, noDueDate: int, unassigned: int, created: int, completed: int} $totals
     * @param array<int, array{open: int, overdue: int, dueSoon: int, completed: int, actions: int}> $perPerson user id => numbers
     * @param array<int, array{board: BoardSummary, open: int, done: int, overdue: int}> $perBoard board id => numbers
     * @param array<string, int> $completedByWeek Y-m-d (week start) => cards completed
     */
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly array $totals,
        public readonly array $perPerson,
        public readonly array $perBoard,
        public readonly array $completedByWeek,
    ) {
    }

    /**
     * @param list<BoardSummary> $boards
     * @param list<CardSummary> $openCards open (not archived) cards of those boards
     * @param array<int, list<int>> $members card id => user ids, for open and completed cards
     * @param list<CardSummary> $created cards created in the period
     * @param list<CardSummary> $completed cards completed in the period
     * @param array<int, int> $actions user id => things done in the period (activity)
     */
    public static function build(array $boards, array $openCards, array $members, array $created, array $completed, array $actions, DateTimeImmutable $now, int $days): self
    {
        $today = $now->format('Y-m-d');
        $soon = $now->modify('+7 days')->format('Y-m-d');
        $from = $now->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
        $totals = ['open' => 0, 'done' => 0, 'overdue' => 0, 'dueSoon' => 0, 'noDueDate' => 0, 'unassigned' => 0, 'created' => count($created), 'completed' => count($completed)];
        $person = static fn (): array => ['open' => 0, 'overdue' => 0, 'dueSoon' => 0, 'completed' => 0, 'actions' => 0];
        $perPerson = [];
        $perBoard = [];
        foreach ($boards as $board) {
            $perBoard[$board->id] = ['board' => $board, 'open' => 0, 'done' => 0, 'overdue' => 0];
        }

        foreach ($openCards as $card) {
            if (!isset($perBoard[$card->boardId])) {
                continue;
            }
            if ($card->isComplete()) {
                $totals['done']++;
                $perBoard[$card->boardId]['done']++;
                continue;
            }

            $due = $card->dueDate();
            $late = $due !== null && $due < $today;
            $isSoon = $due !== null && $due >= $today && $due <= $soon;
            $totals['open']++;
            $totals['overdue'] += (int) $late;
            $totals['dueSoon'] += (int) $isSoon;
            $totals['noDueDate'] += (int) ($due === null);
            $totals['unassigned'] += (int) (($members[$card->id] ?? []) === []);
            $perBoard[$card->boardId]['open']++;
            $perBoard[$card->boardId]['overdue'] += (int) $late;

            foreach ($members[$card->id] ?? [] as $userId) {
                $perPerson[$userId] ??= $person();
                $perPerson[$userId]['open']++;
                $perPerson[$userId]['overdue'] += (int) $late;
                $perPerson[$userId]['dueSoon'] += (int) $isSoon;
            }
        }

        $byWeek = [];
        for ($week = self::weekStart(new DateTimeImmutable($from)); $week->format('Y-m-d') <= $today; $week = $week->modify('+7 days')) {
            $byWeek[$week->format('Y-m-d')] = 0;
        }
        foreach ($completed as $card) {
            foreach ($members[$card->id] ?? [] as $userId) {
                $perPerson[$userId] ??= $person();
                $perPerson[$userId]['completed']++;
            }
            $week = self::weekStart(new DateTimeImmutable(substr((string) $card->completedAt, 0, 10)))->format('Y-m-d');
            if (isset($byWeek[$week])) {
                $byWeek[$week]++;
            }
        }

        foreach ($actions as $userId => $count) {
            $perPerson[$userId] ??= $person();
            $perPerson[$userId]['actions'] = $count;
        }
        uasort($perPerson, static fn (array $a, array $b) => [$b['open'], $b['completed']] <=> [$a['open'], $a['completed']]);

        return new self($from, $today, $totals, $perPerson, $perBoard, $byWeek);
    }

    public static function percent(int $done, int $open): int
    {
        return $done + $open === 0 ? 0 : (int) round(100 * $done / ($done + $open));
    }

    private static function weekStart(DateTimeImmutable $day): DateTimeImmutable
    {
        return $day->modify('-' . (((int) $day->format('N')) - 1) . ' days');
    }
}
