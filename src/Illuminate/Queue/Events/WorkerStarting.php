<?php

declare(strict_types=1);

namespace Illuminate\Queue\Events;

class WorkerStarting
{
    /**
     * Create a new event instance.
     *
     * @param  string  $connectionName
     * @param  string  $queue
     * @param  \Illuminate\Queue\WorkerOptions  $workerOptions
     */
    public function __construct(
        public $connectionName,
        public $queue,
        public $workerOptions,
    ) {
    }
}
