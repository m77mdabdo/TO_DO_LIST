<?php

declare(strict_types=1);

namespace App\Shared\Export;

use Closure;

/**
 * Extra columns other modules add to a board's CSV (custom fields). Each
 * source gets the board and its cards and returns its column names and
 * the cells per card; a source never widens what the user may see.
 */
final class ExportColumns
{
    /** @var list<Closure(int, list<int>): array{headers: list<string>, cells: array<int, list<string>>}> */
    private array $sources = [];

    /** @param Closure(int, list<int>): array{headers: list<string>, cells: array<int, list<string>>} $source */
    public function add(Closure $source): void
    {
        $this->sources[] = $source;
    }

    /**
     * @param list<int> $cardIds
     * @return array{headers: list<string>, cells: array<int, list<string>>} cells per card, one per header
     */
    public function forBoard(int $boardId, array $cardIds): array
    {
        $headers = [];
        $cells = [];
        foreach ($this->sources as $source) {
            $part = $source($boardId, $cardIds);
            $width = count($part['headers']);
            foreach ($cardIds as $cardId) {
                $row = $part['cells'][$cardId] ?? [];
                $cells[$cardId] = [...($cells[$cardId] ?? []), ...array_pad(array_slice($row, 0, $width), $width, '')];
            }
            $headers = [...$headers, ...$part['headers']];
        }

        return ['headers' => $headers, 'cells' => $cells];
    }
}
