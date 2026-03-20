<?php

declare (strict_types=1);
namespace Illuminate\Cache\Limiters;

use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Throwable;
class Concurrency_Limiter
{
    /**
     * Create a new concurrency limiter instance.
     *
     * @param  \Illuminate\Contracts\Cache\LockProvider  $store
     * @param  string  $name
     * @param  int  $maxLocks
     * @param  int  $releaseAfter
     */
    public function __construct(
        /**
         * The cache store instance.
         */
        protected $store,
        /**
         * The name of the limiter.
         */
        protected $name,
        /**
         * The allowed number of concurrent locks.
         */
        protected $max_locks,
        /**
         * The number of seconds a slot should be maintained.
         */
        protected $release_after
    )
    {
    }
    /**
     * Attempt to acquire the lock for the given number of seconds.
     *
     * @param  int  $timeout
     * @param  callable|null  $callback
     * @param  int  $sleep
     * @return mixed
     *
     * @throws \Illuminate\Cache\Limiters\LimiterTimeoutException
     * @throws \Throwable
     */
    public function block($timeout, $callback = null, $sleep = 250)
    {
        $starting = time();
        $id = Str::random(20);
        while (!$slot = $this->acquire($id)) {
            if (time() - $timeout >= $starting) {
                throw new Limiter_Timeout_Exception();
            }
            Sleep::usleep($sleep * 1000);
        }
        if (is_callable($callback)) {
            try {
                return tap($callback(), function () use ($slot): void {
                    $this->release($slot);
                });
            } catch (Throwable $exception) {
                $this->release($slot);
                throw $exception;
            }
        }
        return true;
    }
    /**
     * Attempt to acquire a slot lock.
     *
     * @param  string  $id
     * @return \Illuminate\Contracts\Cache\Lock|false
     */
    protected function acquire($id)
    {
        for ($i = 1; $i <= $this->max_locks; $i++) {
            $lock = $this->store->lock($this->name . $i, $this->release_after, $id);
            if ($lock->acquire()) {
                return $lock;
            }
        }
        return false;
    }
    /**
     * Release the lock.
     *
     * @param  \Illuminate\Contracts\Cache\Lock  $lock
     * @return void
     */
    protected function release($lock)
    {
        $lock->release();
    }
}