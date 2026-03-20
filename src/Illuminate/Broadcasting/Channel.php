<?php

declare (strict_types=1);
namespace Illuminate\Broadcasting;

use Illuminate\Contracts\Broadcasting\Has_Broadcast_Channel;
use Stringable;
class Channel implements Stringable
{
    /**
     * The channel's name.
     *
     * @var string
     */
    public $name;
    /**
     * Create a new channel instance.
     *
     * @param  \Illuminate\Contracts\Broadcasting\HasBroadcastChannel|string  $name
     */
    public function __construct($name)
    {
        $this->name = $name instanceof Has_Broadcast_Channel ? $name->broadcast_channel() : $name;
    }
    /**
     * Convert the channel instance to a string.
     */
    public function __toString(): string
    {
        return $this->name;
    }
}