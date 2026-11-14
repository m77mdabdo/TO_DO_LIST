<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * The weeks shown for one month in a calendar: whole weeks from the one
 * that holds the 1st to the one that holds the last day.
 */
final class MonthGrid
{
    /** @param list<list<string>> $weeks Y-m-d per day */
    private function __construct(
        public readonly DateTimeImmutable $month,
        public readonly array $weeks,
    ) {
    }

    /**
     * @param string $month "Y-m"; anything else means the month of $today
     * @param int $firstDay 0 = Sunday … 6 = Saturday
     */
    public static function for(string $month, DateTimeImmutable $today, int $firstDay = 0): self
    {
        $start = preg_match('/^(20\d\d|19\d\d)-(0[1-9]|1[0-2])$/', $month) === 1
            ? new DateTimeImmutable($month . '-01')
            : new DateTimeImmutable($today->format('Y-m-01'));

        $offset = ((int) $start->format('w') - $firstDay + 7) % 7;
        $day = $start->modify("-$offset days");
        $last = $start->modify('last day of this month');

        $weeks = [];
        do {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = $day->format('Y-m-d');
                $day = $day->modify('+1 day');
            }
            $weeks[] = $week;
        } while ($day <= $last);

        return new self($start, $weeks);
    }

    public function first(): string
    {
        return $this->weeks[0][0];
    }

    public function last(): string
    {
        return $this->weeks[count($this->weeks) - 1][6];
    }

    public function previous(): string
    {
        return $this->month->modify('-1 month')->format('Y-m');
    }

    public function next(): string
    {
        return $this->month->modify('+1 month')->format('Y-m');
    }

    public function contains(string $date): bool
    {
        return str_starts_with($date, $this->month->format('Y-m'));
    }
}
