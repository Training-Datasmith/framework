<?php

declare (strict_types=1);
namespace Illuminate\Database\Events;

abstract class Connection_Event
{
    /**
     * The name of the connection.
     *
     * @var string
     */
    public $connection_name;
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Database\Connection  $connection
     */
    public function __construct(
        /**
         * The database connection instance.
         */
        public $connection
    )
    {
        $this->connection_name = $this->connection->get_name();
    }
}