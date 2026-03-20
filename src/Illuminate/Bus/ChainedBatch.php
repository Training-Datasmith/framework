<?php

declare (strict_types=1);
namespace Illuminate\Bus;

use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\Should_Queue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Interacts_With_Queue;
use Illuminate\Support\Collection;
use Throwable;
class Chained_Batch implements Should_Queue
{
    use Batchable;
    use Dispatchable;
    use Interacts_With_Queue;
    use Queueable;
    /**
     * The collection of batched jobs.
     */
    public Collection $jobs;
    /**
     * The name of the batch.
     */
    public string $name;
    /**
     * The batch options.
     */
    public array $options;
    /**
     * Create a new chained batch instance.
     */
    public function __construct(Pending_Batch $batch)
    {
        $this->jobs = static::prepare_nested_batches($batch->jobs);
        $this->name = $batch->name;
        $this->options = $batch->options;
        $this->queue = $batch->queue();
        $this->connection = $batch->connection();
    }
    /**
     * Prepare any nested batches within the given collection of jobs.
     */
    public static function prepare_nested_batches(Collection $jobs): Collection
    {
        return $jobs->filter()->values()->map(fn(\Illuminate\Support\Collection $job) => match (true) {
            is_array($job) => static::prepare_nested_batches(new Collection($job))->all(),
            $job instanceof Collection => static::prepare_nested_batches($job),
            $job instanceof Pending_Batch => new Chained_Batch($job),
            default => $job,
        });
    }
    /**
     * Handle the job.
     */
    public function handle(): void
    {
        $this->attach_remainder_of_chain_to_end_of_batch($this->to_pending_batch())->dispatch();
    }
    /**
     * Convert the chained batch instance into a pending batch.
     *
     * @return \Illuminate\Bus\PendingBatch
     */
    public function to_pending_batch()
    {
        $batch = Container::get_instance()->make(Dispatcher::class)->batch($this->jobs);
        $batch->name = $this->name;
        $batch->options = $this->options;
        if ($this->queue) {
            $batch->on_queue($this->queue);
        }
        if ($this->connection) {
            $batch->on_connection($this->connection);
        }
        foreach ($this->chain_catch_callbacks ?? [] as $callback) {
            $batch->catch(function (Batch $batch, ?Throwable $exception) use ($callback): void {
                if (!$batch->allows_failures()) {
                    $callback($exception);
                }
            });
        }
        return $batch;
    }
    /**
     * Move the remainder of the chain to a "finally" batch callback.
     */
    protected function attach_remainder_of_chain_to_end_of_batch(Pending_Batch $batch): Pending_Batch
    {
        if (is_array($this->chained) && !empty($this->chained)) {
            $next = unserialize(array_shift($this->chained));
            $next->chained = $this->chained;
            $next->on_connection($next->connection ?: $this->chain_connection);
            $next->on_queue($next->queue ?: $this->chain_queue);
            $next->chain_connection = $this->chain_connection;
            $next->chain_queue = $this->chain_queue;
            $next->chain_catch_callbacks = $this->chain_catch_callbacks;
            $batch->finally(function (Batch $batch) use ($next): void {
                if (!$batch->cancelled()) {
                    Container::get_instance()->make(Dispatcher::class)->dispatch($next);
                }
            });
            $this->chained = [];
        }
        return $batch;
    }
}