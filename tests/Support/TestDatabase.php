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

    private static int $sequence = 0;

    private static bool $cleanupRegistered = false;

    private static ?PDO $server = null;

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

        $name = 'todo_test_' . getmypid() . '_' . self::$sequence++;
        self::server()->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        if (!self::$cleanupRegistered) {
            register_shutdown_function([self::class, 'dropCreated']);
            self::$cleanupRegistered = true;
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

    /**
     * Drops the databases made since the last call. tests/run.php calls this after
     * every test: left until the end of the run, the ~290 schemas pile up tens of
     * thousands of tables, and MySQL 8 then fails cascading deletes with error 1615
     * ("Prepared statement needs to be re-prepared") once table definitions no
     * longer fit its definition cache.
     */
    public static function dropCreated(): void
    {
        if (self::$created === []) {
            return;
        }

        $server = self::server();
        foreach (self::$created as $db) {
            $server->exec("DROP DATABASE IF EXISTS `$db`");
        }
        self::$created = [];
    }

    private static function server(): PDO
    {
        if (self::$server !== null) {
            return self::$server;
        }

        self::$server = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', getenv('TEST_DB_HOST') ?: '127.0.0.1', getenv('TEST_DB_PORT') ?: '3306'),
            getenv('TEST_DB_USER') ?: 'root',
            getenv('TEST_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        // A test that left a transaction open must not make DROP DATABASE wait forever.
        self::$server->exec('SET SESSION lock_wait_timeout = 10');

        return self::$server;
    }
}
