<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

use BadMethodCallException;
use Closure;
use DateTimeInterface;
use Illuminate\Bus\Unique_Lock;
use Illuminate\Console\Application;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Binding_Resolution_Exception;
use Illuminate\Contracts\Queue\Should_Be_Unique;
use Illuminate\Contracts\Queue\Should_Queue;
use Illuminate\Queue\Call_Queued_Closure;
use Illuminate\Support\Collection;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Process_Utils;
use Illuminate\Support\Traits\Macroable;
use RuntimeException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
/**
 * @mixin \Illuminate\Console\Scheduling\PendingEventAttributes
 */
class Schedule
{
    use Macroable {
        __call as macroCall;
    }
    public const SUNDAY = 0;
    public const MONDAY = 1;
    public const TUESDAY = 2;
    public const WEDNESDAY = 3;
    public const THURSDAY = 4;
    public const FRIDAY = 5;
    public const SATURDAY = 6;
    /**
     * All of the events on the schedule.
     *
     * @var \Illuminate\Console\Scheduling\Event[]
     */
    protected $events = [];
    /**
     * The event mutex implementation.
     *
     * @var \Illuminate\Console\Scheduling\EventMutex
     */
    protected $event_mutex;
    /**
     * The scheduling mutex implementation.
     *
     * @var \Illuminate\Console\Scheduling\SchedulingMutex
     */
    protected $scheduling_mutex;
    /**
     * The job dispatcher implementation.
     *
     * @var \Illuminate\Contracts\Bus\Dispatcher
     */
    protected $dispatcher;
    /**
     * The cache of mutex results.
     *
     * @var array<string, bool>
     */
    protected $mutex_cache = [];
    /**
     * The attributes to pass to the event.
     *
     * @var \Illuminate\Console\Scheduling\PendingEventAttributes|null
     */
    protected $attributes;
    /**
     * The schedule group attributes stack.
     *
     * @var array<int, PendingEventAttributes>
     */
    protected array $group_stack = [];
    /**
     * Create a new schedule instance.
     *
     * @param  \DateTimeZone|string|null  $timezone
     *
     * @throws \RuntimeException
     */
    public function __construct(
        /**
         * The timezone the date should be evaluated on.
         */
        protected $timezone = null
    )
    {
        if (!class_exists(Container::class)) {
            throw new RuntimeException('A container implementation is required to use the scheduler. Please install the illuminate/container package.');
        }
        $container = Container::get_instance();
        $this->event_mutex = $container->bound(Event_Mutex::class) ? $container->make(Event_Mutex::class) : $container->make(Cache_Event_Mutex::class);
        $this->scheduling_mutex = $container->bound(Scheduling_Mutex::class) ? $container->make(Scheduling_Mutex::class) : $container->make(Cache_Scheduling_Mutex::class);
    }
    /**
     * Add a new callback event to the schedule.
     *
     * @param  string|callable  $callback
     * @return \Illuminate\Console\Scheduling\CallbackEvent
     */
    public function call($callback, array $parameters = [])
    {
        $this->events[] = $event = new Callback_Event($this->event_mutex, $callback, $parameters, $this->timezone);
        $this->merge_pending_attributes($event);
        return $event;
    }
    /**
     * Add a new Artisan command event to the schedule.
     *
     * @param  \Symfony\Component\Console\Command\Command|string  $command
     * @return \Illuminate\Console\Scheduling\Event
     */
    public function command($command, array $parameters = [])
    {
        if ($command instanceof Symfony_Command) {
            $command = $command::class;
            $command = Container::get_instance()->make($command);
            return $this->exec(Application::format_command_string($command->get_name()), $parameters)->description($command->get_description());
        }
        if (class_exists($command)) {
            $command = Container::get_instance()->make($command);
            return $this->exec(Application::format_command_string($command->get_name()), $parameters)->description($command->get_description());
        }
        return $this->exec(Application::format_command_string($command), $parameters);
    }
    /**
     * Add a new job callback event to the schedule.
     *
     * @param  object|string  $job
     * @param  \UnitEnum|string|null  $queue
     * @param  \UnitEnum|string|null  $connection
     * @return \Illuminate\Console\Scheduling\CallbackEvent
     */
    public function job($job, $queue = null, $connection = null)
    {
        $job_name = $job;
        $queue = enum_value($queue);
        $connection = enum_value($connection);
        if (!is_string($job)) {
            $job_name = method_exists($job, 'displayName') ? $job->display_name() : $job::class;
        }
        $this->events[] = $event = new Callback_Event($this->event_mutex, function () use ($job, $queue, $connection): void {
            $job = is_string($job) ? Container::get_instance()->make($job) : $job;
            if ($job instanceof Should_Queue) {
                $this->dispatch_to_queue($job, $queue ?? $job->queue, $connection ?? $job->connection);
            } else {
                $this->dispatch_now($job);
            }
        }, [], $this->timezone);
        $event->name($job_name);
        $this->merge_pending_attributes($event);
        return $event;
    }
    /**
     * Dispatch the given job to the queue.
     *
     * @param  object  $job
     * @param  string|null  $queue
     * @param  string|null  $connection
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function dispatch_to_queue($job, $queue, $connection)
    {
        if ($job instanceof Closure) {
            if (!class_exists(Call_Queued_Closure::class)) {
                throw new RuntimeException('To enable support for closure jobs, please install the illuminate/queue package.');
            }
            $job = Call_Queued_Closure::create($job);
        }
        if ($job instanceof Should_Be_Unique) {
            return $this->dispatch_unique_job_to_queue($job, $queue, $connection);
        }
        $this->get_dispatcher()->dispatch($job->on_connection($connection)->on_queue($queue));
    }
    /**
     * Dispatch the given unique job to the queue.
     *
     * @param  object  $job
     * @param  string|null  $queue
     * @param  string|null  $connection
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function dispatch_unique_job_to_queue($job, $queue, $connection)
    {
        if (!Container::get_instance()->bound(Cache::class)) {
            throw new RuntimeException('Cache driver not available. Scheduling unique jobs not supported.');
        }
        if (!(new Unique_Lock(Container::get_instance()->make(Cache::class)))->acquire($job)) {
            return;
        }
        $this->get_dispatcher()->dispatch($job->on_connection($connection)->on_queue($queue));
    }
    /**
     * Dispatch the given job right now.
     *
     * @param  object  $job
     * @return void
     */
    protected function dispatch_now($job)
    {
        $this->get_dispatcher()->dispatch_now($job);
    }
    /**
     * Add a new command event to the schedule.
     *
     * @return \Illuminate\Console\Scheduling\Event
     */
    public function exec(string $command, array $parameters = [])
    {
        if (count($parameters)) {
            $command .= ' ' . $this->compile_parameters($parameters);
        }
        $this->events[] = $event = new Event($this->event_mutex, $command, $this->timezone);
        $this->merge_pending_attributes($event);
        return $event;
    }
    /**
     * Create new schedule group.
     *
     *
     * @throws \RuntimeException
     */
    public function group(Closure $events): void
    {
        if ($this->attributes === null) {
            throw new RuntimeException('Invoke an attribute method such as Schedule::daily() before defining a schedule group.');
        }
        $this->group_stack[] = $this->attributes;
        $this->attributes = null;
        $events($this);
        array_pop($this->group_stack);
    }
    /**
     * Merge the current group attributes with the given event.
     *
     * @return void
     */
    protected function merge_pending_attributes(Event $event)
    {
        if (!empty($this->group_stack)) {
            $group = array_last($this->group_stack);
            $group->merge_attributes($event);
        }
        if (isset($this->attributes)) {
            $this->attributes->merge_attributes($event);
            $this->attributes = null;
        }
    }
    /**
     * Compile parameters for a command.
     */
    protected function compile_parameters(array $parameters): string
    {
        return (new Collection($parameters))->map(function ($value, $key) {
            if (is_array($value)) {
                return $this->compile_array_input($key, $value);
            }
            if (!is_numeric($value) && !preg_match('/^(-.$|--.*)/i', $value)) {
                $value = Process_Utils::escape_argument($value);
            }
            return is_numeric($key) ? $value : "{$key}={$value}";
        })->implode(' ');
    }
    /**
     * Compile array input for a command.
     *
     * @param  string|int  $key
     * @param  array  $value
     */
    public function compile_array_input($key, $value): string
    {
        $value = (new Collection($value))->map(fn($value) => Process_Utils::escape_argument($value));
        if (str_starts_with((string) $key, '--')) {
            $value = $value->map(fn($value): string => "{$key}={$value}");
        } elseif (str_starts_with((string) $key, '-')) {
            $value = $value->map(fn($value): string => "{$key} {$value}");
        }
        return $value->implode(' ');
    }
    /**
     * Determine if the server is allowed to run this event.
     *
     * @return bool
     */
    public function server_should_run(Event $event, DateTimeInterface $time)
    {
        return $this->mutex_cache[$event->mutex_name()] ??= $this->scheduling_mutex->create($event, $time);
    }
    /**
     * Get all of the events on the schedule that are due.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return \Illuminate\Support\Collection
     */
    public function due_events($app): bool
    {
        return (new Collection($this->events))->filter->is_due($app);
    }
    /**
     * Get all of the events on the schedule.
     *
     * @return \Illuminate\Console\Scheduling\Event[]
     */
    public function events()
    {
        return $this->events;
    }
    /**
     * Specify the cache store that should be used to store mutexes.
     *
     * @param  \UnitEnum|string  $store
     * @return $this
     */
    public function use_cache($store): static
    {
        $store = enum_value($store);
        if ($this->event_mutex instanceof Cache_Aware) {
            $this->event_mutex->use_store($store);
        }
        if ($this->scheduling_mutex instanceof Cache_Aware) {
            $this->scheduling_mutex->use_store($store);
        }
        return $this;
    }
    /**
     * Get the job dispatcher, if available.
     *
     * @return \Illuminate\Contracts\Bus\Dispatcher
     *
     * @throws \RuntimeException
     */
    protected function get_dispatcher()
    {
        if ($this->dispatcher === null) {
            try {
                $this->dispatcher = Container::get_instance()->make(Dispatcher::class);
            } catch (Binding_Resolution_Exception $e) {
                throw new RuntimeException('Unable to resolve the dispatcher from the service container. Please bind it or install the illuminate/bus package.', is_int($e->get_code()) ? $e->get_code() : 0, $e);
            }
        }
        return $this->dispatcher;
    }
    /**
     * Dynamically handle calls into the schedule instance.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (static::has_macro($method)) {
            return $this->macro_call($method, $parameters);
        }
        if (method_exists(Pending_Event_Attributes::class, $method) || Event::has_macro($method)) {
            $this->attributes ??= $this->group_stack ? clone array_last($this->group_stack) : new Pending_Event_Attributes($this);
            return $this->attributes->{$method}(...$parameters);
        }
        throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $method));
    }
}