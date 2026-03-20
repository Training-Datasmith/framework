<?php

declare (strict_types=1);
namespace Illuminate\Cache\Limiters;

use Illuminate\Support\Interacts_With_Time;
class Concurrency_Limiter_Builder
{
    use Interacts_With_Time;
    /**
     * The maximum number of entities that can hold the lock at the same time.
     *
     * @var int
     */
    public $max_locks;
    /**
     * The number of seconds to maintain the lock until it is automatically released.
     *
     * @var int
     */
    public $release_after = 60;
    /**
     * The number of seconds to block until a lock is available.
     *
     * @var int
     */
    public $timeout = 3;
    /**
     * The number of milliseconds to wait between attempts to acquire the lock.
     *
     * @var int
     */
    public $sleep = 250;
    /**
     * Create a new builder instance.
     *
     * @param  mixed  $connection
     * @param  string  $name
     */
    public function __construct(
        /**
         * The cache repository or Redis connection.
         */
        public $connection,
        /**
         * The name of the lock.
         */
        public $name
    )
    {
    }
    /**
     * Set the maximum number of locks that can be obtained per time window.
     *
     * @param  int  $maxLocks
     * @return $this
     */
    public function limit($max_locks): static
    {
        $this->max_locks = $max_locks;
        return $this;
    }
    /**
     * Set the number of seconds until the lock will be released.
     *
     * @param  int  $releaseAfter
     * @return $this
     */
    public function release_after($release_after): static
    {
        $this->release_after = $this->seconds_until($release_after);
        return $this;
    }
    /**
     * Set the number of seconds to block until a lock is available.
     *
     * @param  int  $timeout
     * @return $this
     */
    public function block($timeout): static
    {
        $this->timeout = $timeout;
        return $this;
    }
    /**
     * The number of milliseconds to wait between lock acquisition attempts.
     *
     * @param  int  $sleep
     * @return $this
     */
    public function sleep($sleep): static
    {
        $this->sleep = $sleep;
        return $this;
    }
    /**
     * Execute the given callback if a lock is obtained, otherwise call the failure callback.
     *
     * @return mixed
     *
     * @throws \Illuminate\Cache\Limiters\LimiterTimeoutException
     */
    public function then(callable $callback, ?callable $failure = null)
    {
        try {
            return $this->create_limiter()->block($this->timeout, $callback, $this->sleep);
        } catch (Limiter_Timeout_Exception $e) {
            if ($failure) {
                return $failure($e);
            }
            throw $e;
        }
    }
    /**
     * Create the concurrency limiter instance.
     */
    protected function create_limiter(): \Illuminate\Cache\Limiters\Concurrency_Limiter
    {
        return new Concurrency_Limiter($this->connection->get_store(), $this->name, $this->max_locks, $this->release_after);
    }
}