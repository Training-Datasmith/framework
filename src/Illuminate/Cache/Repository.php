<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use DateTimeInterface;
use Illuminate\Cache\Events\Cache_Flushed;
use Illuminate\Cache\Events\Cache_Flush_Failed;
use Illuminate\Cache\Events\Cache_Flushing;
use Illuminate\Cache\Events\Cache_Hit;
use Illuminate\Cache\Events\Cache_Missed;
use Illuminate\Cache\Events\Forgetting_Key;
use Illuminate\Cache\Events\Key_Forget_Failed;
use Illuminate\Cache\Events\Key_Forgotten;
use Illuminate\Cache\Events\Key_Write_Failed;
use Illuminate\Cache\Events\Key_Written;
use Illuminate\Cache\Events\Retrieving_Key;
use Illuminate\Cache\Events\Retrieving_Many_Keys;
use Illuminate\Cache\Events\Writing_Key;
use Illuminate\Cache\Events\Writing_Many_Keys;
use Illuminate\Cache\Limiters\Concurrency_Limiter_Builder;
use Illuminate\Contracts\Cache\Lock_Provider;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use function Illuminate\Support\defer;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Interacts_With_Time;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
/**
 * @mixin \Illuminate\Contracts\Cache\Store
 */
class Repository implements ArrayAccess, Cache_Contract
{
    use Interacts_With_Time, Macroable {
        __call as macroCall;
    }
    /**
     * The event dispatcher implementation.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher|null
     */
    protected $events;
    /**
     * The default number of seconds to store items.
     *
     * @var int|null
     */
    protected $default = 3600;
    /**
     * Create a new cache repository instance.
     */
    public function __construct(
        /**
         * The cache store implementation.
         */
        protected \Illuminate\Contracts\Cache\Store $store,
        /**
         * The cache store configuration options.
         */
        protected array $config = []
    )
    {
    }
    /**
     * Determine if an item exists in the cache.
     *
     * @param  \UnitEnum|array|string  $key
     */
    public function has($key): bool
    {
        return !is_null($this->get($key));
    }
    /**
     * Determine if an item doesn't exist in the cache.
     *
     * @param  \UnitEnum|string  $key
     */
    public function missing($key): bool
    {
        return !$this->has($key);
    }
    /**
     * Retrieve an item from the cache by key.
     *
     * @param  \UnitEnum|array|string  $key
     * @param  mixed  $default
     */
    public function get($key, $default = null): mixed
    {
        if (is_array($key)) {
            return $this->many($key);
        }
        $key = enum_value($key);
        $this->event(new Retrieving_Key($this->get_name(), $key));
        $value = $this->store->get($this->item_key($key));
        // If we could not find the cache value, we will fire the missed event and get
        // the default value for this cache value. This default could be a callback
        // so we will execute the value function which will resolve it if needed.
        if (is_null($value)) {
            $this->event(new Cache_Missed($this->get_name(), $key));
            $value = value($default);
        } else {
            $this->event(new Cache_Hit($this->get_name(), $key, $value));
        }
        return $value;
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
        $this->event(new Retrieving_Many_Keys($this->get_name(), $keys));
        $values = $this->store->many((new Collection($keys))->map(fn($value, $key) => is_string($key) ? $key : enum_value($value))->values()->all());
        return (new Collection($values))->map(fn($value, $key): mixed => $this->handle_many_result($keys, $key, $value))->all();
    }
    /**
     * {@inheritdoc}
     */
    public function get_multiple($keys, $default = null): iterable
    {
        $defaults = [];
        foreach ($keys as $key) {
            $defaults[enum_value($key)] = $default;
        }
        return $this->many($defaults);
    }
    /**
     * Handle a result for the "many" method.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function handle_many_result(array $keys, $key, $value)
    {
        // If we could not find the cache value, we will fire the missed event and get
        // the default value for this cache value. This default could be a callback
        // so we will execute the value function which will resolve it if needed.
        if (is_null($value)) {
            $this->event(new Cache_Missed($this->get_name(), $key));
            return isset($keys[$key]) && !array_is_list($keys) ? value($keys[$key]) : null;
        }
        // If we found a valid value we will fire the "hit" event and return the value
        // back from this function. The "hit" event gives developers an opportunity
        // to listen for every possible cache "hit" throughout this applications.
        $this->event(new Cache_Hit($this->get_name(), $key, $value));
        return $value;
    }
    /**
     * Retrieve an item from the cache and delete it.
     *
     * @param  \UnitEnum|array|string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        return tap($this->get($key, $default), function () use ($key): void {
            $this->forget($key);
        });
    }
    /**
     * Retrieve a string item from the cache.
     *
     * @param  \UnitEnum|string  $key
     * @param  (\Closure():(string|null))|string|null  $default
     *
     * @throws \InvalidArgumentException
     */
    public function string(string $key, $default = null): string
    {
        $value = $this->get($key, $default);
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('Cache value for key [%s] must be a string, %s given.', $key, gettype($value)));
        }
        return $value;
    }
    /**
     * Retrieve an integer item from the cache.
     *
     * @param  \UnitEnum|string  $key
     * @param  (\Closure():(int|null))|int|null  $default
     *
     * @throws \InvalidArgumentException
     */
    public function integer(string $key, $default = null): int
    {
        $value = $this->get($key, $default);
        if (is_int($value)) {
            return $value;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return (int) $value;
        }
        throw new InvalidArgumentException(sprintf('Cache value for key [%s] must be an integer, %s given.', $key, gettype($value)));
    }
    /**
     * Retrieve a float item from the cache.
     *
     * @param  \UnitEnum|string  $key
     * @param  (\Closure():(float|null))|float|null  $default
     *
     * @throws \InvalidArgumentException
     */
    public function float(string $key, $default = null): float
    {
        $value = $this->get($key, $default);
        if (is_float($value)) {
            return $value;
        }
        if (filter_var($value, FILTER_VALIDATE_FLOAT) !== false) {
            return (float) $value;
        }
        throw new InvalidArgumentException(sprintf('Cache value for key [%s] must be a float, %s given.', $key, gettype($value)));
    }
    /**
     * Retrieve a boolean item from the cache.
     *
     * @param  \UnitEnum|string  $key
     * @param  (\Closure():(bool|null))|bool|null  $default
     *
     * @throws \InvalidArgumentException
     */
    public function boolean(string $key, $default = null): bool
    {
        $value = $this->get($key, $default);
        if (!is_bool($value)) {
            throw new InvalidArgumentException(sprintf('Cache value for key [%s] must be a boolean, %s given.', $key, gettype($value)));
        }
        return $value;
    }
    /**
     * Retrieve an array item from the cache.
     *
     * @param  \UnitEnum|string  $key
     * @param  (\Closure():(array<array-key, mixed>|null))|array<array-key, mixed>|null  $default
     * @return array<array-key, mixed>
     *
     * @throws \InvalidArgumentException
     */
    public function array(string $key, $default = null): array
    {
        $value = $this->get($key, $default);
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('Cache value for key [%s] must be an array, %s given.', $key, gettype($value)));
        }
        return $value;
    }
    /**
     * Store an item in the cache.
     *
     * @param  \UnitEnum|array|string  $key
     * @param  mixed  $value
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @return bool
     */
    public function put($key, $value, $ttl = null)
    {
        if (is_array($key)) {
            return $this->put_many($key, $value);
        }
        $key = enum_value($key);
        if ($ttl === null) {
            return $this->forever($key, $value);
        }
        $seconds = $this->get_seconds($ttl);
        if ($seconds <= 0) {
            return $this->forget($key);
        }
        $this->event(new Writing_Key($this->get_name(), $key, $value, $seconds));
        $result = $this->store->put($this->item_key($key), $value, $seconds);
        if ($result) {
            $this->event(new Key_Written($this->get_name(), $key, $value, $seconds));
        } else {
            $this->event(new Key_Write_Failed($this->get_name(), $key, $value, $seconds));
        }
        return $result;
    }
    /**
     * Store an item in the cache.
     *
     * @param  \UnitEnum|array|string  $key
     * @param  mixed  $value
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     */
    public function set($key, $value, $ttl = null): bool
    {
        return $this->put($key, $value, $ttl);
    }
    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @return bool
     */
    public function put_many(array $values, $ttl = null)
    {
        if ($ttl === null) {
            return $this->put_many_forever($values);
        }
        $seconds = $this->get_seconds($ttl);
        if ($seconds <= 0) {
            return $this->delete_multiple(array_keys($values));
        }
        $this->event(new Writing_Many_Keys($this->get_name(), array_keys($values), array_values($values), $seconds));
        $result = $this->store->put_many($values, $seconds);
        foreach ($values as $key => $value) {
            if ($result) {
                $this->event(new Key_Written($this->get_name(), $key, $value, $seconds));
            } else {
                $this->event(new Key_Write_Failed($this->get_name(), $key, $value, $seconds));
            }
        }
        return $result;
    }
    /**
     * Store multiple items in the cache indefinitely.
     *
     * @return bool
     */
    protected function put_many_forever(array $values)
    {
        $result = true;
        foreach ($values as $key => $value) {
            if (!$this->forever($key, $value)) {
                $result = false;
            }
        }
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    public function set_multiple($values, $ttl = null): bool
    {
        return $this->put_many(is_array($values) ? $values : iterator_to_array($values), $ttl);
    }
    /**
     * Store an item in the cache if the key does not exist.
     *
     * @param  \UnitEnum|array|string  $key
     * @param  mixed  $value
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @return bool
     */
    public function add($key, $value, $ttl = null)
    {
        $key = enum_value($key);
        $seconds = null;
        if ($ttl !== null) {
            $seconds = $this->get_seconds($ttl);
            if ($seconds <= 0) {
                return false;
            }
            // If the store has an "add" method we will call the method on the store so it
            // has a chance to override this logic. Some drivers better support the way
            // this operation should work with a total "atomic" implementation of it.
            if (method_exists($this->store, 'add')) {
                return $this->store->add($this->item_key($key), $value, $seconds);
            }
        }
        // If the value did not exist in the cache, we will put the value in the cache
        // so it exists for subsequent requests. Then, we will return true so it is
        // easy to know if the value gets added. Otherwise, we will return false.
        if (is_null($this->get($key))) {
            return $this->put($key, $value, $seconds);
        }
        return false;
    }
    /**
     * Increment the value of an item in the cache.
     *
     * @param  \UnitEnum|string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function increment($key, $value = 1)
    {
        return $this->store->increment(enum_value($key), $value);
    }
    /**
     * Decrement the value of an item in the cache.
     *
     * @param  \UnitEnum|string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function decrement($key, $value = 1)
    {
        return $this->store->decrement(enum_value($key), $value);
    }
    /**
     * Store an item in the cache indefinitely.
     *
     * @param  \UnitEnum|string  $key
     * @param  mixed  $value
     * @return bool
     */
    public function forever($key, $value)
    {
        $key = enum_value($key);
        $this->event(new Writing_Key($this->get_name(), $key, $value));
        $result = $this->store->forever($this->item_key($key), $value);
        if ($result) {
            $this->event(new Key_Written($this->get_name(), $key, $value));
        } else {
            $this->event(new Key_Write_Failed($this->get_name(), $key, $value));
        }
        return $result;
    }
    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @template TCacheValue
     *
     * @param  \UnitEnum|string  $key
     * @param  \Closure|\DateTimeInterface|\DateInterval|int|null  $ttl
     * @param  \Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function remember($key, $ttl, Closure $callback)
    {
        $value = $this->get($key);
        // If the item exists in the cache we will just return this immediately and if
        // not we will execute the given Closure and cache the result of that for a
        // given number of seconds so it's available for all subsequent requests.
        if (!is_null($value)) {
            return $value;
        }
        $value = $callback();
        $this->put($key, $value, value($ttl, $value));
        return $value;
    }
    /**
     * Get an item from the cache, or execute the given Closure and store the result forever.
     *
     * @template TCacheValue
     *
     * @param  \UnitEnum|string  $key
     * @param  \Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function sear($key, Closure $callback)
    {
        return $this->remember_forever($key, $callback);
    }
    /**
     * Get an item from the cache, or execute the given Closure and store the result forever.
     *
     * @template TCacheValue
     *
     * @param  \UnitEnum|string  $key
     * @param  \Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function remember_forever($key, Closure $callback)
    {
        $value = $this->get($key);
        // If the item exists in the cache we will just return this immediately
        // and if not we will execute the given Closure and cache the result
        // of that forever so it is available for all subsequent requests.
        if (!is_null($value)) {
            return $value;
        }
        $this->forever($key, $value = $callback());
        return $value;
    }
    /**
     * Retrieve an item from the cache by key, refreshing it in the background if it is stale.
     *
     * @template TCacheValue
     *
     * @param  \UnitEnum|string  $key
     * @param  array{ 0: \DateTimeInterface|\DateInterval|int, 1: \DateTimeInterface|\DateInterval|int }  $ttl
     * @param  (callable(): TCacheValue)  $callback
     * @param  array{ seconds?: int, owner?: string }|null  $lock
     * @return TCacheValue
     */
    public function flexible($key, $ttl, $callback, $lock = null, bool $always_defer = false)
    {
        $key = enum_value($key);
        [$key => $value, "illuminate:cache:flexible:created:{$key}" => $created] = $this->many([$key, "illuminate:cache:flexible:created:{$key}"]);
        if (in_array(null, [$value, $created], true)) {
            return tap(value($callback), fn($value) => $this->put_many([$key => $value, "illuminate:cache:flexible:created:{$key}" => Carbon::now()->get_timestamp()], $ttl[1]));
        }
        if ($created + $this->get_seconds($ttl[0]) > Carbon::now()->get_timestamp()) {
            return $value;
        }
        $refresh = function () use ($key, $ttl, $callback, $lock, $created): void {
            $this->store->lock("illuminate:cache:flexible:lock:{$key}", $lock['seconds'] ?? 0, $lock['owner'] ?? null)->get(function () use ($key, $callback, $created, $ttl): void {
                if ($created !== $this->get("illuminate:cache:flexible:created:{$key}")) {
                    return;
                }
                $this->put_many([$key => value($callback), "illuminate:cache:flexible:created:{$key}" => Carbon::now()->get_timestamp()], $ttl[1]);
            });
        };
        defer($refresh, "illuminate:cache:flexible:{$key}", $always_defer);
        return $value;
    }
    /**
     * Execute a callback while holding an atomic lock on a cache mutex to prevent overlapping calls.
     *
     * @template TReturn
     *
     * @param  \UnitEnum|string  $key
     * @param  callable(): TReturn  $callback
     * @param  int  $lockFor
     * @param  int  $waitFor
     * @param  string|null  $owner
     * @return TReturn
     *
     * @throws \Illuminate\Contracts\Cache\LockTimeoutException
     */
    public function without_overlapping($key, callable $callback, $lock_for = 0, $wait_for = 10, $owner = null)
    {
        return $this->store->lock(enum_value($key), $lock_for, $owner)->block($wait_for, $callback);
    }
    /**
     * Funnel a callback for a maximum number of simultaneous executions.
     *
     * @param  \UnitEnum|string  $name
     */
    public function funnel($name): \Illuminate\Cache\Limiters\Concurrency_Limiter_Builder
    {
        if (!$this->store instanceof Lock_Provider) {
            throw new BadMethodCallException('This cache store does not support locks.');
        }
        return new Concurrency_Limiter_Builder($this, enum_value($name));
    }
    /**
     * Remove an item from the cache.
     *
     * @param  \UnitEnum|array|string  $key
     * @return bool
     */
    public function forget($key)
    {
        $key = enum_value($key);
        $this->event(new Forgetting_Key($this->get_name(), $key));
        return tap($this->store->forget($this->item_key($key)), function ($result) use ($key): void {
            if ($result) {
                $this->event(new Key_Forgotten($this->get_name(), $key));
            } else {
                $this->event(new Key_Forget_Failed($this->get_name(), $key));
            }
        });
    }
    /**
     * Remove an item from the cache.
     *
     * @param  \UnitEnum|array|string  $key
     */
    public function delete($key): bool
    {
        return $this->forget($key);
    }
    /**
     * {@inheritdoc}
     */
    public function delete_multiple($keys): bool
    {
        $result = true;
        foreach ($keys as $key) {
            if (!$this->forget($key)) {
                $result = false;
            }
        }
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->event(new Cache_Flushing($this->get_name()));
        $result = $this->store->flush();
        if ($result) {
            $this->event(new Cache_Flushed($this->get_name()));
        } else {
            $this->event(new Cache_Flush_Failed($this->get_name()));
        }
        return $result;
    }
    /**
     * Begin executing a new tags operation if the store supports it.
     *
     * @param  mixed  $names
     * @return \Illuminate\Cache\TaggedCache
     *
     * @throws \BadMethodCallException
     */
    public function tags($names)
    {
        if (!$this->supports_tags()) {
            throw new BadMethodCallException('This cache store does not support tagging.');
        }
        $cache = $this->store->tags(is_array($names) ? $names : func_get_args());
        $cache->config = $this->config;
        if (!is_null($this->events)) {
            $cache->set_event_dispatcher($this->events);
        }
        return $cache->set_default_cache_time($this->default);
    }
    /**
     * Format the key for a cache item.
     *
     * @param  string  $key
     * @return string
     */
    protected function item_key($key)
    {
        return $key;
    }
    /**
     * Calculate the number of seconds for the given TTL.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $ttl
     */
    protected function get_seconds($ttl): int
    {
        $duration = $this->parse_date_interval($ttl);
        if ($duration instanceof DateTimeInterface) {
            $duration = (int) ceil(Carbon::now()->diff_in_milliseconds($duration, false) / 1000);
        }
        return (int) ($duration > 0 ? $duration : 0);
    }
    /**
     * Get the name of the cache store.
     *
     * @return string|null
     */
    public function get_name()
    {
        return $this->config['store'] ?? null;
    }
    /**
     * Determine if the current store supports tags.
     */
    public function supports_tags(): bool
    {
        return method_exists($this->store, 'tags');
    }
    /**
     * Get the default cache time.
     *
     * @return int|null
     */
    public function get_default_cache_time()
    {
        return $this->default;
    }
    /**
     * Set the default cache time in seconds.
     *
     * @param  int|null  $seconds
     * @return $this
     */
    public function set_default_cache_time($seconds): static
    {
        $this->default = $seconds;
        return $this;
    }
    /**
     * Get the cache store implementation.
     */
    public function get_store(): \Illuminate\Contracts\Cache\Store
    {
        return $this->store;
    }
    /**
     * Set the cache store implementation.
     */
    public function set_store(\Illuminate\Contracts\Cache\Store $store): static
    {
        $this->store = $store;
        return $this;
    }
    /**
     * Fire an event for this cache instance.
     *
     * @param  object|string  $event
     * @return void
     */
    protected function event($event)
    {
        $this->events?->dispatch($event);
    }
    /**
     * Get the event dispatcher instance.
     *
     * @return \Illuminate\Contracts\Events\Dispatcher|null
     */
    public function get_event_dispatcher()
    {
        return $this->events;
    }
    /**
     * Set the event dispatcher instance.
     */
    public function set_event_dispatcher(Dispatcher $events): void
    {
        $this->events = $events;
    }
    /**
     * Determine if a cached value exists.
     *
     * @param  \UnitEnum|string  $key
     */
    public function offsetExists($key): bool
    {
        return $this->has($key);
    }
    /**
     * Retrieve an item from the cache by key.
     *
     * @param  \UnitEnum|string  $key
     */
    public function offsetGet($key): mixed
    {
        return $this->get($key);
    }
    /**
     * Store an item in the cache for the default time.
     *
     * @param  \UnitEnum|string  $key
     * @param  mixed  $value
     */
    public function offsetSet($key, $value): void
    {
        $this->put($key, $value, $this->default);
    }
    /**
     * Remove an item from the cache.
     *
     * @param  \UnitEnum|string  $key
     */
    public function offsetUnset($key): void
    {
        $this->forget($key);
    }
    /**
     * Handle dynamic calls into macros or pass missing methods to the store.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (static::has_macro($method)) {
            return $this->macro_call($method, $parameters);
        }
        return $this->store->{$method}(...$parameters);
    }
    /**
     * Clone cache repository instance.
     */
    public function __clone()
    {
        $this->store = clone $this->store;
    }
}