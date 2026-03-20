<?php

declare (strict_types=1);
namespace Illuminate\Events;

use Closure;
use Illuminate\Support\Collection;
use function Illuminate\Support\enum_value;
use Laravel\Serializable_Closure\Serializable_Closure;
class Queued_Closure
{
    /**
     * The underlying Closure.
     *
     * @var \Closure
     */
    public $closure;
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
     * @var \DateTimeInterface|\DateInterval|int|null
     */
    public $delay;
    /**
     * All of the "catch" callbacks for the queued closure.
     *
     * @var array
     */
    public $catch_callbacks = [];
    /**
     * Create a new queued closure event listener resolver.
     */
    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
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
     * Set the desired job "group".
     *
     * This feature is only supported by some queues, such as Amazon SQS.
     *
     * @param  \UnitEnum|string  $group
     * @return $this
     */
    public function on_group($group): static
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
    public function with_deduplicator($deduplicator): static
    {
        $this->deduplicator = $deduplicator instanceof Closure ? new Serializable_Closure($deduplicator) : $deduplicator;
        return $this;
    }
    /**
     * Set the desired delay in seconds for the job.
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
     * Specify a callback that should be invoked if the queued listener job fails.
     *
     * @return $this
     */
    public function catch(Closure $closure): static
    {
        $this->catch_callbacks[] = $closure;
        return $this;
    }
    /**
     * Resolve the actual event listener callback.
     *
     * @return \Closure
     */
    public function resolve()
    {
        return function (...$arguments): void {
            dispatch(new Call_Queued_Listener(Invoke_Queued_Closure::class, 'handle', ['closure' => new Serializable_Closure($this->closure), 'arguments' => $arguments, 'catch' => (new Collection($this->catch_callbacks))->map(fn($callback): \Laravel\Serializable_Closure\Serializable_Closure => new Serializable_Closure($callback))->all()]))->on_connection($this->connection)->on_queue($this->queue)->delay($this->delay)->on_group($this->message_group)->with_deduplicator($this->deduplicator);
        };
    }
}