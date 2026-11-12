<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract;

use App\Modules\Card\Contract\CardSummary;
use DateTimeImmutable;

/**
 * The board page's filters, read from the query string:
 *
 *   q       text in the title or description
 *   label   a label id
 *   member  "me", "none" or a user id
 *   due     overdue | today | week | none
 *   status  open | done
 *
 * Unknown values are ignored, never echoed into SQL: filtering happens in
 * memory on the cards the page already loaded, one pass, O(cards).
 */
final class CardFilter
{
    private const DUE = ['overdue', 'today', 'week', 'none'];
    private const STATUS = ['open', 'done'];

    private function __construct(
        public readonly string $q,
        public readonly ?int $label,
        public readonly string $member,
        public readonly string $due,
        public readonly string $status,
    ) {
    }

    /** @param callable(string): string $input reads one query value */
    public static function fromInput(callable $input): self
    {
        $label = $input('label');
        $member = $input('member');

        return new self(
            mb_substr($input('q'), 0, 100),
            ctype_digit($label) && $label !== '0' ? (int) $label : null,
            in_array($member, ['me', 'none'], true) || (ctype_digit($member) && $member !== '0') ? $member : '',
            in_array($input('due'), self::DUE, true) ? $input('due') : '',
            in_array($input('status'), self::STATUS, true) ? $input('status') : '',
        );
    }

    public function isActive(): bool
    {
        return $this->q !== '' || $this->label !== null || $this->member !== '' || $this->due !== '' || $this->status !== '';
    }

    /**
     * @param array<int, list<CardSummary>> $cardsByList
     * @param array<int, list<\App\Modules\Label\Contract\Label>> $labels card id => labels
     * @param array<int, list<int>> $members card id => user ids
     * @return array<int, list<CardSummary>> the same shape, holding only matching cards
     */
    public function apply(array $cardsByList, array $labels, array $members, int $userId, DateTimeImmutable $today): array
    {
        if (!$this->isActive()) {
            return $cardsByList;
        }

        $filtered = [];
        foreach ($cardsByList as $listId => $cards) {
            $filtered[$listId] = array_values(array_filter(
                $cards,
                fn (CardSummary $card) => $this->matches($card, $labels[$card->id] ?? [], $members[$card->id] ?? [], $userId, $today),
            ));
        }

        return $filtered;
    }

    /**
     * @param list<\App\Modules\Label\Contract\Label> $labels
     * @param list<int> $members
     */
    public function matches(CardSummary $card, array $labels, array $members, int $userId, DateTimeImmutable $today): bool
    {
        if ($this->q !== '' && mb_stripos($card->title, $this->q) === false && mb_stripos((string) $card->description, $this->q) === false) {
            return false;
        }

        if ($this->label !== null && !in_array($this->label, array_map(static fn ($l) => $l->id, $labels), true)) {
            return false;
        }

        $memberOk = match ($this->member) {
            ''      => true,
            'me'    => in_array($userId, $members, true),
            'none'  => $members === [],
            default => in_array((int) $this->member, $members, true),
        };
        if (!$memberOk) {
            return false;
        }

        $todayDate = $today->format('Y-m-d');
        $dueOk = match ($this->due) {
            ''        => true,
            'none'    => $card->dueAt === null,
            'overdue' => $card->isOverdue($today),
            'today'   => $card->dueDate() === $todayDate && !$card->isComplete(),
            'week'    => $card->dueDate() !== null && !$card->isComplete()
                && $card->dueDate() >= $todayDate && $card->dueDate() <= $today->modify('+7 days')->format('Y-m-d'),
        };
        if (!$dueOk) {
            return false;
        }

        return match ($this->status) {
            ''     => true,
            'open' => !$card->isComplete(),
            'done' => $card->isComplete(),
        };
    }

    /** @return array<string, string> the active filters, for links that keep them */
    public function query(): array
    {
        return array_filter([
            'q' => $this->q, 'label' => (string) $this->label, 'member' => $this->member, 'due' => $this->due, 'status' => $this->status,
        ], static fn (string $value) => $value !== '');
    }
}
