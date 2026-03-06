<?php

declare(strict_types=1);

namespace Illuminate\Bus;

use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Throwable;

class ChainedBatch implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
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
    public function __construct(PendingBatch $batch)
    {
        $this->jobs = static::prepareNestedBatches($batch->jobs);

        $this->name = $batch->name;
        $this->options = $batch->options;

        $this->queue = $batch->queue();
        $this->connection = $batch->connection();
    }

    /**
     * Prepare any nested batches within the given collection of jobs.
     */
    public static function prepareNestedBatches(Collection $jobs): Collection
    {
        return $jobs->filter()->values()->map(fn (\Illuminate\Support\Collection $job) => match (true) {
            is_array($job) => static::prepareNestedBatches(new Collection($job))->all(),
            $job instanceof Collection => static::prepareNestedBatches($job),
            $job instanceof PendingBatch => new ChainedBatch($job),
            default => $job,
        });
    }

    /**
     * Handle the job.
     */
    public function handle(): void
    {
        $this->attachRemainderOfChainToEndOfBatch(
            $this->toPendingBatch()
        )->dispatch();
    }

    /**
     * Convert the chained batch instance into a pending batch.
     *
     * @return \Illuminate\Bus\PendingBatch
     */
    public function toPendingBatch()
    {
        $batch = Container::getInstance()->make(Dispatcher::class)->batch($this->jobs);

        $batch->name = $this->name;
        $batch->options = $this->options;

        if ($this->queue) {
            $batch->onQueue($this->queue);
        }

        if ($this->connection) {
            $batch->onConnection($this->connection);
        }

        foreach ($this->chainCatchCallbacks ?? [] as $callback) {
            $batch->catch(function (Batch $batch, ?Throwable $exception) use ($callback): void {
                if (! $batch->allowsFailures()) {
                    $callback($exception);
                }
            });
        }

        return $batch;
    }

    /**
     * Move the remainder of the chain to a "finally" batch callback.
     */
    protected function attachRemainderOfChainToEndOfBatch(PendingBatch $batch): PendingBatch
    {
        if (is_array($this->chained) && ! empty($this->chained)) {
            $next = unserialize(array_shift($this->chained));

            $next->chained = $this->chained;

            $next->onConnection($next->connection ?: $this->chainConnection);
            $next->onQueue($next->queue ?: $this->chainQueue);

            $next->chainConnection = $this->chainConnection;
            $next->chainQueue = $this->chainQueue;
            $next->chainCatchCallbacks = $this->chainCatchCallbacks;

            $batch->finally(function (Batch $batch) use ($next): void {
                if (! $batch->cancelled()) {
                    Container::getInstance()->make(Dispatcher::class)->dispatch($next);
                }
            });

            $this->chained = [];
        }

        return $batch;
    }
}
