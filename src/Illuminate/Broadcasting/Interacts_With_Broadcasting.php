<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting;

use Illuminate\Support\Arr;
use function Illuminate\Support\enum_value;
trait Interacts_With_Broadcasting
{
    /**
     * The broadcaster connection to use to broadcast the event.
     *
     * @var array
     */
    protected $broadcast_connection = [null];
    /**
     * Broadcast the event using a specific broadcaster.
     *
     * @param  \UnitEnum|array|string|null  $connection
     * @return $this
     */
    public function broadcast_via($connection = null)
    {
        $connection = enum_value($connection);
        $this->broadcast_connection = is_null($connection) ? [null] : Arr::wrap($connection);
        return $this;
    }
    /**
     * Get the broadcaster connections the event should be broadcast on.
     *
     * @return array
     */
    public function broadcast_connections()
    {
        return $this->broadcast_connection;
    }
}