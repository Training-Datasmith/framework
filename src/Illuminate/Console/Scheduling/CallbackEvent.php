<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Reflector;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;
class Callback_Event extends Event
{
    /**
     * The callback to call.
     *
     * @var string
     */
    protected $callback;
    /**
     * The result of the callback's execution.
     *
     * @var mixed
     */
    protected $result;
    /**
     * The exception that was thrown when calling the callback, if any.
     *
     * @var \Throwable|null
     */
    protected $exception;
    /**
     * Create a new event instance.
     *
     * @param  string|callable  $callback
     * @param  \DateTimeZone|string|null  $timezone
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        Event_Mutex $mutex,
        $callback,
        /**
         * The parameters to pass to the method.
         */
        protected array $parameters = [],
        $timezone = null
    )
    {
        if (!is_string($callback) && !Reflector::is_callable($callback)) {
            throw new InvalidArgumentException('Invalid scheduled callback event. Must be a string or callable.');
        }
        $this->mutex = $mutex;
        $this->callback = $callback;
        $this->timezone = $timezone;
    }
    /**
     * Run the callback event.
     *
     * @throws \Throwable
     */
    public function run(Container $container): void
    {
        parent::run($container);
        if ($this->exception) {
            throw $this->exception;
        }
        return $this->result;
    }
    /**
     * Determine if the event should skip because another process is overlapping.
     */
    public function should_skip_due_to_overlapping(): bool
    {
        return $this->description && parent::should_skip_due_to_overlapping();
    }
    /**
     * Indicate that the callback should run in the background.
     *
     *
     * @throws \RuntimeException
     */
    public function run_in_background(): never
    {
        throw new RuntimeException('Scheduled closures can not be run in the background.');
    }
    /**
     * Run the callback.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     */
    protected function execute($container): int
    {
        try {
            $this->result = is_object($this->callback) ? $container->call([$this->callback, '__invoke'], $this->parameters) : $container->call($this->callback, $this->parameters);
            return $this->result === false ? 1 : 0;
        } catch (Throwable $e) {
            $this->exception = $e;
            return 1;
        }
    }
    /**
     * Do not allow the event to overlap each other.
     *
     * The expiration time of the underlying cache lock may be specified in minutes.
     *
     * @param  int  $expiresAt
     * @return $this
     *
     * @throws \LogicException
     */
    public function without_overlapping($expires_at = 1440)
    {
        if (!isset($this->description)) {
            throw new LogicException("A scheduled event name is required to prevent overlapping. Use the 'name' method before 'withoutOverlapping'.");
        }
        return parent::without_overlapping($expires_at);
    }
    /**
     * Allow the event to only run on one server for each cron expression.
     *
     * @return $this
     *
     * @throws \LogicException
     */
    public function on_one_server()
    {
        if (!isset($this->description)) {
            throw new LogicException("A scheduled event name is required to only run on one server. Use the 'name' method before 'onOneServer'.");
        }
        return parent::on_one_server();
    }
    /**
     * Get the summary of the event for display.
     */
    public function get_summary_for_display(): string
    {
        if (is_string($this->description)) {
            return $this->description;
        }
        return is_string($this->callback) ? $this->callback : 'Callback';
    }
    /**
     * Get the mutex name for the scheduled command.
     */
    public function mutex_name(): string
    {
        return 'framework/schedule-' . sha1($this->description ?? '');
    }
    /**
     * Clear the mutex for the event.
     *
     * @return void
     */
    protected function remove_mutex()
    {
        if ($this->description) {
            parent::remove_mutex();
        }
    }
}