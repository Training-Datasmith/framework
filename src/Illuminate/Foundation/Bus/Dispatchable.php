<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Bus;

use Closure;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Fluent;
trait Dispatchable
{
    /**
     * Dispatch the job with the given arguments.
     *
     * @param  mixed  ...$arguments
     */
    public static function dispatch(...$arguments): \Illuminate\Foundation\Bus\Pending_Dispatch
    {
        return static::new_pending_dispatch(new static(...$arguments));
    }
    /**
     * Dispatch the job with the given arguments if the given truth test passes.
     *
     * @param  bool|\Closure  $boolean
     * @param  mixed  ...$arguments
     */
    public static function dispatch_if($boolean, ...$arguments): \Illuminate\Foundation\Bus\Pending_Dispatch|\Illuminate\Support\Fluent
    {
        if ($boolean instanceof Closure) {
            $dispatchable = new static(...$arguments);
            return value($boolean, $dispatchable) ? static::new_pending_dispatch($dispatchable) : new Fluent();
        }
        return value($boolean) ? static::new_pending_dispatch(new static(...$arguments)) : new Fluent();
    }
    /**
     * Dispatch the job with the given arguments unless the given truth test passes.
     *
     * @param  bool|\Closure  $boolean
     * @param  mixed  ...$arguments
     */
    public static function dispatch_unless($boolean, ...$arguments): \Illuminate\Foundation\Bus\Pending_Dispatch|\Illuminate\Support\Fluent
    {
        if ($boolean instanceof Closure) {
            $dispatchable = new static(...$arguments);
            return !value($boolean, $dispatchable) ? static::new_pending_dispatch($dispatchable) : new Fluent();
        }
        return !value($boolean) ? static::new_pending_dispatch(new static(...$arguments)) : new Fluent();
    }
    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * Queueable jobs will be dispatched to the "sync" queue.
     *
     * @param  mixed  ...$arguments
     * @return mixed
     */
    public static function dispatch_sync(...$arguments)
    {
        return app(Dispatcher::class)->dispatch_sync(new static(...$arguments));
    }
    /**
     * Dispatch a command to its appropriate handler after the current process.
     *
     * @param  mixed  ...$arguments
     */
    public static function dispatch_after_response(...$arguments): \Illuminate\Foundation\Bus\Pending_Dispatch
    {
        return self::dispatch(...$arguments)->after_response();
    }
    /**
     * Set the jobs that should run if this job is successful.
     *
     * @param  array  $chain
     */
    public static function with_chain($chain): \Illuminate\Foundation\Bus\Pending_Chain
    {
        return new Pending_Chain(static::class, $chain);
    }
    /**
     * Create a new pending job dispatch instance.
     *
     * @param  mixed  $job
     */
    protected static function new_pending_dispatch($job): \Illuminate\Foundation\Bus\Pending_Dispatch
    {
        return new Pending_Dispatch($job);
    }
}