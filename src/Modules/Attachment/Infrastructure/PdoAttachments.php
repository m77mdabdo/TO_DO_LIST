<?php

declare(strict_types=1);

namespace App\Modules\Attachment\Infrastructure;

use App\Modules\Attachment\Contract\Attachment;
use App\Modules\Attachment\Domain\AttachmentRepository;
use App\Shared\Ids\Ulid;
use App\Support\Clock;
use PDO;

final class PdoAttachments implements AttachmentRepository
{
    private const COLUMNS = 'id, public_id, card_id, board_id, uploader_id, kind, name, url, mime, size, stored_name, created_at';

    public function __construct(private PDO $db, private Clock $clock)
    {
    }

    public function addFile(int $cardId, int $boardId, int $uploaderId, string $name, string $mime, int $size, string $storedName): Attachment
    {
        return $this->insert($cardId, $boardId, $uploaderId, 'file', $name, null, $mime, $size, $storedName);
    }

    public function addLink(int $cardId, int $boardId, int $uploaderId, string $name, string $url): Attachment
    {
        return $this->insert($cardId, $boardId, $uploaderId, 'link', $name, $url, null, 0, null);
    }

    public function findByPublicId(string $publicId): ?Attachment
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM attachments WHERE public_id = ?');
        $stmt->execute([$publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function forCard(int $cardId): array
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM attachments WHERE card_id = ? ORDER BY id DESC');
        $stmt->execute([$cardId]);

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function countForCard(int $cardId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM attachments WHERE card_id = ?');
        $stmt->execute([$cardId]);

        return (int) $stmt->fetchColumn();
    }

    public function countsForCards(array $cardIds): array
    {
        if ($cardIds === []) {
            return [];
        }
        $stmt = $this->db->prepare('SELECT card_id, COUNT(*) FROM attachments WHERE card_id IN (' . implode(',', array_fill(0, count($cardIds), '?')) . ') GROUP BY card_id');
        $stmt->execute(array_values($cardIds));

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    public function bytesOnBoard(int $boardId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(size), 0) FROM attachments WHERE board_id = ?');
        $stmt->execute([$boardId]);

        return (int) $stmt->fetchColumn();
    }

    public function remove(int $id): void
    {
        $this->db->prepare('DELETE FROM attachments WHERE id = ?')->execute([$id]);
    }

    private function insert(int $cardId, int $boardId, int $uploaderId, string $kind, string $name, ?string $url, ?string $mime, int $size, ?string $storedName): Attachment
    {
        $publicId = Ulid::generate();
        $this->db->prepare('INSERT INTO attachments (public_id, card_id, board_id, uploader_id, kind, name, url, mime, size, stored_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$publicId, $cardId, $boardId, $uploaderId, $kind, $name, $url, $mime, $size, $storedName, $this->clock->now()->format('Y-m-d H:i:s')]);

        return $this->findByPublicId($publicId) ?? throw new \LogicException('Attachment not saved.');
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Attachment
    {
        return new Attachment(
            (int) $row['id'],
            (string) $row['public_id'],
            (int) $row['card_id'],
            (int) $row['board_id'],
            (int) $row['uploader_id'],
            (string) $row['kind'],
            (string) $row['name'],
            $row['url'] !== null ? (string) $row['url'] : null,
            $row['mime'] !== null ? (string) $row['mime'] : null,
            (int) $row['size'],
            $row['stored_name'] !== null ? (string) $row['stored_name'] : null,
            (string) $row['created_at'],
        );
    }
}
