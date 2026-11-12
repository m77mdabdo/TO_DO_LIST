<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Applies versioned SQL migrations exactly once each.
 *
 * Every module ships its own folder of files named
 *   <id>.<driver>.sql   e.g. 2026_11_12_000100_create_companies.sqlite.sql
 * Ids sort by time, so ksort() gives the run order (O(n log n)). The ids
 * already applied are loaded once into a hash set, so "is it pending?"
 * is an O(1) lookup per migration instead of a query each.
 */
final class MigrationRunner
{
    private const TABLE = 'schema_migrations';

    /** @param list<string> $directories */
    public function __construct(private PDO $pdo, private array $directories)
    {
    }

    /** @return list<string> ids applied by this call, in order */
    public function migrate(): array
    {
        $this->ensureTable();

        $applied = array_flip($this->appliedIds());
        $ran = [];

        foreach ($this->available() as $id => $file) {
            if (isset($applied[$id])) {
                continue;
            }

            $this->run($id, $file);
            $ran[] = $id;
        }

        return $ran;
    }

    /** @return list<string> */
    public function pending(): array
    {
        $this->ensureTable();
        $applied = array_flip($this->appliedIds());

        return array_values(array_filter(array_keys($this->available()), static fn (string $id) => !isset($applied[$id])));
    }

    /** @return array<string, string> id => file path, sorted by id */
    private function available(): array
    {
        $suffix = '.' . $this->driver() . '.sql';
        $found = [];

        foreach ($this->directories as $directory) {
            foreach (glob(rtrim($directory, '/') . '/*' . $suffix) ?: [] as $file) {
                $id = basename($file, $suffix);

                if (isset($found[$id])) {
                    throw new RuntimeException("Two migrations share the id [$id]: $found[$id] and $file.");
                }

                $found[$id] = $file;
            }
        }

        ksort($found, SORT_STRING);

        return $found;
    }

    private function run(string $id, string $file): void
    {
        // MySQL commits DDL implicitly, so a transaction would only pretend to protect it.
        $transactional = $this->driver() === 'sqlite';

        if ($transactional) {
            $this->pdo->beginTransaction();
        }

        try {
            foreach (SqlScript::statements((string) file_get_contents($file)) as $statement) {
                $this->pdo->exec($statement);
            }

            $this->pdo->prepare('INSERT INTO ' . self::TABLE . ' (id, applied_at) VALUES (?, ?)')
                ->execute([$id, gmdate('Y-m-d H:i:s')]);

            if ($transactional) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($transactional && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw new RuntimeException("Migration [$id] failed: " . $e->getMessage(), 0, $e);
        }
    }

    private function ensureTable(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            id         VARCHAR(190) NOT NULL PRIMARY KEY,
            applied_at VARCHAR(19)  NOT NULL
        )');
    }

    /** @return list<string> */
    private function appliedIds(): array
    {
        return array_map('strval', $this->pdo->query('SELECT id FROM ' . self::TABLE)->fetchAll(PDO::FETCH_COLUMN));
    }

    private function driver(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'mysql' : 'sqlite';
    }
}
