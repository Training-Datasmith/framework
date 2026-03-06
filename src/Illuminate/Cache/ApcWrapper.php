<?php

declare(strict_types=1);

namespace Illuminate\Cache;

class ApcWrapper
{
    /**
     * Get an item from the cache.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        $fetchedValue = apcu_fetch($key, $success);

        return $success ? $fetchedValue : null;
    }

    /**
     * Store an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     */
    public function put($key, $value, $seconds): bool
    {
        return apcu_store($key, $value, $seconds);
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  int  $value
     * @return int|false
     */
    public function increment($key, $value): int|false
    {
        return apcu_inc($key, $value);
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  int  $value
     * @return int|false
     */
    public function decrement($key, $value): int|false
    {
        return apcu_dec($key, $value);
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     */
    public function delete($key): bool
    {
        return apcu_delete($key);
    }

    /**
     * Remove all items from the cache.
     */
    public function flush(): bool
    {
        return apcu_clear_cache();
    }
}
