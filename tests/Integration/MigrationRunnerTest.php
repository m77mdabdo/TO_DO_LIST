<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Database\ConnectionFactory;
use App\Database\Migrations\MigrationRunner;
use App\Database\Migrations\SqlScript;
use RuntimeException;
use Tests\TestCase;

final class MigrationRunnerTest extends TestCase
{
    private string $dir;

    public function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/todo-migrations-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/a', 0777, true);
        mkdir($this->dir . '/b', 0777, true);
    }

    public function testRunsPendingMigrationsInIdOrderAcrossFolders(): void
    {
        file_put_contents("$this->dir/b/2026_02_01_000000_second.sqlite.sql", 'CREATE TABLE two (id INTEGER);');
        file_put_contents("$this->dir/a/2026_01_01_000000_first.sqlite.sql", 'CREATE TABLE one (id INTEGER);');
        file_put_contents("$this->dir/a/2026_01_01_000000_first.mysql.sql", 'not for sqlite');
        $runner = new MigrationRunner(ConnectionFactory::sqlite(':memory:'), ["$this->dir/a", "$this->dir/b"]);

        $this->assertSame(['2026_01_01_000000_first', '2026_02_01_000000_second'], $runner->migrate());
        $this->assertSame([], $runner->migrate());
        $this->assertSame([], $runner->pending());
    }

    public function testAFailedMigrationLeavesNothingBehind(): void
    {
        file_put_contents("$this->dir/a/2026_01_01_000000_broken.sqlite.sql", "CREATE TABLE ok (id INTEGER);\nNOT SQL;");
        $pdo = ConnectionFactory::sqlite(':memory:');
        $runner = new MigrationRunner($pdo, ["$this->dir/a"]);

        $this->assertThrows(RuntimeException::class, fn () => $runner->migrate());
        $this->assertSame(['2026_01_01_000000_broken'], $runner->pending());
        $this->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'ok'")->fetchColumn());
    }

    public function testDuplicateIdsAreRejected(): void
    {
        file_put_contents("$this->dir/a/2026_01_01_000000_same.sqlite.sql", 'SELECT 1;');
        file_put_contents("$this->dir/b/2026_01_01_000000_same.sqlite.sql", 'SELECT 1;');
        $runner = new MigrationRunner(ConnectionFactory::sqlite(':memory:'), ["$this->dir/a", "$this->dir/b"]);

        $this->assertThrows(RuntimeException::class, fn () => $runner->migrate());
    }

    public function testTriggerBodiesStayOneStatement(): void
    {
        $sql = "-- comment\nCREATE TABLE t (id INTEGER);\n\nCREATE TRIGGER t_ai AFTER INSERT ON t BEGIN\n  SELECT 1;\n  SELECT 2;\nEND;\nSELECT 3;";

        $this->assertCount(3, SqlScript::statements($sql));
    }

    public function testTheAppSchemaIsRecordedAsApplied(): void
    {
        $pdo = \Tests\Support\Database::fresh();
        $ids = $pdo->query('SELECT id FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertTrue(in_array('2026_01_01_000000_core_baseline', $ids, true));
    }
}
