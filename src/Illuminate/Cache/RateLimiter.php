<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Redis\Connections\Php_Redis_Connection;
use Illuminate\Support\Collection;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Interacts_With_Time;
class Rate_Limiter
{
    use Interacts_With_Time;
    /**
     * The configured limit object resolvers.
     *
     * @var array
     */
    protected $limiters = [];
    /**
     * Create a new rate limiter instance.
     */
    public function __construct(
        /**
         * The cache store implementation.
         */
        protected \Illuminate\Contracts\Cache\Repository $cache
    )
    {
    }
    /**
     * Register a named limiter configuration.
     *
     * @param  \UnitEnum|string  $name
     * @return $this
     */
    public function for($name, Closure $callback): static
    {
        $resolved_name = $this->resolve_limiter_name($name);
        $this->limiters[$resolved_name] = $callback;
        return $this;
    }
    /**
     * Get the given named rate limiter.
     *
     * @param  \UnitEnum|string  $name
     * @return \Closure|null
     */
    public function limiter($name)
    {
        $resolved_name = $this->resolve_limiter_name($name);
        $limiter = $this->limiters[$resolved_name] ?? null;
        if (!is_callable($limiter)) {
            return;
        }
        return function (...$args) use ($limiter) {
            $result = $limiter(...$args);
            if (!is_array($result)) {
                return $result;
            }
            $duplicates = (new Collection($result))->duplicates('key');
            if ($duplicates->is_empty()) {
                return $result;
            }
            foreach ($result as $limit) {
                if ($duplicates->contains($limit->key)) {
                    $limit->key = $limit->fallback_key();
                }
            }
            return $result;
        };
    }
    /**
     * Attempts to execute a callback if it's not limited.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  \DateTimeInterface|\DateInterval|int  $decaySeconds
     * @return mixed
     */
    public function attempt($key, $max_attempts, Closure $callback, $decay_seconds = 60)
    {
        if ($this->too_many_attempts($key, $max_attempts)) {
            return false;
        }
        if (is_null($result = $callback())) {
            $result = true;
        }
        return tap($result, function () use ($key, $decay_seconds): void {
            $this->hit($key, $decay_seconds);
        });
    }
    /**
     * Determine if the given key has been "accessed" too many times.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     */
    public function too_many_attempts($key, $max_attempts): bool
    {
        if ($this->attempts($key) >= $max_attempts) {
            if ($this->cache->has($this->clean_rate_limiter_key($key) . ':timer')) {
                return true;
            }
            $this->reset_attempts($key);
        }
        return false;
    }
    /**
     * Increment (by 1) the counter for a given key for a given decay time.
     *
     * @param  string  $key
     * @param  \DateTimeInterface|\DateInterval|int  $decaySeconds
     */
    public function hit($key, $decay_seconds = 60): int
    {
        return $this->increment($key, $decay_seconds);
    }
    /**
     * Increment the counter for a given key for a given decay time by a given amount.
     *
     * @param  string  $key
     * @param  \DateTimeInterface|\DateInterval|int  $decaySeconds
     * @param  int  $amount
     */
    public function increment($key, $decay_seconds = 60, $amount = 1): int
    {
        $key = $this->clean_rate_limiter_key($key);
        $this->cache->add($key . ':timer', $this->available_at($decay_seconds), $decay_seconds);
        $added = $this->without_serialization_or_compression(fn() => $this->cache->add($key, 0, $decay_seconds));
        $hits = (int) $this->cache->increment($key, $amount);
        if (!$added && $hits == 1) {
            $this->without_serialization_or_compression(fn() => $this->cache->put($key, 1, $decay_seconds));
        }
        return $hits;
    }
    /**
     * Decrement the counter for a given key for a given decay time by a given amount.
     *
     * @param  string  $key
     * @param  \DateTimeInterface|\DateInterval|int  $decaySeconds
     * @param  int  $amount
     */
    public function decrement($key, $decay_seconds = 60, $amount = 1): int
    {
        return $this->increment($key, $decay_seconds, $amount * -1);
    }
    /**
     * Get the number of attempts for the given key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function attempts($key)
    {
        $key = $this->clean_rate_limiter_key($key);
        return $this->without_serialization_or_compression(fn() => $this->cache->get($key, 0));
    }
    /**
     * Reset the number of attempts for the given key.
     *
     * @param  string  $key
     * @return bool
     */
    public function reset_attempts($key)
    {
        $key = $this->clean_rate_limiter_key($key);
        return $this->cache->forget($key);
    }
    /**
     * Get the number of retries left for the given key.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @return int
     */
    public function remaining($key, $max_attempts): float|int
    {
        $key = $this->clean_rate_limiter_key($key);
        $attempts = $this->attempts($key);
        return max(0, $max_attempts - $attempts);
    }
    /**
     * Get the number of retries left for the given key.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @return int
     */
    public function retries_left($key, $max_attempts): float|int
    {
        return $this->remaining($key, $max_attempts);
    }
    /**
     * Clear the hits and lockout timer for the given key.
     *
     * @param  string  $key
     */
    public function clear($key): void
    {
        $key = $this->clean_rate_limiter_key($key);
        $this->reset_attempts($key);
        $this->cache->forget($key . ':timer');
    }
    /**
     * Get the number of seconds until the "key" is accessible again.
     *
     * @param  string  $key
     * @return int
     */
    public function available_in($key): float|int
    {
        $key = $this->clean_rate_limiter_key($key);
        return max(0, $this->cache->get($key . ':timer') - $this->current_time());
    }
    /**
     * Clean the rate limiter key from unicode characters.
     *
     * @param  string  $key
     * @return string
     */
    public function clean_rate_limiter_key($key): ?string
    {
        return preg_replace('/&([a-z])[a-z]+;/i', '$1', htmlentities($key));
    }
    /**
     * Execute the given callback without serialization or compression when applicable.
     *
     * @return mixed
     */
    protected function without_serialization_or_compression(callable $callback)
    {
        $store = $this->cache->get_store();
        if (!$store instanceof Redis_Store) {
            return $callback();
        }
        $connection = $store->connection();
        if (!$connection instanceof Php_Redis_Connection) {
            return $callback();
        }
        return $connection->without_serialization_or_compression($callback);
    }
    /**
     * Resolve the rate limiter name.
     *
     * @param  \UnitEnum|string  $name
     */
    private function resolve_limiter_name($name): string
    {
        return (string) enum_value($name);
    }
}