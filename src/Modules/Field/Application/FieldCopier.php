<?php

declare(strict_types=1);

namespace App\Modules\Field\Application;

use App\Modules\Board\Contract\Events\BoardCopied;
use App\Modules\Card\Contract\CardQueries;
use App\Modules\Card\Contract\Events\CardCopied;
use App\Modules\Field\Contract\CustomField;
use App\Modules\Field\Domain\FieldRepository;

/**
 * Custom fields for copied boards and cards. A copied board gets the same
 * fields; a copied card keeps its values on a field of the same name and
 * type (made on the target board when it has none, while there is room).
 * Choices are matched by name. Quiet: no activity lines.
 */
final class FieldCopier
{
    public function __construct(private FieldRepository $fields, private CardQueries $cards)
    {
    }

    public function onBoardCopied(BoardCopied $event): void
    {
        foreach ($this->fields->forBoard($event->sourceBoardId) as $field) {
            $this->ensure($event->boardId, $field);
        }
    }

    public function onCardCopied(CardCopied $event): void
    {
        if (!$event->copies('fields')) {
            return;
        }
        $source = $this->cards->find($event->sourceCardId);
        $values = $this->fields->valuesForCards([$event->sourceCardId])[$event->sourceCardId] ?? [];
        if ($source === null || $values === []) {
            return;
        }

        foreach ($this->fields->forBoard($source->boardId) as $field) {
            if (!isset($values[$field->id])) {
                continue;
            }
            $target = $source->boardId === $event->boardId ? $field : $this->ensure($event->boardId, $field);
            if ($target === null) {
                continue;
            }
            $value = $values[$field->id];
            if ($field->type === 'select') {
                $name = $field->optionName($value);
                $value = '';
                foreach ($target->options as $option) {
                    if ($name !== null && mb_strtolower($option['name']) === mb_strtolower($name)) {
                        $value = $option['id'];
                    }
                }
            }
            if ($value !== '') {
                $this->fields->setValue($event->cardId, $target->id, $value);
            }
        }
    }

    /** The target board's field like $field: found by name and type, or made, or null when there is no room or the type differs. */
    private function ensure(int $boardId, CustomField $field): ?CustomField
    {
        $existing = $this->fields->findByName($boardId, $field->name);
        if ($existing !== null) {
            return $existing->type === $field->type ? $existing : null;
        }
        if (count($this->fields->forBoard($boardId)) >= CustomField::MAX_PER_BOARD) {
            return null;
        }

        return $this->fields->create($boardId, $field->name, $field->type, $field->options, $field->onCard);
    }
}
