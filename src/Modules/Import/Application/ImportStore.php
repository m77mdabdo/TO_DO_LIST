<?php

declare(strict_types=1);

namespace App\Modules\Import\Application;

use App\Shared\Ids\Ulid;
use App\Support\Clock;
use PDO;
use RuntimeException;

/**
 * Pending and finished imports. The data read from an upload is kept as a
 * JSON file (not in the database, so big Trello exports don't hit MySQL's
 * packet size) and is deleted once the import runs or is cancelled.
 */
final class ImportStore
{
    /** Pending imports older than this are thrown away. */
    public const KEEP_HOURS = 24;

    public function __construct(private PDO $db, private Clock $clock, private string $path)
    {
    }

    /** @param array<string, mixed> $data */
    public function create(int $userId, int $workspaceId, ?int $boardId, string $kind, string $fileName, string $sourceKey, array $data): string
    {
        $this->prune();
        $publicId = Ulid::generate();
        if (!is_dir($this->path) && !@mkdir($this->path, 0775, true) && !is_dir($this->path)) {
            throw new RuntimeException('The imports folder (' . basename($this->path) . ') can\'t be created. Check that the web server may write to storage/.');
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (@file_put_contents($this->file($publicId), $json, LOCK_EX) === false) {
            throw new RuntimeException('The import could not be saved. Check that the web server may write to storage/imports.');
        }

        $this->db->prepare(
            'INSERT INTO imports (public_id, user_id, workspace_id, board_id, kind, status, file_name, source_key, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        )->execute([$publicId, $userId, $workspaceId, $boardId, $kind, 'pending', mb_substr($fileName, 0, 255), $sourceKey, $this->now()]);

        return $publicId;
    }

    /**
     * The person's own pending import, with its data.
     *
     * @return array{id: int, publicId: string, workspaceId: int, boardId: ?int, kind: string, fileName: string, sourceKey: string, createdAt: string, data: array<string, mixed>}|null
     */
    public function pending(int $userId, string $publicId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM imports WHERE public_id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$publicId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !is_file($this->file($publicId))) {
            return null;
        }
        $data = json_decode((string) file_get_contents($this->file($publicId)), true);

        return is_array($data) ? [
            'id'          => (int) $row['id'],
            'publicId'    => (string) $row['public_id'],
            'workspaceId' => (int) $row['workspace_id'],
            'boardId'     => $row['board_id'] !== null ? (int) $row['board_id'] : null,
            'kind'        => (string) $row['kind'],
            'fileName'    => (string) $row['file_name'],
            'sourceKey'   => (string) $row['source_key'],
            'createdAt'   => (string) $row['created_at'],
            'data'        => $data,
        ] : null;
    }

    /** @return list<array{publicId: string, kind: string, fileName: string, createdAt: string}> */
    public function pendingFor(int $userId, ?int $boardId = null): array
    {
        $sql = "SELECT public_id, kind, file_name, created_at FROM imports WHERE user_id = ? AND status = 'pending'" . ($boardId !== null ? ' AND board_id = ?' : " AND kind = 'trello'") . ' ORDER BY id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($boardId !== null ? [$userId, $boardId] : [$userId]);

        return array_map(static fn (array $r) => [
            'publicId'  => (string) $r['public_id'],
            'kind'      => (string) $r['kind'],
            'fileName'  => (string) $r['file_name'],
            'createdAt' => (string) $r['created_at'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** When the same source was already imported into this workspace, the time it was. */
    public function alreadyImported(int $workspaceId, string $kind, string $sourceKey): ?string
    {
        $stmt = $this->db->prepare("SELECT finished_at FROM imports WHERE workspace_id = ? AND kind = ? AND source_key = ? AND status = 'done' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$workspaceId, $kind, $sourceKey]);
        $at = $stmt->fetchColumn();

        return $at === false ? null : (string) $at;
    }

    /** @param array<string, mixed> $summary */
    public function finish(int $id, string $publicId, ?int $boardId, array $summary): void
    {
        $this->db->prepare("UPDATE imports SET status = 'done', board_id = ?, summary = ?, finished_at = ? WHERE id = ?")
            ->execute([$boardId, json_encode($summary, JSON_UNESCAPED_UNICODE), $this->now(), $id]);
        @unlink($this->file($publicId));
    }

    public function cancel(int $userId, string $publicId): void
    {
        $this->db->prepare("UPDATE imports SET status = 'cancelled', finished_at = ? WHERE public_id = ? AND user_id = ? AND status = 'pending'")
            ->execute([$this->now(), $publicId, $userId]);
        @unlink($this->file($publicId));
    }

    /** Throws away pending imports nobody finished. */
    public function prune(): void
    {
        $before = $this->clock->now()->modify('-' . self::KEEP_HOURS . ' hours')->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare("SELECT public_id FROM imports WHERE status = 'pending' AND created_at < ?");
        $stmt->execute([$before]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $publicId) {
            @unlink($this->file((string) $publicId));
        }
        $this->db->prepare("UPDATE imports SET status = 'cancelled', finished_at = ? WHERE status = 'pending' AND created_at < ?")->execute([$this->now(), $before]);
    }

    private function file(string $publicId): string
    {
        return rtrim($this->path, '/') . '/' . $publicId . '.json';
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
