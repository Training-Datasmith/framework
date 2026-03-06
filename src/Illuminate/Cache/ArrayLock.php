<?php

namespace Illuminate\Cache;

use Illuminate\Support\Carbon;

class ArrayLock extends Lock
{
    /**
     * Create a new lock instance.
     *
     * @param  \Illuminate\Cache\ArrayStore  $store
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     */
    public function __construct(/**
     * The parent array cache store.
     */
    protected $store, $name, $seconds, $owner = null)
    {
        parent::__construct($name, $seconds, $owner);
    }

    /**
     * Attempt to acquire the lock.
     */
    public function acquire(): bool
    {
        $expiration = $this->store->locks[$this->name]['expiresAt'] ?? Carbon::now()->addSecond();

        if ($this->exists() && $expiration->isFuture()) {
            return false;
        }

        $this->store->locks[$this->name] = [
            'owner' => $this->owner,
            'expiresAt' => $this->seconds === 0 ? null : Carbon::now()->addSeconds($this->seconds),
        ];

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
        if (! $this->exists()) {
            return false;
        }

        if (! $this->isOwnedByCurrentProcess()) {
            return false;
        }

        $this->forceRelease();

        return true;
    }

    /**
     * Returns the owner value written into the driver for this lock.
     *
     * @return string|null
     */
    protected function getCurrentOwner()
    {
        if (! $this->exists()) {
            return null;
        }

        return $this->store->locks[$this->name]['owner'];
    }

    /**
     * Releases this lock regardless of ownership.
     */
    public function forceRelease(): void
    {
        unset($this->store->locks[$this->name]);
    }
}
