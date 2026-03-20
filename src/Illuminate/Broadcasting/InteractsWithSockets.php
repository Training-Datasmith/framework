<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting;

use Illuminate\Support\Facades\Broadcast;
trait Interacts_With_Sockets
{
    /**
     * The socket ID for the user that raised the event.
     *
     * @var string|null
     */
    public $socket;
    /**
     * Exclude the current user from receiving the broadcast.
     *
     * @return $this
     */
    public function dont_broadcast_to_current_user()
    {
        $this->socket = Broadcast::socket();
        return $this;
    }
    /**
     * Broadcast the event to everyone.
     *
     * @return $this
     */
    public function broadcast_to_everyone()
    {
        $this->socket = null;
        return $this;
    }
}