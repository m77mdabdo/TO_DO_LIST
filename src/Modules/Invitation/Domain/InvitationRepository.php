<?php

declare(strict_types=1);

namespace App\Modules\Invitation\Domain;

use App\Modules\Access\Contract\BoardRole;
use App\Modules\Invitation\Contract\Invitation;
use App\Modules\Workspace\Contract\WorkspaceRole;

interface InvitationRepository
{
    /** @param list<int> $boardIds */
    public function create(int $workspaceId, string $email, WorkspaceRole $role, BoardRole $boardRole, array $boardIds, string $tokenHash, int $invitedBy, string $expiresAt): Invitation;

    public function findByTokenHash(string $tokenHash): ?Invitation;

    public function findByPublicId(string $publicId): ?Invitation;

    /** The open (not accepted, not revoked) invitation for this email in the workspace, if any. */
    public function openFor(int $workspaceId, string $email): ?Invitation;

    /** @return list<Invitation> open invitations of the workspace, newest first */
    public function open(int $workspaceId): array;

    /** A new secret and expiry, e.g. when the invitation is sent again. */
    public function renew(int $id, string $tokenHash, string $expiresAt): void;

    /**
     * Marks it accepted only if it is still open and unexpired. Returns false
     * when another request got there first, so a link works exactly once.
     */
    public function markAccepted(int $id, int $userId, string $now): bool;

    public function markRevoked(int $id): void;

    public function sentSince(int $userId, string $since): int;
}
