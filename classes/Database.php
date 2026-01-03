<?php

namespace Classes;

use PDO;
use PDOException;

final class Database
{
    public static function connect(array $config): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if ($config['driver'] === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['port'],
                $config['name']
            );
            $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
        } else {
            $dir = dirname($config['sqlite_path']);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $config['sqlite_path'], null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
        }

        self::migrate($pdo, $config['driver'] === 'mysql' ? 'mysql' : 'sqlite');

        return $pdo;
    }

    // Creates the tables the first time the app runs.
    private static function migrate(PDO $pdo, string $driver): void
    {
        try {
            $pdo->query('SELECT 1 FROM tasks LIMIT 1');
            return;
        } catch (PDOException) {
            // tables are missing, create them below
        }

        $sql = file_get_contents(BASE_PATH . "/database/schema.$driver.sql");

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
        }
    }
}
