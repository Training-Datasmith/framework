<?php

declare(strict_types=1);

namespace Illuminate\Queue\Connectors;

use Illuminate\Queue\BackgroundQueue;

class BackgroundConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config): \Illuminate\Queue\BackgroundQueue
    {
        return new BackgroundQueue($config['after_commit'] ?? null);
    }
}
