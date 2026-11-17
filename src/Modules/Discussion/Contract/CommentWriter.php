<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Contract;

use App\Http\UploadedFile;
use App\Modules\Card\Contract\CardSummary;

/** Reading and posting comments from other modules (the API, imports), with the same checks as the card page. */
interface CommentWriter
{
    /** @return list<Comment> newest first, one page */
    public function comments(int $cardId, ?int $beforeId = null): array;

    /**
     * @throws \App\Validation\ValidationException
     * @throws \App\Modules\Access\Contract\AccessDenied when the person may not comment
     */
    public function post(int $actorId, CardSummary $card, string $body, ?UploadedFile $file = null): int;
}
