<?php

declare(strict_types=1);

namespace Illuminate\Queue\Jobs;

use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Support\Str;

class FakeJob extends Job implements JobContract
{
    /**
     * The number of seconds the released job was delayed.
     *
     * @var int
     */
    public $releaseDelay;

    /**
     * The number of attempts made to process the job.
     *
     * @var int
     */
    public $attempts = 1;

    /**
     * The exception the job failed with.
     *
     * @var \Throwable
     */
    public $failedWith;

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return once(fn (): string => (string) Str::uuid());
    }

    /**
     * Get the raw body of the job.
     */
    public function getRawBody(): string
    {
        return '';
    }

    /**
     * Release the job back into the queue after (n) seconds.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     */
    public function release($delay = 0): void
    {
        $this->released = true;
        $this->releaseDelay = $delay;
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts()
    {
        return $this->attempts;
    }

    /**
     * Delete the job from the queue.
     */
    public function delete(): void
    {
        $this->deleted = true;
    }

    /**
     * Delete the job, call the "failed" method, and raise the failed job event.
     *
     * @param  \Throwable|null  $exception
     */
    public function fail($exception = null): void
    {
        $this->failed = true;
        $this->failedWith = $exception;
    }
}
