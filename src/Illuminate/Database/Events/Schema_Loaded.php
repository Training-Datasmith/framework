<?php

declare (strict_types=1);
namespace Illuminate\Database\Events;

class Schema_Loaded
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
     * @param  \Illuminate\Database\Connection  $connection
     * @param  string  $path
     */
    public function __construct(
        /**
         * The database connection instance.
         */
        public $connection,
        /**
         * The path to the schema dump.
         */
        public $path
    )
    {
        $this->connection_name = $this->connection->get_name();
    }
}