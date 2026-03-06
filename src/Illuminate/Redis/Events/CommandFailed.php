<?php

namespace Illuminate\Redis\Events;

use Throwable;

class CommandFailed
{
    /**
     * The exception that was thrown.
     *
     * @var \Throwable
     */
    public $exception;

    /**
     * The Redis connection name.
     *
     * @var string
     */
    public $connectionName;

    /**
     * Create a new event instance.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @param  \Illuminate\Redis\Connections\Connection  $connection
     */
    public function __construct(/**
     * The Redis command that failed.
     */
    public $command, /**
     * The array of command parameters.
     */
    public $parameters, Throwable $exception, /**
     * The Redis connection instance.
     */
    public $connection)
    {
        $this->exception = $exception;
        $this->connectionName = $this->connection->getName();
    }
}
