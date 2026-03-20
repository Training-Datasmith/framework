<?php

declare (strict_types=1);
namespace Illuminate\Database\Concerns;

use Closure;
use Illuminate\Database\Deadlock_Exception;
use RuntimeException;
use Throwable;
/**
 * @mixin \Illuminate\Database\Connection
 */
trait Manages_Transactions
{
    /**
     * @template TReturn of mixed
     *
     * Execute a Closure within a transaction.
     *
     * @param  (\Closure(static): TReturn)  $callback
     * @param  int  $attempts
     * @return TReturn
     *
     * @throws \Throwable
     */
    public function transaction(Closure $callback, $attempts = 1)
    {
        for ($current_attempt = 1; $current_attempt <= $attempts; $current_attempt++) {
            $this->begin_transaction();
            // We'll simply execute the given callback within a try / catch block and if we
            // catch any exception we can rollback this transaction so that none of this
            // gets actually persisted to a database or stored in a permanent fashion.
            try {
                $callback_result = $callback($this);
            } catch (Throwable $e) {
                $this->handle_transaction_exception($e, $current_attempt, $attempts);
                continue;
            }
            $level_being_committed = $this->transactions;
            try {
                if ($this->transactions == 1) {
                    $this->fire_connection_event('committing');
                    $this->get_pdo()->commit();
                }
                $this->transactions = max(0, $this->transactions - 1);
            } catch (Throwable $e) {
                $this->handle_commit_transaction_exception($e, $current_attempt, $attempts);
                continue;
            }
            $this->transactions_manager?->commit($this->get_name(), $level_being_committed, $this->transactions);
            $this->fire_connection_event('committed');
            return $callback_result;
        }
    }
    /**
     * Handle an exception encountered when running a transacted statement.
     *
     * @param  int  $currentAttempt
     * @param  int  $maxAttempts
     * @return void
     * @throws \Throwable
     */
    protected function handle_transaction_exception(Throwable $e, $current_attempt, $max_attempts)
    {
        // On a deadlock, MySQL rolls back the entire transaction so we can't just
        // retry the query. We have to throw this exception all the way out and
        // let the developer handle it in another way. We will decrement too.
        if ($this->caused_by_concurrency_error($e) && $this->transactions > 1) {
            $this->transactions--;
            $this->transactions_manager?->rollback($this->get_name(), $this->transactions);
            throw new Deadlock_Exception($e->get_message(), is_int($e->get_code()) ? $e->get_code() : 0, $e);
        }
        // If there was an exception we will rollback this transaction and then we
        // can check if we have exceeded the maximum attempt count for this and
        // if we haven't we will return and try this query again in our loop.
        $this->roll_back();
        if ($this->caused_by_concurrency_error($e) && $current_attempt < $max_attempts) {
            return;
        }
        throw $e;
    }
    /**
     * Start a new database transaction.
     *
     *
     * @throws \Throwable
     */
    public function begin_transaction(): void
    {
        foreach ($this->before_starting_transaction as $callback) {
            $callback($this);
        }
        $this->create_transaction();
        $this->transactions++;
        $this->transactions_manager?->begin($this->get_name(), $this->transactions);
        $this->fire_connection_event('beganTransaction');
    }
    /**
     * Create a transaction within the database.
     *
     * @return void
     *
     * @throws \Throwable
     */
    protected function create_transaction()
    {
        if ($this->transactions == 0) {
            $this->reconnect_if_missing_connection();
            try {
                $this->execute_begin_transaction_statement();
            } catch (Throwable $e) {
                $this->handle_begin_transaction_exception($e);
            }
        } elseif ($this->transactions >= 1 && $this->query_grammar->supports_savepoints()) {
            $this->create_savepoint();
        }
    }
    /**
     * Create a save point within the database.
     *
     * @return void
     *
     * @throws \Throwable
     */
    protected function create_savepoint()
    {
        $this->get_pdo()->exec($this->query_grammar->compile_savepoint('trans' . ($this->transactions + 1)));
    }
    /**
     * Handle an exception from a transaction beginning.
     *
     * @return void
     * @throws \Throwable
     */
    protected function handle_begin_transaction_exception(Throwable $e)
    {
        if ($this->caused_by_lost_connection($e)) {
            $this->reconnect();
            $this->execute_begin_transaction_statement();
        } else {
            throw $e;
        }
    }
    /**
     * Commit the active database transaction.
     *
     *
     * @throws \Throwable
     */
    public function commit(): void
    {
        if ($this->transaction_level() == 1) {
            $this->fire_connection_event('committing');
            $this->get_pdo()->commit();
        }
        [$level_being_committed, $this->transactions] = [$this->transactions, max(0, $this->transactions - 1)];
        $this->transactions_manager?->commit($this->get_name(), $level_being_committed, $this->transactions);
        $this->fire_connection_event('committed');
    }
    /**
     * Handle an exception encountered when committing a transaction.
     *
     * @param  int  $currentAttempt
     * @param  int  $maxAttempts
     * @return void
     * @throws \Throwable
     */
    protected function handle_commit_transaction_exception(Throwable $e, $current_attempt, $max_attempts)
    {
        $this->transactions = max(0, $this->transactions - 1);
        if ($this->caused_by_concurrency_error($e) && $current_attempt < $max_attempts) {
            $pdo = $this->get_pdo();
            if ($pdo->in_transaction()) {
                $pdo->roll_back();
            }
            return;
        }
        if ($this->caused_by_lost_connection($e)) {
            $this->transactions = 0;
        }
        throw $e;
    }
    /**
     * Rollback the active database transaction.
     *
     * @param  int|null  $toLevel
     *
     * @throws \Throwable
     */
    public function roll_back($to_level = null): void
    {
        // We allow developers to rollback to a certain transaction level. We will verify
        // that this given transaction level is valid before attempting to rollback to
        // that level. If it's not we will just return out and not attempt anything.
        $to_level = is_null($to_level) ? $this->transactions - 1 : $to_level;
        if ($to_level < 0 || $to_level >= $this->transactions) {
            return;
        }
        // Next, we will actually perform this rollback within this database and fire the
        // rollback event. We will also set the current transaction level to the given
        // level that was passed into this method so it will be right from here out.
        try {
            $this->perform_roll_back($to_level);
        } catch (Throwable $e) {
            $this->handle_roll_back_exception($e);
        }
        $this->transactions = $to_level;
        $this->transactions_manager?->rollback($this->get_name(), $this->transactions);
        $this->fire_connection_event('rollingBack');
    }
    /**
     * Perform a rollback within the database.
     *
     * @param  int  $toLevel
     * @return void
     *
     * @throws \Throwable
     */
    protected function perform_roll_back($to_level)
    {
        if ($to_level == 0) {
            $pdo = $this->get_pdo();
            if ($pdo->in_transaction()) {
                $pdo->roll_back();
            }
        } elseif ($this->query_grammar->supports_savepoints()) {
            $this->get_pdo()->exec($this->query_grammar->compile_savepoint_roll_back('trans' . ($to_level + 1)));
        }
    }
    /**
     * Handle an exception from a rollback.
     *
     * @return void
     * @throws \Throwable
     */
    protected function handle_roll_back_exception(Throwable $e)
    {
        if ($this->caused_by_lost_connection($e)) {
            $this->transactions = 0;
            $this->transactions_manager?->rollback($this->get_name(), $this->transactions);
        }
        throw $e;
    }
    /**
     * Get the number of active transactions.
     *
     * @return int
     */
    public function transaction_level()
    {
        return $this->transactions;
    }
    /**
     * Execute the callback after a transaction commits.
     *
     * @param  callable  $callback
     * @return void
     *
     * @throws \RuntimeException
     */
    public function after_commit($callback)
    {
        if ($this->transactions_manager) {
            return $this->transactions_manager->add_callback($callback);
        }
        throw new RuntimeException('Transactions Manager has not been set.');
    }
    /**
     * Execute the callback after a transaction rolls back.
     *
     * @param  callable  $callback
     * @return void
     *
     * @throws \RuntimeException
     */
    public function after_roll_back($callback)
    {
        if ($this->transactions_manager) {
            return $this->transactions_manager->add_callback_for_rollback($callback);
        }
        throw new RuntimeException('Transactions Manager has not been set.');
    }
}