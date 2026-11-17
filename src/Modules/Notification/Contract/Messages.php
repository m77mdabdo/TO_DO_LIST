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
        'card.reminder'          => 'Reminder: :subject is due :date',
        'board.added'            => ':actor added you to the board :subject',
        'invitation.accepted'    => ':actor accepted your invitation to :subject',
        'workspace.role_changed' => ':actor made you :role in :subject',
        'workspace.removed'      => ':actor removed you from :subject',
        'automation.notice'      => ':rule on :subject: :message',
    ];

    /** Kinds important enough to email (when the person has email on). */
    public const EMAILED = [
        'card.assigned', 'checklist.assigned', 'comment.mentioned', 'card.due_soon', 'card.overdue', 'card.reminder',
        'board.added', 'workspace.role_changed', 'workspace.removed', 'automation.notice',
    ];

    /** What each kind is about, for the settings page. */
    public const LABELS = [
        'card.assigned'          => 'Someone adds me to a card',
        'checklist.assigned'     => 'Someone gives me a checklist item',
        'comment.mentioned'      => 'Someone mentions me',
        'comment.posted'         => 'New comments on cards I watch',
        'card.due_changed'       => 'Due date changes on cards I watch',
        'card.completed'         => 'Cards I watch are completed',
        'card.moved'             => 'Cards I watch move',
        'card.due_soon'          => 'A due date comes close',
        'card.overdue'           => 'A card is overdue',
        'card.reminder'          => 'Reminders I set',
        'board.added'            => 'Someone adds me to a board',
        'invitation.accepted'    => 'Someone accepts my invitation',
        'workspace.role_changed' => 'My role changes',
        'workspace.removed'      => 'I am removed from a workspace',
        'automation.notice'      => 'Automation messages',
    ];

    /** Changes to a person's own access always show in the app; only their email can be turned off. */
    public const ALWAYS_SHOWN = ['workspace.role_changed', 'workspace.removed'];

    public static function sentence(string $type): string
    {
        return self::SENTENCES[$type] ?? ':actor updated :subject';
    }
}
