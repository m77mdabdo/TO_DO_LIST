<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract;

/**
 * The widgets one person keeps on a board's dashboard, in their order.
 * Every widget counts only the board's cards, which everyone who can open
 * the dashboard can already see.
 */
final class DashboardLayout
{
    /** key => [title, where its numbers come from] (both translation keys) */
    public const WIDGETS = [
        'progress' => ['Progress', 'Cards on this board that are marked done, out of all open (not archived) cards.'],
        'summary'  => ['Summary', 'Open (not archived) cards on this board, counted by due date and by whether someone is on them. Each number opens the board with that filter.'],
        'lists'    => ['Cards per list', 'Open and done cards in each list of this board. Archived cards and lists are left out.'],
        'people'   => ['Work per person', 'Cards each person is on, open and done, and how many of the open ones are overdue.'],
        'labels'   => ['Labels', 'How many open (not archived) cards carry each label of this board.'],
        'trend'    => ['Completed in the last 14 days', 'Cards on this board marked done on each of the last 14 days, by the date they were completed.'],
    ];

    /** @param list<string> $widgets */
    public function __construct(public readonly array $widgets, public readonly bool $saved = false)
    {
    }

    public static function default(): self
    {
        return new self(array_keys(self::WIDGETS));
    }

    /** @return list<string> widgets that are not shown, to add back */
    public function hidden(): array
    {
        return array_values(array_diff(array_keys(self::WIDGETS), $this->widgets));
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $widgets = is_array($data['widgets'] ?? null) ? array_values(array_unique(array_filter($data['widgets'], static fn ($w) => is_string($w) && isset(self::WIDGETS[$w])))) : array_keys(self::WIDGETS);

        return new self($widgets, true);
    }
}
