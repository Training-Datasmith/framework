<?php

namespace Illuminate\Cache\Limiters;

use Illuminate\Support\InteractsWithTime;

class ConcurrencyLimiterBuilder
{
    use InteractsWithTime;

    /**
     * The maximum number of entities that can hold the lock at the same time.
     *
     * @var int
     */
    public $maxLocks;

    /**
     * The number of seconds to maintain the lock until it is automatically released.
     *
     * @var int
     */
    public $releaseAfter = 60;

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
    public function limit($maxLocks): static
    {
        $this->maxLocks = $maxLocks;

        return $this;
    }

    /**
     * Set the number of seconds until the lock will be released.
     *
     * @param  int  $releaseAfter
     * @return $this
     */
    public function releaseAfter($releaseAfter): static
    {
        $this->releaseAfter = $this->secondsUntil($releaseAfter);

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
            return $this->createLimiter()->block($this->timeout, $callback, $this->sleep);
        } catch (LimiterTimeoutException $e) {
            if ($failure) {
                return $failure($e);
            }

            throw $e;
        }
    }

    /**
     * Create the concurrency limiter instance.
     */
    protected function createLimiter(): \Illuminate\Cache\Limiters\ConcurrencyLimiter
    {
        return new ConcurrencyLimiter(
            $this->connection->getStore(),
            $this->name,
            $this->maxLocks,
            $this->releaseAfter
        );
    }
}
