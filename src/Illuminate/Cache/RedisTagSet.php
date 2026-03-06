<?php

declare(strict_types=1);

namespace Illuminate\Cache;

use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

class RedisTagSet extends TagSet
{
    /**
     * Add a reference entry to the tag set's underlying sorted set.
     *
     * @param  string|null  $updateWhen
     */
    public function addEntry(string $key, ?int $ttl = null, $updateWhen = null): void
    {
        $ttl = is_null($ttl) ? -1 : Carbon::now()->addSeconds($ttl)->getTimestamp();

        foreach ($this->tagIds() as $tagKey) {
            if ($updateWhen) {
                $this->store->connection()->zadd($this->store->getPrefix().$tagKey, $updateWhen, $ttl, $key);
            } else {
                $this->store->connection()->zadd($this->store->getPrefix().$tagKey, $ttl, $key);
            }
        }
    }

    /**
     * Get all of the cache entry keys for the tag set.
     */
    public function entries(): \Illuminate\Support\LazyCollection
    {
        $connection = $this->store->connection();

        $defaultCursorValue = match (true) {
            $connection instanceof PhpRedisConnection && version_compare(phpversion('redis'), '6.1.0', '>=') => null,
            default => '0',
        };

        return new LazyCollection(function () use ($connection, $defaultCursorValue) {
            foreach ($this->tagIds() as $tagKey) {
                $cursor = $defaultCursorValue;

                do {
                    $results = $connection->zscan(
                        $this->store->getPrefix().$tagKey,
                        $cursor,
                        ['match' => '*', 'count' => 1000]
                    );

                    if (! is_array($results)) {
                        break;
                    }

                    [$cursor, $entries] = $results;

                    if (! is_array($entries)) {
                        break;
                    }

                    $entries = array_unique(array_keys($entries));

                    if (count($entries) === 0) {
                        continue;
                    }

                    foreach ($entries as $entry) {
                        yield $entry;
                    }
                } while (((string) $cursor) !== $defaultCursorValue);
            }
        });
    }

    /**
     * Remove the stale entries from the tag set.
     */
    public function flushStaleEntries(): void
    {
        $flushStaleEntries = function ($pipe): void {
            foreach ($this->tagIds() as $tagKey) {
                $pipe->zremrangebyscore($this->store->getPrefix().$tagKey, 0, Carbon::now()->getTimestamp());
            }
        };

        $connection = $this->store->connection();

        if ($connection instanceof PhpRedisConnection) {
            $flushStaleEntries($connection);
        } else {
            $connection->pipeline($flushStaleEntries);
        }
    }

    /**
     * Flush the tag from the cache.
     *
     * @param  string  $name
     */
    public function flushTag($name): void
    {
        return $this->resetTag($name);
    }

    /**
     * Reset the tag and return the new tag identifier.
     *
     * @param  string  $name
     * @return string
     */
    public function resetTag($name): string|array
    {
        $this->store->forget($this->tagKey($name));

        return $this->tagId($name);
    }

    /**
     * Get the unique tag identifier for a given tag.
     *
     * @param  string  $name
     */
    public function tagId($name): string
    {
        return "tag:{$name}:entries";
    }

    /**
     * Get the tag identifier key for a given tag.
     *
     * @param  string  $name
     */
    public function tagKey($name): string
    {
        return "tag:{$name}:entries";
    }
}
