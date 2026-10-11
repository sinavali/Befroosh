<?php

declare(strict_types=1);

namespace App\Domain\Common;

/**
 * Contract for transactional atomicity across database operations.
 */
interface TransactionManagerInterface
{
    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    /**
     * Executes a callable within a transaction boundary.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transactional(callable $operation): mixed;
}
