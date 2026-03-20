<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Illuminate\Redis\Connections\Php_Redis_Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Lazy_Collection;
class Redis_Tag_Set extends Tag_Set
{
    /**
     * Add a reference entry to the tag set's underlying sorted set.
     *
     * @param  string|null  $updateWhen
     */
    public function add_entry(string $key, ?int $ttl = null, $update_when = null): void
    {
        $ttl = is_null($ttl) ? -1 : Carbon::now()->add_seconds($ttl)->get_timestamp();
        foreach ($this->tag_ids() as $tag_key) {
            if ($update_when) {
                $this->store->connection()->zadd($this->store->get_prefix() . $tag_key, $update_when, $ttl, $key);
            } else {
                $this->store->connection()->zadd($this->store->get_prefix() . $tag_key, $ttl, $key);
            }
        }
    }
    /**
     * Get all of the cache entry keys for the tag set.
     */
    public function entries(): \Illuminate\Support\Lazy_Collection
    {
        $connection = $this->store->connection();
        $default_cursor_value = match (true) {
            $connection instanceof Php_Redis_Connection && version_compare(phpversion('redis'), '6.1.0', '>=') => null,
            default => '0',
        };
        return new Lazy_Collection(function () use ($connection, $default_cursor_value) {
            foreach ($this->tag_ids() as $tag_key) {
                $cursor = $default_cursor_value;
                do {
                    $results = $connection->zscan($this->store->get_prefix() . $tag_key, $cursor, ['match' => '*', 'count' => 1000]);
                    if (!is_array($results)) {
                        break;
                    }
                    [$cursor, $entries] = $results;
                    if (!is_array($entries)) {
                        break;
                    }
                    $entries = array_unique(array_keys($entries));
                    if (count($entries) === 0) {
                        continue;
                    }
                    foreach ($entries as $entry) {
                        yield $entry;
                    }
                } while ((string) $cursor !== $default_cursor_value);
            }
        });
    }
    /**
     * Remove the stale entries from the tag set.
     */
    public function flush_stale_entries(): void
    {
        $flush_stale_entries = function ($pipe): void {
            foreach ($this->tag_ids() as $tag_key) {
                $pipe->zremrangebyscore($this->store->get_prefix() . $tag_key, 0, Carbon::now()->get_timestamp());
            }
        };
        $connection = $this->store->connection();
        if ($connection instanceof Php_Redis_Connection) {
            $flush_stale_entries($connection);
        } else {
            $connection->pipeline($flush_stale_entries);
        }
    }
    /**
     * Flush the tag from the cache.
     *
     * @param  string  $name
     */
    public function flush_tag($name): void
    {
        return $this->reset_tag($name);
    }
    /**
     * Reset the tag and return the new tag identifier.
     *
     * @param  string  $name
     * @return string
     */
    public function reset_tag($name): string|array
    {
        $this->store->forget($this->tag_key($name));
        return $this->tag_id($name);
    }
    /**
     * Get the unique tag identifier for a given tag.
     *
     * @param  string  $name
     */
    public function tag_id($name): string
    {
        return "tag:{$name}:entries";
    }
    /**
     * Get the tag identifier key for a given tag.
     *
     * @param  string  $name
     */
    public function tag_key($name): string
    {
        return "tag:{$name}:entries";
    }
}