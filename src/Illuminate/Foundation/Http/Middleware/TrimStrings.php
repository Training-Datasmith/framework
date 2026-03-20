<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Http\Middleware;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
class Trim_Strings extends Transforms_Request
{
    /**
     * The attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = ['current_password', 'password', 'password_confirmation'];
    /**
     * The globally ignored attributes that should not be trimmed.
     *
     * @var array
     */
    protected static $never_trim = [];
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
        $except = array_merge($this->except, static::$never_trim);
        if ($this->should_skip($key, $except) || !is_string($value)) {
            return $value;
        }
        return Str::trim($value);
    }
    /**
     * Determine if the given key should be skipped.
     *
     * @param  string  $key
     * @param  array  $except
     */
    protected function should_skip($key, $except): bool
    {
        return Str::is($except, $key);
    }
    /**
     * Indicate that the given attributes should never be trimmed.
     *
     * @param  array|string  $attributes
     */
    public static function except($attributes): void
    {
        static::$never_trim = array_values(array_unique(array_merge(static::$never_trim, Arr::wrap($attributes))));
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
        static::$never_trim = [];
        static::$skip_callbacks = [];
    }
}