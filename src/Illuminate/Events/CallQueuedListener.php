<?php

declare (strict_types=1);
namespace Illuminate\Events;

use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\Should_Queue;
use Illuminate\Queue\Interacts_With_Queue;
class Call_Queued_Listener implements Should_Queue
{
    use Interacts_With_Queue;
    use Queueable;
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries;
    /**
     * The maximum number of exceptions allowed, regardless of attempts.
     *
     * @var int
     */
    public $max_exceptions;
    /**
     * The number of seconds to wait before retrying a job that encountered an uncaught exception.
     *
     * @var int
     */
    public $backoff;
    /**
     * The timestamp indicating when the job should timeout.
     *
     * @var int
     */
    public $retry_until;
    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout;
    /**
     * Indicates if the job should fail if the timeout is exceeded.
     *
     * @var bool
     */
    public $fail_on_timeout = false;
    /**
     * Indicates if the job should be encrypted.
     *
     * @var bool
     */
    public $should_be_encrypted = false;
    /**
     * Indicates if the listener should be unique.
     */
    public bool $should_be_unique = false;
    /**
     * Indicates if the listener should be unique until processing begins.
     */
    public bool $should_be_unique_until_processing = false;
    /**
     * The unique ID of the listener.
     */
    public mixed $unique_id = null;
    /**
     * The number of seconds the unique lock should be maintained.
     */
    public ?int $unique_for = null;
    /**
     * Create a new job instance.
     *
     * @param  class-string  $class
     * @param  string  $method
     * @param  array  $data
     */
    public function __construct(
        /**
         * The listener class name.
         */
        public $class,
        /**
         * The listener method.
         */
        public $method,
        /**
         * The data to be passed to the listener.
         */
        public $data
    )
    {
    }
    /**
     * Handle the queued job.
     */
    public function handle(Container $container): void
    {
        $this->prepare_data();
        $handler = $this->set_job_instance_if_necessary($this->job, $container->make($this->class));
        $handler->{$this->method}(...array_values($this->data));
    }
    /**
     * Determine if the listener should be unique.
     */
    public function should_be_unique(): bool
    {
        return $this->should_be_unique;
    }
    /**
     * Determine if the listener should be unique until processing begins.
     */
    public function should_be_unique_until_processing(): bool
    {
        return $this->should_be_unique_until_processing;
    }
    /**
     * Get the unique ID for the listener.
     */
    public function unique_id(): mixed
    {
        return $this->unique_id;
    }
    /**
     * Get the number of seconds the unique lock should be maintained.
     */
    public function unique_for(): ?int
    {
        return $this->unique_for;
    }
    /**
     * Get the cache store used to manage unique locks.
     */
    public function unique_via(): ?Cache
    {
        $listener = Container::get_instance()->make($this->class);
        if (!method_exists($listener, 'uniqueVia')) {
            return null;
        }
        $this->prepare_data();
        return $listener->unique_via(...array_values($this->data));
    }
    /**
     * Set the job instance of the given class if necessary.
     *
     * @param  object  $instance
     * @return object
     */
    protected function set_job_instance_if_necessary(Job $job, $instance)
    {
        if (in_array(Interacts_With_Queue::class, class_uses_recursive($instance))) {
            $instance->set_job($job);
        }
        return $instance;
    }
    /**
     * Call the failed method on the job instance.
     *
     * The event instance and the exception will be passed.
     *
     * @param  \Throwable  $e
     */
    public function failed($e): void
    {
        $this->prepare_data();
        $handler = Container::get_instance()->make($this->class);
        $parameters = array_merge(array_values($this->data), [$e]);
        if (method_exists($handler, 'failed')) {
            $handler->failed(...$parameters);
        }
    }
    /**
     * Unserialize the data if needed.
     *
     * @return void
     */
    protected function prepare_data()
    {
        if (is_string($this->data)) {
            $this->data = unserialize($this->data);
        }
    }
    /**
     * Get the display name for the queued job.
     *
     * @return string
     */
    public function display_name()
    {
        return $this->class;
    }
    /**
     * Prepare the instance for cloning.
     */
    public function __clone()
    {
        $this->data = array_map(fn($data) => is_object($data) ? clone $data : $data, $this->data);
    }
}