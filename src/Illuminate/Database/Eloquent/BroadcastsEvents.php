<?php

declare(strict_types=1);

namespace Illuminate\Database\Eloquent;

use Illuminate\Support\Arr;

trait BroadcastsEvents
{
    /**
     * Boot the event broadcasting trait.
     */
    public static function bootBroadcastsEvents(): void
    {
        static::created(function ($model): void {
            $model->broadcastCreated();
        });

        static::updated(function ($model): void {
            $model->broadcastUpdated();
        });

        if (method_exists(static::class, 'bootSoftDeletes')) {
            static::softDeleted(function ($model): void {
                $model->broadcastTrashed();
            });

            static::restored(function ($model): void {
                $model->broadcastRestored();
            });
        }

        static::deleted(function ($model): void {
            $model->broadcastDeleted();
        });
    }

    /**
     * Broadcast that the model was created.
     *
     * @param  \Illuminate\Broadcasting\Channel|\Illuminate\Contracts\Broadcasting\HasBroadcastChannel|array|null  $channels
     * @return \Illuminate\Broadcasting\PendingBroadcast
     */
    public function broadcastCreated($channels = null)
    {
        return $this->broadcastIfBroadcastChannelsExistForEvent(
            $this->newBroadcastableModelEvent('created'),
            'created',
            $channels
        );
    }

    /**
     * Broadcast that the model was updated.
     *
     * @param  \Illuminate\Broadcasting\Channel|\Illuminate\Contracts\Broadcasting\HasBroadcastChannel|array|null  $channels
     * @return \Illuminate\Broadcasting\PendingBroadcast
     */
    public function broadcastUpdated($channels = null)
    {
        return $this->broadcastIfBroadcastChannelsExistForEvent(
            $this->newBroadcastableModelEvent('updated'),
            'updated',
            $channels
        );
    }

    /**
     * Broadcast that the model was trashed.
     *
     * @param  \Illuminate\Broadcasting\Channel|\Illuminate\Contracts\Broadcasting\HasBroadcastChannel|array|null  $channels
     * @return \Illuminate\Broadcasting\PendingBroadcast
     */
    public function broadcastTrashed($channels = null)
    {
        return $this->broadcastIfBroadcastChannelsExistForEvent(
            $this->newBroadcastableModelEvent('trashed'),
            'trashed',
            $channels
        );
    }

    /**
     * Broadcast that the model was restored.
     *
     * @param  \Illuminate\Broadcasting\Channel|\Illuminate\Contracts\Broadcasting\HasBroadcastChannel|array|null  $channels
     * @return \Illuminate\Broadcasting\PendingBroadcast
     */
    public function broadcastRestored($channels = null)
    {
        return $this->broadcastIfBroadcastChannelsExistForEvent(
            $this->newBroadcastableModelEvent('restored'),
            'restored',
            $channels
        );
    }

    /**
     * Broadcast that the model was deleted.
     *
     * @param  \Illuminate\Broadcasting\Channel|\Illuminate\Contracts\Broadcasting\HasBroadcastChannel|array|null  $channels
     * @return \Illuminate\Broadcasting\PendingBroadcast
     */
    public function broadcastDeleted($channels = null)
    {
        return $this->broadcastIfBroadcastChannelsExistForEvent(
            $this->newBroadcastableModelEvent('deleted'),
            'deleted',
            $channels
        );
    }

    /**
     * Broadcast the given event instance if channels are configured for the model event.
     *
     * @param  mixed  $instance
     * @param  string  $event
     * @param  mixed  $channels
     * @return \Illuminate\Broadcasting\PendingBroadcast|null
     */
    protected function broadcastIfBroadcastChannelsExistForEvent($instance, $event, $channels = null)
    {
        if (! static::$isBroadcasting) {
            return;
        }

        if (! empty($this->broadcastOn($event)) || ! empty($channels)) {
            return broadcast($instance->onChannels(Arr::wrap($channels)));
        }
    }

    /**
     * Create a new broadcastable model event event.
     *
     * @param  string  $event
     * @return mixed
     */
    public function newBroadcastableModelEvent($event)
    {
        return tap($this->newBroadcastableEvent($event), function ($event): void {
            $event->connection = property_exists($this, 'broadcastConnection')
                ? $this->broadcastConnection
                : $this->broadcastConnection();

            $event->queue = property_exists($this, 'broadcastQueue')
                ? $this->broadcastQueue
                : $this->broadcastQueue();

            $event->afterCommit = property_exists($this, 'broadcastAfterCommit')
                ? $this->broadcastAfterCommit
                : $this->broadcastAfterCommit();
        });
    }

    /**
     * Create a new broadcastable model event for the model.
     */
    protected function newBroadcastableEvent(string $event): \Illuminate\Database\Eloquent\BroadcastableModelEventOccurred
    {
        return new BroadcastableModelEventOccurred($this, $event);
    }

    /**
     * Get the channels that model events should broadcast on.
     *
     * @param  string  $event
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn($event): array
    {
        return [$this];
    }

    /**
     * Get the queue connection that should be used to broadcast model events.
     */
    public function broadcastConnection(): void
    {

    }

    /**
     * Get the queue that should be used to broadcast model events.
     */
    public function broadcastQueue(): void
    {

    }

    /**
     * Determine if the model event broadcast queued job should be dispatched after all transactions are committed.
     */
    public function broadcastAfterCommit(): bool
    {
        return false;
    }
}
