<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Illuminate\Contracts\Cache\Lock_Provider;
use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Redis\Connections\Php_Redis_Cluster_Connection;
use Illuminate\Redis\Connections\Php_Redis_Connection;
use Illuminate\Redis\Connections\Predis_Cluster_Connection;
use Illuminate\Redis\Connections\Predis_Connection;
use Illuminate\Support\Lazy_Collection;
use Illuminate\Support\Str;
class Redis_Store extends Taggable_Store implements Lock_Provider
{
    use Retrieves_Multiple_Keys {
        many as private manyAlias;
        putMany as private putManyAlias;
    }
    /**
     * A string that should be prepended to keys.
     *
     * @var string
     */
    protected $prefix;
    /**
     * The Redis connection instance that should be used to manage locks.
     *
     * @var string
     */
    protected $connection;
    /**
     * The name of the connection that should be used for locks.
     *
     * @var string
     */
    protected $lock_connection;
    /**
     * Create a new Redis store.
     *
     * @param  string  $prefix
     * @param  string  $connection
     * @param  array|bool|null  $serializableClasses
     */
    public function __construct(
        /**
         * The Redis factory implementation.
         */
        protected \Redis $redis,
        $prefix = '',
        $connection = 'default',
        /**
         * The classes that should be allowed during unserialization.
         */
        protected $serializable_classes = null
    )
    {
        $this->set_prefix($prefix);
        $this->set_connection($connection);
    }
    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        $connection = $this->connection();
        $value = $connection->get($this->prefix . $key);
        return !is_null($value) ? $this->connection_aware_unserialize($value, $connection) : null;
    }
    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     */
    public function many(array $keys): array
    {
        if (count($keys) === 0) {
            return [];
        }
        $results = [];
        $connection = $this->connection();
        // PredisClusterConnection does not support reading multiple values if the keys hash differently...
        if ($connection instanceof Predis_Cluster_Connection) {
            return $this->many_alias($keys);
        }
        $values = $connection->mget(array_map(fn($key): string => $this->prefix . $key, $keys));
        foreach ($values as $index => $value) {
            $results[$keys[$index]] = !is_null($value) ? $this->connection_aware_unserialize($value, $connection) : null;
        }
        return $results;
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
        $connection = $this->connection();
        return (bool) $connection->setex($this->prefix . $key, max(1, $seconds), $this->connection_aware_serialize($value, $connection));
    }
    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param  int  $seconds
     * @return bool
     */
    public function put_many(array $values, $seconds)
    {
        $connection = $this->connection();
        // Cluster connections do not support writing multiple values if the keys hash differently...
        if ($connection instanceof Php_Redis_Cluster_Connection || $connection instanceof Predis_Cluster_Connection) {
            return $this->put_many_alias($values, $seconds);
        }
        $serialized_values = [];
        foreach ($values as $key => $value) {
            $serialized_values[$this->prefix . $key] = $this->connection_aware_serialize($value, $connection);
        }
        $connection->multi();
        $many_result = null;
        foreach ($serialized_values as $key => $value) {
            $result = (bool) $connection->setex($key, max(1, $seconds), $value);
            $many_result = is_null($many_result) ? $result : $result && $many_result;
        }
        $connection->exec();
        return $many_result ?: false;
    }
    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param  mixed  $value
     * @param  int  $seconds
     */
    public function add(string $key, $value, $seconds): bool
    {
        $connection = $this->connection();
        return (bool) $connection->eval(Lua_Scripts::add(), 1, $this->prefix . $key, $this->pack($value, $connection), max(1, $seconds));
    }
    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int
     */
    public function increment($key, $value = 1)
    {
        return $this->connection()->incrby($this->prefix . $key, $value);
    }
    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int
     */
    public function decrement($key, $value = 1)
    {
        return $this->connection()->decrby($this->prefix . $key, $value);
    }
    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    public function forever($key, $value): bool
    {
        $connection = $this->connection();
        return (bool) $connection->set($this->prefix . $key, $this->connection_aware_serialize($value, $connection));
    }
    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function lock($name, $seconds = 0, $owner = null): \Illuminate\Cache\Php_Redis_Lock|\Illuminate\Cache\Redis_Lock
    {
        $lock_name = $this->prefix . $name;
        $lock_connection = $this->lock_connection();
        if ($lock_connection instanceof Php_Redis_Connection) {
            return new Php_Redis_Lock($lock_connection, $lock_name, $seconds, $owner);
        }
        return new Redis_Lock($lock_connection, $lock_name, $seconds, $owner);
    }
    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function restore_lock($name, $owner): \Illuminate\Cache\Redis_Lock
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
        return (bool) $this->connection()->del($this->prefix . $key);
    }
    /**
     * Remove all items from the cache.
     */
    public function flush(): bool
    {
        $this->connection()->flushdb();
        return true;
    }
    /**
     * Remove all expired tag set entries.
     */
    public function flush_stale_tags(): void
    {
        foreach ($this->current_tags()->chunk(1000) as $tags) {
            $this->tags($tags->all())->flush_stale();
        }
    }
    /**
     * Begin executing a new tags operation.
     *
     * @param  mixed  $names
     */
    public function tags($names): \Illuminate\Cache\Redis_Tagged_Cache
    {
        return new Redis_Tagged_Cache($this, new Redis_Tag_Set($this, is_array($names) ? $names : func_get_args()));
    }
    /**
     * Get a collection of all of the cache tags currently being used.
     *
     * @param  int  $chunkSize
     */
    protected function current_tags($chunk_size = 1000): \Illuminate\Support\Lazy_Collection
    {
        $connection = $this->connection();
        // Connections can have a global prefix...
        $connection_prefix = match (true) {
            $connection instanceof Php_Redis_Connection => $connection->_prefix(''),
            $connection instanceof Predis_Connection => $connection->get_options()->prefix ?: '',
            default => '',
        };
        $default_cursor_value = match (true) {
            $connection instanceof Php_Redis_Connection && version_compare(phpversion('redis'), '6.1.0', '>=') => null,
            default => '0',
        };
        $prefix = $connection_prefix . $this->get_prefix();
        return (new Lazy_Collection(function () use ($connection, $chunk_size, $prefix, $default_cursor_value) {
            $cursor = $default_cursor_value;
            do {
                $scan_result = $connection->scan($cursor, ['match' => $prefix . 'tag:*:entries', 'count' => $chunk_size]);
                if (!is_array($scan_result)) {
                    break;
                }
                [$cursor, $tags_chunk] = $scan_result;
                if (!is_array($tags_chunk)) {
                    break;
                }
                $tags_chunk = array_unique($tags_chunk);
                if (empty($tags_chunk)) {
                    continue;
                }
                foreach ($tags_chunk as $tag) {
                    yield $tag;
                }
            } while ((string) $cursor !== $default_cursor_value);
        }))->map(fn(string $tag_key): string => Str::match('/^' . preg_quote($prefix, '/') . 'tag:(.*):entries$/', $tag_key));
    }
    /**
     * Get the Redis connection instance.
     *
     * @return \Illuminate\Redis\Connections\Connection
     */
    public function connection()
    {
        return $this->redis->connection($this->connection);
    }
    /**
     * Get the Redis connection instance that should be used to manage locks.
     *
     * @return \Illuminate\Redis\Connections\Connection
     */
    public function lock_connection()
    {
        return $this->redis->connection($this->lock_connection ?? $this->connection);
    }
    /**
     * Specify the name of the connection that should be used to store data.
     *
     * @param  string  $connection
     */
    public function set_connection($connection): void
    {
        $this->connection = $connection;
    }
    /**
     * Specify the name of the connection that should be used to manage locks.
     *
     * @param  string  $connection
     * @return $this
     */
    public function set_lock_connection($connection): static
    {
        $this->lock_connection = $connection;
        return $this;
    }
    /**
     * Get the Redis database instance.
     *
     * @return \Illuminate\Contracts\Redis\Factory
     */
    public function get_redis(): \Redis
    {
        return $this->redis;
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
    /**
     * Prepare a value to be used with the Redis cache store when used by eval scripts.
     *
     * @param  mixed  $value
     * @param  \Illuminate\Redis\Connections\Connection  $connection
     * @return mixed
     */
    protected function pack($value, $connection)
    {
        if ($connection instanceof Php_Redis_Connection) {
            if ($connection->serialized()) {
                return $connection->pack([$value])[0];
            }
            if ($connection->compressed()) {
                return $connection->pack([$this->serialize($value)])[0];
            }
        }
        return $this->serialize($value);
    }
    /**
     * Serialize the value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function serialize($value)
    {
        return $this->should_be_stored_without_serialization($value) ? $value : serialize($value);
    }
    /**
     * Determine if the given value should be stored as plain value.
     *
     * @param  mixed  $value
     */
    protected function should_be_stored_without_serialization($value): bool
    {
        return is_numeric($value) && is_finite($value);
    }
    /**
     * Unserialize the value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function unserialize($value)
    {
        if (is_numeric($value)) {
            return $value;
        }
        if ($this->serializable_classes !== null) {
            return unserialize($value, ['allowed_classes' => $this->serializable_classes]);
        }
        return unserialize($value);
    }
    /**
     * Handle connection specific considerations when a value needs to be serialized.
     *
     * @param  mixed  $value
     * @param  \Illuminate\Redis\Connections\Connection  $connection
     * @return mixed
     */
    protected function connection_aware_serialize($value, $connection)
    {
        if ($connection instanceof Php_Redis_Connection && $connection->serialized()) {
            return $value;
        }
        return $this->serialize($value);
    }
    /**
     * Handle connection specific considerations when a value needs to be unserialized.
     *
     * @param  mixed  $value
     * @param  \Illuminate\Redis\Connections\Connection  $connection
     * @return mixed
     */
    protected function connection_aware_unserialize($value, $connection)
    {
        if ($connection instanceof Php_Redis_Connection && $connection->serialized()) {
            return $value;
        }
        return $this->unserialize($value);
    }
}