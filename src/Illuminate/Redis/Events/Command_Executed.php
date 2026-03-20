<?php

declare(strict_types=1);

namespace Illuminate\Redis\Events;

class CommandExecuted
{
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
     * @param  float|null  $time
     * @param  \Illuminate\Redis\Connections\Connection  $connection
     */
    public function __construct(/**
     * The Redis command that was executed.
     */
        public $command, /**
     * The array of command parameters.
     */
        public $parameters, /**
     * The number of milliseconds it took to execute the command.
     */
        public $time, /**
     * The Redis connection instance.
     */
        public $connection
    ) {
        $this->connectionName = $this->connection->getName();
    }
}
