<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Testing;

use Illuminate\Database\Database_Transactions_Manager as BaseManager;
class Database_Transactions_Manager extends Base_Manager
{
    /**
     * Create a new database transaction manager instance.
     */
    public function __construct(protected array $connections_transacting)
    {
        parent::__construct();
    }
    /**
     * Register a transaction callback.
     *
     * @param  callable  $callback
     * @return void
     */
    public function add_callback($callback)
    {
        // If there are no transactions, we'll run the callbacks right away. Also, we'll run it
        // right away when we're in test mode and we only have the wrapping transaction. For
        // every other case, we'll queue up the callback to run after the commit happens.
        if ($this->callback_applicable_transactions()->count() === 0) {
            return $callback();
        }
        $this->pending_transactions->last()->add_callback($callback);
    }
    /**
     * Get the transactions that are applicable to callbacks.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\DatabaseTransactionRecord>
     */
    public function callback_applicable_transactions(): \Illuminate\Support\Collection
    {
        return $this->pending_transactions->skip(count($this->connections_transacting))->values();
    }
    /**
     * Determine if after commit callbacks should be executed for the given transaction level.
     *
     * @param  int  $level
     */
    public function after_commit_callbacks_should_be_executed($level): bool
    {
        return $level === 1;
    }
}