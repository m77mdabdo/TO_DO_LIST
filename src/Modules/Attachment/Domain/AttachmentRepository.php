<?php

declare(strict_types=1);

namespace App\Modules\Attachment\Domain;

use App\Modules\Attachment\Contract\Attachment;

interface AttachmentRepository
{
    public function addFile(int $cardId, int $boardId, int $uploaderId, string $name, string $mime, int $size, string $storedName): Attachment;

    public function addLink(int $cardId, int $boardId, int $uploaderId, string $name, string $url): Attachment;

    public function findByPublicId(string $publicId): ?Attachment;

    /** @return list<Attachment> newest first */
    public function forCard(int $cardId): array;

    public function countForCard(int $cardId): int;

    /**
     * @param list<int> $cardIds
     * @return array<int, int> card id => number of attachments
     */
    public function countsForCards(array $cardIds): array;

    /** Bytes stored for a whole board, for a per-board quota. */
    public function bytesOnBoard(int $boardId): int;

    public function remove(int $id): void;
}
