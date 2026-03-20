<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Bus;

interface Queueing_Dispatcher extends Dispatcher
{
    /**
     * Attempt to find the batch with the given ID.
     *
     * @return \Illuminate\Bus\Batch|null
     */
    public function find_batch(string $batch_id);
    /**
     * Create a new batch of queueable jobs.
     *
     * @param  \Illuminate\Support\Collection|array  $jobs
     * @return \Illuminate\Bus\PendingBatch
     */
    public function batch($jobs);
    /**
     * Dispatch a command to its appropriate handler behind a queue.
     *
     * @param  mixed  $command
     * @return mixed
     */
    public function dispatch_to_queue($command);
}