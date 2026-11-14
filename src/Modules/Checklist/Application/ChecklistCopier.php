<?php

declare(strict_types=1);

namespace App\Modules\Checklist\Application;

use App\Modules\Card\Contract\Events\CardCopied;
use App\Modules\Checklist\Domain\ChecklistRepository;

/** Copies a card's checklists to its copy: same titles and items, all open again, nobody assigned. */
final class ChecklistCopier
{
    public function __construct(private ChecklistRepository $checklists)
    {
    }

    public function onCardCopied(CardCopied $event): void
    {
        foreach ($this->checklists->forCard($event->sourceCardId) as $checklist) {
            $copy = $this->checklists->create($event->cardId, $checklist->title, $event->actorId);
            foreach ($checklist->items as $item) {
                $itemId = $this->checklists->addItem($copy, $event->cardId, $item->text, $event->actorId);
                if ($item->dueAt !== null) {
                    $this->checklists->updateItem($itemId, ['due_at' => $item->dueAt]);
                }
            }
        }
    }
}
