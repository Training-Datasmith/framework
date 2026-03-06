<?php

namespace Illuminate\Queue;

class ListenerOptions extends WorkerOptions
{
    /**
     * Create a new listener options instance.
     *
     * @param  string  $name
     * @param  string|null  $environment
     * @param  int|int[]  $backoff
     * @param  int  $memory
     * @param  int  $timeout
     * @param  int  $sleep
     * @param  int  $maxTries
     * @param  bool  $force
     * @param  int  $rest
     */
    public function __construct($name = 'default', /**
     * The environment the worker should run in.
     */
    public $environment = null, $backoff = 0, $memory = 128, $timeout = 60, $sleep = 3, $maxTries = 1, $force = false, $rest = 0)
    {
        parent::__construct($name, $backoff, $memory, $timeout, $sleep, $maxTries, $force, false, 0, 0, $rest);
    }
}
