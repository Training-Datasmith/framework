<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use BadMethodCallException;
use Illuminate\Contracts\Cache\Lock_Provider;
use Illuminate\Contracts\Cache\Store;
class Memoized_Store implements Lock_Provider, Store
{
    /**
     * The memoized cache values.
     *
     * @var array<string, mixed>
     */
    protected $cache = [];
    /**
     * Create a new memoized cache instance.
     *
     * @param  string  $name
     * @param  \Illuminate\Cache\Repository  $repository
     */
    public function __construct(protected $name, protected $repository)
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
        $prefixed_key = $this->prefix($key);
        if (array_key_exists($prefixed_key, $this->cache)) {
            return $this->cache[$prefixed_key];
        }
        return $this->cache[$prefixed_key] = $this->repository->get($key);
    }
    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     */
    public function many(array $keys): array
    {
        [$memoized, $retrieved, $missing] = [[], [], []];
        foreach ($keys as $key) {
            $prefixed_key = $this->prefix($key);
            if (array_key_exists($prefixed_key, $this->cache)) {
                $memoized[$key] = $this->cache[$prefixed_key];
            } else {
                $missing[] = $key;
            }
        }
        if (count($missing) > 0) {
            $retrieved = tap($this->repository->many($missing), function ($values): void {
                foreach ($values as $key => $value) {
                    $this->cache[$this->prefix($key)] = $value;
                }
            });
        }
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $memoized)) {
                $result[$key] = $memoized[$key];
            } else {
                $result[$key] = $retrieved[$key];
            }
        }
        return $result;
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
        unset($this->cache[$this->prefix($key)]);
        return $this->repository->put($key, $value, $seconds);
    }
    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param  int  $seconds
     * @return bool
     */
    public function put_many(array $values, $seconds)
    {
        foreach ($values as $key => $value) {
            unset($this->cache[$this->prefix($key)]);
        }
        return $this->repository->put_many($values, $seconds);
    }
    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function increment($key, $value = 1)
    {
        unset($this->cache[$this->prefix($key)]);
        return $this->repository->increment($key, $value);
    }
    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function decrement($key, $value = 1)
    {
        unset($this->cache[$this->prefix($key)]);
        return $this->repository->decrement($key, $value);
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
        unset($this->cache[$this->prefix($key)]);
        return $this->repository->forever($key, $value);
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
        if (!$this->repository->get_store() instanceof Lock_Provider) {
            throw new BadMethodCallException('This cache store does not support locks.');
        }
        return $this->repository->get_store()->lock(...func_get_args());
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
        if (!$this->repository->get_store() instanceof Lock_Provider) {
            throw new BadMethodCallException('This cache store does not support locks.');
        }
        return $this->repository->get_store()->restore_lock(...func_get_args());
    }
    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key)
    {
        unset($this->cache[$this->prefix($key)]);
        return $this->repository->forget($key);
    }
    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        $this->cache = [];
        return $this->repository->flush();
    }
    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function get_prefix()
    {
        return $this->repository->get_prefix();
    }
    /**
     * Prefix the given key.
     */
    protected function prefix(string $key): string
    {
        return $this->get_prefix() . $key;
    }
}