<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Common\TransactionManagerInterface;
use PDO;
use Throwable;

/**
 * PDO SQLite Transaction Manager supporting nested transaction savepoints.
 */
final class TransactionManager implements TransactionManagerInterface
{
    private int $transactionDepth = 0;

    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec("SAVEPOINT trans_{$this->transactionDepth}");
        }
        $this->transactionDepth++;
    }

    public function commit(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }

        $this->transactionDepth--;

        if ($this->transactionDepth === 0) {
            $this->pdo->commit();
        } else {
            $this->pdo->exec("RELEASE SAVEPOINT trans_{$this->transactionDepth}");
        }
    }

    public function rollBack(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }

        $this->transactionDepth--;

        if ($this->transactionDepth === 0) {
            $this->pdo->rollBack();
        } else {
            $this->pdo->exec("ROLLBACK TO SAVEPOINT trans_{$this->transactionDepth}");
        }
    }

    public function transactional(callable $operation): mixed
    {
        $this->beginTransaction();
        try {
            $result = $operation();
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }
}
