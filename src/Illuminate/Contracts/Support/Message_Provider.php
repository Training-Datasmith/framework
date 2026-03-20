<?php

declare (strict_types=1);
namespace Illuminate\Contracts\Support;

interface Message_Provider
{
    /**
     * Get the messages for the instance.
     *
     * @return \Illuminate\Contracts\Support\MessageBag
     */
    public function get_message_bag();
}