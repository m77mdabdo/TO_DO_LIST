<?php

declare(strict_types=1);

namespace App\Modules\Card\Application;

use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Card\Domain\CardRepository;

final class CardQueryService implements CardQueries
{
    public function __construct(private CardRepository $cards)
    {
    }

    public function find(int $cardId): ?CardSummary
    {
        return $this->cards->find($cardId);
    }

    public function findByPublicId(string $publicId): ?CardSummary
    {
        return $this->cards->findByPublicId($publicId);
    }

    public function openCardsByList(int $boardId): array
    {
        return $this->cards->openCardsByList($boardId);
    }

    public function archivedCards(int $boardId): array
    {
        return $this->cards->archivedCards($boardId);
    }
}
