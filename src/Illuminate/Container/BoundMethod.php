<?php

declare (strict_types=1);
namespace Illuminate\Container;

use Closure;
use Illuminate\Contracts\Container\Binding_Resolution_Exception;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionMethod;
class Bound_Method
{
    /**
     * Call the given Closure / class@method and inject its dependencies.
     *
     * @param  \Illuminate\Container\Container  $container
     * @param  callable|string  $callback
     * @param  string|null  $defaultMethod
     * @return mixed
     *
     * @throws \ReflectionException
     * @throws \InvalidArgumentException
     */
    public static function call($container, $callback, array $parameters = [], $default_method = null)
    {
        if (is_string($callback) && !$default_method && method_exists($callback, '__invoke')) {
            $default_method = '__invoke';
        }
        if (static::is_callable_with_at_sign($callback) || $default_method) {
            return static::call_class($container, $callback, $parameters, $default_method);
        }
        return static::call_bound_method($container, $callback, fn() => $callback(...array_values(static::get_method_dependencies($container, $callback, $parameters))));
    }
    /**
     * Call a string reference to a class using Class@method syntax.
     *
     * @param  \Illuminate\Container\Container  $container
     * @param  string  $target
     * @param  string|null  $defaultMethod
     * @return mixed
     * @throws \InvalidArgumentException
     */
    protected static function call_class($container, $target, array $parameters = [], $default_method = null)
    {
        $segments = explode('@', $target);
        // We will assume an @ sign is used to delimit the class name from the method
        // name. We will split on this @ sign and then build a callable array that
        // we can pass right back into the "call" method for dependency binding.
        $method = count($segments) === 2 ? $segments[1] : $default_method;
        if (is_null($method)) {
            throw new InvalidArgumentException('Method not provided.');
        }
        return static::call($container, [$container->make($segments[0]), $method], $parameters);
    }
    /**
     * Call a method that has been bound to the container.
     *
     * @param  \Illuminate\Container\Container  $container
     * @param  callable  $callback
     * @param  mixed  $default
     * @return mixed
     */
    protected static function call_bound_method($container, $callback, $default)
    {
        if (!is_array($callback)) {
            return Util::unwrap_if_closure($default);
        }
        // Here we need to turn the array callable into a Class@method string we can use to
        // examine the container and see if there are any method bindings for this given
        // method. If there are, we can call this method binding callback immediately.
        $method = static::normalize_method($callback);
        if ($container->has_method_binding($method)) {
            return $container->call_method_binding($method, $callback[0]);
        }
        return Util::unwrap_if_closure($default);
    }
    /**
     * Normalize the given callback into a Class@method string.
     *
     * @param  callable  $callback
     */
    protected static function normalize_method($callback): string
    {
        $class = is_string($callback[0]) ? $callback[0] : $callback[0]::class;
        return "{$class}@{$callback[1]}";
    }
    /**
     * Get all dependencies for a given method.
     *
     * @param  \Illuminate\Container\Container  $container
     * @param  callable|string  $callback
     *
     * @throws \ReflectionException
     */
    protected static function get_method_dependencies($container, $callback, array $parameters = []): array
    {
        $dependencies = [];
        foreach (static::get_call_reflector($callback)->get_parameters() as $parameter) {
            static::add_dependency_for_call_parameter($container, $parameter, $parameters, $dependencies);
        }
        return array_merge($dependencies, array_values($parameters));
    }
    /**
     * Get the proper reflection instance for the given callback.
     *
     * @param  callable|string  $callback
     * @return \ReflectionFunctionAbstract
     *
     * @throws \ReflectionException
     */
    protected static function get_call_reflector($callback): \ReflectionMethod|\ReflectionFunction
    {
        if (is_string($callback) && str_contains($callback, '::')) {
            $callback = explode('::', $callback);
        } elseif (is_object($callback) && !$callback instanceof Closure) {
            $callback = [$callback, '__invoke'];
        }
        return is_array($callback) ? new ReflectionMethod($callback[0], $callback[1]) : new ReflectionFunction($callback);
    }
    /**
     * Get the dependency for the given call parameter.
     *
     * @param  \Illuminate\Container\Container  $container
     * @param  \ReflectionParameter  $parameter
     * @param  array  $dependencies
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected static function add_dependency_for_call_parameter($container, $parameter, array &$parameters, &$dependencies)
    {
        $pending_dependencies = [];
        if (array_key_exists($param_name = $parameter->get_name(), $parameters)) {
            $pending_dependencies[] = $parameters[$param_name];
            unset($parameters[$param_name]);
        } elseif ($attribute = Util::get_contextual_attribute_from_dependency($parameter)) {
            $pending_dependencies[] = $container->resolve_from_attribute($attribute);
        } elseif (!is_null($class_name = Util::get_parameter_class_name($parameter))) {
            if (array_key_exists($class_name, $parameters)) {
                $pending_dependencies[] = $parameters[$class_name];
                unset($parameters[$class_name]);
            } elseif ($parameter->is_variadic()) {
                $variadic_dependencies = $container->make($class_name);
                $pending_dependencies = array_merge($pending_dependencies, is_array($variadic_dependencies) ? $variadic_dependencies : [$variadic_dependencies]);
            } else {
                $pending_dependencies[] = $container->make($class_name);
            }
        } elseif ($parameter->is_default_value_available()) {
            $pending_dependencies[] = $parameter->get_default_value();
        } elseif (!$parameter->is_optional() && !array_key_exists($param_name, $parameters)) {
            $message = "Unable to resolve dependency [{$parameter}] in class {$parameter->get_declaring_class()->get_name()}";
            throw new Binding_Resolution_Exception($message);
        }
        foreach ($pending_dependencies as $dependency) {
            $container->fire_after_resolving_attribute_callbacks($parameter->get_attributes(), $dependency);
        }
        $dependencies = array_merge($dependencies, $pending_dependencies);
    }
    /**
     * Determine if the given string is in Class@method syntax.
     *
     * @param  mixed  $callback
     */
    protected static function is_callable_with_at_sign($callback): bool
    {
        return is_string($callback) && str_contains($callback, '@');
    }
}