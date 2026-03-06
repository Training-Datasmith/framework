<?php

namespace Illuminate\Queue\Connectors;

use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Queue\RedisQueue;

class RedisConnector implements ConnectorInterface
{
    /**
     * Create a new Redis queue connector instance.
     *
     * @param  string|null  $connection
     */
    public function __construct(
        /**
         * The Redis database instance.
         */
        protected \Redis $redis,
        /**
         * The connection name.
         */
        protected $connection = null
    )
    {
    }

    /**
     * Establish a queue connection.
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config): \Illuminate\Queue\RedisQueue
    {
        return new RedisQueue(
            $this->redis, $config['queue'],
            $config['connection'] ?? $this->connection,
            $config['retry_after'] ?? 60,
            $config['block_for'] ?? null,
            $config['after_commit'] ?? null,
            $config['migration_batch_size'] ?? -1
        );
    }
}
