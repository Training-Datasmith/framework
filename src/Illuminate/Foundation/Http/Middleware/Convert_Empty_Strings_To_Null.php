<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Http\Middleware;

use Closure;
class Convert_Empty_Strings_To_Null extends Transforms_Request
{
    /**
     * All of the registered skip callbacks.
     *
     * @var array
     */
    protected static $skip_callbacks = [];
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        foreach (static::$skip_callbacks as $callback) {
            if ($callback($request)) {
                return $next($request);
            }
        }
        return parent::handle($request, $next);
    }
    /**
     * Transform the given value.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function transform($key, $value)
    {
        return $value === '' ? null : $value;
    }
    /**
     * Register a callback that instructs the middleware to be skipped.
     */
    public static function skip_when(Closure $callback): void
    {
        static::$skip_callbacks[] = $callback;
    }
    /**
     * Flush the middleware's global state.
     */
    public static function flush_state(): void
    {
        static::$skip_callbacks = [];
    }
}