<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use Illuminate\Support\Arr;
trait Broadcasts_Events
{
    /**
     * Boot the event broadcasting trait.
     */
    public static function boot_broadcasts_events(): void
    {
        static::created(function ($model): void {
            $model->broadcast_created();
        });
        static::updated(function ($model): void {
            $model->broadcast_updated();
        });
        if (method_exists(static::class, 'bootSoftDeletes')) {
            static::soft_deleted(function ($model): void {
                $model->broadcast_trashed();
            });
            static::restored(function ($model): void {
                $model->broadcast_restored();
            });
        }
        static::deleted(function ($model): void {
            $model->broadcast_deleted();
        });
    }
    /**
     * Broadcast that the model was created.
     *
     * @param  \Illuminate\Broadcasting\Channel|\Illuminate\Contracts\Broadcasting\HasBroadcastChannel|array|null  $channels
     * @return \Illuminate\Broadcasting\PendingBroadcast
     */
    public function broadcast_created($channels = null)
    {
        return $this->broadcast_if_broadcast_channels_exist_for_event($this->new_broadcastable_model_event('created'), 'created', $channels);
    }
    /**
     * Broadcast that the model was updated.
     *
     * @param  \Illuminate\Broadcasting\Channel|\Illuminate\Contracts\Broadcasting\HasBroadcastChannel|array|null  $channels
     * @return \Illuminate\Broadcasting\PendingBroadcast
     */
    public function broadcast_updated($channels = null)
    {
        return $this->broadcast_if_broadcast_channels_exist_for_event($this->new_broadcastable_model_event('updated'), 'updated', $channels);
    }
    /**
     * Broadcast that the model was trashed.
     *
     * @param  \Illuminate\Broadcasting\Channel|\Illuminate\Contracts\Broadcasting\HasBroadcastChannel|array|null  $channels
     * @return \Illuminate\Broadcasting\PendingBroadcast
     */
    public function broadcast_trashed($channels = null)
    {
        return $this->broadcast_if_broadcast_channels_exist_for_event($this->new_broadcastable_model_event('trashed'), 'trashed', $channels);
    }
    /**
     * Broadcast that the model was restored.
     *
     * @param  \Illuminate\Broadcasting\Channel|\Illuminate\Contracts\Broadcasting\HasBroadcastChannel|array|null  $channels
     * @return \Illuminate\Broadcasting\PendingBroadcast
     */
    public function broadcast_restored($channels = null)
    {
        return $this->broadcast_if_broadcast_channels_exist_for_event($this->new_broadcastable_model_event('restored'), 'restored', $channels);
    }
    /**
     * Broadcast that the model was deleted.
     *
     * @param  \Illuminate\Broadcasting\Channel|\Illuminate\Contracts\Broadcasting\HasBroadcastChannel|array|null  $channels
     * @return \Illuminate\Broadcasting\PendingBroadcast
     */
    public function broadcast_deleted($channels = null)
    {
        return $this->broadcast_if_broadcast_channels_exist_for_event($this->new_broadcastable_model_event('deleted'), 'deleted', $channels);
    }
    /**
     * Broadcast the given event instance if channels are configured for the model event.
     *
     * @param  mixed  $instance
     * @param  string  $event
     * @param  mixed  $channels
     * @return \Illuminate\Broadcasting\PendingBroadcast|null
     */
    protected function broadcast_if_broadcast_channels_exist_for_event($instance, $event, $channels = null)
    {
        if (!static::$is_broadcasting) {
            return;
        }
        if (!empty($this->broadcast_on($event)) || !empty($channels)) {
            return broadcast($instance->on_channels(Arr::wrap($channels)));
        }
    }
    /**
     * Create a new broadcastable model event event.
     *
     * @param  string  $event
     * @return mixed
     */
    public function new_broadcastable_model_event($event)
    {
        return tap($this->new_broadcastable_event($event), function ($event): void {
            $event->connection = property_exists($this, 'broadcastConnection') ? $this->broadcast_connection : $this->broadcast_connection();
            $event->queue = property_exists($this, 'broadcastQueue') ? $this->broadcast_queue : $this->broadcast_queue();
            $event->after_commit = property_exists($this, 'broadcastAfterCommit') ? $this->broadcast_after_commit : $this->broadcast_after_commit();
        });
    }
    /**
     * Create a new broadcastable model event for the model.
     */
    protected function new_broadcastable_event(string $event): \Illuminate\Database\Eloquent\Broadcastable_Model_Event_Occurred
    {
        return new Broadcastable_Model_Event_Occurred($this, $event);
    }
    /**
     * Get the channels that model events should broadcast on.
     *
     * @param  string  $event
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcast_on($event): array
    {
        return [$this];
    }
    /**
     * Get the queue connection that should be used to broadcast model events.
     */
    public function broadcast_connection(): void
    {
    }
    /**
     * Get the queue that should be used to broadcast model events.
     */
    public function broadcast_queue(): void
    {
    }
    /**
     * Determine if the model event broadcast queued job should be dispatched after all transactions are committed.
     */
    public function broadcast_after_commit(): bool
    {
        return false;
    }
}