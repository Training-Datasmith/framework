<?php

namespace Illuminate\Queue\Connectors;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Queue\DatabaseQueue;

class DatabaseConnector implements ConnectorInterface
{
    /**
     * Create a new connector instance.
     */
    public function __construct(
        /**
         * Database connections.
         */
        protected \Illuminate\Database\ConnectionResolverInterface $connections
    )
    {
    }

    /**
     * Establish a queue connection.
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config): \Illuminate\Queue\DatabaseQueue
    {
        return new DatabaseQueue(
            $this->connections->connection($config['connection'] ?? null),
            $config['table'],
            $config['queue'],
            $config['retry_after'] ?? 60,
            $config['after_commit'] ?? null
        );
    }
}
