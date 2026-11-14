<?php

declare(strict_types=1);

namespace App\Modules\Automation\Domain;

/**
 * Checks an automation's conditions against what is known about a card.
 * Pure: the engine gathers the facts (in grouped queries) and asks here.
 */
final class Conditions
{
    /** type => does it take a value (a list or label id)? */
    public const TYPES = [
        'in_list'      => true,
        'not_in_list'  => true,
        'has_label'    => true,
        'lacks_label'  => true,
        'has_members'  => false,
        'no_members'   => false,
        'has_due'      => false,
        'no_due'       => false,
        'overdue'      => false,
        'complete'     => false,
        'not_complete' => false,
    ];

    /**
     * @param list<array<string, string>> $conditions
     * @param array{list: int, labels: list<int>, members: list<int>, due: ?string, complete: bool, today: string} $card
     */
    public static function hold(array $conditions, array $card): bool
    {
        foreach ($conditions as $condition) {
            $value = (int) ($condition['value'] ?? 0);
            $holds = match ($condition['type'] ?? '') {
                'in_list'      => $card['list'] === $value,
                'not_in_list'  => $card['list'] !== $value,
                'has_label'    => in_array($value, $card['labels'], true),
                'lacks_label'  => !in_array($value, $card['labels'], true),
                'has_members'  => $card['members'] !== [],
                'no_members'   => $card['members'] === [],
                'has_due'      => $card['due'] !== null,
                'no_due'       => $card['due'] === null,
                'overdue'      => $card['due'] !== null && !$card['complete'] && $card['due'] < $card['today'],
                'complete'     => $card['complete'],
                'not_complete' => !$card['complete'],
                default        => false,
            };

            if (!$holds) {
                return false;
            }
        }

        return true;
    }
}
