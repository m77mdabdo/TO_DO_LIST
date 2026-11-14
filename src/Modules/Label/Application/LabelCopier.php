<?php

declare(strict_types=1);

namespace App\Modules\Label\Application;

use App\Modules\Board\Contract\Events\BoardCopied;
use App\Modules\Board\Contract\Events\BoardSeeded;
use App\Modules\Card\Contract\Events\CardCopied;
use App\Modules\Label\Contract\Label;
use App\Modules\Label\Domain\LabelRepository;

/**
 * Labels for boards made from a template or a copy. Labels are matched by
 * name, so a card's label and the board's label become the same one in
 * the copy whichever event comes first. Quiet: no activity lines.
 */
final class LabelCopier
{
    public function __construct(private LabelRepository $labels)
    {
    }

    public function onBoardCopied(BoardCopied $event): void
    {
        foreach ($this->labels->forBoard($event->sourceBoardId) as $label) {
            $this->ensure($event->boardId, $label->name, $label->color);
        }
    }

    public function onBoardSeeded(BoardSeeded $event): void
    {
        foreach ($event->labels as [$name, $color]) {
            $this->ensure($event->boardId, $name, in_array($color, Label::COLORS, true) ? $color : 'slate');
        }
    }

    public function onCardCopied(CardCopied $event): void
    {
        foreach ($this->labels->forCards([$event->sourceCardId])[$event->sourceCardId] ?? [] as $label) {
            $copy = $this->ensure($event->boardId, $label->name, $label->color);
            if (!$this->labels->isAttached($event->cardId, $copy->id)) {
                $this->labels->attach($event->cardId, $copy->id);
            }
        }
    }

    private function ensure(int $boardId, string $name, string $color): Label
    {
        return $this->labels->findByName($boardId, $name) ?? $this->labels->create($boardId, $name, $color);
    }
}
