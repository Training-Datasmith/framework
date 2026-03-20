<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Attributes\Observed_By;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Null_Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use ReflectionClass;
trait Has_Events
{
    /**
     * The event map for the model.
     *
     * Allows for object-based events for native Eloquent events.
     *
     * @var array<string, class-string>
     */
    protected $dispatches_events = [];
    /**
     * User exposed observable events.
     *
     * These are extra user-defined events observers may subscribe to.
     *
     * @var string[]
     */
    protected $observables = [];
    /**
     * Boot the has event trait for a model.
     */
    public static function boot_has_events(): void
    {
        static::when_booted(fn() => static::observe(static::resolve_observe_attributes()));
    }
    /**
     * Resolve the observe class names from the attributes.
     *
     * @return array
     */
    public static function resolve_observe_attributes()
    {
        $reflection_class = new ReflectionClass(static::class);
        $is_eloquent_grandchild = is_subclass_of(static::class, Model::class) && get_parent_class(static::class) !== Model::class;
        return (new Collection($reflection_class->get_attributes(Observed_By::class)))->map(fn($attribute): array => $attribute->get_arguments())->flatten()->when($is_eloquent_grandchild, fn(Collection $attributes): \Illuminate\Support\Collection => (new Collection(get_parent_class(static::class)::resolve_observe_attributes()))->merge($attributes))->all();
    }
    /**
     * Register observers with the model.
     *
     * @param  object|string[]|string  $classes
     *
     * @throws \RuntimeException
     */
    public static function observe($classes): void
    {
        $instance = new static();
        foreach (Arr::wrap($classes) as $class) {
            $instance->register_observer($class);
        }
    }
    /**
     * Register a single observer with the model.
     *
     * @param  object|string  $class
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function register_observer($class)
    {
        $class_name = $this->resolve_observer_class_name($class);
        // When registering a model observer, we will spin through the possible events
        // and determine if this observer has that method. If it does, we will hook
        // it into the model's event system, making it convenient to watch these.
        foreach ($this->get_observable_events() as $event) {
            if (method_exists($class, $event)) {
                static::register_model_event($event, $class_name . '@' . $event);
            }
        }
    }
    /**
     * Resolve the observer's class name from an object or string.
     *
     * @param  object|string  $class
     * @return class-string
     *
     * @throws \InvalidArgumentException
     */
    private function resolve_observer_class_name($class): string
    {
        if (is_object($class)) {
            return $class::class;
        }
        if (class_exists($class)) {
            return $class;
        }
        throw new InvalidArgumentException('Unable to find observer: ' . $class);
    }
    /**
     * Get the observable event names.
     *
     * @return string[]
     */
    public function get_observable_events(): array
    {
        return array_merge(['retrieved', 'creating', 'created', 'updating', 'updated', 'saving', 'saved', 'restoring', 'restored', 'replicating', 'trashed', 'deleting', 'deleted', 'forceDeleting', 'forceDeleted'], $this->observables);
    }
    /**
     * Set the observable event names.
     *
     * @param  string[]  $observables
     * @return $this
     */
    public function set_observable_events(array $observables)
    {
        $this->observables = $observables;
        return $this;
    }
    /**
     * Add an observable event name.
     *
     * @param  string|string[]  $observables
     */
    public function add_observable_events($observables): void
    {
        $this->observables = array_unique(array_merge($this->observables, is_array($observables) ? $observables : func_get_args()));
    }
    /**
     * Remove an observable event name.
     *
     * @param  string|string[]  $observables
     */
    public function remove_observable_events($observables): void
    {
        $this->observables = array_diff($this->observables, is_array($observables) ? $observables : func_get_args());
    }
    /**
     * Register a model event with the dispatcher.
     *
     * @param  string  $event
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     * @return void
     */
    protected static function register_model_event($event, $callback)
    {
        if (isset(static::$dispatcher)) {
            $name = static::class;
            static::$dispatcher->listen("eloquent.{$event}: {$name}", $callback);
        }
    }
    /**
     * Fire the given event for the model.
     *
     * @param  string  $event
     * @param  bool  $halt
     * @return mixed
     */
    protected function fire_model_event($event, $halt = true)
    {
        if (!isset(static::$dispatcher)) {
            return true;
        }
        // First, we will get the proper method to call on the event dispatcher, and then we
        // will attempt to fire a custom, object based event for the given event. If that
        // returns a result we can return that result, or we'll call the string events.
        $method = $halt ? 'until' : 'dispatch';
        $result = $this->filter_model_event_results($this->fire_custom_model_event($event, $method));
        if ($result === false) {
            return false;
        }
        return !empty($result) ? $result : static::$dispatcher->{$method}("eloquent.{$event}: " . static::class, $this);
    }
    /**
     * Fire a custom model event for the given event.
     *
     * @param  string  $event
     * @param  'until'|'dispatch'  $method
     * @return array|null|void
     */
    protected function fire_custom_model_event($event, $method)
    {
        if (!isset($this->dispatches_events[$event])) {
            return;
        }
        $result = static::$dispatcher->{$method}(new $this->dispatches_events[$event]($this));
        if (!is_null($result)) {
            return $result;
        }
    }
    /**
     * Filter the model event results.
     *
     * @param  mixed  $result
     * @return mixed
     */
    protected function filter_model_event_results($result)
    {
        if (is_array($result)) {
            return array_filter($result, fn($response): bool => !is_null($response));
        }
        return $result;
    }
    /**
     * Register a retrieved model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function retrieved($callback): void
    {
        static::register_model_event('retrieved', $callback);
    }
    /**
     * Register a saving model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function saving($callback): void
    {
        static::register_model_event('saving', $callback);
    }
    /**
     * Register a saved model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function saved($callback): void
    {
        static::register_model_event('saved', $callback);
    }
    /**
     * Register an updating model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function updating($callback): void
    {
        static::register_model_event('updating', $callback);
    }
    /**
     * Register an updated model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function updated($callback): void
    {
        static::register_model_event('updated', $callback);
    }
    /**
     * Register a creating model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function creating($callback): void
    {
        static::register_model_event('creating', $callback);
    }
    /**
     * Register a created model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function created($callback): void
    {
        static::register_model_event('created', $callback);
    }
    /**
     * Register a replicating model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function replicating($callback): void
    {
        static::register_model_event('replicating', $callback);
    }
    /**
     * Register a deleting model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function deleting($callback): void
    {
        static::register_model_event('deleting', $callback);
    }
    /**
     * Register a deleted model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function deleted($callback): void
    {
        static::register_model_event('deleted', $callback);
    }
    /**
     * Remove all the event listeners for the model.
     */
    public static function flush_event_listeners(): void
    {
        if (!isset(static::$dispatcher)) {
            return;
        }
        $instance = new static();
        foreach ($instance->get_observable_events() as $event) {
            static::$dispatcher->forget("eloquent.{$event}: " . static::class);
        }
        foreach ($instance->dispatches_events as $event) {
            static::$dispatcher->forget($event);
        }
    }
    /**
     * Get the event map for the model.
     *
     * @return array
     */
    public function dispatches_events()
    {
        return $this->dispatches_events;
    }
    /**
     * Get the event dispatcher instance.
     *
     * @return \Illuminate\Contracts\Events\Dispatcher|null
     */
    public static function get_event_dispatcher()
    {
        return static::$dispatcher;
    }
    /**
     * Set the event dispatcher instance.
     */
    public static function set_event_dispatcher(Dispatcher $dispatcher): void
    {
        static::$dispatcher = $dispatcher;
    }
    /**
     * Unset the event dispatcher for models.
     */
    public static function unset_event_dispatcher(): void
    {
        static::$dispatcher = null;
    }
    /**
     * Execute a callback without firing any model events for any model type.
     *
     * @return mixed
     */
    public static function without_events(callable $callback)
    {
        $dispatcher = static::get_event_dispatcher();
        if ($dispatcher) {
            static::set_event_dispatcher(new Null_Dispatcher($dispatcher));
        }
        try {
            return $callback();
        } finally {
            if ($dispatcher) {
                static::set_event_dispatcher($dispatcher);
            }
        }
    }
}