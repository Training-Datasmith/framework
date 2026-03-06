<?php

namespace Illuminate\Broadcasting;

use Illuminate\Contracts\Events\Dispatcher;

use function Illuminate\Support\enum_value;

class PendingBroadcast
{
    /**
     * Create a new pending broadcast instance.
     *
     * @param  mixed  $event
     */
    public function __construct(
        /**
         * The event dispatcher implementation.
         */
        protected \Illuminate\Contracts\Events\Dispatcher $events,
        /**
         * The event instance.
         */
        protected $event
    )
    {
    }

    /**
     * Broadcast the event using a specific broadcaster.
     *
     * @param  \UnitEnum|string|null  $connection
     * @return $this
     */
    public function via($connection = null): static
    {
        if (method_exists($this->event, 'broadcastVia')) {
            $this->event->broadcastVia(enum_value($connection));
        }

        return $this;
    }

    /**
     * Broadcast the event to everyone except the current user.
     *
     * @return $this
     */
    public function toOthers(): static
    {
        if (method_exists($this->event, 'dontBroadcastToCurrentUser')) {
            $this->event->dontBroadcastToCurrentUser();
        }

        return $this;
    }

    /**
     * Handle the object's destruction.
     */
    public function __destruct()
    {
        $this->events->dispatch($this->event);
    }
}
