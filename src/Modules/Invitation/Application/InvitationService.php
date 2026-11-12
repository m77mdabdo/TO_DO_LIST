<?php

declare(strict_types=1);

namespace App\Modules\Invitation\Application;

use App\Core\Config;
use App\Database\Transactions;
use App\Http\HttpException;
use App\I18n\Translator;
use App\Mail\Mailer;
use App\Mail\Message;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\BoardMembers;
use App\Modules\Access\Contract\BoardRole;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Identity\Contract\User;
use App\Modules\Identity\Contract\UserDirectory;
use App\Modules\Invitation\Contract\Events\InvitationAccepted;
use App\Modules\Invitation\Contract\Events\InvitationRevoked;
use App\Modules\Invitation\Contract\Events\InvitationSent;
use App\Modules\Invitation\Contract\Invitation;
use App\Modules\Invitation\Domain\InvitationRepository;
use App\Modules\Invitation\Domain\InvitationToken;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Modules\Workspace\Contract\WorkspaceInfo;
use App\Modules\Workspace\Contract\WorkspaceMembership;
use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use App\Validation\Validator;

/**
 * The invitation life cycle:
 *   invite -> (resend)* -> accept | revoke | expire
 *
 * A link holds a 256-bit secret, is valid for VALID_DAYS, works once, and
 * only for the account with the invited email. It can never give more than
 * the inviter may give: the role must be one they can grant, and every
 * board must be in the workspace and managed by them.
 */
final class InvitationService
{
    public const VALID_DAYS = 7;
    /** Invitations one person may send (or resend) in an hour. */
    public const HOURLY_LIMIT = 30;
    public const MAX_BOARDS = 50;

