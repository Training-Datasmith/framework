<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Bus;

use Illuminate\Bus\Unique_Lock;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Queue\Should_Be_Unique;
use Illuminate\Foundation\Queue\Interacts_With_Unique_Jobs;
class Pending_Dispatch
{
    use Interacts_With_Unique_Jobs;
    /**
     * Indicates if the job should be dispatched immediately after sending the response.
     *
     * @var bool
     */
    protected $after_response = false;
    /**
     * Create a new pending job dispatch.
     *
     * @param  mixed  $job
     */
    public function __construct(
        /**
         * The job.
         */
        protected $job
    )
    {
    }
    /**
     * Set the desired connection for the job.
     *
     * @param  \BackedEnum|string|null  $connection
     * @return $this
     */
    public function on_connection($connection): static
    {
        $this->job->on_connection($connection);
        return $this;
    }
    /**
     * Set the desired queue for the job.
     *
     * @param  \BackedEnum|string|null  $queue
     * @return $this
     */
    public function on_queue($queue): static
    {
        $this->job->on_queue($queue);
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
        $this->job->on_group($group);
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
        $this->job->with_deduplicator($deduplicator);
        return $this;
    }
    /**
     * Set the desired connection for the chain.
     *
     * @param  \BackedEnum|string|null  $connection
     * @return $this
     */
    public function all_on_connection($connection): static
    {
        $this->job->all_on_connection($connection);
        return $this;
    }
    /**
     * Set the desired queue for the chain.
     *
     * @param  \BackedEnum|string|null  $queue
     * @return $this
     */
    public function all_on_queue($queue): static
    {
        $this->job->all_on_queue($queue);
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
        $this->job->delay($delay);
        return $this;
    }
    /**
     * Set the delay for the job to zero seconds.
     *
     * @return $this
     */
    public function without_delay(): static
    {
        $this->job->without_delay();
        return $this;
    }
    /**
     * Indicate that the job should be dispatched after all database transactions have committed.
     *
     * @return $this
     */
    public function after_commit(): static
    {
        $this->job->after_commit();
        return $this;
    }
    /**
     * Indicate that the job should not wait until database transactions have been committed before dispatching.
     *
     * @return $this
     */
    public function before_commit(): static
    {
        $this->job->before_commit();
        return $this;
    }
    /**
     * Set the jobs that should run if this job is successful.
     *
     * @param  array  $chain
     * @return $this
     */
    public function chain($chain): static
    {
        $this->job->chain($chain);
        return $this;
    }
    /**
     * Indicate that the job should be dispatched after the response is sent to the browser.
     *
     * @param  bool  $afterResponse
     * @return $this
     */
    public function after_response($after_response = true): static
    {
        $this->after_response = $after_response;
        return $this;
    }
    /**
     * Determine if the job should be dispatched.
     *
     * @return bool
     */
    protected function should_dispatch()
    {
        if (!$this->job instanceof Should_Be_Unique) {
            return true;
        }
        return (new Unique_Lock(Container::get_instance()->make(Cache::class)))->acquire($this->job);
    }
    /**
     * Get the underlying job instance.
     *
     * @return mixed
     */
    public function get_job()
    {
        return $this->job;
    }
    /**
     * Dynamically proxy methods to the underlying job.
     *
     * @return $this
     */
    public function __call(string $method, array $parameters)
    {
        $this->job->{$method}(...$parameters);
        return $this;
    }
    /**
     * Handle the object's destruction.
     */
    public function __destruct()
    {
        $this->add_unique_job_information_to_context($this->job);
        if (!$this->should_dispatch()) {
            $this->remove_unique_job_information_from_context($this->job);
            return;
        }
        if ($this->after_response) {
            app(Dispatcher::class)->dispatch_after_response($this->job);
        } else {
            app(Dispatcher::class)->dispatch($this->job);
        }
        $this->remove_unique_job_information_from_context($this->job);
    }
}