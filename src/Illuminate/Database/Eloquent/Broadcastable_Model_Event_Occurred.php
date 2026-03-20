<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent;

use Illuminate\Broadcasting\Interacts_With_Sockets;
use Illuminate\Broadcasting\Private_Channel;
use Illuminate\Contracts\Broadcasting\Should_Broadcast;
use Illuminate\Queue\Serializes_Models;
use Illuminate\Support\Collection as BaseCollection;
class Broadcastable_Model_Event_Occurred implements Should_Broadcast
{
    use Interacts_With_Sockets;
    use Serializes_Models;
    /**
     * The channels that the event should be broadcast on.
     *
     * @var array
     */
    protected $channels = [];
    /**
     * The queue connection that should be used to queue the broadcast job.
     *
     * @var string
     */
    public $connection;
    /**
     * The queue that should be used to queue the broadcast job.
     *
     * @var string
     */
    public $queue;
    /**
     * Indicates whether the job should be dispatched after all database transactions have committed.
     *
     * @var bool|null
     */
    public $after_commit;
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $event
     */
    public function __construct(
        /**
         * The model instance corresponding to the event.
         */
        public $model,
        /**
         * The event name (created, updated, etc.).
         */
        protected $event
    )
    {
    }
    /**
     * The channels the event should broadcast on.
     *
     * @return array
     */
    public function broadcast_on()
    {
        $channels = empty($this->channels) ? $this->model->broadcast_on($this->event) ?: [] : $this->channels;
        return (new Base_Collection($channels))->map(fn($channel): mixed => $channel instanceof Model ? new Private_Channel($channel) : $channel)->all();
    }
    /**
     * The name the event should broadcast as.
     *
     * @return string
     */
    public function broadcast_as()
    {
        $default = class_basename($this->model) . ucfirst($this->event);
        return method_exists($this->model, 'broadcastAs') ? $this->model->broadcast_as($this->event) ?: $default : $default;
    }
    /**
     * Get the data that should be sent with the broadcasted event.
     *
     * @return array|null
     */
    public function broadcast_with()
    {
        return method_exists($this->model, 'broadcastWith') ? $this->model->broadcast_with($this->event) : null;
    }
    /**
     * Manually specify the channels the event should broadcast on.
     *
     * @return $this
     */
    public function on_channels(array $channels): static
    {
        $this->channels = $channels;
        return $this;
    }
    /**
     * Determine if the event should be broadcast synchronously.
     */
    public function should_broadcast_now(): bool
    {
        return $this->event === 'deleted' && !method_exists($this->model, 'bootSoftDeletes');
    }
    /**
     * Get the event name.
     *
     * @return string
     */
    public function event()
    {
        return $this->event;
    }
}