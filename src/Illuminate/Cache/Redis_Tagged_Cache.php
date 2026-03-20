<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Illuminate\Cache\Events\Cache_Flushed;
use Illuminate\Cache\Events\Cache_Flushing;
use Illuminate\Redis\Connections\Php_Redis_Cluster_Connection;
use Illuminate\Redis\Connections\Php_Redis_Connection;
use Illuminate\Redis\Connections\Predis_Cluster_Connection;
use Illuminate\Redis\Connections\Predis_Connection;
class Redis_Tagged_Cache extends Tagged_Cache
{
    /**
     * Store an item in the cache if the key does not exist.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @return bool
     */
    public function add($key, $value, $ttl = null)
    {
        $seconds = null;
        if ($ttl !== null) {
            $seconds = $this->get_seconds($ttl);
            if ($seconds > 0) {
                $this->tags->add_entry($this->item_key($key), $seconds);
            }
        }
        return parent::add($key, $value, $ttl);
    }
    /**
     * Store an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @return bool
     */
    public function put($key, $value, $ttl = null)
    {
        if (is_null($ttl)) {
            return $this->forever($key, $value);
        }
        $seconds = $this->get_seconds($ttl);
        if ($seconds > 0) {
            $this->tags->add_entry($this->item_key($key), $seconds);
        }
        return parent::put($key, $value, $ttl);
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
        $this->tags->add_entry($this->item_key($key), updateWhen: 'NX');
        return parent::increment($key, $value);
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
        $this->tags->add_entry($this->item_key($key), updateWhen: 'NX');
        return parent::decrement($key, $value);
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
        $this->tags->add_entry($this->item_key($key));
        return parent::forever($key, $value);
    }
    /**
     * Remove all items from the cache.
     */
    public function flush(): bool
    {
        $connection = $this->store->connection();
        if ($connection instanceof Predis_Cluster_Connection || $connection instanceof Php_Redis_Cluster_Connection) {
            return $this->flush_clustered_connection();
        }
        $this->event(new Cache_Flushing($this->get_name()));
        $redis_prefix = match (true) {
            $connection instanceof Php_Redis_Connection => $connection->client()->get_option(\Redis::OPT_PREFIX),
            $connection instanceof Predis_Connection => $connection->client()->get_options()->prefix,
        };
        $cache_prefix = $redis_prefix . $this->store->get_prefix();
        $cache_tags = [];
        foreach ($this->tags->get_names() as $name) {
            $cache_tags[] = $cache_prefix . $this->tags->tag_id($name);
        }
        $script = <<<'LUA'
            local prefix = table.remove(ARGV, 1)
        
            for i, key in ipairs(KEYS) do
                redis.call('DEL', key)
        
                for j, arg in ipairs(ARGV) do
                    local zkey = string.gsub(key, prefix, "")
                    redis.call('ZREM', arg, zkey)
                end
            end
        LUA;
        $entries = $this->tags->entries()->map(fn(string $key): string => $this->store->get_prefix() . $key)->chunk(1000);
        foreach ($entries as $keys_to_be_deleted) {
            $connection->eval($script, count($keys_to_be_deleted), ...$keys_to_be_deleted, ...[str_replace('-', '%-', $cache_prefix), ...$cache_tags]);
        }
        $this->event(new Cache_Flushed($this->get_name()));
        return true;
    }
    /**
     * Remove all items from the cache.
     */
    protected function flush_clustered_connection(): bool
    {
        $this->event(new Cache_Flushing($this->get_name()));
        $this->flush_values();
        $this->tags->flush();
        $this->event(new Cache_Flushed($this->get_name()));
        return true;
    }
    /**
     * Flush the individual cache entries for the tags.
     *
     * @return void
     */
    protected function flush_values()
    {
        $entries = $this->tags->entries()->map(fn(string $key): string => $this->store->get_prefix() . $key)->chunk(1000);
        $connection = $this->store->connection();
        foreach ($entries as $cache_keys) {
            if ($connection instanceof Predis_Cluster_Connection) {
                $connection->pipeline(function ($connection) use ($cache_keys): void {
                    foreach ($cache_keys as $cache_key) {
                        $connection->del($cache_key);
                    }
                });
            } else {
                $connection->del(...$cache_keys);
            }
        }
    }
    /**
     * Remove all stale reference entries from the tag set.
     */
    public function flush_stale(): bool
    {
        $this->tags->flush_stale_entries();
        return true;
    }
}