<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Illuminate\Contracts\Cache\Lock_Provider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Interacts_With_Time;
class Array_Store extends Taggable_Store implements Lock_Provider
{
    use Interacts_With_Time;
    use Retrieves_Multiple_Keys;
    /**
     * The array of stored values.
     *
     * @var array<string, array{value: mixed, expiresAt: float}>
     */
    protected $storage = [];
    /**
     * The array of locks.
     *
     * @var array<string, array{owner: ?string, expiresAt: ?\Illuminate\Support\Carbon}>
     */
    public $locks = [];
    /**
     * Create a new Array store.
     *
     * @param  bool  $serializesValues
     * @param  array|bool|null  $serializableClasses
     */
    public function __construct(
        /**
         * Indicates if values are serialized within the store.
         */
        protected $serializes_values = false,
        /**
         * The classes that should be allowed during unserialization.
         */
        protected $serializable_classes = null
    )
    {
    }
    /**
     * Get all of the cached values and their expiration times.
     *
     * @param  bool  $unserialize
     * @return array<string, array{value: mixed, expiresAt: float}>
     */
    public function all($unserialize = true)
    {
        if ($unserialize === false || $this->serializes_values === false) {
            return $this->storage;
        }
        $storage = [];
        foreach ($this->storage as $key => $data) {
            $storage[$key] = ['value' => $this->unserialize($data['value']), 'expiresAt' => $data['expiresAt']];
        }
        return $storage;
    }
    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        if (!isset($this->storage[$key])) {
            return;
        }
        $item = $this->storage[$key];
        $expires_at = $item['expiresAt'] ?? 0;
        if ($expires_at !== 0 && Carbon::now()->get_precise_timestamp(3) / 1000 >= $expires_at) {
            $this->forget($key);
            return;
        }
        return $this->serializes_values ? $this->unserialize($item['value']) : $item['value'];
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
        $this->storage[$key] = ['value' => $this->serializes_values ? serialize($value) : $value, 'expiresAt' => $this->calculate_expiration($seconds)];
        return true;
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
        if (!is_null($existing = $this->get($key))) {
            return tap((int) $existing + $value, function ($incremented) use ($key): void {
                $value = $this->serializes_values ? serialize($incremented) : $incremented;
                $this->storage[$key]['value'] = $value;
            });
        }
        $this->forever($key, $value);
        return $value;
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
        return $this->increment($key, $value * -1);
    }
    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    public function forever($key, $value): bool
    {
        return $this->put($key, $value, 0);
    }
    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     */
    public function forget($key): bool
    {
        if (array_key_exists($key, $this->storage)) {
            unset($this->storage[$key]);
            return true;
        }
        return false;
    }
    /**
     * Remove all items from the cache.
     */
    public function flush(): bool
    {
        $this->storage = [];
        return true;
    }
    /**
     * Get the cache key prefix.
     */
    public function get_prefix(): string
    {
        return '';
    }
    /**
     * Get the expiration time of the key.
     *
     * @param  int  $seconds
     * @return float
     */
    protected function calculate_expiration($seconds): int|float
    {
        return $this->to_timestamp($seconds);
    }
    /**
     * Get the UNIX timestamp, with milliseconds, for the given number of seconds in the future.
     *
     * @param  int  $seconds
     * @return float
     */
    protected function to_timestamp($seconds): int|float
    {
        return $seconds > 0 ? Carbon::now()->get_precise_timestamp(3) / 1000 + $seconds : 0;
    }
    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function lock($name, $seconds = 0, $owner = null): \Illuminate\Cache\Array_Lock
    {
        return new Array_Lock($this, $name, $seconds, $owner);
    }
    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function restore_lock($name, $owner): \Illuminate\Cache\Array_Lock
    {
        return $this->lock($name, 0, $owner);
    }
    /**
     * Unserialize the given value.
     *
     * @param  string  $value
     */
    protected function unserialize($value): mixed
    {
        if ($this->serializable_classes !== null) {
            return unserialize($value, ['allowed_classes' => $this->serializable_classes]);
        }
        return unserialize($value);
    }
}