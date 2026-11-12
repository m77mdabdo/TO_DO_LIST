<?php

declare(strict_types=1);

namespace App\Modules\Invitation\Infrastructure;

use App\Modules\Access\Contract\BoardRole;
use App\Modules\Invitation\Contract\Invitation;
use App\Modules\Invitation\Domain\InvitationRepository;
use App\Modules\Workspace\Contract\WorkspaceRole;
use App\Shared\Ids\Ulid;
use App\Support\Clock;
use PDO;

final class PdoInvitations implements InvitationRepository
{
    private const COLUMNS = 'id, public_id, workspace_id, email, role, board_role, invited_by, sent_at, expires_at, accepted_at, revoked_at';

    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function create(int $workspaceId, string $email, WorkspaceRole $role, BoardRole $boardRole, array $boardIds, string $tokenHash, int $invitedBy, string $expiresAt): Invitation
    {
        $now = $this->now();
        $this->db->prepare('INSERT INTO invitations (public_id, workspace_id, email, role, board_role, token_hash, invited_by, created_at, sent_at, expires_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([Ulid::generate(), $workspaceId, $email, $role->value, $boardRole->value, $tokenHash, $invitedBy, $now, $now, $expiresAt]);
        $id = (int) $this->db->lastInsertId();

        $link = $this->db->prepare('INSERT INTO invitation_boards (invitation_id, board_id) VALUES (?, ?)');
        foreach (array_unique($boardIds) as $boardId) {
            $link->execute([$id, $boardId]);
        }

        return $this->one('id = ?', [$id]) ?? throw new \LogicException('Invitation was not saved.');
    }

    public function findByTokenHash(string $tokenHash): ?Invitation
    {
        return $this->one('token_hash = ?', [$tokenHash]);
    }

    public function findByPublicId(string $publicId): ?Invitation
    {
        return $this->one('public_id = ?', [$publicId]);
    }

    public function openFor(int $workspaceId, string $email): ?Invitation
    {
        return $this->one('workspace_id = ? AND email = ? AND accepted_at IS NULL AND revoked_at IS NULL', [$workspaceId, $email]);
    }

    public function open(int $workspaceId): array
    {
        return $this->many('workspace_id = ? AND accepted_at IS NULL AND revoked_at IS NULL ORDER BY id DESC', [$workspaceId]);
    }

    public function renew(int $id, string $tokenHash, string $expiresAt): void
    {
        $this->db->prepare('UPDATE invitations SET token_hash = ?, sent_at = ?, expires_at = ? WHERE id = ?')
            ->execute([$tokenHash, $this->now(), $expiresAt, $id]);
    }

    public function markAccepted(int $id, int $userId, string $now): bool
    {
        // The conditions make this a compare-and-set: two clicks on the same
        // link at once can't both succeed.
        $stmt = $this->db->prepare('UPDATE invitations SET accepted_at = ?, accepted_by = ?
            WHERE id = ? AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at > ?');
        $stmt->execute([$now, $userId, $id, $now]);

        return $stmt->rowCount() === 1;
    }

    public function markRevoked(int $id): void
    {
        $this->db->prepare('UPDATE invitations SET revoked_at = ? WHERE id = ? AND accepted_at IS NULL')->execute([$this->now(), $id]);
    }

    public function sentSince(int $userId, string $since): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM invitations WHERE invited_by = ? AND sent_at >= ?');
        $stmt->execute([$userId, $since]);

        return (int) $stmt->fetchColumn();
    }

    /** @param list<mixed> $params */
    private function one(string $where, array $params): ?Invitation
    {
        return $this->many($where . ' LIMIT 1', $params)[0] ?? null;
    }

    /**
     * Two queries whatever the number of invitations: the rows, then all
     * their boards at once.
     *
     * @param list<mixed> $params
     * @return list<Invitation>
     */
    private function many(string $where, array $params): array
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM invitations WHERE ' . $where);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $row) => (int) $row['id'], $rows);
        $boards = $this->db->prepare('SELECT invitation_id, board_id FROM invitation_boards WHERE invitation_id IN ('
            . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY board_id');
        $boards->execute($ids);
        $boardIds = [];
        foreach ($boards->fetchAll() as $row) {
            $boardIds[(int) $row['invitation_id']][] = (int) $row['board_id'];
        }

        return array_map(static fn (array $row) => new Invitation(
            (int) $row['id'],
            (string) $row['public_id'],
            (int) $row['workspace_id'],
            (string) $row['email'],
            WorkspaceRole::from((string) $row['role']),
            BoardRole::from((string) $row['board_role']),
            $boardIds[(int) $row['id']] ?? [],
            (int) $row['invited_by'],
            (string) $row['sent_at'],
            (string) $row['expires_at'],
            $row['accepted_at'] !== null ? (string) $row['accepted_at'] : null,
            $row['revoked_at'] !== null ? (string) $row['revoked_at'] : null,
        ), $rows);
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
