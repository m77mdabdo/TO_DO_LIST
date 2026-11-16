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
}
