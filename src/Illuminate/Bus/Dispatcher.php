<?php

declare (strict_types=1);
namespace Illuminate\Bus;

use Illuminate\Contracts\Bus\Queueing_Dispatcher;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Contracts\Queue\Should_Queue;
use Illuminate\Foundation\Bus\Pending_Chain;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Queue\Interacts_With_Queue;
use Illuminate\Queue\Jobs\Sync_Job;
use Illuminate\Support\Collection;
use RuntimeException;
class Dispatcher implements Queueing_Dispatcher
{
    /**
     * The pipeline instance for the bus.
     */
    protected \Illuminate\Pipeline\Pipeline $pipeline;
    /**
     * The pipes to send commands through before dispatching.
     *
     * @var array
     */
    protected $pipes = [];
    /**
     * The command to handler mapping for non-self-handling events.
     *
     * @var array
     */
    protected $handlers = [];
    /**
     * Indicates if dispatching after response is disabled.
     *
     * @var bool
     */
    protected $allows_dispatching_after_responses = true;
    /**
     * Create a new command dispatcher instance.
     */
    public function __construct(
        /**
         * The container implementation.
         */
        protected \Illuminate\Contracts\Container\Container $container,
        /**
         * The queue resolver callback.
         */
        protected ?\Closure $queue_resolver = null
    )
    {
        $this->pipeline = new Pipeline($this->container);
    }
    /**
     * Dispatch a command to its appropriate handler.
     *
     * @param  mixed  $command
     * @return mixed
     */
    public function dispatch($command)
    {
        return $this->queue_resolver && $this->command_should_be_queued($command) ? $this->dispatch_to_queue($command) : $this->dispatch_now($command);
    }
    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * Queueable jobs will be dispatched to the "sync" queue.
     *
     * @param  mixed  $command
     * @param  mixed  $handler
     * @return mixed
     */
    public function dispatch_sync($command, $handler = null)
    {
        if ($this->queue_resolver && $this->command_should_be_queued($command) && method_exists($command, 'onConnection')) {
            return $this->dispatch_to_queue($command->on_connection('sync'));
        }
        return $this->dispatch_now($command, $handler);
    }
    /**
     * Dispatch a command to its appropriate handler in the current process without using the synchronous queue.
     *
     * @param  mixed  $command
     * @param  mixed  $handler
     * @return mixed
     */
    public function dispatch_now($command, $handler = null)
    {
        $uses = class_uses_recursive($command);
        if (isset($uses[Interacts_With_Queue::class], $uses[Queueable::class]) && !$command->job) {
            $command->set_job(new Sync_Job($this->container, json_encode([]), 'sync', 'sync'));
        }
        if ($handler || $handler = $this->get_command_handler($command)) {
            $callback = function ($command) use ($handler) {
                $method = method_exists($handler, 'handle') ? 'handle' : '__invoke';
                return $handler->{$method}($command);
            };
        } else {
            $callback = function ($command) {
                $method = method_exists($command, 'handle') ? 'handle' : '__invoke';
                return $this->container->call([$command, $method]);
            };
        }
        return $this->pipeline->send($command)->through($this->pipes)->then($callback);
    }
    /**
     * Attempt to find the batch with the given ID.
     *
     * @return \Illuminate\Bus\Batch|null
     */
    public function find_batch(string $batch_id)
    {
        return $this->container->make(Batch_Repository::class)->find($batch_id);
    }
    /**
     * Create a new batch of queueable jobs.
     *
     * @param  \Illuminate\Support\Collection|mixed  $jobs
     */
    public function batch($jobs): \Illuminate\Bus\Pending_Batch
    {
        return new Pending_Batch($this->container, Collection::wrap($jobs));
    }
    /**
     * Create a new chain of queueable jobs.
     *
     * @param  \Illuminate\Support\Collection|array|null  $jobs
     */
    public function chain($jobs = null): \Illuminate\Foundation\Bus\Pending_Chain
    {
        $jobs = Collection::wrap($jobs);
        $jobs = Chained_Batch::prepare_nested_batches($jobs);
        return new Pending_Chain($jobs->shift(), $jobs->to_array());
    }
    /**
     * Determine if the given command has a handler.
     *
     * @param  mixed  $command
     */
    public function has_command_handler($command): bool
    {
        return array_key_exists($command::class, $this->handlers);
    }
    /**
     * Retrieve the handler for a command.
     *
     * @param  mixed  $command
     * @return mixed
     */
    public function get_command_handler($command)
    {
        if ($this->has_command_handler($command)) {
            return $this->container->make($this->handlers[$command::class]);
        }
        return false;
    }
    /**
     * Determine if the given command should be queued.
     *
     * @param  mixed  $command
     */
    protected function command_should_be_queued($command): bool
    {
        return $command instanceof Should_Queue;
    }
    /**
     * Dispatch a command to its appropriate handler behind a queue.
     *
     * @param  mixed  $command
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public function dispatch_to_queue($command)
    {
        $connection = $command->connection ?? null;
        $queue = ($this->queue_resolver)($connection);
        if (!$queue instanceof Queue) {
            throw new RuntimeException('Queue resolver did not return a Queue implementation.');
        }
        if (method_exists($command, 'queue')) {
            return $command->queue($queue, $command);
        }
        return $this->push_command_to_queue($queue, $command);
    }
    /**
     * Push the command onto the given queue instance.
     *
     * @param  \Illuminate\Contracts\Queue\Queue  $queue
     * @param  mixed  $command
     * @return mixed
     */
    protected function push_command_to_queue($queue, $command)
    {
        if (isset($command->delay)) {
            return $queue->later($command->delay, $command, queue: $command->queue ?? null);
        }
        return $queue->push($command, queue: $command->queue ?? null);
    }
    /**
     * Dispatch a command to its appropriate handler after the current process.
     *
     * @param  mixed  $command
     * @param  mixed  $handler
     */
    public function dispatch_after_response($command, $handler = null): void
    {
        if (!$this->allows_dispatching_after_responses) {
            $this->dispatch_sync($command);
            return;
        }
        $this->container->terminating(function () use ($command, $handler): void {
            $this->dispatch_sync($command, $handler);
        });
    }
    /**
     * Set the pipes through which commands should be piped before dispatching.
     *
     * @return $this
     */
    public function pipe_through(array $pipes): static
    {
        $this->pipes = $pipes;
        return $this;
    }
    /**
     * Map a command to a handler.
     *
     * @return $this
     */
    public function map(array $map): static
    {
        $this->handlers = array_merge($this->handlers, $map);
        return $this;
    }
    /**
     * Allow dispatching after responses.
     *
     * @return $this
     */
    public function with_dispatching_after_responses(): static
    {
        $this->allows_dispatching_after_responses = true;
        return $this;
    }
    /**
     * Disable dispatching after responses.
     *
     * @return $this
     */
    public function without_dispatching_after_responses(): static
    {
        $this->allows_dispatching_after_responses = false;
        return $this;
    }
}