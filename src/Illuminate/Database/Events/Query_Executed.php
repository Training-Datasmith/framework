<?php

declare (strict_types=1);
namespace Illuminate\Database\Events;

class Query_Executed
{
    /**
     * The database connection name.
     *
     * @var string
     */
    public $connection_name;
    /**
     * Create a new event instance.
     *
     * @param  string  $sql
     * @param  array  $bindings
     * @param  float|null  $time
     * @param  \Illuminate\Database\Connection  $connection
     * @param  null|'read'|'write'  $readWriteType
     */
    public function __construct(
        /**
         * The SQL query that was executed.
         */
        public $sql,
        /**
         * The array of query bindings.
         */
        public $bindings,
        /**
         * The number of milliseconds it took to execute the query.
         */
        public $time,
        /**
         * The database connection instance.
         */
        public $connection,
        /**
         * The PDO read / write type for the executed query.
         */
        public $read_write_type = null
    )
    {
        $this->connection_name = $this->connection->get_name();
    }
    /**
     * Get the raw SQL representation of the query with embedded bindings.
     */
    public function to_raw_sql(): string
    {
        return $this->connection->query()->get_grammar()->substitute_bindings_into_raw_sql($this->sql, $this->connection->prepare_bindings($this->bindings));
    }
}