<?php

declare (strict_types=1);
namespace Illuminate\Bus;

use Closure;
use Illuminate\Bus\Events\Batch_Dispatched;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Traits\Conditionable;
use Laravel\Serializable_Closure\Serializable_Closure;
use RuntimeException;
use Throwable;
use Unit_Enum;
class Pending_Batch
{
    use Conditionable;
    /**
     * The batch name.
     *
     * @var string
     */
    public $name = '';
    /**
     * The jobs that belong to the batch.
     *
     * @var \Illuminate\Support\Collection
     */
    public $jobs;
    /**
     * The batch options.
     *
     * @var array
     */
    public $options = [];
    /**
     * Jobs that have been verified to contain the Batchable trait.
     *
     * @var array<class-string, bool>
     */
    protected static $batchable_classes = [];
    /**
     * Create a new pending batch instance.
     */
    public function __construct(
        /**
         * The IoC container instance.
         */
        protected \Illuminate\Contracts\Container\Container $container,
        Collection $jobs
    )
    {
        $this->jobs = $jobs->filter()->values()->each(function (object|array $job): void {
            $this->ensure_job_is_batchable($job);
        });
    }
    /**
     * Add jobs to the batch.
     *
     * @param  iterable|object|array  $jobs
     * @return $this
     */
    public function add($jobs): static
    {
        $jobs = is_iterable($jobs) ? $jobs : Arr::wrap($jobs);
        foreach ($jobs as $job) {
            $this->ensure_job_is_batchable($job);
            $this->jobs->push($job);
        }
        return $this;
    }
    /**
     * Ensure the given job is batchable.
     */
    protected function ensure_job_is_batchable(object|array $job): void
    {
        foreach (Arr::wrap($job) as $job) {
            if ($job instanceof Pending_Batch || $job instanceof Closure) {
                return;
            }
            if (!(static::$batchable_classes[$job::class] ?? false) && !in_array(Batchable::class, class_uses_recursive($job))) {
                static::$batchable_classes[$job::class] = false;
                throw new RuntimeException(sprintf('Attempted to batch job [%s], but it does not use the Batchable trait.', $job::class));
            }
            static::$batchable_classes[$job::class] = true;
        }
    }
    /**
     * Add a callback to be executed when the batch is stored.
     *
     * @return $this
     */
    public function before(\Closure|callable $callback): static
    {
        $this->register_callback('before', $callback);
        return $this;
    }
    /**
     * Get the "before" callbacks that have been registered with the pending batch.
     *
     * @return array
     */
    public function before_callbacks()
    {
        return $this->options['before'] ?? [];
    }
    /**
     * Add a callback to be executed after a job in the batch have executed successfully.
     *
     * @return $this
     */
    public function progress(\Closure|callable $callback): static
    {
        $this->register_callback('progress', $callback);
        return $this;
    }
    /**
     * Get the "progress" callbacks that have been registered with the pending batch.
     *
     * @return array
     */
    public function progress_callbacks()
    {
        return $this->options['progress'] ?? [];
    }
    /**
     * Add a callback to be executed after all jobs in the batch have executed successfully.
     *
     * @return $this
     */
    public function then(\Closure|callable $callback): static
    {
        $this->register_callback('then', $callback);
        return $this;
    }
    /**
     * Get the "then" callbacks that have been registered with the pending batch.
     *
     * @return array
     */
    public function then_callbacks()
    {
        return $this->options['then'] ?? [];
    }
    /**
     * Add a callback to be executed after the first failing job in the batch.
     *
     * @return $this
     */
    public function catch(\Closure|callable $callback): static
    {
        $this->register_callback('catch', $callback);
        return $this;
    }
    /**
     * Get the "catch" callbacks that have been registered with the pending batch.
     *
     * @return array
     */
    public function catch_callbacks()
    {
        return $this->options['catch'] ?? [];
    }
    /**
     * Add a callback to be executed after the batch has finished executing.
     *
     * @return $this
     */
    public function finally(\Closure|callable $callback): static
    {
        $this->register_callback('finally', $callback);
        return $this;
    }
    /**
     * Get the "finally" callbacks that have been registered with the pending batch.
     *
     * @return array
     */
    public function finally_callbacks()
    {
        return $this->options['finally'] ?? [];
    }
    /**
     * Indicate that the batch should not be canceled when a job within the batch fails.
     *
     * Optionally, add callbacks to be executed upon each job failure.
     *
     * @phpstan-type TParam (Closure(\Illuminate\Bus\Batch, \Throwable|null): mixed)|(callable(\Illuminate\Bus\Batch, \Throwable|null): mixed)
     *
     * @param  bool|TParam|array<array-key, TParam>  $param
     * @return $this
     */
    public function allow_failures($param = true): static
    {
        if (!is_bool($param)) {
            $param = Arr::wrap($param);
            foreach ($param as $callback) {
                if (is_callable($callback)) {
                    $this->register_callback('failure', $callback);
                }
            }
        }
        $this->options['allowFailures'] = !($param === false);
        return $this;
    }
    /**
     * Determine if the pending batch allows jobs to fail without cancelling the batch.
     */
    public function allows_failures(): bool
    {
        return Arr::get($this->options, 'allowFailures', false) === true;
    }
    /**
     * Get the "failure" callbacks that have been registered with the pending batch.
     *
     * @return array<array-key, Closure|callable>
     */
    public function failure_callbacks(): array
    {
        return $this->options['failure'] ?? [];
    }
    /**
     * Register a callback with proper serialization.
     */
    private function register_callback(string $type, Closure|callable $callback): void
    {
        $this->options[$type][] = $callback instanceof Closure ? new Serializable_Closure($callback) : $callback;
    }
    /**
     * Set the name for the batch.
     *
     * @return $this
     */
    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }
    /**
     * Specify the queue connection that the batched jobs should run on.
     *
     * @return $this
     */
    public function on_connection(Unit_Enum|string $connection): static
    {
        $this->options['connection'] = enum_value($connection);
        return $this;
    }
    /**
     * Get the connection used by the pending batch.
     *
     * @return string|null
     */
    public function connection()
    {
        return $this->options['connection'] ?? null;
    }
    /**
     * Specify the queue that the batched jobs should run on.
     *
     * @param  \UnitEnum|string|null  $queue
     * @return $this
     */
    public function on_queue($queue): static
    {
        $this->options['queue'] = enum_value($queue);
        return $this;
    }
    /**
     * Get the queue used by the pending batch.
     *
     * @return string|null
     */
    public function queue()
    {
        return $this->options['queue'] ?? null;
    }
    /**
     * Add additional data into the batch's options array.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function with_option(string $key, $value): static
    {
        $this->options[$key] = $value;
        return $this;
    }
    /**
     * Dispatch the batch.
     *
     * @return \Illuminate\Bus\Batch
     *
     * @throws \Throwable
     */
    public function dispatch()
    {
        $repository = $this->container->make(Batch_Repository::class);
        try {
            $batch = $this->store($repository);
            $batch = $batch->add($this->jobs);
        } catch (Throwable $e) {
            if (isset($batch)) {
                $repository->delete($batch->id);
            }
            throw $e;
        }
        $this->container->make(Event_Dispatcher::class)->dispatch(new Batch_Dispatched($batch));
        return $batch;
    }
    /**
     * Dispatch the batch after the response is sent to the browser.
     *
     * @return \Illuminate\Bus\Batch
     */
    public function dispatch_after_response()
    {
        $repository = $this->container->make(Batch_Repository::class);
        $batch = $this->store($repository);
        if ($batch) {
            $this->container->terminating(function () use ($batch): void {
                $this->dispatch_existing_batch($batch);
            });
        }
        return $batch;
    }
    /**
     * Dispatch an existing batch.
     *
     * @param  \Illuminate\Bus\Batch  $batch
     * @return void
     *
     * @throws \Throwable
     */
    protected function dispatch_existing_batch($batch)
    {
        try {
            $batch = $batch->add($this->jobs);
        } catch (Throwable $e) {
            $batch->delete();
            throw $e;
        }
        $this->container->make(Event_Dispatcher::class)->dispatch(new Batch_Dispatched($batch));
    }
    /**
     * Dispatch the batch if the given truth test passes.
     *
     * @param  bool|\Closure  $boolean
     * @return \Illuminate\Bus\Batch|null
     */
    public function dispatch_if($boolean)
    {
        return value($boolean) ? $this->dispatch() : null;
    }
    /**
     * Dispatch the batch unless the given truth test passes.
     *
     * @param  bool|\Closure  $boolean
     * @return \Illuminate\Bus\Batch|null
     */
    public function dispatch_unless($boolean)
    {
        return !value($boolean) ? $this->dispatch() : null;
    }
    /**
     * Store the batch using the given repository.
     *
     * @param  \Illuminate\Bus\BatchRepository  $repository
     * @return \Illuminate\Bus\Batch
     */
    protected function store($repository)
    {
        $batch = $repository->store($this);
        (new Collection($this->before_callbacks()))->each(function ($handler) use ($batch) {
            try {
                return $handler($batch);
            } catch (Throwable $e) {
                if (function_exists('report')) {
                    report($e);
                }
            }
        });
        return $batch;
    }
}