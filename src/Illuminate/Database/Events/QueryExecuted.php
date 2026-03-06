<?php

namespace Illuminate\Database\Events;

class QueryExecuted
{
    /**
     * The database connection name.
     *
     * @var string
     */
    public $connectionName;

    /**
     * Create a new event instance.
     *
     * @param  string  $sql
     * @param  array  $bindings
     * @param  float|null  $time
     * @param  \Illuminate\Database\Connection  $connection
     * @param  null|'read'|'write'  $readWriteType
     */
    public function __construct(/**
     * The SQL query that was executed.
     */
    public $sql, /**
     * The array of query bindings.
     */
    public $bindings, /**
     * The number of milliseconds it took to execute the query.
     */
    public $time, /**
     * The database connection instance.
     */
    public $connection, /**
     * The PDO read / write type for the executed query.
     */
    public $readWriteType = null)
    {
        $this->connectionName = $this->connection->getName();
    }

    /**
     * Get the raw SQL representation of the query with embedded bindings.
     *
     * @return string
     */
    public function toRawSql()
    {
        return $this->connection
            ->query()
            ->getGrammar()
            ->substituteBindingsIntoRawSql($this->sql, $this->connection->prepareBindings($this->bindings));
    }
}
