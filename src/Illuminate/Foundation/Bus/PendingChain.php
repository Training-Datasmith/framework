<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Bus;

use Closure;
use Illuminate\Bus\Chained_Batch;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Queue\Call_Queued_Closure;
use Illuminate\Support\Collection;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Traits\Conditionable;
use Laravel\Serializable_Closure\Serializable_Closure;
class Pending_Chain
{
    use Conditionable;
    /**
     * The name of the connection the chain should be sent to.
     *
     * @var string|null
     */
    public $connection;
    /**
     * The name of the queue the chain should be sent to.
     *
     * @var string|null
     */
    public $queue;
    /**
     * The number of seconds before the chain should be made available.
     *
     * @var \DateTimeInterface|\DateInterval|int|null
     */
    public $delay;
    /**
     * The callbacks to be executed on failure.
     *
     * @var array
     */
    public $catch_callbacks = [];
    /**
     * Create a new PendingChain instance.
     *
     * @param  mixed  $job
     * @param  array  $chain
     */
    public function __construct(
        /**
         * The class name of the job being dispatched.
         */
        public $job,
        /**
         * The jobs to be chained.
         */
        public $chain
    )
    {
    }
    /**
     * Set the desired connection for the job.
     *
     * @param  \UnitEnum|string|null  $connection
     * @return $this
     */
    public function on_connection($connection): static
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
    public function on_queue($queue): static
    {
        $this->queue = enum_value($queue);
        return $this;
    }
    /**
     * Prepend a job to the chain.
     *
     * @param  mixed  $job
     * @return $this
     */
    public function prepend($job): static
    {
        $jobs = Chained_Batch::prepare_nested_batches(Collection::wrap($job));
        if ($this->job) {
            array_unshift($this->chain, $this->job);
        }
        $this->job = $jobs->shift();
        array_unshift($this->chain, ...$jobs->to_array());
        return $this;
    }
    /**
     * Append a job to the chain.
     *
     * @param  mixed  $job
     * @return $this
     */
    public function append($job): static
    {
        $jobs = Chained_Batch::prepare_nested_batches(Collection::wrap($job));
        if (!$this->job) {
            $this->job = $jobs->shift();
        }
        array_push($this->chain, ...$jobs->to_array());
        return $this;
    }
    /**
     * Set the desired delay in seconds for the chain.
     *
     * @param  \DateTimeInterface|\DateInterval|int|null  $delay
     * @return $this
     */
    public function delay($delay): static
    {
        $this->delay = $delay;
        return $this;
    }
    /**
     * Add a callback to be executed on job failure.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function catch($callback): static
    {
        $this->catch_callbacks[] = $callback instanceof Closure ? new Serializable_Closure($callback) : $callback;
        return $this;
    }
    /**
     * Get the "catch" callbacks that have been registered.
     *
     * @return array
     */
    public function catch_callbacks()
    {
        return $this->catch_callbacks ?? [];
    }
    /**
     * Dispatch the job chain.
     *
     * @return \Illuminate\Foundation\Bus\PendingDispatch
     */
    public function dispatch()
    {
        if (is_string($this->job)) {
            $first_job = new $this->job(...func_get_args());
        } elseif ($this->job instanceof Closure) {
            $first_job = Call_Queued_Closure::create($this->job);
        } else {
            $first_job = $this->job;
        }
        if ($this->connection) {
            $first_job->chain_connection = $this->connection;
            $first_job->connection = $first_job->connection ?: $this->connection;
        }
        if ($this->queue) {
            $first_job->chain_queue = $this->queue;
            $first_job->queue = $first_job->queue ?: $this->queue;
        }
        if ($this->delay) {
            $first_job->delay = !is_null($first_job->delay) ? $first_job->delay : $this->delay;
        }
        $first_job->chain($this->chain);
        $first_job->chain_catch_callbacks = $this->catch_callbacks();
        return app(Dispatcher::class)->dispatch($first_job);
    }
    /**
     * Dispatch the job chain if the given truth test passes.
     *
     * @param  bool|\Closure  $boolean
     * @return \Illuminate\Foundation\Bus\PendingDispatch|null
     */
    public function dispatch_if($boolean)
    {
        return value($boolean) ? $this->dispatch() : null;
    }
    /**
     * Dispatch the job chain unless the given truth test passes.
     *
     * @param  bool|\Closure  $boolean
     * @return \Illuminate\Foundation\Bus\PendingDispatch|null
     */
    public function dispatch_unless($boolean)
    {
        return !value($boolean) ? $this->dispatch() : null;
    }
}