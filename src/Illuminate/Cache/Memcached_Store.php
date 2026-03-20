<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Illuminate\Contracts\Cache\Lock_Provider;
use Illuminate\Support\Interacts_With_Time;
use Memcached;
use ReflectionMethod;
class Memcached_Store extends Taggable_Store implements Lock_Provider
{
    use Interacts_With_Time;
    /**
     * A string that should be prepended to keys.
     *
     * @var string
     */
    protected $prefix;
    /**
     * Indicates whether we are using Memcached version >= 3.0.0.
     */
    protected bool $on_version_three;
    /**
     * Create a new Memcached store.
     *
     * @param  \Memcached  $memcached
     * @param  string  $prefix
     */
    public function __construct(
        /**
         * The Memcached instance.
         */
        protected $memcached,
        $prefix = ''
    )
    {
        $this->set_prefix($prefix);
        $this->on_version_three = (new ReflectionMethod('Memcached', 'getMulti'))->get_number_of_parameters() == 2;
    }
    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        $value = $this->memcached->get($this->prefix . $key);
        if ($this->memcached->get_result_code() == 0) {
            return $value;
        }
    }
    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     */
    public function many(array $keys): array
    {
        $prefixed_keys = array_map(fn($key): string => $this->prefix . $key, $keys);
        if ($this->on_version_three) {
            $values = $this->memcached->get_multi($prefixed_keys, Memcached::GET_PRESERVE_ORDER);
        } else {
            $null = null;
            $values = $this->memcached->get_multi($prefixed_keys, $null, Memcached::GET_PRESERVE_ORDER);
        }
        if ($this->memcached->get_result_code() != 0) {
            return array_fill_keys($keys, null);
        }
        return array_combine($keys, $values);
    }
    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     */
    public function put($key, $value, $seconds): bool
    {
        return $this->memcached->set($this->prefix . $key, $value, $this->calculate_expiration($seconds));
    }
    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param  int  $seconds
     */
    public function put_many(array $values, $seconds): bool
    {
        $prefixed_values = [];
        foreach ($values as $key => $value) {
            $prefixed_values[$this->prefix . $key] = $value;
        }
        return $this->memcached->set_multi($prefixed_values, $this->calculate_expiration($seconds));
    }
    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param  mixed  $value
     * @param  int  $seconds
     */
    public function add(string $key, $value, $seconds): bool
    {
        return $this->memcached->add($this->prefix . $key, $value, $this->calculate_expiration($seconds));
    }
    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|false
     */
    public function increment($key, $value = 1): int|false
    {
        return $this->memcached->increment($this->prefix . $key, $value);
    }
    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|false
     */
    public function decrement($key, $value = 1): int|false
    {
        return $this->memcached->decrement($this->prefix . $key, $value);
    }
    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    public function forever($key, $value): bool
    {
        return $this->put($key, $value, 0);
    }
    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function lock($name, $seconds = 0, $owner = null): \Illuminate\Cache\Memcached_Lock
    {
        return new Memcached_Lock($this->memcached, $this->prefix . $name, $seconds, $owner);
    }
    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function restore_lock($name, $owner): \Illuminate\Cache\Memcached_Lock
    {
        return $this->lock($name, 0, $owner);
    }
    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     */
    public function forget($key): bool
    {
        return $this->memcached->delete($this->prefix . $key);
    }
    /**
     * Remove all items from the cache.
     */
    public function flush(): bool
    {
        return $this->memcached->flush();
    }
    /**
     * Get the expiration time of the key.
     *
     * @param  int  $seconds
     * @return int
     */
    protected function calculate_expiration($seconds)
    {
        return $this->to_timestamp($seconds);
    }
    /**
     * Get the UNIX timestamp for the given number of seconds.
     *
     * @param  int  $seconds
     * @return int
     */
    protected function to_timestamp($seconds)
    {
        return $seconds > 0 ? $this->available_at($seconds) : 0;
    }
    /**
     * Get the underlying Memcached connection.
     *
     * @return \Memcached
     */
    public function get_memcached()
    {
        return $this->memcached;
    }
    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function get_prefix()
    {
        return $this->prefix;
    }
    /**
     * Set the cache key prefix.
     *
     * @param  string  $prefix
     */
    public function set_prefix($prefix): void
    {
        $this->prefix = $prefix;
    }
}