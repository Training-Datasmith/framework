<?php

declare(strict_types=1);

namespace Illuminate\Queue\Connectors;

use Illuminate\Queue\NullQueue;

class NullConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config): \Illuminate\Queue\NullQueue
    {
        return new NullQueue();
    }
}
