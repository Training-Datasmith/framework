<?php

namespace Illuminate\Events;

use Illuminate\Support\Collection;

class InvokeQueuedClosure
{
    /**
     * Handle the event.
     *
     * @param  \Laravel\SerializableClosure\SerializableClosure  $closure
     */
    public function handle($closure, array $arguments): void
    {
        call_user_func($closure->getClosure(), ...$arguments);
    }

    /**
     * Handle a job failure.
     *
     * @param  \Laravel\SerializableClosure\SerializableClosure  $closure
     * @param  \Throwable  $exception
     */
    public function failed($closure, array $arguments, array $catchCallbacks, $exception): void
    {
        $arguments[] = $exception;

        (new Collection($catchCallbacks))->each->__invoke(...$arguments);
    }
}
