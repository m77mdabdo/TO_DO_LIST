<?php

declare(strict_types=1);

namespace App\Modules\Attachment\Application;

use App\Http\UploadedFile;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Attachment\Contract\Attachment;
use App\Modules\Attachment\Domain\FileStore;
use App\Modules\Attachment\Domain\UploadPolicy;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Contract\Events\BoardChanged;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use PDO;
use Throwable;

/**
 * A picture behind a board. Setting or removing it needs ManageSettings on
 * an open board; seeing it needs ViewBoard, checked on every request. The
 * file is stored like an attachment: outside the web root, under a random
 * name, and only JPEG, PNG, GIF or WebP (checked from the bytes) up to 5 MB.
 */
final class BoardBackgrounds
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private PDO $db,
        private FileStore $files,
        private UploadPolicy $policy,
        private AccessGate $gate,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function has(int $boardId): bool
    {
        return $this->row($boardId) !== null;
    }

    public function canManage(int $userId, BoardSummary $board): bool
    {
        return !$board->archived && $this->gate->can($userId, $board->id, Permission::ManageSettings);
    }

    public function upload(int $actorId, BoardSummary $board, ?UploadedFile $file): void
    {
        $this->assertCanManage($actorId, $board);

        if ($file === null || $file->error === UPLOAD_ERR_NO_FILE) {
            $this->fail('Choose a picture.');
        }
        if ($file->isTooLargeForServer() || $file->size > self::MAX_BYTES) {
            $this->fail('The picture is larger than 5 MB.');
        }
        if (!$file->isOk() || $file->size === 0) {
            $this->fail('The file could not be uploaded. Please try again.');
        }
        $mime = $this->policy->allowedType($file->tmpPath, $file->clientName);
        if ($mime === null || !in_array($mime, Attachment::IMAGE_TYPES, true)) {
            $this->fail('Use a JPEG, PNG, GIF or WebP picture.');
        }

        $old = $this->row($board->id);
        $stored = $this->files->put($file);
        try {
            $this->db->prepare('DELETE FROM board_backgrounds WHERE board_id = ?')->execute([$board->id]);
            $this->db->prepare('INSERT INTO board_backgrounds (board_id, stored_name, mime, size, uploader_id, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$board->id, $stored, $mime, $file->size, $actorId, $this->clock->now()->format('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            $this->files->delete($stored);
            throw $e;
        }
        if ($old !== null) {
            $this->files->delete($old['stored_name']);
        }
        $this->events->publish(new BoardChanged($board->id, $actorId, 'background', [], $this->clock->now()));
    }

    public function remove(int $actorId, BoardSummary $board): void
    {
        $this->assertCanManage($actorId, $board);
        $old = $this->row($board->id);
        if ($old === null) {
            return;
        }
        $this->db->prepare('DELETE FROM board_backgrounds WHERE board_id = ?')->execute([$board->id]);
        $this->files->delete($old['stored_name']);
        $this->events->publish(new BoardChanged($board->id, $actorId, 'background', [], $this->clock->now()));
    }

    /**
     * The stored picture, after checking that the user may see the board.
     *
     * @return array{mime: string, path: string}
     */
    public function file(int $userId, BoardSummary $board): array
    {
        $this->gate->authorize($userId, $board->id, Permission::ViewBoard);
        $row = $this->row($board->id) ?? throw AccessDenied::hidden();
        $path = $this->files->path($row['stored_name']) ?? throw AccessDenied::hidden();

        return ['mime' => $row['mime'], 'path' => $path];
    }

    /** @return array{stored_name: string, mime: string}|null */
    private function row(int $boardId): ?array
    {
        $stmt = $this->db->prepare('SELECT stored_name, mime FROM board_backgrounds WHERE board_id = ?');
        $stmt->execute([$boardId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? ['stored_name' => (string) $row['stored_name'], 'mime' => (string) $row['mime']] : null;
    }

    private function assertCanManage(int $actorId, BoardSummary $board): void
    {
        $this->gate->authorize($actorId, $board->id, Permission::ViewBoard);
        if (!$this->canManage($actorId, $board)) {
            throw AccessDenied::forbidden();
        }
    }

    private function fail(string $message): never
    {
        throw new ValidationException(new ValidationResult(['background_picture' => $message]));
    }
}
