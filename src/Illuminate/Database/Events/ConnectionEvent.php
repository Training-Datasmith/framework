<?php

namespace Illuminate\Database\Events;

abstract class ConnectionEvent
{
    /**
     * The name of the connection.
     *
     * @var string
     */
    public $connectionName;

    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Database\Connection  $connection
     */
    public function __construct(/**
     * The database connection instance.
     */
    public $connection)
    {
        $this->connectionName = $this->connection->getName();
    }
}
