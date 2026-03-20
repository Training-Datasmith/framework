<?php

declare (strict_types=1);
namespace Illuminate\Auth\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use function Illuminate\Support\enum_value;
class Authorize
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        /**
         * The gate instance.
         */
        protected \Illuminate\Contracts\Auth\Access\Gate $gate
    )
    {
    }
    /**
     * Specify the ability and models for the middleware.
     *
     * @param  \UnitEnum|string  $ability
     * @param  string  ...$models
     */
    public static function using($ability, ...$models): string
    {
        return static::class . ':' . implode(',', [enum_value($ability), ...$models]);
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $ability
     * @param  array|null  ...$models
     * @return mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function handle($request, Closure $next, $ability, ...$models)
    {
        $this->gate->authorize($ability, $this->get_gate_arguments($request, $models));
        return $next($request);
    }
    /**
     * Get the arguments parameter for the gate.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array|null  $models
     * @return array
     */
    protected function get_gate_arguments($request, $models)
    {
        if (is_null($models)) {
            return [];
        }
        return (new Collection($models))->map(fn($model) => $model instanceof Model ? $model : $this->get_model($request, $model))->all();
    }
    /**
     * Get the model to authorize.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $model
     * @return \Illuminate\Database\Eloquent\Model|string
     */
    protected function get_model($request, $model)
    {
        if ($this->is_class_name($model)) {
            return trim($model);
        }
        return $request->route($model) ?? (preg_match("/^['\"](.*)['\"]\$/", trim($model), $matches) ? $matches[1] : null);
    }
    /**
     * Checks if the given string looks like a fully-qualified class name.
     *
     * @param  string  $value
     */
    protected function is_class_name($value): bool
    {
        return str_contains($value, '\\');
    }
}