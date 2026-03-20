<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Illuminate\Contracts\Cache\Lock_Provider;
class Null_Store extends Taggable_Store implements Lock_Provider
{
    use Retrieves_Multiple_Keys;
    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     */
    public function get($key): void
    {
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
        return false;
    }
    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return false
     */
    public function increment($key, $value = 1): bool
    {
        return false;
    }
    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return false
     */
    public function decrement($key, $value = 1): bool
    {
        return false;
    }
    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    public function forever($key, $value): bool
    {
        return false;
    }
    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function lock($name, $seconds = 0, $owner = null): \Illuminate\Cache\No_Lock
    {
        return new No_Lock($name, $seconds, $owner);
    }
    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function restore_lock($name, $owner): \Illuminate\Cache\No_Lock
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
        return true;
    }
    /**
     * Remove all items from the cache.
     */
    public function flush(): bool
    {
        return true;
    }
    /**
     * Get the cache key prefix.
     */
    public function get_prefix(): string
    {
        return '';
    }
}