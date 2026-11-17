<?php

declare(strict_types=1);

namespace App\Modules\Board\Application;

use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Domain\BoardRepository;
use App\Modules\Workspace\Contract\Events\WorkspaceAdminAction;
use App\Modules\Workspace\Contract\WorkspaceDirectory;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use PDO;

/**
 * A secret, read-only link to a board for people without an account.
 *
 * The secret is 256 random bits shown once to the person who makes it; only
 * its hash is stored. The link stops working when it is replaced or turned
 * off, when the board is archived, or when the workspace rules stop allowing
 * public links. Callers check ManageSettings before create/revoke.
 */
final class PublicLinks
{
    public function __construct(
        private PDO $db,
        private BoardRepository $boards,
        private WorkspaceDirectory $workspaces,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function allowed(BoardSummary $board): bool
    {
        return $this->workspaces->policy($board->workspaceId)->publicLinks;
    }

    /** @return array{createdAt: string, createdBy: ?int}|null */
    public function status(int $boardId): ?array
    {
        $stmt = $this->db->prepare('SELECT created_at, created_by FROM board_public_links WHERE board_id = ?');
        $stmt->execute([$boardId]);
        $row = $stmt->fetch();

        return $row === false ? null : ['createdAt' => (string) $row['created_at'], 'createdBy' => $row['created_by'] !== null ? (int) $row['created_by'] : null];
    }

    /** Makes a new link (the old one stops working) and returns its secret. */
    public function create(int $actorId, BoardSummary $board): string
    {
        if (!$this->allowed($board)) {
            throw new ValidationException(new ValidationResult(['public_link' => 'The workspace rules don\'t allow public links. An owner can change that on the members page.']));
        }
        if ($board->archived) {
            throw new ValidationException(new ValidationResult(['public_link' => 'Restore the board first.']));
        }
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $this->db->prepare('DELETE FROM board_public_links WHERE board_id = ?')->execute([$board->id]);
        $this->db->prepare('INSERT INTO board_public_links (board_id, token_hash, created_by, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$board->id, hash('sha256', $secret), $actorId, $now]);
        $this->record($board, $actorId, 'board.public_link_created');

        return $secret;
    }

    public function revoke(int $actorId, BoardSummary $board): void
    {
        $stmt = $this->db->prepare('DELETE FROM board_public_links WHERE board_id = ?');
        $stmt->execute([$board->id]);
        if ($stmt->rowCount() > 0) {
            $this->record($board, $actorId, 'board.public_link_revoked');
        }
    }

    /** The board a secret opens, or null when the link is unknown or no longer allowed. */
    public function boardFor(string $secret): ?BoardSummary
    {
        $stmt = $this->db->prepare('SELECT board_id FROM board_public_links WHERE token_hash = ?');
        $stmt->execute([hash('sha256', $secret)]);
        $boardId = $stmt->fetchColumn();
        if ($boardId === false) {
            return null;
        }
        $board = $this->boards->find((int) $boardId);

        return $board !== null && !$board->archived && $this->allowed($board) ? $board : null;
    }

    private function record(BoardSummary $board, int $actorId, string $action): void
    {
        $this->events->publish(new WorkspaceAdminAction($board->workspaceId, $actorId, $action, $board->name, ['board' => $board->publicId], $this->clock->now()));
    }
}
