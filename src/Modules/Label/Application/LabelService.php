<?php

declare(strict_types=1);

namespace App\Modules\Label\Application;

use App\Database\Transactions;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Label\Contract\Events\LabelAttached;
use App\Modules\Label\Contract\Events\LabelDetached;
use App\Modules\Label\Contract\Label;
use App\Modules\Label\Contract\LabelCatalog;
use App\Modules\Label\Domain\LabelRepository;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\Validator;

final class LabelService implements LabelCatalog
{
    public function __construct(
        private LabelRepository $labels,
        private CardQueries $cards,
        private AccessGate $gate,
        private Transactions $transactions,
        private EventBus $events,
        private Clock $clock,
        private Validator $validator,
    ) {
    }

    public function forBoard(int $boardId): array
    {
        return $this->labels->forBoard($boardId);
    }

    public function forCards(array $cardIds): array
    {
        return $this->labels->forCards($cardIds);
    }

    public function ensureLabel(int $actorId, int $boardId, string $name, string $color): Label
    {
        $this->gate->authorize($actorId, $boardId, Permission::ManageLabels);

        $result = $this->validator->validate(['name' => $name, 'color' => $color], [
            'name'  => ['Label name', 'required|max:60'],
            'color' => ['Color', 'required|in:' . implode(',', Label::COLORS)],
        ]);

        if ($result->fails()) {
            throw new ValidationException($result);
        }

        return $this->labels->findByName($boardId, trim($name)) ?? $this->labels->create($boardId, trim($name), $color);
    }

    public function attach(int $actorId, int $cardId, int $labelId): void
    {
        $this->toggle($actorId, $cardId, $labelId, true);
    }

    /** Adds or removes a label on a card. Returns whether it is now attached. */
    public function toggle(int $actorId, int $cardId, int $labelId, ?bool $attach = null): bool
    {
        $card = $this->cards->find($cardId) ?? throw AccessDenied::hidden();
        $this->gate->authorize($actorId, $card->boardId, Permission::EditCards);

        // The label must belong to the card's own board.
        $label = $this->labels->find($labelId, $card->boardId) ?? throw AccessDenied::hidden();
        $attached = $this->labels->isAttached($card->id, $label->id);
        $attach ??= !$attached;

        if ($attach === $attached) {
            return $attached;
        }

        $this->transactions->run(function () use ($actorId, $card, $label, $attach): void {
            if ($attach) {
                $this->labels->attach($card->id, $label->id);
                $this->events->publish(new LabelAttached($card->id, $card->boardId, $actorId, $label->name, $this->clock->now()));
            } else {
                $this->labels->detach($card->id, $label->id);
                $this->events->publish(new LabelDetached($card->id, $card->boardId, $actorId, $label->name, $this->clock->now()));
            }
        });

        return $attach;
    }
}
