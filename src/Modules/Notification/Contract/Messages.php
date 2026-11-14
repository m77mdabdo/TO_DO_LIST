<?php

declare(strict_types=1);

namespace App\Modules\Notification\Contract;

/**
 * The sentence for each kind of notification, in English (the translation
 * key). Placeholders: :actor, :subject, and any key of the data.
 * The same sentences are used in the bell and in emails.
 */
final class Messages
{
    public const SENTENCES = [
        'card.assigned'          => ':actor added you to :subject',
        'checklist.assigned'     => ':actor gave you ":item" on :subject',
        'comment.mentioned'      => ':actor mentioned you on :subject',
        'comment.posted'         => ':actor commented on :subject',
        'card.due_changed'       => ':actor changed the due date of :subject',
        'card.completed'         => ':actor completed :subject',
        'card.moved'             => ':actor moved :subject to :to',
        'card.due_soon'          => ':subject is due :date',
        'card.overdue'           => ':subject is overdue since :date',
        'board.added'            => ':actor added you to the board :subject',
        'invitation.accepted'    => ':actor accepted your invitation to :subject',
        'workspace.role_changed' => ':actor made you :role in :subject',
        'workspace.removed'      => ':actor removed you from :subject',
    ];

    /** Kinds important enough to email (when the person has email on). */
    public const EMAILED = [
        'card.assigned', 'checklist.assigned', 'comment.mentioned', 'card.due_soon', 'card.overdue',
        'board.added', 'workspace.role_changed', 'workspace.removed',
    ];

    public static function sentence(string $type): string
    {
        return self::SENTENCES[$type] ?? ':actor updated :subject';
    }
}
