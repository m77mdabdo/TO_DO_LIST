<?php

declare(strict_types=1);

namespace App\Modules\Board\Application;

use App\Core\Config;
use App\Modules\Board\Contract\BoardSummary;
use App\Modules\Card\Contract\CardQueries;
use App\Shared\Export\ExportFiles;
use RuntimeException;
use ZipArchive;

/**
 * A board as one ZIP file: board.json (the same document as the JSON export)
 * and the attached files, by card. The size is shown before it is built, and
 * a board over the limit (exports.zip_max_mb, 200 MB by default) is refused.
 * Callers check the Export permission first.
 */
final class BoardZip
{
    public function __construct(
        private BoardExport $export,
        private CardQueries $cards,
        private ExportFiles $files,
        private Config $config,
    ) {
    }

    public static function available(): bool
    {
        return class_exists(ZipArchive::class);
    }

    public function maxBytes(): int
    {
        return max(1, (int) $this->config->get('exports.zip_max_mb', 200)) * 1024 * 1024;
    }

    /** @return array{cards: int, files: int, bytes: int, tooBig: bool} */
    public function preview(BoardSummary $board): array
    {
        $cardIds = $this->cardIds($board);
        $files = $this->files->forCards($cardIds);
        $bytes = array_sum(array_map(static fn (array $f) => $f['size'], $files));

        return ['cards' => count($cardIds), 'files' => count($files), 'bytes' => $bytes, 'tooBig' => $bytes > $this->maxBytes()];
    }

    /** @param string $workspaceName for the JSON document @return string path of a temporary ZIP file */
    public function build(BoardSummary $board, string $workspaceName): string
    {
        if (!self::available()) {
            throw new RuntimeException('The PHP zip extension is not installed.');
        }
        $files = $this->files->forCards($this->cardIds($board));
        if (array_sum(array_map(static fn (array $f) => $f['size'], $files)) > $this->maxBytes()) {
            throw new RuntimeException('This board is larger than the export limit.');
        }
        $path = tempnam(sys_get_temp_dir(), 'board-zip-');
        if ($path === false) {
            throw new RuntimeException('Could not make a temporary file.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the ZIP file.');
        }
        $zip->addFromString('board.json', (string) json_encode($this->export->document([$board], $workspaceName), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $used = [];
        foreach ($files as $file) {
            $name = $file['name'];
            // Never a path that climbs out of the archive's folder.
            $name = str_replace(['\\', '..'], ['/', '_'], $name);
            if (isset($used[$name])) {
                continue;
            }
            $used[$name] = true;
            $zip->addFile($file['path'], $name);
        }
        $zip->close();

        return $path;
    }

    /** @return list<int> open and archived cards, like the JSON export */
    private function cardIds(BoardSummary $board): array
    {
        $open = array_merge([], ...array_values($this->cards->openCardsByList($board->id)));

        return array_map(static fn ($c) => $c->id, [...$open, ...$this->cards->archivedCards($board->id)]);
    }
}
