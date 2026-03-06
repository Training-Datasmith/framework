<?php

declare(strict_types=1);

namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\NullDispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use ReflectionClass;

trait HasEvents
{
    /**
     * The event map for the model.
     *
     * Allows for object-based events for native Eloquent events.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [];

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
    public static function bootHasEvents(): void
    {
        static::whenBooted(fn () => static::observe(static::resolveObserveAttributes()));
    }

    /**
     * Resolve the observe class names from the attributes.
     *
     * @return array
     */
    public static function resolveObserveAttributes()
    {
        $reflectionClass = new ReflectionClass(static::class);

        $isEloquentGrandchild = is_subclass_of(static::class, Model::class)
            && get_parent_class(static::class) !== Model::class;

        return (new Collection($reflectionClass->getAttributes(ObservedBy::class)))
            ->map(fn ($attribute): array => $attribute->getArguments())
            ->flatten()
            ->when($isEloquentGrandchild, fn (Collection $attributes): \Illuminate\Support\Collection => (new Collection(get_parent_class(static::class)::resolveObserveAttributes()))
                ->merge($attributes))
            ->all();
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
            $instance->registerObserver($class);
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
    protected function registerObserver($class)
    {
        $className = $this->resolveObserverClassName($class);

        // When registering a model observer, we will spin through the possible events
        // and determine if this observer has that method. If it does, we will hook
        // it into the model's event system, making it convenient to watch these.
        foreach ($this->getObservableEvents() as $event) {
            if (method_exists($class, $event)) {
                static::registerModelEvent($event, $className.'@'.$event);
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
    private function resolveObserverClassName($class): string
    {
        if (is_object($class)) {
            return $class::class;
        }

        if (class_exists($class)) {
            return $class;
        }

        throw new InvalidArgumentException('Unable to find observer: '.$class);
    }

    /**
     * Get the observable event names.
     *
     * @return string[]
     */
    public function getObservableEvents(): array
    {
        return array_merge(
            [
                'retrieved', 'creating', 'created', 'updating', 'updated',
                'saving', 'saved', 'restoring', 'restored', 'replicating',
                'trashed', 'deleting', 'deleted', 'forceDeleting', 'forceDeleted',
            ],
            $this->observables
        );
    }

    /**
     * Set the observable event names.
     *
     * @param  string[]  $observables
     * @return $this
     */
    public function setObservableEvents(array $observables)
    {
        $this->observables = $observables;

        return $this;
    }

    /**
     * Add an observable event name.
     *
     * @param  string|string[]  $observables
     */
    public function addObservableEvents($observables): void
    {
        $this->observables = array_unique(array_merge(
            $this->observables,
            is_array($observables) ? $observables : func_get_args()
        ));
    }

    /**
     * Remove an observable event name.
     *
     * @param  string|string[]  $observables
     */
    public function removeObservableEvents($observables): void
    {
        $this->observables = array_diff(
            $this->observables,
            is_array($observables) ? $observables : func_get_args()
        );
    }

    /**
     * Register a model event with the dispatcher.
     *
     * @param  string  $event
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     * @return void
     */
    protected static function registerModelEvent($event, $callback)
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
    protected function fireModelEvent($event, $halt = true)
    {
        if (! isset(static::$dispatcher)) {
            return true;
        }

        // First, we will get the proper method to call on the event dispatcher, and then we
        // will attempt to fire a custom, object based event for the given event. If that
        // returns a result we can return that result, or we'll call the string events.
        $method = $halt ? 'until' : 'dispatch';

        $result = $this->filterModelEventResults(
            $this->fireCustomModelEvent($event, $method)
        );

        if ($result === false) {
            return false;
        }

        return ! empty($result) ? $result : static::$dispatcher->{$method}(
            "eloquent.{$event}: ".static::class,
            $this
        );
    }

    /**
     * Fire a custom model event for the given event.
     *
     * @param  string  $event
     * @param  'until'|'dispatch'  $method
     * @return array|null|void
     */
    protected function fireCustomModelEvent($event, $method)
    {
        if (! isset($this->dispatchesEvents[$event])) {
            return;
        }

        $result = static::$dispatcher->$method(new $this->dispatchesEvents[$event]($this));

        if (! is_null($result)) {
            return $result;
        }
    }

    /**
     * Filter the model event results.
     *
     * @param  mixed  $result
     * @return mixed
     */
    protected function filterModelEventResults($result)
    {
        if (is_array($result)) {
            return array_filter($result, fn ($response): bool => ! is_null($response));
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
        static::registerModelEvent('retrieved', $callback);
    }

    /**
     * Register a saving model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function saving($callback): void
    {
        static::registerModelEvent('saving', $callback);
    }

    /**
     * Register a saved model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function saved($callback): void
    {
        static::registerModelEvent('saved', $callback);
    }

    /**
     * Register an updating model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function updating($callback): void
    {
        static::registerModelEvent('updating', $callback);
    }

    /**
     * Register an updated model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function updated($callback): void
    {
        static::registerModelEvent('updated', $callback);
    }

    /**
     * Register a creating model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function creating($callback): void
    {
        static::registerModelEvent('creating', $callback);
    }

    /**
     * Register a created model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function created($callback): void
    {
        static::registerModelEvent('created', $callback);
    }

    /**
     * Register a replicating model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function replicating($callback): void
    {
        static::registerModelEvent('replicating', $callback);
    }

    /**
     * Register a deleting model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function deleting($callback): void
    {
        static::registerModelEvent('deleting', $callback);
    }

    /**
     * Register a deleted model event with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|array|class-string  $callback
     */
    public static function deleted($callback): void
    {
        static::registerModelEvent('deleted', $callback);
    }

    /**
     * Remove all the event listeners for the model.
     */
    public static function flushEventListeners(): void
    {
        if (! isset(static::$dispatcher)) {
            return;
        }

        $instance = new static();

        foreach ($instance->getObservableEvents() as $event) {
            static::$dispatcher->forget("eloquent.{$event}: ".static::class);
        }

        foreach ($instance->dispatchesEvents as $event) {
            static::$dispatcher->forget($event);
        }
    }

    /**
     * Get the event map for the model.
     *
     * @return array
     */
    public function dispatchesEvents()
    {
        return $this->dispatchesEvents;
    }

    /**
     * Get the event dispatcher instance.
     *
     * @return \Illuminate\Contracts\Events\Dispatcher|null
     */
    public static function getEventDispatcher()
    {
        return static::$dispatcher;
    }

    /**
     * Set the event dispatcher instance.
     */
    public static function setEventDispatcher(Dispatcher $dispatcher): void
    {
        static::$dispatcher = $dispatcher;
    }

    /**
     * Unset the event dispatcher for models.
     */
    public static function unsetEventDispatcher(): void
    {
        static::$dispatcher = null;
    }

    /**
     * Execute a callback without firing any model events for any model type.
     *
     * @return mixed
     */
    public static function withoutEvents(callable $callback)
    {
        $dispatcher = static::getEventDispatcher();

        if ($dispatcher) {
            static::setEventDispatcher(new NullDispatcher($dispatcher));
        }

        try {
            return $callback();
        } finally {
            if ($dispatcher) {
                static::setEventDispatcher($dispatcher);
            }
        }
    }
}
