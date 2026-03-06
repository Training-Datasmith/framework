<?php

declare(strict_types=1);

namespace Illuminate\Queue\Connectors;

use Illuminate\Queue\DeferredQueue;

class DeferredConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config): \Illuminate\Queue\DeferredQueue
    {
        return new DeferredQueue($config['after_commit'] ?? null);
    }
}
