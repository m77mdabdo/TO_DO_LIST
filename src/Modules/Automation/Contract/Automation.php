<?php

declare(strict_types=1);

namespace App\Modules\Automation\Contract;

/**
 * One automation on a board: a trigger (or a button, a schedule, a due-date
 * offset), optional conditions, and the actions to run.
 *
 * kind:
 *   rule         - runs when something happens (trigger + triggerValue)
 *   card_button  - a button on every card of the board
 *   board_button - a button on the board that runs on many cards
 *   schedule     - runs every day, week or month at an hour (schedule)
 *   due          - runs a number of days before, on or after a card's due date (schedule)
 *
 * conditions: list of ['type' => 'in_list', 'value' => '12'], all must hold.
 * actions:    list of ['type' => 'card.move', 'value' => '12', 'text' => ''], run in order.
 * schedule:   ['every' => day|week|month, 'day' => int, 'hour' => int]
 *          or ['when' => before|on|after, 'days' => int] for due automations.
 */
final class Automation
{
    public const KINDS = ['rule', 'card_button', 'board_button', 'schedule', 'due'];
    /** The offsets offered for due-date automations, and for "due in N days". */
    public const DUE_DAY_CHOICES = [0, 1, 2, 3, 5, 7, 14, 30];

    /**
     * @param list<array{type: string, value: string}> $conditions
     * @param list<array{type: string, value: string, text: string}> $actions
     * @param array<string, string|int> $schedule
     */
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $boardId,
        public readonly string $kind,
        public readonly string $name,
        public readonly ?string $trigger,
        public readonly ?string $triggerValue,
        public readonly ?int $scopeListId,
        public readonly array $conditions,
        public readonly array $actions,
        public readonly array $schedule,
        public readonly bool $enabled,
        public readonly int $failures,
        public readonly int $createdBy,
        public readonly string $createdAt,
        public readonly ?string $lastRunAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $json = static fn (mixed $v): array => is_string($v) && $v !== '' ? (array) json_decode($v, true) : [];

        return new self(
            (int) $row['id'],
            (string) $row['public_id'],
            (int) $row['board_id'],
            (string) $row['kind'],
            (string) $row['name'],
            $row['trigger_type'] !== null ? (string) $row['trigger_type'] : null,
            $row['trigger_value'] !== null ? (string) $row['trigger_value'] : null,
            $row['scope_list_id'] !== null ? (int) $row['scope_list_id'] : null,
            array_values($json($row['conditions'])),
            array_values($json($row['actions'])),
            $json($row['schedule']),
            (bool) $row['enabled'],
            (int) $row['failures'],
            (int) $row['created_by'],
            (string) $row['created_at'],
            $row['last_run_at'] !== null ? (string) $row['last_run_at'] : null,
        );
    }

    public function isButton(): bool
    {
        return $this->kind === 'card_button' || $this->kind === 'board_button';
    }
}
