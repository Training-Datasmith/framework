<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Events;

trait Dispatchable
{
    /**
     * Dispatch the event with the given arguments.
     *
     * @param  mixed  ...$arguments
     * @return mixed
     */
    public static function dispatch(...$arguments)
    {
        return event(new static(...$arguments));
    }

    /**
     * Dispatch the event with the given arguments if the given truth test passes.
     *
     * @param  bool  $boolean
     * @param  mixed  ...$arguments
     * @return mixed
     */
    public static function dispatchIf($boolean, ...$arguments)
    {
        if ($boolean) {
            return event(new static(...$arguments));
        }
    }

    /**
     * Dispatch the event with the given arguments unless the given truth test passes.
     *
     * @param  bool  $boolean
     * @param  mixed  ...$arguments
     * @return mixed
     */
    public static function dispatchUnless($boolean, ...$arguments)
    {
        if (! $boolean) {
            return event(new static(...$arguments));
        }
    }

    /**
     * Broadcast the event with the given arguments.
     *
     * @param  mixed  ...$arguments
     */
    public static function broadcast(...$arguments): \Illuminate\Broadcasting\PendingBroadcast
    {
        return broadcast(new static(...$arguments));
    }
}
