<?php

declare (strict_types=1);
namespace Illuminate\Console\Events;

use Illuminate\Console\Scheduling\Event;
class Scheduled_Task_Starting
{
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Console\Scheduling\Event  $task  The scheduled event being run.
     */
    public function __construct(public Event $task)
    {
    }
}