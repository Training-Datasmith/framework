<?php

declare (strict_types=1);
namespace Illuminate\Database;

class Database_Transaction_Record
{
    /**
     * The parent instance of this transaction.
     *
     * @var \Illuminate\Database\DatabaseTransactionRecord
     */
    public $parent;
    /**
     * The callbacks that should be executed after committing.
     *
     * @var array
     */
    protected $callbacks = [];
    /**
     * The callbacks that should be executed after rollback.
     *
     * @var array
     */
    protected $callbacks_for_rollback = [];
    /**
     * Create a new database transaction record instance.
     *
     * @param  string  $connection
     * @param  int  $level
     */
    public function __construct(
        /**
         * The name of the database connection.
         */
        public $connection,
        /**
         * The transaction level.
         */
        public $level,
        ?Database_Transaction_Record $parent = null
    )
    {
        $this->parent = $parent;
    }
    /**
     * Register a callback to be executed after committing.
     *
     * @param  callable  $callback
     */
    public function add_callback($callback): void
    {
        $this->callbacks[] = $callback;
    }
    /**
     * Register a callback to be executed after rollback.
     *
     * @param  callable  $callback
     */
    public function add_callback_for_rollback($callback): void
    {
        $this->callbacks_for_rollback[] = $callback;
    }
    /**
     * Execute all of the callbacks.
     */
    public function execute_callbacks(): void
    {
        foreach ($this->callbacks as $callback) {
            $callback();
        }
    }
    /**
     * Execute all of the callbacks for rollback.
     */
    public function execute_callbacks_for_rollback(): void
    {
        foreach ($this->callbacks_for_rollback as $callback) {
            $callback();
        }
    }
    /**
     * Get all of the callbacks.
     *
     * @return array
     */
    public function get_callbacks()
    {
        return $this->callbacks;
    }
    /**
     * Get all of the callbacks for rollback.
     *
     * @return array
     */
    public function get_callbacks_for_rollback()
    {
        return $this->callbacks_for_rollback;
    }
}