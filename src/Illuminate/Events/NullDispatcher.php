<?php

declare(strict_types=1);

namespace Illuminate\Events;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Support\Traits\ForwardsCalls;

class NullDispatcher implements DispatcherContract
{
    use ForwardsCalls;

    /**
     * Create a new event dispatcher instance that does not fire.
     */
    public function __construct(
        /**
         * The underlying event dispatcher instance.
         */
        protected \Illuminate\Contracts\Events\Dispatcher $dispatcher
    ) {
    }

    /**
     * Don't fire an event.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     */
    public function dispatch($event, $payload = [], $halt = false): void
    {

    }

    /**
     * Don't register an event and payload to be fired later.
     *
     * @param  string  $event
     * @param  array  $payload
     */
    public function push($event, $payload = []): void
    {

    }

    /**
     * Don't dispatch an event.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     */
    public function until($event, $payload = []): void
    {

    }

    /**
     * Register an event listener with the dispatcher.
     *
     * @param  \Closure|string|array  $events
     * @param  \Closure|string|array|null  $listener
     */
    public function listen($events, $listener = null): void
    {
        $this->dispatcher->listen($events, $listener);
    }

    /**
     * Determine if a given event has listeners.
     *
     * @param  string  $eventName
     * @return bool
     */
    public function hasListeners($eventName)
    {
        return $this->dispatcher->hasListeners($eventName);
    }

    /**
     * Register an event subscriber with the dispatcher.
     *
     * @param  object|string  $subscriber
     */
    public function subscribe($subscriber): void
    {
        $this->dispatcher->subscribe($subscriber);
    }

    /**
     * Flush a set of pushed events.
     *
     * @param  string  $event
     */
    public function flush($event): void
    {
        $this->dispatcher->flush($event);
    }

    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param  string  $event
     */
    public function forget($event): void
    {
        $this->dispatcher->forget($event);
    }

    /**
     * Forget all of the queued listeners.
     */
    public function forgetPushed(): void
    {
        $this->dispatcher->forgetPushed();
    }

    /**
     * Dynamically pass method calls to the underlying dispatcher.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->forwardDecoratedCallTo($this->dispatcher, $method, $parameters);
    }
}
