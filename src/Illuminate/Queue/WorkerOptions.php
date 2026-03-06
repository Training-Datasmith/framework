<?php

declare(strict_types=1);

namespace Illuminate\Queue;

class WorkerOptions
{
    /**
     * Create a new worker options instance.
     *
     * @param  string  $name
     * @param  int|int[]  $backoff
     * @param  int  $memory
     * @param  int  $timeout
     * @param  int  $sleep
     * @param  int  $maxTries
     * @param  bool  $force
     * @param  bool  $stopWhenEmpty
     * @param  int  $maxJobs
     * @param  int  $maxTime
     * @param  int  $rest
     */
    public function __construct(
        /**
         * The name of the worker.
         */
        public $name = 'default',
        /**
         * The number of seconds to wait before retrying a job that encountered an uncaught exception.
         */
        public $backoff = 0,
        /**
         * The maximum amount of RAM the worker may consume.
         */
        public $memory = 128,
        /**
         * The maximum number of seconds a child worker may run.
         */
        public $timeout = 60,
        /**
         * The number of seconds to wait in between polling the queue.
         */
        public $sleep = 3,
        /**
         * The maximum number of times a job may be attempted.
         */
        public $maxTries = 1,
        /**
         * Indicates if the worker should run in maintenance mode.
         */
        public $force = false,
        /**
         * Indicates if the worker should stop when the queue is empty.
         */
        public $stopWhenEmpty = false,
        /**
         * The maximum number of jobs to run.
         */
        public $maxJobs = 0,
        /**
         * The maximum number of seconds a worker may live.
         */
        public $maxTime = 0,
        /**
         * The number of seconds to rest between jobs.
         */
        public $rest = 0
    ) {
    }
}
