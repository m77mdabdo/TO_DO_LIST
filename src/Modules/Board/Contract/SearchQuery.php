<?php

declare(strict_types=1);

namespace App\Modules\Board\Contract;


/**
 * The search page's question, read from the query string. Everything is
 * checked against a fixed shape and unknown values are dropped, so a
 * saved or shared link can never carry anything else.
 *
 *   q          text (titles, descriptions, comments, checklists, attachments)
 *   workspace  a workspace's public id (default: every workspace I am in)
 *   board      a board's public id
 *   list       a list id (only with a board)
 *   member     me | none | a user id
 *   label      a label name, on any board
 *   due        overdue | today | week | none
 *   status     open | done
 *   archived   1 to include archived cards and boards
 */
final class SearchQuery
{
    private const ULID = '/^[0-9A-HJKMNP-TV-Z]{26}$/';
    private const KEYS = ['q', 'workspace', 'board', 'list', 'member', 'label', 'due', 'status', 'archived'];

    private function __construct(
        public readonly string $q,
        public readonly string $workspace,
        public readonly string $board,
        public readonly ?int $list,
        public readonly string $label,
        public readonly bool $archived,
        public readonly CardFilter $filter,
    ) {
    }

    /** @param callable(string): string $input */
    public static function fromInput(callable $input): self
    {
        $ulid = static fn (string $v): string => preg_match(self::ULID, $v) === 1 ? $v : '';
        $board = $ulid($input('board'));
        $list = $input('list');

        return new self(
            trim(mb_substr($input('q'), 0, 100)),
            $ulid($input('workspace')),
            $board,
            $board !== '' && ctype_digit($list) && $list !== '0' ? (int) $list : null,
            trim(mb_substr($input('label'), 0, 50)),
            $input('archived') === '1',
            // Text is searched by the search sources, so the card filter gets none.
            CardFilter::fromInput(static fn (string $key) => $key === 'q' || $key === 'label' ? '' : $input($key)),
        );
    }

    public static function fromQueryString(string $query): self
    {
        parse_str(ltrim($query, '?'), $values);

        return self::fromInput(static fn (string $key): string => is_string($values[$key] ?? null) ? trim($values[$key]) : '');
    }

    public function hasText(): bool
    {
        return mb_strlen($this->q) >= 2;
    }

    /** Filters other than the text and the scope (workspace, board, archive). */
    public function hasCardFilters(): bool
    {
        return $this->list !== null || $this->label !== '' || $this->filter->isActive();
    }

    /** True when there is something to look for. */
    public function isActive(): bool
    {
        return $this->hasText() || $this->hasCardFilters();
    }

    /** @return array<string, string> only the values that are set, in a fixed order */
    public function query(): array
    {
        $filter = $this->filter->query();

        return array_filter([
            'q'         => $this->q,
            'workspace' => $this->workspace,
            'board'     => $this->board,
            'list'      => $this->list !== null ? (string) $this->list : '',
            'member'    => $filter['member'] ?? '',
            'label'     => $this->label,
            'due'       => $filter['due'] ?? '',
            'status'    => $filter['status'] ?? '',
            'archived'  => $this->archived ? '1' : '',
        ], static fn (string $value) => $value !== '');
    }

    public function queryString(): string
    {
        return http_build_query($this->query(), '', '&', PHP_QUERY_RFC3986);
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return self::KEYS;
    }
}
