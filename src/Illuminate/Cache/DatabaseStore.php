<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Closure;
use Illuminate\Contracts\Cache\Lock_Provider;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\Postgres_Connection;
use Illuminate\Database\Query_Exception;
use Illuminate\Database\Sq_Lite_Connection;
use Illuminate\Database\Sql_Server_Connection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Interacts_With_Time;
use Illuminate\Support\Str;
class Database_Store implements Lock_Provider, Store
{
    use Interacts_With_Time;
    /**
     * The database connection instance that should be used to manage locks.
     *
     * @var \Illuminate\Database\ConnectionInterface
     */
    protected $lock_connection;
    /**
     * Create a new database store.
     *
     * @param  string  $table
     * @param  string  $prefix
     * @param  string  $lockTable
     * @param  array  $lockLottery
     * @param  int  $defaultLockTimeoutInSeconds
     * @param  array|bool|null  $serializableClasses
     */
    public function __construct(
        /**
         * The database connection instance.
         */
        protected \Illuminate\Database\Connection_Interface $connection,
        /**
         * The name of the cache table.
         */
        protected $table,
        /**
         * A string that should be prepended to keys.
         */
        protected $prefix = '',
        /**
         * The name of the cache locks table.
         */
        protected $lock_table = 'cache_locks',
        /**
         * An array representation of the lock lottery odds.
         */
        protected $lock_lottery = [2, 100],
        /**
         * The default number of seconds that a lock should be held.
         */
        protected $default_lock_timeout_in_seconds = 86400,
        /**
         * The classes that should be allowed during unserialization.
         */
        protected $serializable_classes = null
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
        return $this->many([$key])[$key];
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
        $results = array_fill_keys($keys, null);
        // First we will retrieve all of the items from the cache using their keys and
        // the prefix value. Then we will need to iterate through each of the items
        // and convert them to an object when they are currently in array format.
        $values = $this->table()->where_in('key', array_map(fn($key): string => $this->prefix . $key, $keys))->get()->map(fn($value): \stdClass => is_array($value) ? (object) $value : $value);
        $current_time = $this->current_time();
        // If this cache expiration date is past the current time, we will remove this
        // item from the cache. Then we will return a null value since the cache is
        // expired. We will use "Carbon" to make this comparison with the column.
        [$values, $expired] = $values->partition(fn($cache): bool => $cache->expiration > $current_time);
        if ($expired->is_not_empty()) {
            $this->forget_many_if_expired($expired->pluck('key')->all(), prefixed: true);
        }
        return Arr::map($results, function ($value, string $key) use ($values) {
            if ($cache = $values->first_where('key', $this->prefix . $key)) {
                return $this->unserialize($cache->value);
            }
            return $value;
        });
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
        return $this->put_many([$key => $value], $seconds);
    }
    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param  int  $seconds
     */
    public function put_many(array $values, $seconds): bool
    {
        $serialized_values = [];
        $expiration = $this->get_time() + $seconds;
        foreach ($values as $key => $value) {
            $serialized_values[] = ['key' => $this->prefix . $key, 'value' => $this->serialize($value), 'expiration' => $expiration];
        }
        return $this->table()->upsert($serialized_values, 'key') > 0;
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
        if (!is_null($this->get($key))) {
            return false;
        }
        $key = $this->prefix . $key;
        $value = $this->serialize($value);
        $expiration = $this->get_time() + $seconds;
        if (!$this->get_connection() instanceof Sql_Server_Connection) {
            return $this->table()->insert_or_ignore(compact('key', 'value', 'expiration')) > 0;
        }
        try {
            return $this->table()->insert(compact('key', 'value', 'expiration'));
        } catch (Query_Exception) {
            // ...
        }
        return false;
    }
    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  int  $value
     * @return int|false
     */
    public function increment($key, $value = 1)
    {
        return $this->increment_or_decrement($key, $value, fn($current, $value): float|int|array => $current + $value);
    }
    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  int  $value
     * @return int|false
     */
    public function decrement($key, $value = 1)
    {
        return $this->increment_or_decrement($key, $value, fn($current, $value): int|float => $current - $value);
    }
    /**
     * Increment or decrement an item in the cache.
     *
     * @param  string  $key
     * @param  int|float  $value
     * @return int|false
     */
    protected function increment_or_decrement($key, $value, Closure $callback)
    {
        return $this->connection->transaction(function () use ($key, $value, $callback) {
            $prefixed = $this->prefix . $key;
            $cache = $this->table()->where('key', $prefixed)->lock_for_update()->first();
            // If there is no value in the cache, we will return false here. Otherwise the
            // value will be decrypted and we will proceed with this function to either
            // increment or decrement this value based on the given action callbacks.
            if (is_null($cache)) {
                return false;
            }
            $cache = is_array($cache) ? (object) $cache : $cache;
            $current = $this->unserialize($cache->value);
            // Here we'll call this callback function that was given to the function which
            // is used to either increment or decrement the function. We use a callback
            // so we do not have to recreate all this logic in each of the functions.
            $new = $callback((int) $current, $value);
            if (!is_numeric($current)) {
                return false;
            }
            // Here we will update the values in the table. We will also encrypt the value
            // since database cache values are encrypted by default with secure storage
            // that can't be easily read. We will return the new value after storing.
            $this->table()->where('key', $prefixed)->update(['value' => $this->serialize($new)]);
            return $new;
        });
    }
    /**
     * Get the current system time.
     *
     * @return int
     */
    protected function get_time()
    {
        return $this->current_time();
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
        return $this->put($key, $value, 315360000);
    }
    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function lock($name, $seconds = 0, $owner = null): \Illuminate\Cache\Database_Lock
    {
        return new Database_Lock($this->lock_connection ?? $this->connection, $this->lock_table, $this->prefix . $name, $seconds, $owner, $this->lock_lottery, $this->default_lock_timeout_in_seconds);
    }
    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function restore_lock($name, $owner): \Illuminate\Cache\Database_Lock
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
        return $this->forget_many([$key]);
    }
    /**
     * Remove an item from the cache if it is expired.
     *
     * @param  string  $key
     */
    public function forget_if_expired($key): bool
    {
        return $this->forget_many_if_expired([$key]);
    }
    /**
     * Remove all items from the cache.
     */
    protected function forget_many(array $keys): bool
    {
        $this->table()->where_in('key', (new Collection($keys))->flat_map(fn($key): array => [$this->prefix . $key, "{$this->prefix}illuminate:cache:flexible:created:{$key}"])->all())->delete();
        return true;
    }
    /**
     * Remove all expired items from the given set from the cache.
     */
    protected function forget_many_if_expired(array $keys, bool $prefixed = false): bool
    {
        $this->table()->where_in('key', (new Collection($keys))->flat_map(fn($key): array => $prefixed ? [$key, $this->prefix . 'illuminate:cache:flexible:created:' . Str::chop_start($key, $this->prefix)] : ["{$this->prefix}{$key}", "{$this->prefix}illuminate:cache:flexible:created:{$key}"])->all())->where('expiration', '<=', $this->get_time())->delete();
        return true;
    }
    /**
     * Remove all items from the cache.
     */
    public function flush(): bool
    {
        $this->table()->delete();
        return true;
    }
    /**
     * Get a query builder for the cache table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function table()
    {
        return $this->connection->table($this->table);
    }
    /**
     * Get the underlying database connection.
     */
    public function get_connection(): \Illuminate\Database\Connection_Interface
    {
        return $this->connection;
    }
    /**
     * Set the underlying database connection.
     *
     * @return $this
     */
    public function set_connection(\Illuminate\Database\Connection_Interface $connection): static
    {
        $this->connection = $connection;
        return $this;
    }
    /**
     * Get the connection used to manage locks.
     *
     * @return \Illuminate\Database\ConnectionInterface
     */
    public function get_lock_connection()
    {
        return $this->lock_connection;
    }
    /**
     * Specify the connection that should be used to manage locks.
     *
     * @param  \Illuminate\Database\ConnectionInterface  $connection
     * @return $this
     */
    public function set_lock_connection($connection): static
    {
        $this->lock_connection = $connection;
        return $this;
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
     * Serialize the given value.
     *
     * @param  mixed  $value
     */
    protected function serialize($value): string
    {
        $result = serialize($value);
        if (($this->connection instanceof Postgres_Connection || $this->connection instanceof Sq_Lite_Connection) && str_contains($result, "\x00")) {
            return base64_encode($result);
        }
        return $result;
    }
    /**
     * Unserialize the given value.
     *
     * @param  string  $value
     */
    protected function unserialize($value): mixed
    {
        if (($this->connection instanceof Postgres_Connection || $this->connection instanceof Sq_Lite_Connection) && !Str::contains($value, [':', ';'])) {
            $value = base64_decode($value);
        }
        if ($this->serializable_classes !== null) {
            return unserialize($value, ['allowed_classes' => $this->serializable_classes]);
        }
        return unserialize($value);
    }
}