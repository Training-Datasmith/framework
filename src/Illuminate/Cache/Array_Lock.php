<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Illuminate\Support\Carbon;
class Array_Lock extends Lock
{
    /**
     * Create a new lock instance.
     *
     * @param  \Illuminate\Cache\ArrayStore  $store
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     */
    public function __construct(
        /**
         * The parent array cache store.
         */
        protected $store,
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
        $expiration = $this->store->locks[$this->name]['expiresAt'] ?? Carbon::now()->add_second();
        if ($this->exists() && $expiration->is_future()) {
            return false;
        }
        $this->store->locks[$this->name] = ['owner' => $this->owner, 'expiresAt' => $this->seconds === 0 ? null : Carbon::now()->add_seconds($this->seconds)];
        return true;
    }
    /**
     * Determine if the current lock exists.
     */
    protected function exists(): bool
    {
        return isset($this->store->locks[$this->name]);
    }
    /**
     * Release the lock.
     */
    public function release(): bool
    {
        if (!$this->exists()) {
            return false;
        }
        if (!$this->is_owned_by_current_process()) {
            return false;
        }
        $this->force_release();
        return true;
    }
    /**
     * Returns the owner value written into the driver for this lock.
     *
     * @return string|null
     */
    protected function get_current_owner()
    {
        if (!$this->exists()) {
            return null;
        }
        return $this->store->locks[$this->name]['owner'];
    }
    /**
     * Releases this lock regardless of ownership.
     */
    public function force_release(): void
    {
        unset($this->store->locks[$this->name]);
    }
}