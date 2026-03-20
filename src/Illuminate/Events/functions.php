<?php

declare (strict_types=1);
namespace Illuminate\Events;

use Closure;
if (!function_exists('Illuminate\Events\queueable')) {
    /**
     * Create a new queued Closure event listener.
     */
    function queueable(Closure $closure): Queued_Closure
    {
        return new Queued_Closure($closure);
    }
}