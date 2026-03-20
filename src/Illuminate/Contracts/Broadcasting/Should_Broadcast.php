<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Broadcasting;

interface Should_Broadcast
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|\Illuminate\Broadcasting\Channel[]|string[]|string
     */
    public function broadcast_on();
}