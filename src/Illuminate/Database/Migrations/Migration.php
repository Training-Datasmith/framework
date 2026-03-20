<?php

declare (strict_types=1);
namespace Illuminate\Database\Migrations;

abstract class Migration
{
    /**
     * The name of the database connection to use.
     *
     * @var string|null
     */
    protected $connection;
    /**
     * Enables, if supported, wrapping the migration within a transaction.
     *
     * @var bool
     */
    public $within_transaction = true;
    /**
     * Get the migration connection name.
     *
     * @return string|null
     */
    public function get_connection()
    {
        return $this->connection;
    }
    /**
     * Determine if this migration should run.
     */
    public function should_run(): bool
    {
        return true;
    }
}