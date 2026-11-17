<?php

declare(strict_types=1);

namespace App\Shared\Export;

use Closure;

/**
 * Files that go into a board's ZIP export next to the JSON: attachments
 * today. A module returns, for the cards being exported, where each file is
 * on disk and the name it gets inside the archive.
 */
final class ExportFiles
{
    /** @var array<string, Closure(list<int>): list<array{path: string, name: string, size: int}>> */
    private array $sources = [];

    /** @param Closure(list<int>): list<array{path: string, name: string, size: int}> $forCards */
    public function add(string $folder, Closure $forCards): void
    {
        $this->sources[$folder] = $forCards;
    }

    /**
     * @param list<int> $cardIds
     * @return list<array{path: string, name: string, size: int}> name is the path inside the archive
     */
    public function forCards(array $cardIds): array
    {
        $out = [];
        foreach ($this->sources as $folder => $source) {
            foreach ($source($cardIds) as $file) {
                $out[] = ['path' => $file['path'], 'name' => $folder . '/' . $file['name'], 'size' => $file['size']];
            }
        }

        return $out;
    }
}
