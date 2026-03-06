<?php

declare(strict_types=1);

namespace Illuminate\Database;

use Illuminate\Support\Collection;

class DatabaseTransactionsManager
{
    /**
     * All of the committed transactions.
     *
     * @var \Illuminate\Support\Collection<int, \Illuminate\Database\DatabaseTransactionRecord>
     */
    protected \Illuminate\Support\Collection $committedTransactions;

    /**
     * All of the pending transactions.
     *
     * @var \Illuminate\Support\Collection<int, \Illuminate\Database\DatabaseTransactionRecord>
     */
    protected \Illuminate\Support\Collection $pendingTransactions;

    /**
     * The current transaction.
     *
     * @var array
     */
    protected $currentTransaction = [];

    /**
     * Create a new database transactions manager instance.
     */
    public function __construct()
    {
        $this->committedTransactions = new Collection();
        $this->pendingTransactions = new Collection();
    }

    /**
     * Start a new database transaction.
     *
     * @param  string  $connection
     * @param  int  $level
     */
    public function begin($connection, $level): void
    {
        $this->pendingTransactions->push(
            $newTransaction = new DatabaseTransactionRecord(
                $connection,
                $level,
                $this->currentTransaction[$connection] ?? null
            )
        );

        $this->currentTransaction[$connection] = $newTransaction;
    }

    /**
     * Commit the root database transaction and execute callbacks.
     *
     * @param  string  $connection
     * @param  int  $levelBeingCommitted
     * @param  int  $newTransactionLevel
     * @return array
     */
    public function commit($connection, $levelBeingCommitted, $newTransactionLevel)
    {
        $this->stageTransactions($connection, $levelBeingCommitted);

        if (isset($this->currentTransaction[$connection])) {
            $this->currentTransaction[$connection] = $this->currentTransaction[$connection]->parent;
        }

        if (! $this->afterCommitCallbacksShouldBeExecuted($newTransactionLevel) &&
            $newTransactionLevel !== 0) {
            return [];
        }

        // This method is only called when the root database transaction is committed so there
        // shouldn't be any pending transactions, but going to clear them here anyways just
        // in case. This method could be refactored to receive a level in the future too.
        $this->pendingTransactions = $this->pendingTransactions->reject(
            fn ($transaction): bool => $transaction->connection === $connection &&
                $transaction->level >= $levelBeingCommitted
        )->values();

        [$forThisConnection, $forOtherConnections] = $this->committedTransactions->partition(
            fn ($transaction): bool => $transaction->connection == $connection
        );

        $this->committedTransactions = $forOtherConnections->values();

        $forThisConnection->map->executeCallbacks();

        return $forThisConnection;
    }

    /**
     * Move relevant pending transactions to a committed state.
     *
     * @param  string  $connection
     * @param  int  $levelBeingCommitted
     */
    public function stageTransactions($connection, $levelBeingCommitted): void
    {
        $this->committedTransactions = $this->committedTransactions->merge(
            $this->pendingTransactions->filter(
                fn ($transaction): bool => $transaction->connection === $connection &&
                                     $transaction->level >= $levelBeingCommitted
            )
        );

        $this->pendingTransactions = $this->pendingTransactions->reject(
            fn ($transaction): bool => $transaction->connection === $connection &&
                                 $transaction->level >= $levelBeingCommitted
        );
    }

    /**
     * Rollback the active database transaction.
     *
     * @param  string  $connection
     * @param  int  $newTransactionLevel
     */
    public function rollback($connection, $newTransactionLevel): void
    {
        if ($newTransactionLevel === 0) {
            $this->removeAllTransactionsForConnection($connection);
        } else {
            $this->pendingTransactions = $this->pendingTransactions->reject(
                fn ($transaction): bool => $transaction->connection == $connection &&
                                     $transaction->level > $newTransactionLevel
            )->values();

            if ($this->currentTransaction) {
                do {
                    $this->removeCommittedTransactionsThatAreChildrenOf($this->currentTransaction[$connection]);

                    $this->currentTransaction[$connection]->executeCallbacksForRollback();

                    $this->currentTransaction[$connection] = $this->currentTransaction[$connection]->parent;
                } while (
                    isset($this->currentTransaction[$connection]) &&
                    $this->currentTransaction[$connection]->level > $newTransactionLevel
                );
            }
        }
    }

    /**
     * Remove all pending, completed, and current transactions for the given connection name.
     *
     * @param  string  $connection
     * @return void
     */
    protected function removeAllTransactionsForConnection($connection)
    {
        if ($this->currentTransaction) {
            for ($currentTransaction = $this->currentTransaction[$connection]; isset($currentTransaction); $currentTransaction = $currentTransaction->parent) {
                $currentTransaction->executeCallbacksForRollback();
            }
        }

        $this->currentTransaction[$connection] = null;

        $this->pendingTransactions = $this->pendingTransactions->reject(
            fn ($transaction): bool => $transaction->connection == $connection
        )->values();

        $this->committedTransactions = $this->committedTransactions->reject(
            fn ($transaction): bool => $transaction->connection == $connection
        )->values();
    }

    /**
     * Remove all transactions that are children of the given transaction.
     *
     * @return void
     */
    protected function removeCommittedTransactionsThatAreChildrenOf(DatabaseTransactionRecord $transaction)
    {
        [$removedTransactions, $this->committedTransactions] = $this->committedTransactions->partition(
            fn ($committed): bool => $committed->connection == $transaction->connection &&
                               $committed->parent === $transaction
        );

        // There may be multiple deeply nested transactions that have already committed that we
        // also need to remove. We will recurse down the children of all removed transaction
        // instances until there are no more deeply nested child transactions for removal.
        $removedTransactions->each(
            fn (\Illuminate\Database\DatabaseTransactionRecord $transaction) => $this->removeCommittedTransactionsThatAreChildrenOf($transaction)
        );
    }

    /**
     * Register a transaction callback.
     *
     * @param  callable  $callback
     * @return void
     */
    public function addCallback($callback)
    {
        if ($current = $this->callbackApplicableTransactions()->last()) {
            return $current->addCallback($callback);
        }

        $callback();
    }

    /**
     * Register a callback for transaction rollback.
     *
     * @param  callable  $callback
     * @return void
     */
    public function addCallbackForRollback($callback)
    {
        if ($current = $this->callbackApplicableTransactions()->last()) {
            return $current->addCallbackForRollback($callback);
        }
    }

    /**
     * Get the transactions that are applicable to callbacks.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\DatabaseTransactionRecord>
     */
    public function callbackApplicableTransactions(): \Illuminate\Support\Collection
    {
        return $this->pendingTransactions;
    }

    /**
     * Determine if after commit callbacks should be executed for the given transaction level.
     *
     * @param  int  $level
     */
    public function afterCommitCallbacksShouldBeExecuted($level): bool
    {
        return $level === 0;
    }

    /**
     * Get all of the pending transactions.
     */
    public function getPendingTransactions(): \Illuminate\Support\Collection
    {
        return $this->pendingTransactions;
    }

    /**
     * Get all of the committed transactions.
     */
    public function getCommittedTransactions(): \Illuminate\Support\Collection
    {
        return $this->committedTransactions;
    }
}
