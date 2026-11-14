<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application;

use App\Modules\Access\Contract\Events\BoardMemberChanged;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\Events\CardChanged;
use App\Modules\Card\Contract\Events\CardCreated;
use App\Modules\Card\Contract\Events\CardMemberChanged;
use App\Modules\Card\Contract\Events\CardMoved;
use App\Modules\Checklist\Contract\Events\ChecklistChanged;
use App\Modules\Discussion\Contract\Events\CommentPosted;
use App\Modules\Invitation\Contract\Events\InvitationAccepted;
use App\Modules\Notification\Domain\NotificationStore;
use App\Modules\Workspace\Contract\Events\MemberRemoved;
use App\Modules\Workspace\Contract\Events\MemberRoleChanged;
use App\Modules\Workspace\Contract\WorkspaceMembership;
use App\Shared\Events\DomainEvent;

/**
 * Who hears about what. Each other module's event is turned into
 * notifications here, so those modules don't know notifications exist.
 *
 * Watching: the creator of a card and the people assigned to it watch it
 * automatically; anyone can watch a card or a whole board, or stop.
 */
final class NotificationRules
{
    public function __construct(
        private Notifier $notifier,
        private NotificationStore $store,
        private CardQueries $cards,
        private BoardDirectory $boards,
        private WorkspaceMembership $workspaces,
    ) {
    }

    /** @return list<string> */
    public static function events(): array
    {
        return [
            CardCreated::name(), CardMemberChanged::name(), CardChanged::name(), CardMoved::name(),
            ChecklistChanged::name(), CommentPosted::name(), BoardMemberChanged::name(),
            InvitationAccepted::name(), MemberRoleChanged::name(), MemberRemoved::name(),
        ];
    }

    public function handle(DomainEvent $event): void
    {
        match (true) {
            $event instanceof CardCreated        => $this->store->watch($event->actorId, 'card', $event->cardId, $event->boardId),
            $event instanceof CardMemberChanged  => $this->cardMember($event),
            $event instanceof CardChanged        => $this->cardChanged($event),
            $event instanceof CardMoved          => $this->cardMoved($event),
            $event instanceof ChecklistChanged   => $this->checklistItem($event),
            $event instanceof CommentPosted      => $this->comment($event),
            $event instanceof BoardMemberChanged => $this->boardMember($event),
            $event instanceof InvitationAccepted => $this->invitationAccepted($event),
            $event instanceof MemberRoleChanged  => $this->workspaceRole($event),
            $event instanceof MemberRemoved      => $this->workspaceRemoval($event),
            default                              => null,
        };
    }

    private function cardMember(CardMemberChanged $event): void
    {
        if (!$event->added) {
            return;
        }

        $this->store->watch($event->memberId, 'card', $event->cardId, $event->boardId);
        $this->notifier->notify([$event->memberId], 'card.assigned', $event->actorId, $event->boardId, $event->cardId, $event->title, [],
            $this->cardLink($event->cardId), 'assigned:' . $event->cardId . ':' . $this->stamp($event));
    }

    private function cardChanged(CardChanged $event): void
    {
        if (!in_array($event->change, ['due_changed', 'completed'], true)) {
            return;
        }

        $this->notifier->notify($this->store->watchersOfCard($event->cardId, $event->boardId), 'card.' . $event->change, $event->actorId,
            $event->boardId, $event->cardId, $event->title, [], $this->cardLink($event->cardId), $event->change . ':' . $event->cardId . ':' . $this->stamp($event));
    }

    private function cardMoved(CardMoved $event): void
    {
        if ($event->fromListId === $event->toListId) {
            return;
        }

        $to = $this->boards->listOnBoard($event->toListId, $event->boardId)?->name ?? '';
        $this->notifier->notify($this->store->watchersOfCard($event->cardId, $event->boardId), 'card.moved', $event->actorId,
            $event->boardId, $event->cardId, $event->title, ['to' => $to], $this->cardLink($event->cardId), 'moved:' . $event->cardId . ':' . $this->stamp($event));
    }

    private function checklistItem(ChecklistChanged $event): void
    {
        if ($event->change !== 'item_assigned' || $event->assigneeId === null) {
            return;
        }

        $this->notifier->notify([$event->assigneeId], 'checklist.assigned', $event->actorId, $event->boardId, $event->cardId, $event->cardTitle,
            ['item' => $event->subject], $this->cardLink($event->cardId), 'item:' . $event->cardId . ':' . md5($event->subject) . ':' . $this->stamp($event));
    }

    private function comment(CommentPosted $event): void
    {
        $link = $this->cardLink($event->cardId) . '#comment-' . $event->commentId;
        $key = 'comment:' . $event->commentId;
        $data = ['excerpt' => $event->excerpt];

        // Mentions first: someone both mentioned and watching gets the mention only (same key).
        $this->notifier->notify($event->mentionedUserIds, 'comment.mentioned', $event->actorId, $event->boardId, $event->cardId, $event->cardTitle, $data, $link, $key);
        $this->notifier->notify($this->store->watchersOfCard($event->cardId, $event->boardId), 'comment.posted', $event->actorId, $event->boardId, $event->cardId, $event->cardTitle, $data, $link, $key);
    }

    private function boardMember(BoardMemberChanged $event): void
    {
        if ($event->from !== null || $event->to === null) {
            return;
        }

        $board = $this->boards->find($event->boardId);
        if ($board !== null) {
            $this->notifier->notify([$event->userId], 'board.added', $event->actorId, $board->id, null, $board->name,
                ['role' => $event->to->label()], '/boards/' . $board->publicId, 'board:' . $board->id . ':' . $this->stamp($event));
        }
    }

    private function invitationAccepted(InvitationAccepted $event): void
    {
        $workspace = $this->workspaces->describe($event->workspaceId);
        if ($workspace !== null) {
            $this->notifier->notify([$event->inviterId], 'invitation.accepted', $event->userId, null, null, $workspace->companyName, [],
                '/workspaces/' . $workspace->publicId . '/members', 'invitation:' . $event->invitationId);
        }
    }

    private function workspaceRole(MemberRoleChanged $event): void
    {
        $workspace = $this->workspaces->describe($event->workspaceId);
        if ($workspace !== null) {
            $this->notifier->notify([$event->userId], 'workspace.role_changed', $event->actorId, null, null, $workspace->companyName,
                ['role' => $event->to->label()], '/workspaces/' . $workspace->publicId . '/members', 'role:' . $event->workspaceId . ':' . $this->stamp($event));
        }
    }

    private function workspaceRemoval(MemberRemoved $event): void
    {
        $workspace = $this->workspaces->describe($event->workspaceId);
        if ($workspace !== null && $event->actorId !== $event->userId) {
            $this->notifier->notify([$event->userId], 'workspace.removed', $event->actorId, null, null, $workspace->companyName, [],
                '/', 'removed:' . $event->workspaceId . ':' . $this->stamp($event));
        }
    }

    private function cardLink(int $cardId): string
    {
        $card = $this->cards->find($cardId);

        return $card !== null ? '/cards/' . $card->publicId : '/';
    }

    private function stamp(DomainEvent $event): string
    {
        return $event->occurredAt()->format('YmdHis');
    }
}
