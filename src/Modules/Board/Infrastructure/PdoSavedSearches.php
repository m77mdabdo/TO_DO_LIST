<?php

declare(strict_types=1);

namespace App\Modules\Board\Infrastructure;

use App\Modules\Board\Domain\SavedSearches;
use App\Shared\Ids\Ulid;
use App\Support\Clock;
use PDO;

final class PdoSavedSearches implements SavedSearches
{
    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT id, public_id, name, query, updated_at FROM saved_searches WHERE user_id = ? ORDER BY name, id');
        $stmt->execute([$userId]);

        return array_map(self::row(...), $stmt->fetchAll());
    }

    public function find(int $userId, string $publicId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, public_id, name, query, updated_at FROM saved_searches WHERE public_id = ? AND user_id = ?');
        $stmt->execute([$publicId, $userId]);
        $row = $stmt->fetch();

        return $row ? self::row($row) : null;
    }

    public function create(int $userId, string $name, string $query): string
    {
        $publicId = Ulid::generate();
        $now = $this->now();
        $this->db->prepare('INSERT INTO saved_searches (public_id, user_id, name, query, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$publicId, $userId, $name, $query, $now, $now]);

        return $publicId;
    }

    public function update(int $id, string $name, string $query): void
    {
        $this->db->prepare('UPDATE saved_searches SET name = ?, query = ?, updated_at = ? WHERE id = ?')->execute([$name, $query, $this->now(), $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM saved_searches WHERE id = ?')->execute([$id]);
    }

    /** @param array<string, mixed> $row */
    private static function row(array $row): array
    {
        return [
            'id'         => (int) $row['id'],
            'public_id'  => (string) $row['public_id'],
            'name'       => (string) $row['name'],
            'query'      => (string) $row['query'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
