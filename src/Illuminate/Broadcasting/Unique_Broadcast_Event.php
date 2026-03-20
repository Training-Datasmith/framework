<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting;

use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\Should_Be_Unique;
class Unique_Broadcast_Event extends Broadcast_Event implements Should_Be_Unique
{
    /**
     * The unique lock identifier.
     *
     * @var mixed
     */
    public $unique_id;
    /**
     * The number of seconds the unique lock should be maintained.
     *
     * @var int
     */
    public $unique_for;
    /**
     * Create a new event instance.
     *
     * @param  mixed  $event
     */
    public function __construct($event)
    {
        if (method_exists($event, 'uniqueId')) {
            $this->unique_id .= $event->unique_id();
        } elseif (property_exists($event, 'uniqueId')) {
            $this->unique_id .= $event->unique_id;
        }
        if (method_exists($event, 'uniqueFor')) {
            $this->unique_for = $event->unique_for();
        } elseif (property_exists($event, 'uniqueFor')) {
            $this->unique_for = $event->unique_for;
        }
        parent::__construct($event);
    }
    /**
     * Resolve the cache implementation that should manage the event's uniqueness.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    public function unique_via()
    {
        return method_exists($this->event, 'uniqueVia') ? $this->event->unique_via() : Container::get_instance()->make(Repository::class);
    }
}