<?php

declare (strict_types=1);
namespace Illuminate\Bus;

use Carbon\Carbon_Immutable;
class Batch_Factory
{
    /**
     * Create a new batch factory instance.
     */
    public function __construct(
        /**
         * The queue factory implementation.
         */
        protected \Illuminate\Contracts\Queue\Factory $queue
    )
    {
    }
    /**
     * Create a new batch instance.
     */
    public function make(Batch_Repository $repository, string $id, string $name, int $total_jobs, int $pending_jobs, int $failed_jobs, array $failed_job_ids, array $options, Carbon_Immutable $created_at, ?Carbon_Immutable $cancelled_at, ?Carbon_Immutable $finished_at): \Illuminate\Bus\Batch
    {
        return new Batch($this->queue, $repository, $id, $name, $total_jobs, $pending_jobs, $failed_jobs, $failed_job_ids, $options, $created_at, $cancelled_at, $finished_at);
    }
}