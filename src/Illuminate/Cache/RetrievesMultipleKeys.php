<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Illuminate\Support\Collection;
trait Retrieves_Multiple_Keys
{
    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     */
    public function many(array $keys): array
    {
        $return = [];
        $keys = (new Collection($keys))->map_with_keys(fn($value, $key): array => [is_string($key) ? $key : $value => is_string($key) ? $value : null])->all();
        foreach ($keys as $key => $default) {
            /** @phpstan-ignore arguments.count (some clients don't accept a default) */
            $return[$key] = $this->get($key, $default);
        }
        return $return;
    }
    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param  int  $seconds
     * @return bool
     */
    public function put_many(array $values, $seconds)
    {
        $many_result = null;
        foreach ($values as $key => $value) {
            $result = $this->put($key, $value, $seconds);
            $many_result = is_null($many_result) ? $result : $result && $many_result;
        }
        return $many_result ?: false;
    }
}