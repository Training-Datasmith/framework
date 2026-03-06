<?php

declare(strict_types=1);

namespace Illuminate\Cache;

use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\InteractsWithTime;

class SessionStore implements Store
{
    use InteractsWithTime;
    use RetrievesMultipleKeys;

    /**
     * Create a new session cache store.
     *
     * @param  \Illuminate\Contracts\Session\Session  $session
     * @param  string  $key
     */
    public function __construct(
        /**
         * The session instance.
         */
        public $session,
        /**
         * The key for cache items.
         */
        public $key = '_cache'
    ) {
    }

    /**
     * Get all of the cached values and their expiration times.
     *
     * @return array<string, array{value: mixed, expiresAt: float}>
     */
    public function all()
    {
        return $this->session->get($this->key, []);
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        if (! $this->session->exists($this->itemKey($key))) {
            return;
        }

        $item = $this->session->get($this->itemKey($key));

        $expiresAt = $item['expiresAt'] ?? 0;

        if ($this->isExpired($expiresAt)) {
            $this->forget($key);

            return;
        }

        return $item['value'];
    }

    /**
     * Determine if the given expiration time is expired.
     *
     * @param  int|float  $expiresAt
     */
    protected function isExpired($expiresAt): bool
    {
        return $expiresAt !== 0 && (Carbon::now()->getPreciseTimestamp(3) / 1000) >= $expiresAt;
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
        $this->session->put($this->itemKey($key), [
            'value' => $value,
            'expiresAt' => $this->toTimestamp($seconds),
        ]);

        return true;
    }

    /**
     * Get the UNIX timestamp, with milliseconds, for the given number of seconds in the future.
     *
     * @param  int  $seconds
     * @return float
     */
    protected function toTimestamp($seconds): int|float
    {
        return $seconds > 0 ? (Carbon::now()->getPreciseTimestamp(3) / 1000) + $seconds : 0;
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
        if (! is_null($existing = $this->get($key))) {
            return tap(((int) $existing) + $value, function ($incremented) use ($key): void {
                $this->session->put($this->itemKey("{$key}.value"), $incremented);
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
        if ($this->session->exists($this->itemKey($key))) {
            $this->session->forget($this->itemKey($key));

            return true;
        }

        return false;
    }

    /**
     * Remove all items from the cache.
     */
    public function flush(): bool
    {
        $this->session->put($this->key, []);

        return true;
    }

    /**
     * Get the cache key prefix.
     */
    public function itemKey($key): string
    {
        return "{$this->key}.{$key}";
    }

    /**
     * Get the cache key prefix.
     */
    public function getPrefix(): string
    {
        return '';
    }
}
