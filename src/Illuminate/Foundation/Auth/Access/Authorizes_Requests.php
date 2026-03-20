<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Auth\Access;

use Illuminate\Contracts\Auth\Access\Gate;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Str;
trait Authorizes_Requests
{
    /**
     * Authorize a given action for the current user.
     *
     * @param  mixed  $ability
     * @param  mixed  $arguments
     * @return \Illuminate\Auth\Access\Response
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorize($ability, $arguments = [])
    {
        [$ability, $arguments] = $this->parse_ability_and_arguments($ability, $arguments);
        return app(Gate::class)->authorize($ability, $arguments);
    }
    /**
     * Authorize a given action for a user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|mixed  $user
     * @param  mixed  $ability
     * @param  mixed  $arguments
     * @return \Illuminate\Auth\Access\Response
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorize_for_user($user, $ability, $arguments = [])
    {
        [$ability, $arguments] = $this->parse_ability_and_arguments($ability, $arguments);
        return app(Gate::class)->for_user($user)->authorize($ability, $arguments);
    }
    /**
     * Guesses the ability's name if it wasn't provided.
     *
     * @param  mixed  $ability
     * @param  mixed  $arguments
     */
    protected function parse_ability_and_arguments($ability, $arguments): array
    {
        $ability = enum_value($ability);
        if (is_string($ability) && !str_contains($ability, '\\')) {
            return [$ability, $arguments];
        }
        $method = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['function'];
        return [$this->normalize_guessed_ability_name($method), $ability];
    }
    /**
     * Normalize the ability name that has been guessed from the method name.
     *
     * @param  string  $ability
     * @return string
     */
    protected function normalize_guessed_ability_name($ability)
    {
        $map = $this->resource_ability_map();
        return $map[$ability] ?? $ability;
    }
    /**
     * Authorize a resource action based on the incoming request.
     *
     * @param  string|array  $model
     * @param  string|array|null  $parameter
     * @param  \Illuminate\Http\Request|null  $request
     */
    public function authorize_resource($model, $parameter = null, array $options = [], $request = null): void
    {
        $model = is_array($model) ? implode(',', $model) : $model;
        $parameter = is_array($parameter) ? implode(',', $parameter) : $parameter;
        $parameter = $parameter ?: Str::snake(class_basename($model));
        $middleware = [];
        foreach ($this->resource_ability_map() as $method => $ability) {
            $model_name = in_array($method, $this->resource_methods_without_models()) ? $model : $parameter;
            $middleware["can:{$ability},{$model_name}"][] = $method;
        }
        foreach ($middleware as $middleware_name => $methods) {
            $this->middleware($middleware_name, $options)->only($methods);
        }
    }
    /**
     * Get the map of resource methods to ability names.
     *
     * @return array<string, string>
     */
    protected function resource_ability_map(): array
    {
        return ['index' => 'viewAny', 'show' => 'view', 'create' => 'create', 'store' => 'create', 'edit' => 'update', 'update' => 'update', 'destroy' => 'delete'];
    }
    /**
     * Get the list of resource methods which do not have model parameters.
     *
     * @return list<string>
     */
    protected function resource_methods_without_models(): array
    {
        return ['index', 'create', 'store'];
    }
}