<?php

declare (strict_types=1);
namespace Illuminate\Database;

use Closure;
interface Connection_Interface
{
    /**
     * Begin a fluent query against a database table.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\UnitEnum|string  $table
     * @param  string|null  $as
     * @return \Illuminate\Database\Query\Builder
     */
    public function table($table, $as = null);
    /**
     * Get a new raw query expression.
     *
     * @param  mixed  $value
     * @return \Illuminate\Contracts\Database\Query\Expression
     */
    public function raw($value);
    /**
     * Run a select statement and return a single result.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return mixed
     */
    public function select_one($query, $bindings = [], $use_read_pdo = true);
    /**
     * Run a select statement and return the first column of the first row.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return mixed
     *
     * @throws \Illuminate\Database\MultipleColumnsSelectedException
     */
    public function scalar($query, $bindings = [], $use_read_pdo = true);
    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $use_read_pdo = true);
    /**
     * Run a select statement against the database and returns a generator.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return \Generator
     */
    public function cursor($query, $bindings = [], $use_read_pdo = true);
    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function insert($query, $bindings = []);
    /**
     * Run an update statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function update($query, $bindings = []);
    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function delete($query, $bindings = []);
    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function statement($query, $bindings = []);
    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function affecting_statement($query, $bindings = []);
    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param  string  $query
     * @return bool
     */
    public function unprepared($query);
    /**
     * Prepare the query bindings for execution.
     *
     * @return array
     */
    public function prepare_bindings(array $bindings);
    /**
     * Execute a Closure within a transaction.
     *
     * @param  int  $attempts
     * @return mixed
     * @throws \Throwable
     */
    public function transaction(Closure $callback, $attempts = 1);
    /**
     * Start a new database transaction.
     *
     * @return void
     */
    public function begin_transaction();
    /**
     * Commit the active database transaction.
     *
     * @return void
     */
    public function commit();
    /**
     * Rollback the active database transaction.
     *
     * @return void
     */
    public function roll_back();
    /**
     * Get the number of active transactions.
     *
     * @return int
     */
    public function transaction_level();
    /**
     * Execute the given callback in "dry run" mode.
     *
     * @return array
     */
    public function pretend(Closure $callback);
    /**
     * Get the name of the connected database.
     *
     * @return string
     */
    public function get_database_name();
}