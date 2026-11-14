<?php

declare(strict_types=1);

namespace App\Modules\Integration\Contract;

/** Sends a signed JSON message to a URL when chosen things happen on a board. */
final class Webhook
{
    /** event => description, the things a webhook can be told about */
    public const EVENTS = [
        'card.created'        => 'A card was added',
        'card.moved'          => 'A card was moved to another list',
        'card.changed'        => 'A card was renamed, completed, given a date, archived or restored',
        'card.member_changed' => 'Someone was added to or removed from a card',
        'label.attached'      => 'A label was added to a card',
        'checklist.changed'   => 'A checklist or an item changed',
        'comment.posted'      => 'Someone commented',
    ];

    /** @param list<string> $events */
    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $boardId,
        public readonly string $url,
        public readonly string $secret,
        public readonly array $events,
        public readonly bool $enabled,
        public readonly int $createdBy,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['public_id'],
            (int) $row['board_id'],
            (string) $row['url'],
            (string) $row['secret'],
            array_values(array_filter((array) json_decode((string) $row['events'], true), 'is_string')),
            (bool) $row['enabled'],
            (int) $row['created_by'],
            (string) $row['created_at'],
        );
    }

    public function wants(string $event): bool
    {
        return $this->enabled && in_array($event, $this->events, true);
    }
}
