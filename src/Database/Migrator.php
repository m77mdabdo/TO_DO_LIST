<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

/**
 * Creates the tables from database/schema.<driver>.sql the first time the app runs.
 */
final class Migrator
{
    public function __construct(private PDO $pdo, private string $schemaDir)
    {
    }

    public function migrate(): void
    {
        if ($this->hasTables()) {
            return;
        }

        $sql = (string) file_get_contents($this->schemaFile());

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $this->pdo->exec($statement);
        }
    }

    private function hasTables(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM tasks LIMIT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    private function schemaFile(): string
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'mysql' : 'sqlite';

        return rtrim($this->schemaDir, '/') . "/schema.$driver.sql";
    }
}
