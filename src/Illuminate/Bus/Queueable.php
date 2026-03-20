<?php

declare (strict_types=1);
namespace Illuminate\Bus;

use Closure;
use Illuminate\Queue\Call_Queued_Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use function Illuminate\Support\enum_value;
use Laravel\Serializable_Closure\Serializable_Closure;
use Php_Unit\Framework\Assert as PHPUnit;
use RuntimeException;
trait Queueable
{
    /**
     * The name of the connection the job should be sent to.
     *
     * @var string|null
     */
    public $connection;
    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue;
    /**
     * The job "group" the job should be sent to.
     *
     * @var string|null
     */
    public $message_group;
    /**
     * The job deduplicator callback the job should use to generate the deduplication ID.
     *
     * @var \Laravel\SerializableClosure\SerializableClosure|null
     */
    public $deduplicator;
    /**
     * The number of seconds before the job should be made available.
     *
     * @var \DateTimeInterface|\DateInterval|array|int|null
     */
    public $delay;
    /**
     * Indicates whether the job should be dispatched after all database transactions have committed.
     *
     * @var bool|null
     */
    public $after_commit;
    /**
     * The middleware the job should be dispatched through.
     *
     * @var array
     */
    public $middleware = [];
    /**
     * The jobs that should run if this job is successful.
     *
     * @var array
     */
    public $chained = [];
    /**
     * The name of the connection the chain should be sent to.
     *
     * @var string|null
     */
    public $chain_connection;
    /**
     * The name of the queue the chain should be sent to.
     *
     * @var string|null
     */
    public $chain_queue;
    /**
     * The callbacks to be executed on chain failure.
     *
     * @var array|null
     */
    public $chain_catch_callbacks;
    /**
     * Set the desired connection for the job.
     *
     * @param  \UnitEnum|string|null  $connection
     * @return $this
     */
    public function on_connection($connection)
    {
        $this->connection = enum_value($connection);
        return $this;
    }
    /**
     * Set the desired queue for the job.
     *
     * @param  \UnitEnum|string|null  $queue
     * @return $this
     */
    public function on_queue($queue)
    {
        $this->queue = enum_value($queue);
        return $this;
    }
    /**
     * Set the desired job "group".
     *
     * This feature is only supported by some queues, such as Amazon SQS.
     *
     * @param  \UnitEnum|string  $group
     * @return $this
     */
    public function on_group($group)
    {
        $this->message_group = enum_value($group);
        return $this;
    }
    /**
     * Set the desired job deduplicator callback.
     *
     * This feature is only supported by some queues, such as Amazon SQS FIFO.
     *
     * @param  callable|null  $deduplicator
     * @return $this
     */
    public function with_deduplicator($deduplicator)
    {
        $this->deduplicator = $deduplicator instanceof Closure ? new Serializable_Closure($deduplicator) : $deduplicator;
        return $this;
    }
    /**
     * Set the desired connection for the chain.
     *
     * @param  \UnitEnum|string|null  $connection
     * @return $this
     */
    public function all_on_connection($connection)
    {
        $resolved_connection = enum_value($connection);
        $this->chain_connection = $resolved_connection;
        $this->connection = $resolved_connection;
        return $this;
    }
    /**
     * Set the desired queue for the chain.
     *
     * @param  \UnitEnum|string|null  $queue
     * @return $this
     */
    public function all_on_queue($queue)
    {
        $resolved_queue = enum_value($queue);
        $this->chain_queue = $resolved_queue;
        $this->queue = $resolved_queue;
        return $this;
    }
    /**
     * Set the desired delay in seconds for the job.
     *
     * @param  \DateTimeInterface|\DateInterval|array|int|null  $delay
     * @return $this
     */
    public function delay($delay)
    {
        $this->delay = $delay;
        return $this;
    }
    /**
     * Set the delay for the job to zero seconds.
     *
     * @return $this
     */
    public function without_delay()
    {
        $this->delay = 0;
        return $this;
    }
    /**
     * Indicate that the job should be dispatched after all database transactions have committed.
     *
     * @return $this
     */
    public function after_commit()
    {
        $this->after_commit = true;
        return $this;
    }
    /**
     * Indicate that the job should not wait until database transactions have been committed before dispatching.
     *
     * @return $this
     */
    public function before_commit()
    {
        $this->after_commit = false;
        return $this;
    }
    /**
     * Specify the middleware the job should be dispatched through.
     *
     * @param  array|object  $middleware
     * @return $this
     */
    public function through($middleware)
    {
        $this->middleware = Arr::wrap($middleware);
        return $this;
    }
    /**
     * Set the jobs that should run if this job is successful.
     *
     * @param  array  $chain
     * @return $this
     */
    public function chain($chain)
    {
        $this->chained = Chained_Batch::prepare_nested_batches(new Collection($chain))->map(fn($job) => $this->serialize_job($job))->all();
        return $this;
    }
    /**
     * Prepend a job to the current chain so that it is run after the currently running job.
     *
     * @param  mixed  $job
     * @return $this
     */
    public function prepend_to_chain($job)
    {
        $jobs = Chained_Batch::prepare_nested_batches(Collection::wrap($job));
        foreach ($jobs->reverse() as $job) {
            $this->chained = Arr::prepend($this->chained, $this->serialize_job($job));
        }
        return $this;
    }
    /**
     * Append a job to the end of the current chain.
     *
     * @param  mixed  $job
     * @return $this
     */
    public function append_to_chain($job)
    {
        $jobs = Chained_Batch::prepare_nested_batches(Collection::wrap($job));
        foreach ($jobs as $job) {
            $this->chained = array_merge($this->chained, [$this->serialize_job($job)]);
        }
        return $this;
    }
    /**
     * Serialize a job for queuing.
     *
     * @param  mixed  $job
     *
     * @throws \RuntimeException
     */
    protected function serialize_job($job): string
    {
        if ($job instanceof Closure) {
            if (!class_exists(Call_Queued_Closure::class)) {
                throw new RuntimeException('To enable support for closure jobs, please install the illuminate/queue package.');
            }
            $job = Call_Queued_Closure::create($job);
        }
        return serialize($job);
    }
    /**
     * Dispatch the next job on the chain.
     */
    public function dispatch_next_job_in_chain(): void
    {
        if (is_array($this->chained) && !empty($this->chained)) {
            dispatch(tap(unserialize(array_shift($this->chained)), function ($next): void {
                $next->chained = $this->chained;
                $next->on_connection($next->connection ?: $this->chain_connection);
                $next->on_queue($next->queue ?: $this->chain_queue);
                $next->chain_connection = $this->chain_connection;
                $next->chain_queue = $this->chain_queue;
                $next->chain_catch_callbacks = $this->chain_catch_callbacks;
            }));
        }
    }
    /**
     * Invoke all of the chain's failed job callbacks.
     *
     * @param  \Throwable  $e
     */
    public function invoke_chain_catch_callbacks($e): void
    {
        (new Collection($this->chain_catch_callbacks))->each(function ($callback) use ($e): void {
            $callback($e);
        });
    }
    /**
     * Assert that the job has the given chain of jobs attached to it.
     *
     * @param  array  $expectedChain
     */
    public function assert_has_chain($expected_chain): void
    {
        Php_Unit::assert_true((new Collection($expected_chain))->is_not_empty(), 'The expected chain can not be empty.');
        if ((new Collection($expected_chain))->contains(fn($job): bool => is_object($job))) {
            $expected_chain = (new Collection($expected_chain))->map(fn($job): string => serialize($job))->all();
        } else {
            $chain = (new Collection($this->chained))->map(fn($job): string|false => unserialize($job)::class)->all();
        }
        Php_Unit::assert_true($expected_chain === ($chain ?? $this->chained), 'The job does not have the expected chain.');
    }
    /**
     * Assert that the job has no remaining chained jobs.
     */
    public function assert_doesnt_have_chain(): void
    {
        Php_Unit::assert_empty($this->chained, 'The job has chained jobs.');
    }
}