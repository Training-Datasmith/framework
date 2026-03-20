<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Bus;

trait Dispatches_Jobs
{
    /**
     * Dispatch a job to its appropriate handler.
     *
     * @param  mixed  $job
     */
    protected function dispatch($job): \Illuminate\Foundation\Bus\Pending_Dispatch
    {
        return dispatch($job);
    }
    /**
     * Dispatch a job to its appropriate handler in the current process.
     *
     * Queueable jobs will be dispatched to the "sync" queue.
     *
     * @param  mixed  $job
     * @return mixed
     */
    public function dispatch_sync($job)
    {
        return dispatch_sync($job);
    }
}