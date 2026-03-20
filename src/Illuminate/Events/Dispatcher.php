<?php

declare (strict_types=1);
namespace Illuminate\Events;

use Closure;
use Exception;
use Illuminate\Bus\Unique_Lock;
use Illuminate\Container\Container;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Contracts\Broadcasting\Should_Broadcast;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Events\Should_Dispatch_After_Commit;
use Illuminate\Contracts\Events\Should_Handle_Events_After_Commit;
use Illuminate\Contracts\Queue\Should_Be_Encrypted;
use Illuminate\Contracts\Queue\Should_Be_Unique;
use Illuminate\Contracts\Queue\Should_Be_Unique_Until_Processing;
use Illuminate\Contracts\Queue\Should_Queue;
use Illuminate\Contracts\Queue\Should_Queue_After_Commit;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Traits\Reflects_Closures;
use ReflectionClass;
class Dispatcher implements Dispatcher_Contract
{
    use Macroable;
    use Reflects_Closures;
    /**
     * The IoC container instance.
     */
    protected \Illuminate\Contracts\Container\Container $container;
    /**
     * The registered event listeners.
     *
     * @var array<string, callable|array|class-string|null>
     */
    protected $listeners = [];
    /**
     * The wildcard listeners.
     *
     * @var array<string, \Closure|string>
     */
    protected $wildcards = [];
    /**
     * The cached wildcard listeners.
     *
     * @var array<string, \Closure|string>
     */
    protected $wildcards_cache = [];
    /**
     * The queue resolver instance.
     *
     * @var callable(): \Illuminate\Contracts\Queue\Queue
     */
    protected $queue_resolver;
    /**
     * The database transaction manager resolver instance.
     *
     * @var callable
     */
    protected $transaction_manager_resolver;
    /**
     * The currently deferred events.
     *
     * @var array
     */
    protected $deferred_events = [];
    /**
     * Indicates if events should be deferred.
     *
     * @var bool
     */
    protected $deferring_events = false;
    /**
     * The specific events to defer (null means defer all events).
     *
     * @var string[]|null
     */
    protected $events_to_defer;
    /**
     * Create a new event dispatcher instance.
     */
    public function __construct(?Container_Contract $container = null)
    {
        $this->container = $container ?: new Container();
    }
    /**
     * Register an event listener with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string|string  $events
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string|null  $listener
     * @return void
     */
    public function listen($events, $listener = null)
    {
        if ($events instanceof Closure) {
            return (new Collection($this->first_closure_parameter_types($events)))->each(function ($event) use ($events): void {
                $this->listen($event, $events);
            });
        }
        if ($events instanceof Queued_Closure) {
            return (new Collection($this->first_closure_parameter_types($events->closure)))->each(function ($event) use ($events): void {
                $this->listen($event, $events->resolve());
            });
        }
        if ($listener instanceof Queued_Closure) {
            $listener = $listener->resolve();
        }
        foreach ((array) $events as $event) {
            if (str_contains((string) $event, '*')) {
                $this->setup_wildcard_listen($event, $listener);
            } else {
                $this->listeners[$event][] = $listener;
            }
        }
    }
    /**
     * Setup a wildcard listener callback.
     *
     * @param  \Closure|string  $listener
     * @return void
     */
    protected function setup_wildcard_listen(string $event, $listener)
    {
        $this->wildcards[$event][] = $listener;
        $this->wildcards_cache = [];
    }
    /**
     * Determine if a given event has listeners.
     *
     * @param  string  $eventName
     */
    public function has_listeners($event_name): bool
    {
        return isset($this->listeners[$event_name]) || isset($this->wildcards[$event_name]) || $this->has_wildcard_listeners($event_name);
    }
    /**
     * Determine if the given event has any wildcard listeners.
     *
     * @param  string  $eventName
     */
    public function has_wildcard_listeners($event_name): bool
    {
        foreach ($this->wildcards as $key => $listeners) {
            if (Str::is($key, $event_name)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Register an event and payload to be fired later.
     *
     * @param  string  $event
     * @param  object|array  $payload
     */
    public function push($event, $payload = []): void
    {
        $this->listen($event . '_pushed', function () use ($event, $payload): void {
            $this->dispatch($event, $payload);
        });
    }
    /**
     * Flush a set of pushed events.
     *
     * @param  string  $event
     */
    public function flush($event): void
    {
        $this->dispatch($event . '_pushed');
    }
    /**
     * Register an event subscriber with the dispatcher.
     *
     * @param  object|string  $subscriber
     */
    public function subscribe($subscriber): void
    {
        $subscriber = $this->resolve_subscriber($subscriber);
        $events = $subscriber->subscribe($this);
        if (is_array($events)) {
            foreach ($events as $event => $listeners) {
                foreach (Arr::wrap($listeners) as $listener) {
                    if (is_string($listener) && method_exists($subscriber, $listener)) {
                        $this->listen($event, [$subscriber::class, $listener]);
                        continue;
                    }
                    $this->listen($event, $listener);
                }
            }
        }
    }
    /**
     * Resolve the subscriber instance.
     *
     * @param  object|class-string  $subscriber
     * @return ($subscriber is object ? object : mixed)
     */
    protected function resolve_subscriber($subscriber)
    {
        if (is_string($subscriber)) {
            return $this->container->make($subscriber);
        }
        return $subscriber;
    }
    /**
     * Fire an event until the first non-null response is returned.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @return array|null
     */
    public function until($event, $payload = [])
    {
        return $this->dispatch($event, $payload, true);
    }
    /**
     * Fire an event and call the listeners.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     * @return array|null
     */
    public function dispatch($event, $payload = [], $halt = false)
    {
        // When the given "event" is actually an object, we will assume it is an event
        // object, and use the class as the event name and this event itself as the
        // payload to the handler, which makes object-based events quite simple.
        [$is_event_object, $parsed_event, $parsed_payload] = [is_object($event), ...$this->parse_event_and_payload($event, $payload)];
        if ($this->should_defer_event($parsed_event)) {
            $this->deferred_events[] = func_get_args();
            return null;
        }
        // If the event is not intended to be dispatched unless the current database
        // transaction is successful, we'll register a callback which will handle
        // dispatching this event on the next successful DB transaction commit.
        if ($is_event_object && $parsed_payload[0] instanceof Should_Dispatch_After_Commit && !is_null($transactions = $this->resolve_transaction_manager())) {
            $transactions->add_callback(fn() => $this->invoke_listeners($parsed_event, $parsed_payload, $halt));
            return null;
        }
        return $this->invoke_listeners($parsed_event, $parsed_payload, $halt);
    }
    /**
     * Broadcast an event and call its listeners.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     * @return array|null
     */
    protected function invoke_listeners($event, $payload, $halt = false)
    {
        if ($this->should_broadcast($payload)) {
            $this->broadcast_event($payload[0]);
        }
        $responses = [];
        foreach ($this->get_listeners($event) as $listener) {
            $response = $listener($event, $payload);
            // If a response is returned from the listener and event halting is enabled
            // we will just return this response, and not call the rest of the event
            // listeners. Otherwise we will add the response on the response list.
            if ($halt && !is_null($response)) {
                return $response;
            }
            // If a boolean false is returned from a listener, we will stop propagating
            // the event to any further listeners down in the chain, else we keep on
            // looping through the listeners and firing every one in our sequence.
            if ($response === false) {
                break;
            }
            $responses[] = $response;
        }
        return $halt ? null : $responses;
    }
    /**
     * Parse the given event and payload and prepare them for dispatching.
     *
     * @param  mixed  $event
     * @param  mixed  $payload
     * @return array{string, array}
     */
    protected function parse_event_and_payload($event, $payload): array
    {
        if (is_object($event)) {
            [$payload, $event] = [[$event], $event::class];
        }
        return [$event, Arr::wrap($payload)];
    }
    /**
     * Determine if the payload has a broadcastable event.
     */
    protected function should_broadcast(array $payload): bool
    {
        return isset($payload[0]) && $payload[0] instanceof Should_Broadcast && $this->broadcast_when($payload[0]);
    }
    /**
     * Check if the event should be broadcasted by the condition.
     *
     * @param  mixed  $event
     * @return bool
     */
    protected function broadcast_when($event)
    {
        return method_exists($event, 'broadcastWhen') ? $event->broadcast_when() : true;
    }
    /**
     * Broadcast the given event class.
     *
     * @param  \Illuminate\Contracts\Broadcasting\ShouldBroadcast  $event
     * @return void
     */
    protected function broadcast_event($event)
    {
        $this->container->make(Broadcast_Factory::class)->queue($event);
    }
    /**
     * Get all of the listeners for a given event name.
     *
     * @param  string  $eventName
     * @return array
     */
    public function get_listeners($event_name)
    {
        $listeners = array_merge($this->prepare_listeners($event_name), $this->wildcards_cache[$event_name] ?? $this->get_wildcard_listeners($event_name));
        return class_exists($event_name, false) ? $this->add_interface_listeners($event_name, $listeners) : $listeners;
    }
    /**
     * Get the wildcard listeners for the event.
     *
     * @param  string  $eventName
     * @return array
     */
    protected function get_wildcard_listeners($event_name)
    {
        $wildcards = [];
        foreach ($this->wildcards as $key => $listeners) {
            if (Str::is($key, $event_name)) {
                foreach ($listeners as $listener) {
                    $wildcards[] = $this->make_listener($listener, true);
                }
            }
        }
        return $this->wildcards_cache[$event_name] = $wildcards;
    }
    /**
     * Add the listeners for the event's interfaces to the given array.
     *
     * @param  string  $eventName
     * @return array
     */
    protected function add_interface_listeners($event_name, array $listeners = [])
    {
        foreach (class_implements($event_name) as $interface) {
            if (isset($this->listeners[$interface])) {
                foreach ($this->prepare_listeners($interface) as $names) {
                    $listeners = array_merge($listeners, (array) $names);
                }
            }
        }
        return $listeners;
    }
    /**
     * Prepare the listeners for a given event.
     *
     * @return \Closure[]
     */
    protected function prepare_listeners(string $event_name): array
    {
        $listeners = [];
        foreach ($this->listeners[$event_name] ?? [] as $listener) {
            $listeners[] = $this->make_listener($listener);
        }
        return $listeners;
    }
    /**
     * Register an event listener with the dispatcher.
     *
     * @param  \Closure|string|array{class-string, string}  $listener
     * @param  bool  $wildcard
     * @return \Closure
     */
    public function make_listener($listener, $wildcard = false)
    {
        if (is_string($listener)) {
            return $this->create_class_listener($listener, $wildcard);
        }
        if (is_array($listener) && isset($listener[0]) && is_string($listener[0])) {
            return $this->create_class_listener($listener, $wildcard);
        }
        return function ($event, $payload) use ($listener, $wildcard) {
            if ($wildcard) {
                return $listener($event, $payload);
            }
            return $listener(...array_values($payload));
        };
    }
    /**
     * Create a class based listener using the IoC container.
     *
     * @param  string  $listener
     * @param  bool  $wildcard
     * @return \Closure
     */
    public function create_class_listener($listener, $wildcard = false)
    {
        return function ($event, $payload) use ($listener, $wildcard) {
            if ($wildcard) {
                return call_user_func($this->create_class_callable($listener), $event, $payload);
            }
            $callable = $this->create_class_callable($listener);
            return $callable(...array_values($payload));
        };
    }
    /**
     * Create the class based event callable.
     *
     * @param  array{class-string, string}|string  $listener
     * @return callable
     */
    protected function create_class_callable($listener)
    {
        [$class, $method] = is_array($listener) ? $listener : $this->parse_class_callable($listener);
        if (!method_exists($class, $method)) {
            $method = '__invoke';
        }
        if ($this->handler_should_be_queued($class)) {
            return $this->create_queued_handler_callable($class, $method);
        }
        $listener = $this->container->make($class);
        return $this->handler_should_be_dispatched_after_database_transactions($listener) ? $this->create_callback_for_listener_running_after_commits($listener, $method) : [$listener, $method];
    }
    /**
     * Parse the class listener into class and method.
     *
     * @param  string  $listener
     * @return array{class-string, string}
     */
    protected function parse_class_callable($listener): array
    {
        return Str::parse_callback($listener, 'handle');
    }
    /**
     * Determine if the event handler class should be queued.
     *
     * @param  class-string  $class
     *
     * @phpstan-assert-if-true class-string<\Illuminate\Contracts\Queue\ShouldQueue> $class
     */
    protected function handler_should_be_queued($class): bool
    {
        try {
            return (new ReflectionClass($class))->implements_interface(Should_Queue::class);
        } catch (Exception) {
            return false;
        }
    }
    /**
     * Create a callable for putting an event handler on the queue.
     *
     * @param  class-string  $class
     * @param  string  $method
     * @return \Closure(): void
     */
    protected function create_queued_handler_callable($class, $method)
    {
        return function () use ($class, $method): void {
            $arguments = array_map(fn($a): mixed => is_object($a) ? clone $a : $a, func_get_args());
            if ($this->handler_wants_to_be_queued($class, $arguments)) {
                $this->queue_handler($class, $method, $arguments);
            }
        };
    }
    /**
     * Determine if the given event handler should be dispatched after all database transactions have committed.
     *
     * @param  mixed  $listener
     */
    protected function handler_should_be_dispatched_after_database_transactions($listener): bool
    {
        return (($listener->after_commit ?? null) || $listener instanceof Should_Handle_Events_After_Commit) && $this->resolve_transaction_manager();
    }
    /**
     * Create a callable for dispatching a listener after database transactions.
     *
     * @param  mixed  $listener
     * @param  string  $method
     * @return \Closure
     */
    protected function create_callback_for_listener_running_after_commits($listener, $method)
    {
        return function () use ($method, $listener): void {
            $payload = func_get_args();
            $this->resolve_transaction_manager()->add_callback(function () use ($listener, $method, $payload): void {
                $listener->{$method}(...$payload);
            });
        };
    }
    /**
     * Determine if the event handler wants to be queued.
     *
     * @param  class-string  $class
     * @return bool
     */
    protected function handler_wants_to_be_queued($class, array $arguments)
    {
        $instance = $this->container->make($class);
        if (method_exists($instance, 'shouldQueue')) {
            return $instance->should_queue($arguments[0]);
        }
        return true;
    }
    /**
     * Queue the handler class.
     *
     * @param  string  $class
     * @param  string  $method
     * @return void
     */
    protected function queue_handler($class, $method, array $arguments)
    {
        [$listener, $job] = $this->create_listener_and_job($class, $method, $arguments);
        if ($job->should_be_unique && !(new Unique_Lock($this->container->make(Cache::class)))->acquire($job)) {
            return;
        }
        $connection = $this->resolve_queue()->connection(method_exists($listener, 'viaConnection') ? isset($arguments[0]) ? $listener->via_connection($arguments[0]) : $listener->via_connection() : $listener->connection ?? null);
        $queue = method_exists($listener, 'viaQueue') ? isset($arguments[0]) ? $listener->via_queue($arguments[0]) : $listener->via_queue() : $listener->queue ?? null;
        $delay = method_exists($listener, 'withDelay') ? isset($arguments[0]) ? $listener->with_delay($arguments[0]) : $listener->with_delay() : $listener->delay ?? null;
        is_null($delay) ? $connection->push_on(enum_value($queue), $job) : $connection->later_on(enum_value($queue), $delay, $job);
    }
    /**
     * Create the listener and job for a queued listener.
     *
     * @template TListener
     *
     * @param  class-string<TListener>  $class
     * @param  string  $method
     * @param  array  $arguments
     * @return array{TListener, mixed}
     */
    protected function create_listener_and_job($class, $method, $arguments): array
    {
        $listener = (new ReflectionClass($class))->new_instance_without_constructor();
        return [$listener, $this->propagate_listener_options($listener, new Call_Queued_Listener($class, $method, $arguments))];
    }
    /**
     * Propagate listener options to the job.
     *
     * @param  mixed  $listener
     * @param  \Illuminate\Events\CallQueuedListener  $job
     * @return \Illuminate\Events\CallQueuedListener
     */
    protected function propagate_listener_options($listener, $job)
    {
        return tap($job, function ($job) use ($listener): void {
            $data = array_values($job->data);
            if ($listener instanceof Should_Queue_After_Commit) {
                $job->after_commit = true;
            } else {
                $job->after_commit = property_exists($listener, 'afterCommit') ? $listener->after_commit : null;
            }
            $job->backoff = method_exists($listener, 'backoff') ? $listener->backoff(...$data) : $listener->backoff ?? null;
            $job->max_exceptions = $listener->max_exceptions ?? null;
            $job->retry_until = method_exists($listener, 'retryUntil') ? $listener->retry_until(...$data) : null;
            $job->should_be_encrypted = $listener instanceof Should_Be_Encrypted;
            $job->timeout = $listener->timeout ?? null;
            $job->fail_on_timeout = $listener->fail_on_timeout ?? false;
            $job->tries = method_exists($listener, 'tries') ? $listener->tries(...$data) : $listener->tries ?? null;
            $job->message_group = method_exists($listener, 'messageGroup') ? $listener->message_group(...$data) : $listener->message_group ?? null;
            $job->with_deduplicator(method_exists($listener, 'deduplicator') ? $listener->deduplicator(...$data) : (method_exists($listener, 'deduplicationId') ? $listener->deduplication_id(...) : null));
            $job->through(array_merge(method_exists($listener, 'middleware') ? $listener->middleware(...$data) : [], $listener->middleware ?? []));
            $job->should_be_unique = $listener instanceof Should_Be_Unique;
            $job->should_be_unique_until_processing = $listener instanceof Should_Be_Unique_Until_Processing;
            if ($job->should_be_unique) {
                $job->unique_id = method_exists($listener, 'uniqueId') ? $listener->unique_id(...$data) : $listener->unique_id ?? null;
                $job->unique_for = method_exists($listener, 'uniqueFor') ? $listener->unique_for(...$data) : $listener->unique_for ?? 0;
            }
        });
    }
    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param  string  $event
     */
    public function forget($event): void
    {
        if (str_contains($event, '*')) {
            unset($this->wildcards[$event]);
        } else {
            unset($this->listeners[$event]);
        }
        foreach ($this->wildcards_cache as $key => $listeners) {
            if (Str::is($event, $key)) {
                unset($this->wildcards_cache[$key]);
            }
        }
    }
    /**
     * Forget all of the pushed listeners.
     */
    public function forget_pushed(): void
    {
        foreach ($this->listeners as $key => $value) {
            if (str_ends_with($key, '_pushed')) {
                $this->forget($key);
            }
        }
    }
    /**
     * Get the queue implementation from the resolver.
     */
    protected function resolve_queue(): \Illuminate\Contracts\Queue\Queue
    {
        return call_user_func($this->queue_resolver);
    }
    /**
     * Set the queue resolver implementation.
     *
     * @param  callable(): \Illuminate\Contracts\Queue\Queue  $resolver
     * @return $this
     */
    public function set_queue_resolver(callable $resolver): static
    {
        $this->queue_resolver = $resolver;
        return $this;
    }
    /**
     * Get the database transaction manager implementation from the resolver.
     *
     * @return \Illuminate\Database\DatabaseTransactionsManager|null
     */
    protected function resolve_transaction_manager(): mixed
    {
        return call_user_func($this->transaction_manager_resolver);
    }
    /**
     * Set the database transaction manager resolver implementation.
     *
     * @param  (callable(): (\Illuminate\Database\DatabaseTransactionsManager|null))  $resolver
     * @return $this
     */
    public function set_transaction_manager_resolver(callable $resolver): static
    {
        $this->transaction_manager_resolver = $resolver;
        return $this;
    }
    /**
     * Execute the given callback while deferring events, then dispatch all deferred events.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @param  string[]|null  $events
     * @return TResult
     */
    public function defer(callable $callback, ?array $events = null)
    {
        $was_deferring = $this->deferring_events;
        $previous_deferred_events = $this->deferred_events;
        $previous_events_to_defer = $this->events_to_defer;
        $this->deferring_events = true;
        $this->deferred_events = [];
        $this->events_to_defer = $events;
        try {
            $result = $callback();
            $this->deferring_events = false;
            foreach ($this->deferred_events as $args) {
                $this->dispatch(...$args);
            }
            return $result;
        } finally {
            $this->deferring_events = $was_deferring;
            $this->deferred_events = $previous_deferred_events;
            $this->events_to_defer = $previous_events_to_defer;
        }
    }
    /**
     * Determine if the given event should be deferred.
     */
    protected function should_defer_event(string $event): bool
    {
        return $this->deferring_events && ($this->events_to_defer === null || in_array($event, $this->events_to_defer));
    }
    /**
     * Gets the raw, unprepared listeners.
     *
     * @return array
     */
    public function get_raw_listeners()
    {
        return $this->listeners;
    }
}