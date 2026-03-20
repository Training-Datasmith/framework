<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Illuminate\Cache\Events\Cache_Failed_Over;
use Illuminate\Contracts\Cache\Lock_Provider;
use Illuminate\Contracts\Events\Dispatcher;
use RuntimeException;
use Throwable;
class Failover_Store extends Taggable_Store implements Lock_Provider
{
    /**
     * The caches which failed on the last action.
     *
     * @var list<string>
     */
    protected array $failing_caches = [];
    /**
     * Create a new failover store.
     *
     * @param  array<int, string>  $stores
     */
    public function __construct(protected Cache_Manager $cache, protected Dispatcher $events, protected array $stores)
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
        return $this->attempt_on_all_stores(__FUNCTION__, func_get_args());
    }
    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     *
     * @return array
     */
    public function many(array $keys)
    {
        return $this->attempt_on_all_stores(__FUNCTION__, func_get_args());
    }
    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     * @return bool
     */
    public function put($key, $value, $seconds)
    {
        return $this->attempt_on_all_stores(__FUNCTION__, func_get_args());
    }
    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param  int  $seconds
     * @return bool
     */
    public function put_many(array $values, $seconds)
    {
        return $this->attempt_on_all_stores(__FUNCTION__, func_get_args());
    }
    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     * @return bool
     */
    public function add($key, $value, $seconds)
    {
        return $this->attempt_on_all_stores(__FUNCTION__, func_get_args());
    }
    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|false
     */
    public function increment($key, $value = 1)
    {
        return $this->attempt_on_all_stores(__FUNCTION__, func_get_args());
    }
    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|false
     */
    public function decrement($key, $value = 1)
    {
        return $this->attempt_on_all_stores(__FUNCTION__, func_get_args());
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
        return $this->attempt_on_all_stores(__FUNCTION__, func_get_args());
    }
    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function lock($name, $seconds = 0, $owner = null)
    {
        return $this->attempt_on_all_stores(__FUNCTION__, func_get_args());
    }
    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function restore_lock($name, $owner)
    {
        return $this->attempt_on_all_stores(__FUNCTION__, func_get_args());
    }
    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key)
    {
        return $this->attempt_on_all_stores(__FUNCTION__, func_get_args());
    }
    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        return $this->attempt_on_all_stores(__FUNCTION__, func_get_args());
    }
    /**
     * Remove all expired tag set entries.
     */
    public function flush_stale_tags(): void
    {
        foreach ($this->stores as $store) {
            if ($this->store($store)->get_store() instanceof Redis_Store) {
                $this->store($store)->flush_stale_tags();
                break;
            }
        }
    }
    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function get_prefix()
    {
        return $this->attempt_on_all_stores(__FUNCTION__, func_get_args());
    }
    /**
     * Attempt the given method on all stores.
     *
     * @return mixed
     *
     * @throws \Throwable
     */
    protected function attempt_on_all_stores(string $method, array $arguments)
    {
        [$last_exception, $failed_caches] = [null, []];
        try {
            foreach ($this->stores as $store) {
                try {
                    return $this->store($store)->{$method}(...$arguments);
                } catch (Throwable $e) {
                    $last_exception = $e;
                    $failed_caches[] = $store;
                    if (!in_array($store, $this->failing_caches)) {
                        $this->events->dispatch(new Cache_Failed_Over($store, $e));
                    }
                }
            }
        } finally {
            $this->failing_caches = $failed_caches;
        }
        throw $last_exception ?? new RuntimeException('All failover cache stores failed.');
    }
    /**
     * Get the cache store for the given store name.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    protected function store(string $store)
    {
        return $this->cache->store($store);
    }
}