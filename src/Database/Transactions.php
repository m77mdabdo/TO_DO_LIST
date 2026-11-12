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
}
