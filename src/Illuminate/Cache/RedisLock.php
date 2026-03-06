<?php

declare(strict_types=1);

namespace Illuminate\Cache;

class RedisLock extends Lock
{
    /**
     * Create a new lock instance.
     *
     * @param  \Illuminate\Redis\Connections\Connection  $redis
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     */
    public function __construct(/**
     * The Redis factory implementation.
     */
        protected $redis,
        $name,
        $seconds,
        $owner = null
    ) {
        parent::__construct($name, $seconds, $owner);
    }

    /**
     * Attempt to acquire the lock.
     */
    public function acquire(): bool
    {
        if ($this->seconds > 0) {
            return $this->redis->set($this->name, $this->owner, 'EX', $this->seconds, 'NX') == true;
        }

        return $this->redis->setnx($this->name, $this->owner) === 1;
    }

    /**
     * Release the lock.
     */
    public function release(): bool
    {
        return (bool) $this->redis->eval(LuaScripts::releaseLock(), 1, $this->name, $this->owner);
    }

    /**
     * Releases this lock in disregard of ownership.
     */
    public function forceRelease(): void
    {
        $this->redis->del($this->name);
    }

    /**
     * Returns the owner value written into the driver for this lock.
     *
     * @return string
     */
    protected function getCurrentOwner()
    {
        return $this->redis->get($this->name);
    }

    /**
     * Get the name of the Redis connection being used to manage the lock.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->redis->getName();
    }
}
