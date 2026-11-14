<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;
use ZipArchive;

/**
 * Makes a copy of the database (and, if asked, of the uploaded files) in
 * a backups folder, and keeps only the newest few.
 *
 * SQLite: a consistent copy with VACUUM INTO, safe while the app runs.
 * MySQL: a plain SQL dump written by PHP (no mysqldump needed), gzipped
 * when zlib is available. Restore it with phpMyAdmin or `mysql < file`.
 */
final class Backup
{
    public function __construct(private PDO $db, private string $dir)
    {
    }

    /** @return string the file written */
    public function database(string $stamp): string
    {
        $this->ensureDir();
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        return $driver === 'sqlite' ? $this->sqlite($stamp) : $this->mysql($stamp);
    }

    /** Zips a folder of uploaded files. @return string|null the file written, or null when there is nothing to save */
    public function files(string $source, string $stamp): ?string
    {
        if (!is_dir($source)) {
            return null;
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The zip extension is needed to back up attachments.');
        }
        $this->ensureDir();
        $target = $this->dir . '/attachments-' . $stamp . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create ' . $target);
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($items as $item) {
            if ($item->isFile()) {
                $zip->addFile($item->getPathname(), substr($item->getPathname(), strlen(rtrim($source, '/')) + 1));
            }
        }
        $zip->close();

        return $target;
    }

    /**
     * Deletes all but the newest $keep backups of each kind.
     *
     * @return list<string> files removed
     */
    public function prune(int $keep): array
    {
        $removed = [];
        foreach (['database-*', 'attachments-*'] as $pattern) {
            $files = glob($this->dir . '/' . $pattern) ?: [];
            rsort($files); // names carry a sortable timestamp
            foreach (array_slice($files, max(1, $keep)) as $old) {
                if (@unlink($old)) {
                    $removed[] = $old;
                }
            }
        }

        return $removed;
    }

    private function sqlite(string $stamp): string
    {
        $target = $this->dir . '/database-' . $stamp . '.sqlite';
        $this->db->exec('VACUUM INTO ' . $this->db->quote($target));

        return $target;
    }

    private function mysql(string $stamp): string
    {
        $gzip = function_exists('gzopen');
        $target = $this->dir . '/database-' . $stamp . '.sql' . ($gzip ? '.gz' : '');
        $out = $gzip ? gzopen($target, 'wb6') : fopen($target, 'wb');
        if ($out === false) {
            throw new RuntimeException('Could not write ' . $target);
        }
        $write = static fn (string $text) => $gzip ? gzwrite($out, $text) : fwrite($out, $text);

        $write("-- Todo Board backup, " . gmdate('Y-m-d H:i:s') . " UTC\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
        foreach ($this->db->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM) as [$table]) {
            $name = '`' . str_replace('`', '``', (string) $table) . '`';
            $create = $this->db->query('SHOW CREATE TABLE ' . $name)->fetch(PDO::FETCH_NUM)[1];
            $write("DROP TABLE IF EXISTS $name;\n$create;\n\n");

            $rows = $this->db->query('SELECT * FROM ' . $name, PDO::FETCH_NUM);
            $batch = [];
            foreach ($rows as $row) {
                $batch[] = '(' . implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : $this->db->quote((string) $v), $row)) . ')';
                if (count($batch) === 200) {
                    $write("INSERT INTO $name VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                $write("INSERT INTO $name VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            $write("\n");
        }
        $write("SET FOREIGN_KEY_CHECKS = 1;\n");
        $gzip ? gzclose($out) : fclose($out);

        return $target;
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->dir) && !mkdir($this->dir, 0750, true) && !is_dir($this->dir)) {
            throw new RuntimeException('Could not create the backups folder ' . $this->dir);
        }
    }
}
