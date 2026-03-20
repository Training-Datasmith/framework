<?php

declare(strict_types=1);

namespace Illuminate\Support\Testing\Fakes;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Batch;
use Illuminate\Bus\UpdatedBatchJobCounts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BatchFake extends Batch
{
    /**
     * The jobs that have been added to the batch.
     *
     * @var array
     */
    public $added = [];

    /**
     * Indicates if the batch has been deleted.
     *
     * @var bool
     */
    public $deleted = false;

    /**
     * Create a new batch instance.
     *
     * @param  \Carbon\CarbonImmutable  $createdAt
     * @param  \Carbon\CarbonImmutable|null  $cancelledAt
     * @param  \Carbon\CarbonImmutable|null  $finishedAt
     */
    public function __construct(
        string $id,
        string $name,
        int $totalJobs,
        int $pendingJobs,
        int $failedJobs,
        array $failedJobIds,
        array $options,
        CarbonImmutable $createdAt,
        ?CarbonImmutable $cancelledAt = null,
        ?CarbonImmutable $finishedAt = null,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->totalJobs = $totalJobs;
        $this->pendingJobs = $pendingJobs;
        $this->failedJobs = $failedJobs;
        $this->failedJobIds = $failedJobIds;
        $this->options = $options;
        $this->createdAt = $createdAt;
        $this->cancelledAt = $cancelledAt;
        $this->finishedAt = $finishedAt;
    }

    /**
     * Get a fresh instance of the batch represented by this ID.
     */
    public function fresh(): static
    {
        return $this;
    }

    /**
     * Add additional jobs to the batch.
     *
     * @param  \Illuminate\Support\Enumerable|object|array  $jobs
     */
    public function add($jobs): static
    {
        $jobs = Collection::wrap($jobs);

        foreach ($jobs as $job) {
            $this->added[] = $job;
        }

        $this->totalJobs += $jobs->count();

        return $this;
    }

    /**
     * Record that a job within the batch finished successfully, executing any callbacks if necessary.
     */
    public function recordSuccessfulJob(string $jobId): void
    {

    }

    /**
     * Decrement the pending jobs for the batch.
     */
    public function decrementPendingJobs(string $jobId): void
    {

    }

    /**
     * Record that a job within the batch failed to finish successfully, executing any callbacks if necessary.
     *
     * @param  \Throwable  $e
     */
    public function recordFailedJob(string $jobId, $e): void
    {

    }

    /**
     * Increment the failed jobs for the batch.
     */
    public function incrementFailedJobs(string $jobId): \Illuminate\Bus\UpdatedBatchJobCounts
    {
        return new UpdatedBatchJobCounts();
    }

    /**
     * Cancel the batch.
     */
    public function cancel(): void
    {
        $this->cancelledAt = Carbon::now();
    }

    /**
     * Delete the batch from storage.
     */
    public function delete(): void
    {
        $this->deleted = true;
    }

    /**
     * Determine if the batch has been deleted.
     *
     * @return bool
     */
    public function deleted()
    {
        return $this->deleted;
    }
}
