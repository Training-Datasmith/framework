<?php

namespace Illuminate\Support\Testing\Fakes;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\PendingBatch;
use Illuminate\Bus\UpdatedBatchJobCounts;
use Illuminate\Support\Str;

class BatchRepositoryFake implements BatchRepository
{
    /**
     * The batches stored in the repository.
     *
     * @var \Illuminate\Bus\Batch[]
     */
    protected $batches = [];

    /**
     * Retrieve a list of batches.
     *
     * @param  int  $limit
     * @param  mixed  $before
     * @return \Illuminate\Bus\Batch[]
     */
    public function get($limit, $before)
    {
        return $this->batches;
    }

    /**
     * Retrieve information about an existing batch.
     *
     * @return \Illuminate\Bus\Batch|null
     */
    public function find(string $batchId)
    {
        return $this->batches[$batchId] ?? null;
    }

    /**
     * Store a new pending batch.
     *
     * @return \Illuminate\Bus\Batch
     */
    public function store(PendingBatch $batch)
    {
        $id = (string) Str::orderedUuid();

        $this->batches[$id] = new BatchFake(
            $id,
            $batch->name,
            count($batch->jobs),
            count($batch->jobs),
            0,
            [],
            $batch->options,
            CarbonImmutable::now()
        );

        return $this->batches[$id];
    }

    /**
     * Increment the total number of jobs within the batch.
     */
    public function incrementTotalJobs(string $batchId, int $amount): void
    {
        //
    }

    /**
     * Decrement the total number of pending jobs for the batch.
     */
    public function decrementPendingJobs(string $batchId, string $jobId): \Illuminate\Bus\UpdatedBatchJobCounts
    {
        return new UpdatedBatchJobCounts;
    }

    /**
     * Increment the total number of failed jobs for the batch.
     */
    public function incrementFailedJobs(string $batchId, string $jobId): \Illuminate\Bus\UpdatedBatchJobCounts
    {
        return new UpdatedBatchJobCounts;
    }

    /**
     * Mark the batch that has the given ID as finished.
     */
    public function markAsFinished(string $batchId): void
    {
        if (isset($this->batches[$batchId])) {
            $this->batches[$batchId]->finishedAt = now();
        }
    }

    /**
     * Cancel the batch that has the given ID.
     */
    public function cancel(string $batchId): void
    {
        if (isset($this->batches[$batchId])) {
            $this->batches[$batchId]->cancel();
        }
    }

    /**
     * Delete the batch that has the given ID.
     */
    public function delete(string $batchId): void
    {
        unset($this->batches[$batchId]);
    }

    /**
     * Execute the given Closure within a storage specific transaction.
     *
     * @return mixed
     */
    public function transaction(Closure $callback)
    {
        return $callback();
    }

    /**
     * Rollback the last database transaction for the connection.
     */
    public function rollBack(): void
    {
        //
    }
}
