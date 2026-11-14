<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Domain\UserTokens;
use PDO;

final class PdoUserTokens implements UserTokens
{
    public function __construct(private PDO $db)
    {
    }

    public function create(int $userId, string $purpose, string $tokenHash, string $expiresAt, string $now): void
    {
        $this->db->prepare('INSERT INTO user_tokens (user_id, purpose, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$userId, $purpose, $tokenHash, $expiresAt, $now]);
    }

    public function findUsable(string $purpose, string $tokenHash, string $now): ?int
    {
        $stmt = $this->db->prepare('SELECT user_id FROM user_tokens WHERE token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > ?');
        $stmt->execute([$tokenHash, $purpose, $now]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function consume(string $purpose, string $tokenHash, string $now): bool
    {
        $stmt = $this->db->prepare('UPDATE user_tokens SET used_at = ? WHERE token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > ?');
        $stmt->execute([$now, $tokenHash, $purpose, $now]);

        return $stmt->rowCount() === 1;
    }

    public function revokeAll(int $userId, string $purpose, string $now): void
    {
        $this->db->prepare('UPDATE user_tokens SET used_at = ? WHERE user_id = ? AND purpose = ? AND used_at IS NULL')
            ->execute([$now, $userId, $purpose]);
    }

    public function countSince(int $userId, string $purpose, string $since): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM user_tokens WHERE user_id = ? AND purpose = ? AND created_at >= ?');
        $stmt->execute([$userId, $purpose, $since]);

        return (int) $stmt->fetchColumn();
    }

    public function prune(string $before): int
    {
        $stmt = $this->db->prepare('DELETE FROM user_tokens WHERE expires_at < ? OR used_at < ?');
        $stmt->execute([$before, $before]);

        return $stmt->rowCount();
    }
}
