<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Onceable;
use WeakMap;
trait Prevents_Circular_Recursion
{
    /**
     * The cache of objects processed to prevent infinite recursion.
     *
     * @var WeakMap<static, array<string, mixed>>
     */
    protected static $recursion_cache;
    /**
     * Prevent a method from being called multiple times on the same object within the same call stack.
     *
     * @param  mixed  $default
     * @return mixed
     */
    protected function without_recursion(callable $callback, $default = null)
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        $onceable = Onceable::try_from_trace($trace, $callback);
        if (is_null($onceable)) {
            return call_user_func($callback);
        }
        $stack = static::get_recursive_call_stack($this);
        if (array_key_exists($onceable->hash, $stack)) {
            return is_callable($stack[$onceable->hash]) ? static::set_recursive_call_value($this, $onceable->hash, call_user_func($stack[$onceable->hash])) : $stack[$onceable->hash];
        }
        try {
            static::set_recursive_call_value($this, $onceable->hash, $default);
            return call_user_func($onceable->callable);
        } finally {
            static::clear_recursive_call_value($this, $onceable->hash);
        }
    }
    /**
     * Remove an entry from the recursion cache for an object.
     *
     * @param  object  $object
     */
    protected static function clear_recursive_call_value($object, string $hash)
    {
        if ($stack = Arr::except(static::get_recursive_call_stack($object), $hash)) {
            static::get_recursion_cache()->offsetSet($object, $stack);
        } elseif (static::get_recursion_cache()->offsetExists($object)) {
            static::get_recursion_cache()->offsetUnset($object);
        }
    }
    /**
     * Get the stack of methods being called recursively for the current object.
     *
     * @param  object  $object
     */
    protected static function get_recursive_call_stack($object): array
    {
        return static::get_recursion_cache()->offsetExists($object) ? static::get_recursion_cache()->offsetGet($object) : [];
    }
    /**
     * Get the current recursion cache being used by the model.
     *
     * @return WeakMap
     */
    protected static function get_recursion_cache()
    {
        return static::$recursion_cache ??= new WeakMap();
    }
    /**
     * Set a value in the recursion cache for the given object and method.
     *
     * @param  object  $object
     * @param  mixed  $value
     * @return mixed
     */
    protected static function set_recursive_call_value($object, string $hash, $value)
    {
        static::get_recursion_cache()->offsetSet($object, tap(static::get_recursive_call_stack($object), fn(&$stack) => $stack[$hash] = $value));
        return static::get_recursive_call_stack($object)[$hash];
    }
}