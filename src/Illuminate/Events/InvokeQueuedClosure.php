<?php

declare (strict_types=1);
namespace Illuminate\Events;

use Illuminate\Support\Collection;
class Invoke_Queued_Closure
{
    /**
     * Handle the event.
     *
     * @param  \Laravel\SerializableClosure\SerializableClosure  $closure
     */
    public function handle($closure, array $arguments): void
    {
        call_user_func($closure->get_closure(), ...$arguments);
    }
    /**
     * Handle a job failure.
     *
     * @param  \Laravel\SerializableClosure\SerializableClosure  $closure
     * @param  \Throwable  $exception
     */
    public function failed($closure, array $arguments, array $catch_callbacks, $exception): void
    {
        $arguments[] = $exception;
        (new Collection($catch_callbacks))->each->__invoke(...$arguments);
    }
}