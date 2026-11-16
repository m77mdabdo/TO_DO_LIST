<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * The database the tests run on. SQLite in memory by default; set
 * TEST_DB=mysql (and TEST_DB_HOST, TEST_DB_PORT, TEST_DB_USER, TEST_DB_PASS)
 * to run the same tests on MySQL/MariaDB. Each App then gets its own
 * throwaway database, dropped when the run ends.
 *
 *   TEST_DB=mysql TEST_DB_USER=root php tests/run.php
 */
final class TestDatabase
{
    /** @var list<string> */
    private static array $created = [];

    public static function driver(): string
    {
        return getenv('TEST_DB') === 'mysql' ? 'mysql' : 'sqlite';
    }

    /** @return array<string, string> the "db" section of the config */
    public static function config(): array
    {
        if (self::driver() === 'sqlite') {
            return ['driver' => 'sqlite', 'sqlite_path' => ':memory:'];
        }

        $server = self::server();
        $name = 'todo_test_' . getmypid() . '_' . count(self::$created);
        $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        if (self::$created === []) {
            register_shutdown_function(static function (): void {
                $server = self::server();
                foreach (self::$created as $db) {
                    $server->exec("DROP DATABASE IF EXISTS `$db`");
                }
            });
        }
        self::$created[] = $name;

        return [
            'driver' => 'mysql',
            'host'   => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port'   => getenv('TEST_DB_PORT') ?: '3306',
            'name'   => $name,
            'user'   => getenv('TEST_DB_USER') ?: 'root',
            'pass'   => getenv('TEST_DB_PASS') ?: '',
        ];
    }

    private static function server(): PDO
    {
        return new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', getenv('TEST_DB_HOST') ?: '127.0.0.1', getenv('TEST_DB_PORT') ?: '3306'),
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
