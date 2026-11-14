<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure;

use App\Modules\Integration\Contract\ApiToken;
use App\Modules\Integration\Domain\TokenRepository;
use App\Shared\Ids\Ulid;
use App\Support\Clock;
use PDO;

final class PdoTokens implements TokenRepository
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function create(int $userId, string $name, string $hash, string $scope, array $boardIds, ?string $expiresAt): ApiToken
    {
        $publicId = Ulid::generate();
        $this->db->prepare('INSERT INTO api_tokens (public_id, user_id, name, token_hash, scope, board_ids, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$publicId, $userId, $name, $hash, $scope, json_encode(array_values($boardIds)), $expiresAt, $this->now()]);

        return $this->findByPublicId($publicId) ?? throw new \LogicException('Token not saved.');
    }

    public function findByHash(string $hash): ?ApiToken
    {
        return $this->one('SELECT * FROM api_tokens WHERE token_hash = ?', $hash);
    }

    public function findByPublicId(string $publicId): ?ApiToken
    {
        return $this->one('SELECT * FROM api_tokens WHERE public_id = ?', $publicId);
    }

    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM api_tokens WHERE user_id = ? ORDER BY id DESC');
        $stmt->execute([$userId]);

        return array_map([ApiToken::class, 'fromRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function touch(int $id): void
    {
        $this->db->prepare('UPDATE api_tokens SET last_used_at = ? WHERE id = ?')->execute([$this->now(), $id]);
    }

    public function revoke(int $id): void
    {
        $this->db->prepare('UPDATE api_tokens SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL')->execute([$this->now(), $id]);
    }

    private function one(string $sql, string $value): ?ApiToken
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? ApiToken::fromRow($row) : null;
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
