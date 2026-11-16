<?php

declare(strict_types=1);

namespace App\Modules\Attachment\Contract;

use App\Http\UploadedFile;
use App\Modules\Card\Contract\CardSummary;

/**
 * Attaching files from outside the Attachment module (a file sent with a
 * comment). The same checks as the card's own upload form apply.
 */
interface AttachmentFiles
{
    /** May the user attach files to this card (EditCards, card and board open)? */
    public function canAdd(int $userId, CardSummary $card): bool;

    /** @throws \App\Validation\ValidationException with the error on "file" */
    public function upload(int $actorId, CardSummary $card, ?UploadedFile $file): Attachment;

    /** @return list<Attachment> */
    public function forCard(int $cardId): array;

    /**
     * Checks and stores a file that is not on a card yet (a personal inbox
     * item). The caller keeps the returned details and later adopts or drops it.
     *
     * @return array{name: string, mime: string, size: int, stored: string}
     * @throws \App\Validation\ValidationException with the error on "file"
     */
    public function hold(?UploadedFile $file): array;

    /** Absolute path of a held file, or null when it is missing. */
    public function heldPath(string $stored): ?string;

    public function dropHeld(string $stored): void;

    /**
     * Puts a held file on a card, with the same checks as an upload.
     *
     * @throws \App\Validation\ValidationException with the error on "file"
     */
    public function adoptHeld(int $actorId, CardSummary $card, string $name, string $mime, int $size, string $stored): Attachment;
}
