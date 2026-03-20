<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting;

use Illuminate\Contracts\Broadcasting\Has_Broadcast_Channel;
class Private_Channel extends Channel
{
    /**
     * Create a new channel instance.
     *
     * @param  \Illuminate\Contracts\Broadcasting\HasBroadcastChannel|string  $name
     */
    public function __construct($name)
    {
        $name = $name instanceof Has_Broadcast_Channel ? $name->broadcast_channel() : $name;
        parent::__construct('private-' . $name);
    }
}