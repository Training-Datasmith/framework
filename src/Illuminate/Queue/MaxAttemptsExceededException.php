<?php

declare(strict_types=1);

namespace Illuminate\Queue;

use RuntimeException;

class MaxAttemptsExceededException extends RuntimeException
{
    /**
     * The job instance.
     *
     * @var \Illuminate\Contracts\Queue\Job|null
     */
    public $job;

    /**
     * Create a new instance for the job.
     *
     * @param  \Illuminate\Contracts\Queue\Job  $job
     * @return static
     */
    public static function forJob($job)
    {
        return tap(new static($job->resolveName().' has been attempted too many times.'), function ($e) use ($job): void {
            $e->job = $job;
        });
    }
}
