<?php

declare(strict_types=1);

namespace App\Modules\Personal\Application;

use App\Database\Transactions;
use App\Http\UploadedFile;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Attachment\Contract\AttachmentFiles;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Board\Contract\ListSummary;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Card\Contract\CardWriter;
use App\Shared\Ids\Ulid;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use PDO;

/**
 * A personal inbox: quick notes that belong to one person and to no board.
 * Only its owner ever sees an item. Moving an item makes a card on a board
 * and list where the person may add cards, with the item's title,
 * description, due date and files, and then removes the item.
 */
final class Inbox
{
    public const MAX_ITEMS = 300;
    public const MAX_FILES = 10;
    public const MAX_BYTES = 100 * 1024 * 1024;
    public const TITLE_MAX = 200;
    public const DESCRIPTION_MAX = 10000;

    public function __construct(
        private PDO $db,
        private CardWriter $cards,
        private BoardDirectory $boards,
        private AccessGate $gate,
        private AttachmentFiles $files,
        private Transactions $transactions,
        private Clock $clock,
    ) {
    }

    /** @return list<array{id: int, public_id: string, title: string, description: ?string, due_date: ?string, created_at: string, files: list<array{public_id: string, name: string, mime: string, size: int}>}> newest first */
    public function items(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT id, public_id, title, description, due_date, created_at FROM inbox_items WHERE user_id = ? ORDER BY created_at DESC, id DESC');
        $stmt->execute([$userId]);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[(int) $row['id']] = [
                'id'          => (int) $row['id'],
                'public_id'   => (string) $row['public_id'],
                'title'       => (string) $row['title'],
                'description' => $row['description'] !== null ? (string) $row['description'] : null,
                'due_date'    => $row['due_date'] !== null ? substr((string) $row['due_date'], 0, 10) : null,
                'created_at'  => (string) $row['created_at'],
                'files'       => [],
            ];
        }
        if ($items !== []) {
            $marks = implode(', ', array_fill(0, count($items), '?'));
            $files = $this->db->prepare("SELECT item_id, public_id, name, mime, size FROM inbox_files WHERE item_id IN ($marks) ORDER BY id");
            $files->execute(array_keys($items));
            foreach ($files->fetchAll(PDO::FETCH_ASSOC) as $file) {
                $items[(int) $file['item_id']]['files'][] = ['public_id' => (string) $file['public_id'], 'name' => (string) $file['name'], 'mime' => (string) $file['mime'], 'size' => (int) $file['size']];
            }
        }

