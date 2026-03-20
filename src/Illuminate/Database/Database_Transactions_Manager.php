<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Illuminate\Support\Collection;
class Database_Transactions_Manager
{
    /**
     * All of the committed transactions.
     *
     * @var \Illuminate\Support\Collection<int, \Illuminate\Database\DatabaseTransactionRecord>
     */
    protected \Illuminate\Support\Collection $committed_transactions;
    /**
     * All of the pending transactions.
     *
     * @var \Illuminate\Support\Collection<int, \Illuminate\Database\DatabaseTransactionRecord>
     */
    protected \Illuminate\Support\Collection $pending_transactions;
    /**
     * The current transaction.
     *
     * @var array
     */
    protected $current_transaction = [];
    /**
     * Create a new database transactions manager instance.
     */
    public function __construct()
    {
        $this->committed_transactions = new Collection();
        $this->pending_transactions = new Collection();
    }
    /**
     * Start a new database transaction.
     *
     * @param  string  $connection
     * @param  int  $level
     */
    public function begin($connection, $level): void
    {
        $this->pending_transactions->push($new_transaction = new Database_Transaction_Record($connection, $level, $this->current_transaction[$connection] ?? null));
        $this->current_transaction[$connection] = $new_transaction;
    }
    /**
     * Commit the root database transaction and execute callbacks.
     *
     * @param  string  $connection
     * @param  int  $levelBeingCommitted
     * @param  int  $newTransactionLevel
     * @return array
     */
    public function commit($connection, $level_being_committed, $new_transaction_level)
    {
        $this->stage_transactions($connection, $level_being_committed);
        if (isset($this->current_transaction[$connection])) {
            $this->current_transaction[$connection] = $this->current_transaction[$connection]->parent;
        }
        if (!$this->after_commit_callbacks_should_be_executed($new_transaction_level) && $new_transaction_level !== 0) {
            return [];
        }
        // This method is only called when the root database transaction is committed so there
        // shouldn't be any pending transactions, but going to clear them here anyways just
        // in case. This method could be refactored to receive a level in the future too.
        $this->pending_transactions = $this->pending_transactions->reject(fn($transaction): bool => $transaction->connection === $connection && $transaction->level >= $level_being_committed)->values();
        [$for_this_connection, $for_other_connections] = $this->committed_transactions->partition(fn($transaction): bool => $transaction->connection == $connection);
        $this->committed_transactions = $for_other_connections->values();
        $for_this_connection->map->execute_callbacks();
        return $for_this_connection;
    }
    /**
     * Move relevant pending transactions to a committed state.
     *
     * @param  string  $connection
     * @param  int  $levelBeingCommitted
     */
    public function stage_transactions($connection, $level_being_committed): void
    {
        $this->committed_transactions = $this->committed_transactions->merge($this->pending_transactions->filter(fn($transaction): bool => $transaction->connection === $connection && $transaction->level >= $level_being_committed));
        $this->pending_transactions = $this->pending_transactions->reject(fn($transaction): bool => $transaction->connection === $connection && $transaction->level >= $level_being_committed);
    }
    /**
     * Rollback the active database transaction.
     *
     * @param  string  $connection
     * @param  int  $newTransactionLevel
     */
    public function rollback($connection, $new_transaction_level): void
    {
        if ($new_transaction_level === 0) {
            $this->remove_all_transactions_for_connection($connection);
        } else {
            $this->pending_transactions = $this->pending_transactions->reject(fn($transaction): bool => $transaction->connection == $connection && $transaction->level > $new_transaction_level)->values();
            if ($this->current_transaction) {
                do {
                    $this->remove_committed_transactions_that_are_children_of($this->current_transaction[$connection]);
                    $this->current_transaction[$connection]->execute_callbacks_for_rollback();
                    $this->current_transaction[$connection] = $this->current_transaction[$connection]->parent;
                } while (isset($this->current_transaction[$connection]) && $this->current_transaction[$connection]->level > $new_transaction_level);
            }
        }
    }
    /**
     * Remove all pending, completed, and current transactions for the given connection name.
     *
     * @param  string  $connection
     * @return void
     */
    protected function remove_all_transactions_for_connection($connection)
    {
        if ($this->current_transaction) {
            for ($current_transaction = $this->current_transaction[$connection]; isset($current_transaction); $current_transaction = $current_transaction->parent) {
                $current_transaction->execute_callbacks_for_rollback();
            }
        }
        $this->current_transaction[$connection] = null;
        $this->pending_transactions = $this->pending_transactions->reject(fn($transaction): bool => $transaction->connection == $connection)->values();
        $this->committed_transactions = $this->committed_transactions->reject(fn($transaction): bool => $transaction->connection == $connection)->values();
    }
    /**
     * Remove all transactions that are children of the given transaction.
     *
     * @return void
     */
    protected function remove_committed_transactions_that_are_children_of(Database_Transaction_Record $transaction)
    {
        [$removed_transactions, $this->committed_transactions] = $this->committed_transactions->partition(fn($committed): bool => $committed->connection == $transaction->connection && $committed->parent === $transaction);
        // There may be multiple deeply nested transactions that have already committed that we
        // also need to remove. We will recurse down the children of all removed transaction
        // instances until there are no more deeply nested child transactions for removal.
        $removed_transactions->each(fn(\Illuminate\Database\Database_Transaction_Record $transaction) => $this->remove_committed_transactions_that_are_children_of($transaction));
    }
    /**
     * Register a transaction callback.
     *
     * @param  callable  $callback
     * @return void
     */
    public function add_callback($callback)
    {
        if ($current = $this->callback_applicable_transactions()->last()) {
            return $current->add_callback($callback);
        }
        $callback();
    }
    /**
     * Register a callback for transaction rollback.
     *
     * @param  callable  $callback
     * @return void
     */
    public function add_callback_for_rollback($callback)
    {
        if ($current = $this->callback_applicable_transactions()->last()) {
            return $current->add_callback_for_rollback($callback);
        }
    }
    /**
     * Get the transactions that are applicable to callbacks.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\DatabaseTransactionRecord>
     */
    public function callback_applicable_transactions(): \Illuminate\Support\Collection
    {
        return $this->pending_transactions;
    }
    /**
     * Determine if after commit callbacks should be executed for the given transaction level.
     *
     * @param  int  $level
     */
    public function after_commit_callbacks_should_be_executed($level): bool
    {
        return $level === 0;
    }
    /**
     * Get all of the pending transactions.
     */
    public function get_pending_transactions(): \Illuminate\Support\Collection
    {
        return $this->pending_transactions;
    }
    /**
     * Get all of the committed transactions.
     */
    public function get_committed_transactions(): \Illuminate\Support\Collection
    {
        return $this->committed_transactions;
    }
}