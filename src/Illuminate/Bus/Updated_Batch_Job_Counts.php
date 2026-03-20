<?php

declare (strict_types=1);
namespace Illuminate\Bus;

class Updated_Batch_Job_Counts
{
    /**
     * The number of pending jobs remaining for the batch.
     *
     * @var int
     */
    public $pending_jobs;
    /**
     * The number of failed jobs that belong to the batch.
     *
     * @var int
     */
    public $failed_jobs;
    /**
     * Create a new batch job counts object.
     */
    public function __construct(int $pending_jobs = 0, int $failed_jobs = 0)
    {
        $this->pending_jobs = $pending_jobs;
        $this->failed_jobs = $failed_jobs;
    }
    /**
     * Determine if all jobs have run exactly once.
     */
    public function all_jobs_have_ran_exactly_once(): bool
    {
        return $this->pending_jobs - $this->failed_jobs === 0;
    }
}