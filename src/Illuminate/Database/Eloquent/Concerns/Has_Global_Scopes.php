<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Attributes\Scoped_By;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Reflection_Attribute;
use ReflectionClass;
trait Has_Global_Scopes
{
    /**
     * Boot the has global scopes trait for a model.
     */
    public static function boot_has_global_scopes(): void
    {
        static::add_global_scopes(static::resolve_global_scope_attributes());
    }
    /**
     * Resolve the global scope class names from the attributes.
     *
     * @return array
     */
    public static function resolve_global_scope_attributes()
    {
        $reflection_class = new ReflectionClass(static::class);
        $attributes = new Collection($reflection_class->get_attributes(Scoped_By::class, Reflection_Attribute::IS_INSTANCEOF));
        foreach ($reflection_class->get_traits() as $trait) {
            $attributes->push(...$trait->get_attributes(Scoped_By::class, Reflection_Attribute::IS_INSTANCEOF));
        }
        return $attributes->map(fn($attribute): array => $attribute->get_arguments())->flatten()->all();
    }
    /**
     * Register a new global scope on the model.
     *
     * @param  \Illuminate\Database\Eloquent\Scope|(\Closure(\Illuminate\Database\Eloquent\Builder<static>): mixed)|string  $scope
     * @param  \Illuminate\Database\Eloquent\Scope|(\Closure(\Illuminate\Database\Eloquent\Builder<static>): mixed)|null  $implementation
     *
     * @throws \InvalidArgumentException
     */
    public static function add_global_scope($scope, $implementation = null): \Closure|\Illuminate\Database\Eloquent\Scope
    {
        if (is_string($scope) && ($implementation instanceof Closure || $implementation instanceof Scope)) {
            return static::$global_scopes[static::class][$scope] = $implementation;
        }
        if ($scope instanceof Closure) {
            return static::$global_scopes[static::class][spl_object_hash($scope)] = $scope;
        }
        if ($scope instanceof Scope) {
            return static::$global_scopes[static::class][$scope::class] = $scope;
        }
        if (is_string($scope) && class_exists($scope) && is_subclass_of($scope, Scope::class)) {
            return static::$global_scopes[static::class][$scope] = new $scope();
        }
        throw new InvalidArgumentException('Global scope must be an instance of Closure or Scope or be a class name of a class extending ' . Scope::class);
    }
    /**
     * Register multiple global scopes on the model.
     */
    public static function add_global_scopes(array $scopes): void
    {
        foreach ($scopes as $key => $scope) {
            if (is_string($key)) {
                static::add_global_scope($key, $scope);
            } else {
                static::add_global_scope($scope);
            }
        }
    }
    /**
     * Determine if a model has a global scope.
     *
     * @param  \Illuminate\Database\Eloquent\Scope|string  $scope
     */
    public static function has_global_scope($scope): bool
    {
        return !is_null(static::get_global_scope($scope));
    }
    /**
     * Get a global scope registered with the model.
     *
     * @param  \Illuminate\Database\Eloquent\Scope|string  $scope
     * @return \Illuminate\Database\Eloquent\Scope|(\Closure(\Illuminate\Database\Eloquent\Builder<static>): mixed)|null
     */
    public static function get_global_scope($scope)
    {
        if (is_string($scope)) {
            return Arr::get(static::$global_scopes, static::class . '.' . $scope);
        }
        return Arr::get(static::$global_scopes, static::class . '.' . $scope::class);
    }
    /**
     * Get all of the global scopes that are currently registered.
     *
     * @return array
     */
    public static function get_all_global_scopes()
    {
        return static::$global_scopes;
    }
    /**
     * Set the current global scopes.
     *
     * @param  array  $scopes
     */
    public static function set_all_global_scopes($scopes): void
    {
        static::$global_scopes = $scopes;
    }
    /**
     * Get the global scopes for this class instance.
     *
     * @return array
     */
    public function get_global_scopes()
    {
        return Arr::get(static::$global_scopes, static::class, []);
    }
}