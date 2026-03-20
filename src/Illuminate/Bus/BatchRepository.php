<?php

declare (strict_types=1);
namespace Illuminate\Bus;

use Closure;
interface Batch_Repository
{
    /**
     * Retrieve a list of batches.
     *
     * @param  int  $limit
     * @param  mixed  $before
     * @return \Illuminate\Bus\Batch[]
     */
    public function get($limit, $before);
    /**
     * Retrieve information about an existing batch.
     *
     * @return \Illuminate\Bus\Batch|null
     */
    public function find(string $batch_id);
    /**
     * Store a new pending batch.
     *
     * @return \Illuminate\Bus\Batch
     */
    public function store(Pending_Batch $batch);
    /**
     * Increment the total number of jobs within the batch.
     *
     * @return void
     */
    public function increment_total_jobs(string $batch_id, int $amount);
    /**
     * Decrement the total number of pending jobs for the batch.
     *
     * @return \Illuminate\Bus\UpdatedBatchJobCounts
     */
    public function decrement_pending_jobs(string $batch_id, string $job_id);
    /**
     * Increment the total number of failed jobs for the batch.
     *
     * @return \Illuminate\Bus\UpdatedBatchJobCounts
     */
    public function increment_failed_jobs(string $batch_id, string $job_id);
    /**
     * Mark the batch that has the given ID as finished.
     *
     * @return void
     */
    public function mark_as_finished(string $batch_id);
    /**
     * Cancel the batch that has the given ID.
     *
     * @return void
     */
    public function cancel(string $batch_id);
    /**
     * Delete the batch that has the given ID.
     *
     * @return void
     */
    public function delete(string $batch_id);
    /**
     * Execute the given Closure within a storage specific transaction.
     *
     * @return mixed
     */
    public function transaction(Closure $callback);
    /**
     * Rollback the last database transaction for the connection.
     *
     * @return void
     */
    public function roll_back();
}