    public function __construct(
        private InvitationRepository $invitations,
        private WorkspaceMembership $workspaces,
        private WorkspaceDirectory $directory,
        private BoardDirectory $boards,
        private BoardMembers $boardMembers,
        private AccessGate $gate,
        private UserDirectory $users,
        private Mailer $mailer,
        private Translator $translator,
        private Config $config,
        private Transactions $transactions,
        private EventBus $events,
        private Clock $clock,
        private Validator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $input email, role, board_role, boards (list of board ids)
     * @return array{0: Invitation, 1: string} the invitation and its link, shown once to the inviter
     */
    public function invite(int $actorId, int $workspaceId, array $input): array
    {
        $this->assertManager($actorId, $workspaceId);

        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $result = $this->validator->validate(['email' => $email], ['email' => ['Email', 'required|email|max:190']]);
        $role = WorkspaceRole::tryFrom((string) ($input['role'] ?? ''));
        $boardRole = BoardRole::tryFrom((string) ($input['board_role'] ?? 'member'));
        $boardIds = $this->boardIds($input['boards'] ?? []);

        // Ownership is only handed to someone already in the workspace, never by a link.
        if ($role === null || $role === WorkspaceRole::Owner || !$this->workspaces->mayGrant($actorId, $workspaceId, $role)) {
            $result = $result->with('role', 'You cannot give that role.');
        }
        if ($boardRole === null || ($role === WorkspaceRole::Guest && $boardRole === BoardRole::Manager)) {
            $result = $result->with('board_role', 'Choose a board role.');
        }
        if ($role === WorkspaceRole::Guest && $boardIds === []) {
            $result = $result->with('boards', 'A guest needs at least one board.');
        }
        foreach ($boardIds as $boardId) {
            $board = $this->boards->find($boardId);
            if ($board === null || $board->workspaceId !== $workspaceId || !$this->gate->can($actorId, $boardId, Permission::ManageMembers)) {
                $result = $result->with('boards', 'Choose boards from this workspace that you manage.');
                break;
            }
        }
        if (!$result->has('email') && $this->invitations->openFor($workspaceId, $email) !== null) {
            $result = $result->with('email', 'This email already has an open invitation. Send it again from the list below.');
        }
        if (!$result->has('email') && $this->isFullMember($workspaceId, $email)) {
            $result = $result->with('email', 'This person is already a member of the workspace.');
        }
        $this->assertWithinLimit($actorId, $result);

        if ($result->fails()) {
            throw new ValidationException($result);
        }

        $token = InvitationToken::generate();
        $invitation = $this->invitations->create($workspaceId, $email, $role, $boardRole, $boardIds, $token->hash(), $actorId, $this->expiry());
        $this->send($invitation, $token, $actorId);
        $this->events->publish(new InvitationSent($invitation->id, $workspaceId, $email, $role, $actorId, false, $this->clock->now()));

        return [$invitation, $this->link($token)];
    }

    /** A new link (the old one stops working), a new expiry, and the email again. */
    public function resend(int $actorId, string $publicId): string
    {
        $invitation = $this->openInvitation($actorId, $publicId);
        $this->assertWithinLimit($actorId, new ValidationResult());

        $token = InvitationToken::generate();
        $this->invitations->renew($invitation->id, $token->hash(), $this->expiry());
        $this->send($invitation, $token, $actorId);
        $this->events->publish(new InvitationSent($invitation->id, $invitation->workspaceId, $invitation->email, $invitation->role, $actorId, true, $this->clock->now()));

        return $this->link($token);
    }

    public function revoke(int $actorId, string $publicId): Invitation
    {
        $invitation = $this->openInvitation($actorId, $publicId);
        $this->invitations->markRevoked($invitation->id);
        $this->events->publish(new InvitationRevoked($invitation->id, $invitation->workspaceId, $invitation->email, $actorId, $this->clock->now()));

        return $invitation;
    }

    /** @return list<Invitation> */
    public function openInvitations(int $actorId, int $workspaceId): array
    {
        return $this->workspaces->canManageMembers($actorId, $workspaceId) ? $this->invitations->open($workspaceId) : [];
    }

    /**
     * What the link's landing page needs, whether or not the visitor is
     * signed in. Null when the link is unknown.
     *
     * @return array{invitation: Invitation, state: string, workspace: ?WorkspaceInfo, inviter: ?User}|null
     */
    public function lookup(string $token): ?array
    {
        $invitation = $this->invitations->findByTokenHash(InvitationToken::fromLink($token)->hash());

        if ($invitation === null) {
            return null;
        }

        return [
            'invitation' => $invitation,
            'state'      => $invitation->state($this->clock->now()),
            'workspace'  => $this->workspaces->describe($invitation->workspaceId),
            'inviter'    => $this->users->find($invitation->invitedBy),
        ];
    }

    /**
     * Joins the signed-in user to the workspace and the invited boards.
     *
     * @throws ValidationException when the link is used up, expired, or for another email
     */
    public function accept(User $user, string $token): WorkspaceInfo
    {
        $found = $this->lookup($token);
        $invitation = $found['invitation'] ?? null;

        if ($invitation === null || $found['state'] !== Invitation::PENDING || $found['workspace'] === null) {
            $this->fail('invitation', 'This invitation link is no longer valid. Ask for a new one.');
        }
        if (!$invitation->isFor($user->email)) {
            $this->fail('invitation', 'This invitation was sent to another email address. Sign in with that account to accept it.');
        }

        $this->transactions->run(function () use ($invitation, $user): void {
            if (!$this->invitations->markAccepted($invitation->id, $user->id, $this->clock->now()->format('Y-m-d H:i:s'))) {
                $this->fail('invitation', 'This invitation link is no longer valid. Ask for a new one.');
            }

            $this->workspaces->join($invitation->workspaceId, $user->id, $invitation->role, $invitation->invitedBy);

            foreach ($invitation->boardIds as $boardId) {
                $board = $this->boards->find($boardId);
                $current = $this->boardMembers->role($boardId, $user->id);
                // Never lower a role someone already has on a board.
                if ($board !== null && $board->workspaceId === $invitation->workspaceId && ($current === null || $current->rank() < $invitation->boardRole->rank())) {
                    $this->boardMembers->add($boardId, $user->id, $invitation->boardRole);
                }
            }

            $this->events->publish(new InvitationAccepted(
                $invitation->id,
                $invitation->workspaceId,
                $user->id,
                $invitation->email,
                $invitation->role,
                $invitation->invitedBy,
                $this->clock->now(),
            ));
        });

        $this->workspaces->open($user->id, $invitation->workspaceId);

        return $found['workspace'];
    }

    private function send(Invitation $invitation, InvitationToken $token, int $actorId): void
    {
        $inviter = $this->users->find($actorId);
        $workspace = $this->workspaces->describe($invitation->workspaceId);
        $t = fn (string $text, array $replace = []): string => $this->translator->t($text, $replace);
        $replace = [':name' => $inviter?->name ?? $t('Someone'), ':company' => $workspace?->companyName ?? ''];

        $body = implode("\n", [
            $t(':name invited you to join :company on To Do List.', $replace),
            '',
            $t('Open this link to accept:'),
            $this->link($token),
            '',
            $t('The link works once and expires in :days days.', [':days' => (string) self::VALID_DAYS]),
            $t('If you did not expect this, you can ignore this email.'),
        ]);

        $this->mailer->send(new Message($invitation->email, '', $t('Invitation to join :company', $replace), $body));
    }

    private function link(InvitationToken $token): string
    {
        return rtrim((string) $this->config->get('url', ''), '/') . '/invitations/' . $token->value;
    }

    private function expiry(): string
    {
        return $this->clock->now()->modify('+' . self::VALID_DAYS . ' days')->format('Y-m-d H:i:s');
    }

    private function openInvitation(int $actorId, string $publicId): Invitation
    {
        $invitation = $this->invitations->findByPublicId($publicId);

        if ($invitation === null || !$this->workspaces->canManageMembers($actorId, $invitation->workspaceId)) {
            throw HttpException::notFound();
        }
        if ($invitation->acceptedAt !== null || $invitation->revokedAt !== null) {
            $this->fail('invitation', 'This invitation was already used or cancelled.');
        }

        return $invitation;
    }

    private function assertManager(int $actorId, int $workspaceId): void
    {
        if (!$this->workspaces->canManageMembers($actorId, $workspaceId)) {
            throw new HttpException(403, 'You do not have permission to do that.');
        }
    }

    private function assertWithinLimit(int $actorId, ValidationResult $result): void
    {
        $since = $this->clock->now()->modify('-1 hour')->format('Y-m-d H:i:s');

        if ($this->invitations->sentSince($actorId, $since) >= self::HOURLY_LIMIT) {
            throw new ValidationException($result->with('email', 'You sent many invitations in the last hour. Please wait a little.'));
        }
    }

    /** Is this email the account of a full (non-guest) member? One query for the members' accounts. */
    private function isFullMember(int $workspaceId, string $email): bool
    {
        $members = $this->directory->members($workspaceId);
        $users = $this->users->findMany(array_column($members, 'userId'));

        foreach ($members as $member) {
            $user = $users[$member['userId']] ?? null;
            if ($user !== null && mb_strtolower($user->email) === $email) {
                return $member['role']->isFullMember();
            }
        }

        return false;
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function boardIds(mixed $raw): array
    {
        $ids = [];
        foreach (is_array($raw) ? $raw : [] as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                if ((int) $value > 0) {
                    $ids[(int) $value] = true;
                }
            }
        }

        return array_slice(array_keys($ids), 0, self::MAX_BOARDS);
    }

    private function fail(string $field, string $message): never
    {
        throw new ValidationException(new ValidationResult([$field => $message]));
    }
}
