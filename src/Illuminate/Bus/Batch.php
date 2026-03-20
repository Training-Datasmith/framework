<?php

declare (strict_types=1);
namespace Illuminate\Bus;

use Carbon\Carbon_Immutable;
use Closure;
use Illuminate\Bus\Events\Batch_Canceled;
use Illuminate\Bus\Events\Batch_Finished;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Queue\Call_Queued_Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use JsonSerializable;
use Throwable;
class Batch implements Arrayable, JsonSerializable
{
    /**
     * The batch ID.
     *
     * @var string
     */
    public $id;
    /**
     * The batch name.
     *
     * @var string
     */
    public $name;
    /**
     * The total number of jobs that belong to the batch.
     *
     * @var int
     */
    public $total_jobs;
    /**
     * The total number of jobs that are still pending.
     *
     * @var int
     */
    public $pending_jobs;
    /**
     * The total number of jobs that have failed.
     *
     * @var int
     */
    public $failed_jobs;
    /**
     * The IDs of the jobs that have failed.
     *
     * @var array
     */
    public $failed_job_ids;
    /**
     * The batch options.
     *
     * @var array
     */
    public $options;
    /**
     * The date indicating when the batch was created.
     *
     * @var \Carbon\CarbonImmutable
     */
    public $created_at;
    /**
     * The date indicating when the batch was cancelled.
     *
     * @var \Carbon\CarbonImmutable|null
     */
    public $cancelled_at;
    /**
     * The date indicating when the batch was finished.
     *
     * @var \Carbon\CarbonImmutable|null
     */
    public $finished_at;
    /**
     * Create a new batch instance.
     */
    public function __construct(
        /**
         * The queue factory implementation.
         */
        protected \Illuminate\Contracts\Queue\Factory $queue,
        /**
         * The repository implementation.
         */
        protected \Illuminate\Bus\Batch_Repository $repository,
        string $id,
        string $name,
        int $total_jobs,
        int $pending_jobs,
        int $failed_jobs,
        array $failed_job_ids,
        array $options,
        Carbon_Immutable $created_at,
        ?Carbon_Immutable $cancelled_at = null,
        ?Carbon_Immutable $finished_at = null
    )
    {
        $this->id = $id;
        $this->name = $name;
        $this->total_jobs = $total_jobs;
        $this->pending_jobs = $pending_jobs;
        $this->failed_jobs = $failed_jobs;
        $this->failed_job_ids = $failed_job_ids;
        $this->options = $options;
        $this->created_at = $created_at;
        $this->cancelled_at = $cancelled_at;
        $this->finished_at = $finished_at;
    }
    /**
     * Get a fresh instance of the batch represented by this ID.
     *
     * @return self
     */
    public function fresh()
    {
        return $this->repository->find($this->id);
    }
    /**
     * Add additional jobs to the batch.
     *
     * @param  \Illuminate\Support\Enumerable|object|array  $jobs
     * @return self
     */
    public function add($jobs)
    {
        $count = 0;
        $jobs = Collection::wrap($jobs)->map(function ($job) use (&$count) {
            $job = $job instanceof Closure ? Call_Queued_Closure::create($job) : $job;
            if (is_array($job)) {
                $count += count($job);
                $chain = $this->prepare_batched_chain($job);
                return $chain->first()->all_on_queue($this->options['queue'] ?? null)->all_on_connection($this->options['connection'] ?? null)->chain($chain->slice(1)->values()->all());
            }
            $job->with_batch_id($this->id);
            $count++;
            return $job;
        });
        $this->repository->transaction(function () use ($jobs, $count): void {
            $this->repository->increment_total_jobs($this->id, $count);
            $this->queue->connection($this->options['connection'] ?? null)->bulk($jobs->all(), $data = '', $this->options['queue'] ?? null);
        });
        return $this->fresh();
    }
    /**
     * Prepare a chain that exists within the jobs being added.
     */
    protected function prepare_batched_chain(array $chain): \Illuminate\Support\Collection
    {
        return (new Collection($chain))->map(function ($job) {
            $job = $job instanceof Closure ? Call_Queued_Closure::create($job) : $job;
            return $job->with_batch_id($this->id);
        });
    }
    /**
     * Get the total number of jobs that have been processed by the batch thus far.
     *
     * @return int
     */
    public function processed_jobs(): int|float
    {
        return $this->total_jobs - $this->pending_jobs;
    }
    /**
     * Get the percentage of jobs that have been processed (between 0-100).
     *
     * @return int<0, 100>
     */
    public function progress(): int
    {
        return $this->total_jobs > 0 ? (int) round($this->processed_jobs() / $this->total_jobs * 100) : 0;
    }
    /**
     * Record that a job within the batch finished successfully, executing any callbacks if necessary.
     */
    public function record_successful_job(string $job_id): void
    {
        $counts = $this->decrement_pending_jobs($job_id);
        if ($this->has_progress_callbacks()) {
            $this->invoke_callbacks('progress');
        }
        if ($counts->pending_jobs === 0) {
            $this->repository->mark_as_finished($this->id);
            $container = Container::get_instance();
            if ($container->bound(Dispatcher::class)) {
                $container->make(Dispatcher::class)->dispatch(new Batch_Finished($this));
            }
        }
        if ($counts->pending_jobs === 0 && $this->has_then_callbacks()) {
            $this->invoke_callbacks('then');
        }
        if ($counts->all_jobs_have_ran_exactly_once() && $this->has_finally_callbacks()) {
            $this->invoke_callbacks('finally');
        }
    }
    /**
     * Decrement the pending jobs for the batch.
     *
     * @return \Illuminate\Bus\UpdatedBatchJobCounts
     */
    public function decrement_pending_jobs(string $job_id)
    {
        return $this->repository->decrement_pending_jobs($this->id, $job_id);
    }
    /**
     * Invoke the callbacks of the given type.
     */
    protected function invoke_callbacks(string $type, ?Throwable $e = null): void
    {
        $batch = $this->fresh();
        foreach ($this->options[$type] ?? [] as $handler) {
            $this->invoke_handler_callback($handler, $batch, $e);
        }
    }
    /**
     * Determine if the batch has finished executing.
     */
    public function finished(): bool
    {
        return !is_null($this->finished_at);
    }
    /**
     * Determine if the batch has "progress" callbacks.
     */
    public function has_progress_callbacks(): bool
    {
        return isset($this->options['progress']) && !empty($this->options['progress']);
    }
    /**
     * Determine if the batch has "success" callbacks.
     */
    public function has_then_callbacks(): bool
    {
        return isset($this->options['then']) && !empty($this->options['then']);
    }
    /**
     * Determine if the batch allows jobs to fail without cancelling the batch.
     */
    public function allows_failures(): bool
    {
        return Arr::get($this->options, 'allowFailures', false) === true;
    }
    /**
     * Determine if the batch has job failures.
     */
    public function has_failures(): bool
    {
        return $this->failed_jobs > 0;
    }
    /**
     * Record that a job within the batch failed to finish successfully, executing any callbacks if necessary.
     *
     * @param  \Throwable  $e
     */
    public function record_failed_job(string $job_id, ?\Throwable $e): void
    {
        $counts = $this->increment_failed_jobs($job_id);
        if ($counts->failed_jobs === 1 && !$this->allows_failures()) {
            $this->cancel();
        }
        if ($this->allows_failures()) {
            if ($this->has_progress_callbacks()) {
                $this->invoke_callbacks('progress', $e);
            }
            if ($this->has_failure_callbacks()) {
                $this->invoke_callbacks('failure', $e);
            }
        }
        if ($counts->failed_jobs === 1 && $this->has_catch_callbacks()) {
            $this->invoke_callbacks('catch', $e);
        }
        if ($counts->all_jobs_have_ran_exactly_once() && $this->has_finally_callbacks()) {
            $this->invoke_callbacks('finally');
        }
    }
    /**
     * Increment the failed jobs for the batch.
     *
     * @return \Illuminate\Bus\UpdatedBatchJobCounts
     */
    public function increment_failed_jobs(string $job_id)
    {
        return $this->repository->increment_failed_jobs($this->id, $job_id);
    }
    /**
     * Determine if the batch has "catch" callbacks.
     */
    public function has_catch_callbacks(): bool
    {
        return isset($this->options['catch']) && !empty($this->options['catch']);
    }
    /**
     * Determine if the batch has "failure" callbacks.
     */
    public function has_failure_callbacks(): bool
    {
        return isset($this->options['failure']) && !empty($this->options['failure']);
    }
    /**
     * Determine if the batch has "finally" callbacks.
     */
    public function has_finally_callbacks(): bool
    {
        return isset($this->options['finally']) && !empty($this->options['finally']);
    }
    /**
     * Cancel the batch.
     */
    public function cancel(): void
    {
        $this->repository->cancel($this->id);
        $container = Container::get_instance();
        if ($container->bound(Dispatcher::class)) {
            $container->make(Dispatcher::class)->dispatch(new Batch_Canceled($this));
        }
    }
    /**
     * Determine if the batch has been cancelled.
     */
    public function canceled(): bool
    {
        return $this->cancelled();
    }
    /**
     * Determine if the batch has been cancelled.
     */
    public function cancelled(): bool
    {
        return !is_null($this->cancelled_at);
    }
    /**
     * Delete the batch from storage.
     */
    public function delete(): void
    {
        $this->repository->delete($this->id);
    }
    /**
     * Invoke a batch callback handler.
     *
     * @param  callable  $handler
     * @return void
     */
    protected function invoke_handler_callback($handler, Batch $batch, ?Throwable $e = null)
    {
        try {
            $handler($batch, $e);
        } catch (Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }
    /**
     * Convert the batch to an array.
     */
    public function to_array(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'totalJobs' => $this->total_jobs, 'pendingJobs' => $this->pending_jobs, 'processedJobs' => $this->processed_jobs(), 'progress' => $this->progress(), 'failedJobs' => $this->failed_jobs, 'options' => $this->options, 'createdAt' => $this->created_at, 'cancelledAt' => $this->cancelled_at, 'finishedAt' => $this->finished_at];
    }
    /**
     * Get the JSON serializable representation of the object.
     */
    public function jsonSerialize(): array
    {
        return $this->to_array();
    }
    /**
     * Dynamically access the batch's "options" via properties.
     */
    public function __get(string $key): mixed
    {
        return $this->options[$key] ?? null;
    }
}