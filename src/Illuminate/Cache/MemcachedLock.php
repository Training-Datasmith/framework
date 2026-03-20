<?php

declare (strict_types=1);
namespace Illuminate\Cache;

class Memcached_Lock extends Lock
{
    /**
     * Create a new lock instance.
     *
     * @param  \Memcached  $memcached
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     */
    public function __construct(
        /**
         * The Memcached instance.
         */
        protected $memcached,
        $name,
        $seconds,
        $owner = null
    )
    {
        parent::__construct($name, $seconds, $owner);
    }
    /**
     * Attempt to acquire the lock.
     */
    public function acquire(): bool
    {
        return $this->memcached->add($this->name, $this->owner, $this->seconds);
    }
    /**
     * Release the lock.
     *
     * @return bool
     */
    public function release()
    {
        if ($this->is_owned_by_current_process()) {
            return $this->memcached->delete($this->name);
        }
        return false;
    }
    /**
     * Releases this lock in disregard of ownership.
     */
    public function force_release(): void
    {
        $this->memcached->delete($this->name);
    }
    /**
     * Returns the owner value written into the driver for this lock.
     */
    protected function get_current_owner(): mixed
    {
        return $this->memcached->get($this->name);
    }
}