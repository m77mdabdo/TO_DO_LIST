<?php

declare(strict_types=1);

namespace App\Modules\Field\Contract;

/**
 * A custom field defined on a board. Each card may hold one value for it.
 *
 * Values are kept as text: numbers in plain decimal form, dates as
 * Y-m-d, a choice as its option id (so renaming a choice keeps it on the
 * cards), a checkbox as "1" (unchecked has no value).
 */
final class CustomField
{
    /** type => name shown in the form (the translation key) */
    public const TYPES = [
        'text'     => 'Text',
        'number'   => 'Number',
        'date'     => 'Date',
        'select'   => 'Choice from a list',
        'checkbox' => 'Checkbox',
    ];

    public const MAX_PER_BOARD = 20;
    public const MAX_OPTIONS = 30;
    public const NAME_MAX = 60;
    public const OPTION_MAX = 40;
    public const TEXT_MAX = 500;

    /** @param list<array{id: string, name: string}> $options */
    public function __construct(
        public readonly int $id,
        public readonly int $boardId,
        public readonly string $name,
        public readonly string $type,
        public readonly array $options,
        public readonly int $position,
        public readonly bool $onCard,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $options = json_decode((string) $row['options'], true);
        $clean = [];
        foreach (is_array($options) ? $options : [] as $option) {
            if (is_array($option) && isset($option['id'], $option['name'])) {
                $clean[] = ['id' => (string) $option['id'], 'name' => (string) $option['name']];
            }
        }

        return new self((int) $row['id'], (int) $row['board_id'], (string) $row['name'], (string) $row['type'], $clean, (int) $row['position'], (bool) $row['on_card']);
    }

    public function optionName(string $id): ?string
    {
        foreach ($this->options as $option) {
            if ($option['id'] === $id) {
                return $option['name'];
            }
        }

        return null;
    }

    /** The stored value as people read it ("", when it shows nothing). */
    public function display(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($this->type) {
            'select'   => (string) $this->optionName($value),
            'checkbox' => $value === '1' ? '✓' : '',
            default    => $value,
        };
    }
}
