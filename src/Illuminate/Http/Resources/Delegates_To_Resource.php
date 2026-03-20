<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources;

use Exception;
use Illuminate\Support\Traits\Forwards_Calls;
use Illuminate\Support\Traits\Macroable;
trait Delegates_To_Resource
{
    use Forwards_Calls, Macroable {
        __call as macroCall;
    }
    /**
     * Get the value of the resource's route key.
     *
     * @return mixed
     */
    public function get_route_key()
    {
        return $this->resource->get_route_key();
    }
    /**
     * Get the route key for the resource.
     *
     * @return string
     */
    public function get_route_key_name()
    {
        return $this->resource->get_route_key_name();
    }
    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     *
     * @throws \Exception
     */
    public function resolve_route_binding($value, $field = null): never
    {
        throw new Exception('Resources may not be implicitly resolved from route bindings.');
    }
    /**
     * Retrieve the model for a bound value.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     *
     * @throws \Exception
     */
    public function resolve_child_route_binding($child_type, $value, $field = null): never
    {
        throw new Exception('Resources may not be implicitly resolved from child route bindings.');
    }
    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->resource[$offset]);
    }
    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     */
    public function offsetGet($offset): mixed
    {
        return $this->resource[$offset];
    }
    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->resource[$offset] = $value;
    }
    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->resource[$offset]);
    }
    /**
     * Determine if an attribute exists on the resource.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->resource->{$key});
    }
    /**
     * Unset an attribute on the resource.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        unset($this->resource->{$key});
    }
    /**
     * Dynamically get properties from the underlying resource.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->resource->{$key};
    }
    /**
     * Dynamically pass method calls to the underlying resource.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (static::has_macro($method)) {
            return $this->macro_call($method, $parameters);
        }
        return $this->forward_call_to($this->resource, $method, $parameters);
    }
}