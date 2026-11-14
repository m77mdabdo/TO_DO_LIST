<?php

declare(strict_types=1);

namespace App\Modules\Automation\Contract;

/** One line of a board's automation run log. status: ok | failed | skipped */
final class AutomationRun
{
    public function __construct(
        public readonly int $id,
        public readonly int $automationId,
        public readonly string $automationName,
        public readonly ?int $cardId,
        public readonly ?int $actorId,
        public readonly string $status,
        public readonly string $detail,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['automation_id'],
            (string) ($row['automation_name'] ?? ''),
            $row['card_id'] !== null ? (int) $row['card_id'] : null,
            $row['actor_id'] !== null ? (int) $row['actor_id'] : null,
            (string) $row['status'],
            (string) $row['detail'],
            (string) $row['created_at'],
        );
    }
}
