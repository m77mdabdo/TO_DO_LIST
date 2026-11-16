<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract;

/**
 * How one person set up a board's table: which columns show and in what
 * order, how rows are sorted, and how they are grouped. The card title is
 * always the first column. Custom fields are columns named "field:ID".
 */
final class TableLayout
{
    /** key => header (the translation key) */
    public const COLUMNS = [
        'list'      => 'List',
        'start'     => 'Start',
        'due'       => 'Due',
        'status'    => 'Status',
        'labels'    => 'Labels',
        'people'    => 'People',
        'checklist' => 'Checklist',
    ];
    public const SORTS = ['title' => 'Card', 'list' => 'List', 'start' => 'Start', 'due' => 'Due', 'status' => 'Status'];
    public const GROUPS = ['' => 'No grouping', 'list' => 'List', 'member' => 'Person', 'label' => 'Label'];
    public const MAX_COLUMNS = 60;

    /** @param list<string>|null $columns null: every column, the default */
    public function __construct(
        public readonly ?array $columns = null,
        public readonly string $sort = 'list',
        public readonly bool $desc = false,
        public readonly string $group = '',
        public readonly bool $saved = false,
    ) {
    }

    public static function isColumn(string $key): bool
    {
        return isset(self::COLUMNS[$key]) || preg_match('/^field:[1-9]\d{0,17}$/', $key) === 1;
    }

    /**
     * The columns to show, in order: the saved ones, or every column with the fields last.
     *
     * @param list<string> $fieldKeys "field:ID" of the board's custom fields
     * @return list<string>
     */
    public function shown(array $fieldKeys): array
    {
        if ($this->columns === null) {
            return [...array_keys(self::COLUMNS), ...$fieldKeys];
        }

        return array_values(array_filter($this->columns, static fn (string $key) => isset(self::COLUMNS[$key]) || in_array($key, $fieldKeys, true)));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['columns' => $this->columns, 'sort' => $this->sort, 'desc' => $this->desc, 'group' => $this->group];
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data, bool $saved = true): self
    {
        $columns = is_array($data['columns'] ?? null) ? array_values(array_unique(array_filter($data['columns'], static fn ($k) => is_string($k) && self::isColumn($k)))) : null;
        $sort = is_string($data['sort'] ?? null) && isset(self::SORTS[$data['sort']]) ? $data['sort'] : 'list';
        $group = is_string($data['group'] ?? null) && isset(self::GROUPS[$data['group']]) ? $data['group'] : '';

        return new self($columns, $sort, ($data['desc'] ?? false) === true, $group, $saved);
    }
}