        return array_values($items);
    }

    public function count(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM inbox_items WHERE user_id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /** Adds an item with just a title (quick capture), or with a description and due date. Returns its public id. */
    public function capture(int $userId, string $title, string $description = '', string $dueDate = ''): string
    {
        [$title, $description, $dueDate] = $this->valid($title, $description, $dueDate);
        if ($this->count($userId) >= self::MAX_ITEMS) {
            $this->fail('title', 'Your inbox can hold up to 300 items. Move or delete some first.');
        }
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $publicId = Ulid::generate();
        $this->db->prepare('INSERT INTO inbox_items (public_id, user_id, title, description, due_date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$publicId, $userId, $title, $description, $dueDate, $now, $now]);

        return $publicId;
    }

    public function update(int $userId, string $publicId, string $title, string $description, string $dueDate): void
    {
        $item = $this->own($userId, $publicId);
        [$title, $description, $dueDate] = $this->valid($title, $description, $dueDate);
        $this->db->prepare('UPDATE inbox_items SET title = ?, description = ?, due_date = ?, updated_at = ? WHERE id = ?')
            ->execute([$title, $description, $dueDate, $this->clock->now()->format('Y-m-d H:i:s'), $item['id']]);
    }

    public function delete(int $userId, string $publicId): void
    {
        $item = $this->own($userId, $publicId);
        $stored = $this->storedNames($item['id']);
        $this->db->prepare('DELETE FROM inbox_items WHERE id = ?')->execute([$item['id']]);
        foreach ($stored as $name) {
            $this->files->dropHeld($name);
        }
    }

    public function addFile(int $userId, string $publicId, ?UploadedFile $file): void
    {
        $item = $this->own($userId, $publicId);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM inbox_files WHERE item_id = ?');
        $stmt->execute([$item['id']]);
        if ((int) $stmt->fetchColumn() >= self::MAX_FILES) {
            $this->fail('file', 'An inbox item can have up to 10 files.');
        }
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(f.size), 0) FROM inbox_files f JOIN inbox_items i ON i.id = f.item_id WHERE i.user_id = ?');
        $stmt->execute([$userId]);
        if ($file !== null && (int) $stmt->fetchColumn() + $file->size > self::MAX_BYTES) {
            $this->fail('file', 'Your inbox has used all of its 100 MB for files.');
        }

        $held = $this->files->hold($file);
        try {
            $this->db->prepare('INSERT INTO inbox_files (public_id, item_id, name, mime, size, stored_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([Ulid::generate(), $item['id'], $held['name'], $held['mime'], $held['size'], $held['stored'], $this->clock->now()->format('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            $this->files->dropHeld($held['stored']);
            throw $e;
        }
    }

    public function removeFile(int $userId, string $filePublicId): void
    {
        $file = $this->ownFile($userId, $filePublicId);
        $this->db->prepare('DELETE FROM inbox_files WHERE id = ?')->execute([$file['id']]);
        $this->files->dropHeld($file['stored_name']);
    }

    /** @return array{name: string, mime: string, path: string} for a download by the item's owner */
    public function file(int $userId, string $filePublicId): array
    {
        $file = $this->ownFile($userId, $filePublicId);
        $path = $this->files->heldPath($file['stored_name']) ?? throw AccessDenied::hidden();

        return ['name' => $file['name'], 'mime' => $file['mime'], 'path' => $path];
    }

    /**
     * Where items can go: open lists of open boards where the person adds cards.
     *
     * @param list<\App\Modules\Workspace\Contract\WorkspaceSummary> $workspaces
     * @return list<array{workspace: \App\Modules\Workspace\Contract\WorkspaceSummary, board: BoardSummary, lists: list<ListSummary>}>
     */
    public function targets(int $userId, array $workspaces): array
    {
        $targets = [];
        foreach ($workspaces as $workspace) {
            foreach ($this->boards->allInWorkspace($workspace->id) as $board) {
                if ($board->archived || $board->isTemplate || !$this->gate->can($userId, $board->id, Permission::EditCards)) {
                    continue;
                }
                $lists = $this->boards->openLists($board->id);
                if ($lists !== []) {
                    $targets[] = ['workspace' => $workspace, 'board' => $board, 'lists' => $lists];
                }
            }
        }

        return $targets;
    }

    /** Makes a card from the item in the chosen list ("boardPublicId:listId") and removes the item. */
    public function move(int $userId, string $publicId, string $target): CardSummary
    {
        $item = $this->own($userId, $publicId);
        [$boardPublicId, $listId] = array_pad(explode(':', $target, 2), 2, '');
        $board = $this->boards->findByPublicId($boardPublicId);
        $list = $board !== null && ctype_digit($listId) ? $this->boards->listOnBoard((int) $listId, $board->id) : null;
        if ($board === null || $list === null || $list->archived || $board->archived || $board->isTemplate || !$this->gate->can($userId, $board->id, Permission::EditCards)) {
            $this->fail('move_to', 'Choose a list on a board where you can add cards.');
        }

        $files = $this->db->prepare('SELECT id, name, mime, size, stored_name FROM inbox_files WHERE item_id = ? ORDER BY id');
        $files->execute([$item['id']]);
        $files = $files->fetchAll(PDO::FETCH_ASSOC);

        return $this->transactions->run(function () use ($userId, $board, $list, $item, $files): CardSummary {
            $card = $this->cards->createCard($userId, $board->id, $list->id, $item['title'], [
                'description' => $item['description'],
                'due_date'    => $item['due_date'] !== null ? substr($item['due_date'], 0, 10) : null,
            ]);
            foreach ($files as $file) {
                $this->files->adoptHeld($userId, $card, (string) $file['name'], (string) $file['mime'], (int) $file['size'], (string) $file['stored_name']);
            }
            // The files now belong to the card's attachments, so only the rows go.
            $this->db->prepare('DELETE FROM inbox_files WHERE item_id = ?')->execute([$item['id']]);
            $this->db->prepare('DELETE FROM inbox_items WHERE id = ?')->execute([$item['id']]);

            return $card;
        });
    }

    /** @return array{id: int, title: string, description: ?string, due_date: ?string} */
    private function own(int $userId, string $publicId): array
    {
        $stmt = $this->db->prepare('SELECT id, user_id, title, description, due_date FROM inbox_items WHERE public_id = ?');
        $stmt->execute([$publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        // Someone else's item looks exactly like one that does not exist.
        if ($row === false || (int) $row['user_id'] !== $userId) {
            throw AccessDenied::hidden();
        }

        return ['id' => (int) $row['id'], 'title' => (string) $row['title'], 'description' => $row['description'] !== null ? (string) $row['description'] : null, 'due_date' => $row['due_date'] !== null ? (string) $row['due_date'] : null];
    }

    /** @return array{id: int, name: string, mime: string, stored_name: string} */
    private function ownFile(int $userId, string $filePublicId): array
    {
        $stmt = $this->db->prepare('SELECT f.id, f.name, f.mime, f.stored_name, i.user_id FROM inbox_files f JOIN inbox_items i ON i.id = f.item_id WHERE f.public_id = ?');
        $stmt->execute([$filePublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || (int) $row['user_id'] !== $userId) {
            throw AccessDenied::hidden();
        }

        return ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'mime' => (string) $row['mime'], 'stored_name' => (string) $row['stored_name']];
    }

    /** @return list<string> */
    private function storedNames(int $itemId): array
    {
        $stmt = $this->db->prepare('SELECT stored_name FROM inbox_files WHERE item_id = ?');
        $stmt->execute([$itemId]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array{0: string, 1: ?string, 2: ?string} */
    private function valid(string $title, string $description, string $dueDate): array
    {
        $clean = static fn (string $text): string => trim((string) preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}]+/u', '', $text));
        $title = $clean($title);
        $description = $clean($description);
        $errors = [];
        if ($title === '') {
            $errors['title'] = 'Write what you want to remember.';
        } elseif (mb_strlen($title) > self::TITLE_MAX) {
            $errors['title'] = 'The title may not be longer than 200 characters.';
        }
        if (mb_strlen($description) > self::DESCRIPTION_MAX) {
            $errors['description'] = 'The description may not be longer than 10000 characters.';
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
        if ($dueDate !== '' && ($date === false || $date->format('Y-m-d') !== $dueDate)) {
            $errors['due_date'] = 'Enter a date like 2026-12-31.';
        }
        if ($errors !== []) {
            throw new ValidationException(new ValidationResult($errors));
        }

        return [$title, $description !== '' ? $description : null, $dueDate !== '' ? $dueDate : null];
    }

    private function fail(string $field, string $message): never
    {
        throw new ValidationException(new ValidationResult([$field => $message]));
    }
}
