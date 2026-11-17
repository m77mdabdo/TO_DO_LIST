<?php

declare(strict_types=1);

namespace App\Modules\Field\Application;

use App\Database\Transactions;
use App\Modules\Access\Contract\AccessDenied;
use App\Modules\Access\Contract\AccessGate;
use App\Modules\Access\Contract\Permission;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardSummary;
use App\Modules\Field\Contract\CustomField;
use App\Modules\Field\Contract\Events\FieldsChanged;
use App\Modules\Field\Contract\Events\FieldValueChanged;
use App\Modules\Field\Domain\FieldRepository;
use App\Shared\Events\EventBus;
use App\Support\Clock;
use App\Validation\ValidationException;
use App\Validation\ValidationResult;
use DateTimeImmutable;

/**
 * Custom fields on a board, and their values on cards.
 *
 * Defining fields needs ManageLabels (the same people who shape the board's
 * labels); filling them in needs EditCards on an open card of an open board.
 * Every value is checked against its field's type on the server.
 */
final class FieldService implements \App\Modules\Field\Contract\FieldValues
{
    public function __construct(
        private FieldRepository $fields,
        private AccessGate $gate,
        private Transactions $transactions,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    /** @return list<CustomField> */
    public function forBoard(int $boardId): array
    {
        return $this->fields->forBoard($boardId);
    }

    /**
     * @param list<int> $cardIds
     * @return array<int, array<int, string>> card id => field id => stored value
     */
    public function valuesForCards(array $cardIds): array
    {
        return $this->fields->valuesForCards($cardIds);
    }

    /**
     * What each card shows for the board's fields, in field order, empty values left out.
     *
     * @param list<int> $cardIds
     * @return array<int, list<array{field: CustomField, text: string}>>
     */
    public function shownForCards(int $boardId, array $cardIds, bool $onCardOnly = false): array
    {
        $fields = $this->fields->forBoard($boardId);
        $values = $this->fields->valuesForCards($cardIds);
        $out = [];
        foreach ($values as $cardId => $byField) {
            foreach ($fields as $field) {
                $text = $field->display($byField[$field->id] ?? null);
                if ($text !== '' && (!$onCardOnly || $field->onCard)) {
                    $out[$cardId][] = ['field' => $field, 'text' => $text];
                }
            }
        }

        return $out;
    }

    public function canManage(int $userId, BoardSummary $board): bool
    {
        return !$board->archived && $this->gate->can($userId, $board->id, Permission::ManageLabels);
    }

    public function canFill(int $userId, BoardSummary $board, CardSummary $card): bool
    {
        return !$board->archived && !$card->archived && $this->gate->can($userId, $board->id, Permission::EditCards);
    }

    public function valueCount(int $fieldId, ?string $value = null): int
    {
        return $this->fields->valueCount($fieldId, $value);
    }

    /** @param array<string, mixed> $input name, type, options (one per line), on_card */
    public function create(int $actorId, BoardSummary $board, array $input): CustomField
    {
        $this->assertManages($actorId, $board);
        $name = $this->validName((string) ($input['name'] ?? ''));
        $type = (string) ($input['type'] ?? '');
        if (!isset(CustomField::TYPES[$type])) {
            $this->fail('type', 'Choose a field type.');
        }
        if (count($this->fields->forBoard($board->id)) >= CustomField::MAX_PER_BOARD) {
            $this->fail('name', 'A board can have up to 20 custom fields.');
        }
        if ($this->fields->findByName($board->id, $name) !== null) {
            $this->fail('name', 'This board already has a field with that name.');
        }
        $options = $type === 'select' ? $this->options((string) ($input['options'] ?? ''), []) : [];
        if ($type === 'select' && $options === []) {
            $this->fail('options', 'Write at least one choice, one per line.');
        }

        return $this->transactions->run(function () use ($actorId, $board, $name, $type, $options, $input): CustomField {
            $field = $this->fields->create($board->id, $name, $type, $options, !empty($input['on_card']));
            $this->events->publish(new FieldsChanged($board->id, $actorId, 'added', $name, $this->clock->now()));

            return $field;
        });
    }

    /**
     * Renames the field, changes its choices or whether it shows on the
     * cards. Removing a choice that cards use needs confirm=1, and then
     * clears it from those cards.
     *
     * @param array<string, mixed> $input name, options, on_card, confirm
     */
    public function update(int $actorId, BoardSummary $board, int $fieldId, array $input): CustomField
    {
        $this->assertManages($actorId, $board);
        $field = $this->fields->find($fieldId, $board->id) ?? throw AccessDenied::hidden();
        $name = $this->validName((string) ($input['name'] ?? ''));
        $same = $this->fields->findByName($board->id, $name);
        if ($same !== null && $same->id !== $field->id) {
            $this->fail('name', 'This board already has a field with that name.');
        }

        $options = $field->options;
        $lost = [];
        if ($field->type === 'select') {
            $options = $this->options((string) ($input['options'] ?? ''), $field->options);
            if ($options === []) {
                $this->fail('options', 'Write at least one choice, one per line.');
            }
            $kept = array_column($options, 'id');
            foreach ($field->options as $old) {
                if (!in_array($old['id'], $kept, true) && $this->fields->valueCount($field->id, $old['id']) > 0) {
                    $lost[] = $old['name'];
                }
            }
            if ($lost !== [] && ($input['confirm'] ?? '') !== '1') {
                $this->fail('options', 'Some cards use a choice you removed. Tick “Remove it from those cards” to go ahead.');
            }
        }

        $this->transactions->run(function () use ($actorId, $board, $field, $name, $options, $input): void {
            $this->fields->update($field->id, $name, $options, !empty($input['on_card']));
            if ($field->type === 'select') {
                $this->fields->clearValuesNotIn($field->id, array_column($options, 'id'));
            }
            $this->events->publish(new FieldsChanged($board->id, $actorId, $name !== $field->name ? 'renamed' : 'changed', $name, $this->clock->now()));
        });

        return $this->fields->find($field->id, $board->id) ?? $field;
    }

    public function move(int $actorId, BoardSummary $board, int $fieldId, bool $up): void
    {
        $this->assertManages($actorId, $board);
        $ids = array_map(static fn (CustomField $f) => $f->id, $this->fields->forBoard($board->id));
        $at = array_search($fieldId, $ids, true);
        if ($at === false) {
            throw AccessDenied::hidden();
        }
        $to = $up ? $at - 1 : $at + 1;
        if (!isset($ids[$to])) {
            return;
        }
        [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];

        $this->transactions->run(function () use ($actorId, $board, $ids): void {
            $this->fields->reorder($board->id, $ids);
            $this->events->publish(new FieldsChanged($board->id, $actorId, 'reordered', '', $this->clock->now()));
        });
    }

    /** Deletes the field and every card's value for it. */
    public function delete(int $actorId, BoardSummary $board, int $fieldId): CustomField
    {
        $this->assertManages($actorId, $board);
        $field = $this->fields->find($fieldId, $board->id) ?? throw AccessDenied::hidden();

        $this->transactions->run(function () use ($actorId, $board, $field): void {
            $this->fields->delete($field->id);
            $this->events->publish(new FieldsChanged($board->id, $actorId, 'deleted', $field->name, $this->clock->now()));
        });

        return $field;
    }

    /**
     * Saves the card's values. Only fields of the card's board are read;
     * an empty value clears the field. All values are checked before any
     * is saved.
     *
     * @param array<int|string, mixed> $input field id => raw value
     */
    public function fill(int $actorId, BoardSummary $board, CardSummary $card, array $input): int
    {
        $this->gate->authorize($actorId, $board->id, Permission::ViewBoard);
        if ($card->boardId !== $board->id || !$this->canFill($actorId, $board, $card)) {
            throw AccessDenied::forbidden();
        }

        $fields = $this->fields->forBoard($board->id);
        $current = $this->fields->valuesForCards([$card->id])[$card->id] ?? [];
        $changes = [];
        $errors = [];
        foreach ($fields as $field) {
            // Fields not sent are left alone (the form sends "" for an unticked box).
            if (!array_key_exists((string) $field->id, $input) && !array_key_exists($field->id, $input)) {
                continue;
            }
            $raw = $input[$field->id] ?? $input[(string) $field->id] ?? '';
            try {
                $value = $this->clean($field, is_scalar($raw) ? (string) $raw : '');
            } catch (ValidationException $e) {
                $errors['field_' . $field->id] = (string) current($e->errors());
                continue;
            }
            if ($value !== ($current[$field->id] ?? '')) {
                $changes[] = [$field, $value];
            }
        }
        if ($errors !== []) {
            throw new ValidationException(new ValidationResult($errors));
        }

        $this->transactions->run(function () use ($actorId, $card, $changes): void {
            foreach ($changes as [$field, $value]) {
                $value === '' ? $this->fields->clearValue($card->id, $field->id) : $this->fields->setValue($card->id, $field->id, $value);
                $this->events->publish(new FieldValueChanged($card->id, $card->boardId, $actorId, $card->title, $field->name, $field->display($value), $this->clock->now()));
            }
        });

        return count($changes);
    }

    /** The value to store for $raw, or "" to clear it. */
    public function clean(CustomField $field, string $raw): string
    {
        $raw = trim((string) preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $raw));
        if ($raw === '') {
            return '';
        }

        switch ($field->type) {
            case 'text':
                if (mb_strlen($raw) > CustomField::TEXT_MAX) {
                    $this->fail('value', 'This value may not be longer than 500 characters.');
                }

                return $raw;
            case 'number':
                $number = strtr($raw, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', ',' => '.', ' ' => '']);
                if (!preg_match('/^-?\d{1,12}(\.\d{1,4})?$/', $number)) {
                    $this->fail('value', 'Enter a number, like 12 or 3.5.');
                }

                return $number;
            case 'date':
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
                if ($date === false || $date->format('Y-m-d') !== $raw) {
                    $this->fail('value', 'Enter a date.');
                }

                return $raw;
            case 'select':
                if ($field->optionName($raw) === null) {
                    $this->fail('value', 'Choose one of the choices shown.');
                }

                return $raw;
            case 'checkbox':
                return in_array($raw, ['1', 'on', 'true'], true) ? '1' : '';
            default:
                return '';
        }
    }

    private function assertManages(int $actorId, BoardSummary $board): void
    {
        $this->gate->authorize($actorId, $board->id, Permission::ManageLabels);
        if ($board->archived) {
            throw AccessDenied::forbidden();
        }
    }

    private function validName(string $name): string
    {
        $name = trim((string) preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $name));
        if ($name === '') {
            $this->fail('name', 'Give the field a name.');
        }
        if (mb_strlen($name) > CustomField::NAME_MAX) {
            $this->fail('name', 'The name may not be longer than 60 characters.');
        }

        return $name;
    }

    /**
     * Choices from text, one per line. A line that matches an existing
     * choice keeps that choice's id, so the cards that use it keep it.
     *
     * @param list<array{id: string, name: string}> $existing
     * @return list<array{id: string, name: string}>
     */
    private function options(string $text, array $existing): array
    {
        $byName = [];
        $next = 1;
        foreach ($existing as $option) {
            $byName[mb_strtolower($option['name'])] = $option['id'];
            $next = max($next, (int) substr($option['id'], 1) + 1);
        }

        $out = [];
        $seen = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim((string) preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $line));
            if ($line === '' || isset($seen[mb_strtolower($line)])) {
                continue;
            }
            if (mb_strlen($line) > CustomField::OPTION_MAX) {
                $this->fail('options', 'Each choice may be up to 40 characters.');
            }
            $seen[mb_strtolower($line)] = true;
            $out[] = ['id' => $byName[mb_strtolower($line)] ?? 'o' . $next++, 'name' => $line];
        }
        if (count($out) > CustomField::MAX_OPTIONS) {
            $this->fail('options', 'A field can have up to 30 choices.');
        }

        return $out;
    }

    private function fail(string $field, string $message): never
    {
        throw new ValidationException(new ValidationResult([$field => $message]));
    }
}
