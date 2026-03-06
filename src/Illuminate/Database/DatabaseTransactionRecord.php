<?php

declare(strict_types=1);

namespace Illuminate\Database;

class DatabaseTransactionRecord
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
    protected $callbacksForRollback = [];

    /**
     * Create a new database transaction record instance.
     *
     * @param  string  $connection
     * @param  int  $level
     */
    public function __construct(/**
     * The name of the database connection.
     */
        public $connection, /**
     * The transaction level.
     */
        public $level,
        ?DatabaseTransactionRecord $parent = null
    ) {
        $this->parent = $parent;
    }

    /**
     * Register a callback to be executed after committing.
     *
     * @param  callable  $callback
     */
    public function addCallback($callback): void
    {
        $this->callbacks[] = $callback;
    }

    /**
     * Register a callback to be executed after rollback.
     *
     * @param  callable  $callback
     */
    public function addCallbackForRollback($callback): void
    {
        $this->callbacksForRollback[] = $callback;
    }

    /**
     * Execute all of the callbacks.
     */
    public function executeCallbacks(): void
    {
        foreach ($this->callbacks as $callback) {
            $callback();
        }
    }

    /**
     * Execute all of the callbacks for rollback.
     */
    public function executeCallbacksForRollback(): void
    {
        foreach ($this->callbacksForRollback as $callback) {
            $callback();
        }
    }

    /**
     * Get all of the callbacks.
     *
     * @return array
     */
    public function getCallbacks()
    {
        return $this->callbacks;
    }

    /**
     * Get all of the callbacks for rollback.
     *
     * @return array
     */
    public function getCallbacksForRollback()
    {
        return $this->callbacksForRollback;
    }
}
