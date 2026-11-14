<?php

declare(strict_types=1);

namespace App\Modules\Automation\Domain;

/** What a rule can listen for. type => what its value is (a list id, a label id, or nothing). */
final class Triggers
{
    public const TYPES = [
        'card.created'   => 'list?',   // optional: only cards added to this list
        'card.moved'     => 'list',    // moved into this list
        'card.completed' => 'none',
        'label.added'    => 'label?',
        'member.added'   => 'none',
        'checklist.done' => 'none',    // every checklist item on the card is done
        'comment.posted' => 'none',
    ];

    public static function needsValue(string $type): bool
    {
        return !str_ends_with(self::TYPES[$type] ?? 'none', '?') && (self::TYPES[$type] ?? 'none') !== 'none';
    }
}
