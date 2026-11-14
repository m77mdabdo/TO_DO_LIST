<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use Throwable;

/**
 * Runs work in one database transaction. Nested calls join the outer
 * transaction, so a use case and the event listeners it triggers
 * (which run synchronously) commit or roll back together.
 */
final class Transactions
{
    private int $depth = 0;
    private int $savepoints = 0;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function run(callable $work): mixed
    {
        if ($this->depth === 0) {
            $this->pdo->beginTransaction();
        }

        $this->depth++;

        try {
            $result = $work();
            $this->depth--;

            if ($this->depth === 0) {
                $this->pdo->commit();
            }

            return $result;
        } catch (Throwable $e) {
            $this->depth--;

            if ($this->depth === 0 && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Like run(), but when already inside a transaction the work gets its own
     * savepoint: if it fails, only its writes are undone and the outer
     * transaction carries on. Used for automations, so a failing rule never
     * breaks the change that triggered it.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function attempt(callable $work): mixed
    {
        if ($this->depth === 0) {
            return $this->run($work);
        }

        $name = 'sp_' . (++$this->savepoints);
        $this->pdo->exec("SAVEPOINT $name");
        $this->depth++;

        try {
            $result = $work();
            $this->depth--;
            $this->pdo->exec("RELEASE SAVEPOINT $name");

            return $result;
        } catch (Throwable $e) {
            $this->depth--;
            $this->pdo->exec("ROLLBACK TO SAVEPOINT $name");
            $this->pdo->exec("RELEASE SAVEPOINT $name");

            throw $e;
        }
    }
}
