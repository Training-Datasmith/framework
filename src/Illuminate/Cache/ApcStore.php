<?php

declare (strict_types=1);
namespace Illuminate\Cache;

class Apc_Store extends Taggable_Store
{
    use Retrieves_Multiple_Keys;
    /**
     * Create a new APC store.
     *
     * @param  string  $prefix
     */
    public function __construct(
        /**
         * The APC wrapper instance.
         */
        protected \Illuminate\Cache\Apc_Wrapper $apc,
        /**
         * A string that should be prepended to keys.
         */
        protected $prefix = ''
    )
    {
    }
    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->apc->get($this->prefix . $key);
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
        return $this->apc->put($this->prefix . $key, $value, $seconds);
    }
    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  int  $value
     * @return int|false
     */
    public function increment($key, $value = 1): int|false
    {
        return $this->apc->increment($this->prefix . $key, $value);
    }
    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  int  $value
     * @return int|false
     */
    public function decrement($key, $value = 1): int|false
    {
        return $this->apc->decrement($this->prefix . $key, $value);
    }
    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return bool
     */
    public function forever($key, $value)
    {
        return $this->put($key, $value, 0);
    }
    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     */
    public function forget($key): bool
    {
        return $this->apc->delete($this->prefix . $key);
    }
    /**
     * Remove all items from the cache.
     */
    public function flush(): bool
    {
        return $this->apc->flush();
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