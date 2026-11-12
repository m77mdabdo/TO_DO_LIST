<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

use App\Modules\Access\Contract\Events\BoardMemberChanged;
use App\Modules\Audit\Domain\AuditLog;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Invitation\Contract\Events\InvitationAccepted;
use App\Modules\Invitation\Contract\Events\InvitationRevoked;
use App\Modules\Invitation\Contract\Events\InvitationSent;
use App\Modules\Workspace\Contract\Events\MemberJoined;
use App\Modules\Workspace\Contract\Events\MemberRemoved;
use App\Modules\Workspace\Contract\Events\MemberRoleChanged;
use App\Shared\Events\DomainEvent;

/**
 * Writes the audit log from other modules' events: invitations, workspace
 * roles and removals, and board roles. The target is a person's email (or
 * the invited address), stable even if they later rename themselves.
 */
final class AuditRecorder
{
    public function __construct(
        private AuditLog $log,
        private UserDirectory $users,
        private BoardDirectory $boards,
    ) {
    }

    /** @return list<string> */
    public static function events(): array
    {
        return [
            InvitationSent::name(), InvitationRevoked::name(), InvitationAccepted::name(),
            MemberJoined::name(), MemberRoleChanged::name(), MemberRemoved::name(), BoardMemberChanged::name(),
        ];
    }

    public function record(DomainEvent $event): void
    {
        match (true) {
            $event instanceof InvitationSent => $this->log->add($event->workspaceId, $event->actorId, $event->resent ? 'invitation.resent' : 'invitation.sent', $event->email, ['role' => $event->role->label()]),
            $event instanceof InvitationRevoked => $this->log->add($event->workspaceId, $event->actorId, 'invitation.revoked', $event->email),
            $event instanceof InvitationAccepted => $this->log->add($event->workspaceId, $event->userId, 'invitation.accepted', $event->email, ['role' => $event->role->label()]),
            $event instanceof MemberJoined => $event->actorId === $event->userId ? null : $this->log->add($event->workspaceId, $event->actorId, 'member.joined', $this->email($event->userId), ['role' => $event->role->label()]),
            $event instanceof MemberRoleChanged => $this->log->add($event->workspaceId, $event->actorId, 'member.role_changed', $this->email($event->userId), ['from' => $event->from->label(), 'to' => $event->to->label()]),
            $event instanceof MemberRemoved => $this->log->add($event->workspaceId, $event->actorId, $event->actorId === $event->userId ? 'member.left' : 'member.removed', $this->email($event->userId), ['role' => $event->role->label()]),
            $event instanceof BoardMemberChanged => $this->log->add($event->workspaceId, $event->actorId, 'board.member_changed', $this->email($event->userId), [
                'board' => $this->boards->find($event->boardId)?->name ?? '',
                'from'  => $event->from?->label() ?? '-',
                'to'    => $event->to?->label() ?? '-',
            ]),
            default => null,
        };
    }

    private function email(int $userId): string
    {
        return $this->users->find($userId)?->email ?? '#' . $userId;
    }
}
