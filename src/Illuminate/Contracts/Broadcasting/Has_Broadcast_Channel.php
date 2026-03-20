<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Broadcasting;

interface Has_Broadcast_Channel
{
    /**
     * Get the broadcast channel route definition that is associated with the given entity.
     *
     * @return string
     */
    public function broadcast_channel_route();
    /**
     * Get the broadcast channel name that is associated with the given entity.
     *
     * @return string
     */
    public function broadcast_channel();
}