<?php

namespace Illuminate\Database\Events;

class SchemaDumped
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
     * @param  \Illuminate\Database\Connection  $connection
     * @param  string  $path
     */
    public function __construct(/**
     * The database connection instance.
     */
    public $connection, /**
     * The path to the schema dump.
     */
    public $path)
    {
        $this->connectionName = $this->connection->getName();
    }
}
