<?php

declare (strict_types=1);
namespace Illuminate\Database\Events;

use Illuminate\Contracts\Database\Events\Migration_Event;
class No_Pending_Migrations implements Migration_Event
{
    /**
     * Create a new event instance.
     *
     * @param  string  $method  The migration method that was called.
     */
    public function __construct(public $method)
    {
    }
}