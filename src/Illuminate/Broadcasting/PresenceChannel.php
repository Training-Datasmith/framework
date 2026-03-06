<?php

declare(strict_types=1);

namespace Illuminate\Broadcasting;

class PresenceChannel extends Channel
{
    /**
     * Create a new channel instance.
     *
     * @param  string  $name
     */
    public function __construct($name)
    {
        parent::__construct('presence-'.$name);
    }
}
