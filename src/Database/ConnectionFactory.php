<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class ConnectionFactory
{
    private const OPTIONS = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    /** @param array<string, string> $config the "db" section of config.php */
    public static function make(array $config): PDO
    {
        return ($config['driver'] ?? 'sqlite') === 'mysql'
            ? self::mysql($config)
            : self::sqlite($config['sqlite_path']);
    }

    /** @param array<string, string> $config */
    public static function mysql(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['name']
        );

        return new PDO($dsn, $config['user'], $config['pass'], self::OPTIONS);
    }

    /** Pass ":memory:" for a throwaway database (used by the tests). */
    public static function sqlite(string $path): PDO
    {
        if ($path !== ':memory:' && !is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $pdo = new PDO('sqlite:' . $path, null, null, self::OPTIONS);
        $pdo->exec('PRAGMA foreign_keys = ON');

        if ($path !== ':memory:') {
            $pdo->exec('PRAGMA journal_mode = WAL');
        }

        return $pdo;
    }
}
