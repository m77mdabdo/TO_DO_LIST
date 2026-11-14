<?php

declare(strict_types=1);

namespace App\Modules\Attachment\Application;

use App\Http\UploadedFile;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Attachment\Contract\Attachment;
use App\Modules\Attachment\Contract\Events\AttachmentAdded;
use App\Modules\Attachment\Contract\Events\AttachmentRemoved;
use App\Modules\Attachment\Domain\AttachmentRepository;
use App\Modules\Attachment\Domain\FileStore;
use App\Modules\Attachment\Domain\UploadPolicy;
use App\Modules\Board\Contract\BoardDirectory;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use Throwable;

/**
 * Files and links on cards.
 *
 * Adding needs EditCards on a card that is open on an open board. Removing
 * is for the person who added it, or anyone who may delete items (board
 * managers). Downloading needs ViewBoard, checked on every request.
 */
final class AttachmentService
{
    public function __construct(
        private AttachmentRepository $attachments,
        private FileStore $files,
        private UploadPolicy $policy,
        private AccessGate $gate,
        private BoardDirectory $boards,
        private CardQueries $cards,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    /** @return list<Attachment> */
    public function forCard(int $cardId): array
    {
        return $this->attachments->forCard($cardId);
    }

    /**
     * @param list<int> $cardIds
     * @return array<int, int>
     */
    public function countsForCards(array $cardIds): array
    {
        return $this->attachments->countsForCards($cardIds);
    }

    public function canAdd(int $userId, CardSummary $card): bool
    {
        $board = $this->boards->find($card->boardId);

        return $board !== null && !$board->archived && !$card->archived && $this->gate->can($userId, $card->boardId, Permission::EditCards);
    }

    public function canRemove(int $userId, Attachment $attachment): bool
    {
        $board = $this->boards->find($attachment->boardId);
        if ($board === null || $board->archived) {
            return false;
        }

        return ($attachment->uploaderId === $userId && $this->gate->can($userId, $attachment->boardId, Permission::EditCards))
            || $this->gate->can($userId, $attachment->boardId, Permission::DeleteItems);
    }

    public function upload(int $actorId, CardSummary $card, ?UploadedFile $file): Attachment
    {
        $this->assertCanAdd($actorId, $card);

        if ($file === null || $file->error === UPLOAD_ERR_NO_FILE) {
            $this->fail('file', 'Choose a file.');
        }
        if ($file->isTooLargeForServer() || $file->size > UploadPolicy::MAX_BYTES) {
            $this->fail('file', 'The file is larger than 10 MB.');
        }
        if (!$file->isOk() || $file->size === 0) {
            $this->fail('file', 'The file could not be uploaded. Please try again.');
        }
        $mime = $this->policy->allowedType($file->tmpPath, $file->clientName);
        if ($mime === null) {
            $this->fail('file', 'This type of file can not be attached. Use images, PDF, Office documents, text or zip.');
        }
        $this->assertRoom($card, $file->size);

        $stored = $this->files->put($file);
        try {
            $attachment = $this->attachments->addFile($card->id, $card->boardId, $actorId, $this->policy->cleanName($file->clientName), $mime, $file->size, $stored);
        } catch (Throwable $e) {
            $this->files->delete($stored);
            throw $e;
        }
        $this->events->publish(new AttachmentAdded($attachment->id, $card->id, $card->boardId, $actorId, $card->title, $attachment->name, false, $this->clock->now()));

        return $attachment;
    }

    public function addLink(int $actorId, CardSummary $card, string $url, string $title): Attachment
    {
        $this->assertCanAdd($actorId, $card);

        $url = trim($url);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($url === '' || mb_strlen($url) > 2000 || !in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->fail('url', 'Enter a web address that starts with http:// or https://.');
        }
        $title = trim((string) preg_replace('/[\p{Cc}\p{Cf}]+/u', '', $title));
        if (mb_strlen($title) > 150) {
            $this->fail('title', 'The title may not be longer than 150 characters.');
        }
        $this->assertRoom($card, 0);

        $attachment = $this->attachments->addLink($card->id, $card->boardId, $actorId, $title !== '' ? $title : (string) parse_url($url, PHP_URL_HOST), $url);
        $this->events->publish(new AttachmentAdded($attachment->id, $card->id, $card->boardId, $actorId, $card->title, $attachment->name, true, $this->clock->now()));

        return $attachment;
    }

    /** The attachment and the card it is on, if the user may see them. */
    public function visible(int $userId, string $publicId): Attachment
    {
        $attachment = $this->attachments->findByPublicId($publicId) ?? throw AccessDenied::hidden();
        $this->gate->authorize($userId, $attachment->boardId, Permission::ViewBoard);

        return $attachment;
    }

    /**
     * The stored bytes for a download, after the permission check.
     *
     * @return array{0: Attachment, 1: string}
     */
    public function fileFor(int $userId, string $publicId): array
    {
        $attachment = $this->visible($userId, $publicId);
        $path = $attachment->storedName !== null ? $this->files->path($attachment->storedName) : null;
        if ($attachment->isLink() || $path === null) {
            throw AccessDenied::hidden();
        }

        return [$attachment, $path];
    }

    public function remove(int $actorId, string $publicId): CardSummary
    {
        $attachment = $this->visible($actorId, $publicId);
        if (!$this->canRemove($actorId, $attachment)) {
            throw AccessDenied::forbidden();
        }
        $card = $this->cards->find($attachment->cardId) ?? throw AccessDenied::hidden();

        $this->attachments->remove($attachment->id);
        if ($attachment->storedName !== null) {
            $this->files->delete($attachment->storedName);
        }
        $this->events->publish(new AttachmentRemoved($attachment->id, $card->id, $card->boardId, $actorId, $card->title, $attachment->name, $attachment->isLink(), $this->clock->now()));

        return $card;
    }

    private function assertCanAdd(int $actorId, CardSummary $card): void
    {
        $this->gate->authorize($actorId, $card->boardId, Permission::ViewBoard);
        if (!$this->canAdd($actorId, $card)) {
            throw AccessDenied::forbidden();
        }
    }

    private function assertRoom(CardSummary $card, int $bytes): void
    {
        if ($this->attachments->countForCard($card->id) >= UploadPolicy::MAX_PER_CARD) {
            $this->fail('file', 'A card can have up to 50 attachments.');
        }
        if ($bytes > 0 && $this->attachments->bytesOnBoard($card->boardId) + $bytes > UploadPolicy::MAX_PER_BOARD) {
            $this->fail('file', 'This board has used all of its 500 MB for files.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw new ValidationException(new ValidationResult([$field => $message]));
    }
}
