<?php

declare (strict_types=1);
namespace Illuminate\Cache;

class No_Lock extends Lock
{
    /**
     * Attempt to acquire the lock.
     */
    public function acquire(): bool
    {
        return true;
    }
    /**
     * Release the lock.
     */
    public function release(): bool
    {
        return true;
    }
    /**
     * Releases this lock in disregard of ownership.
     */
    public function force_release(): void
    {
    }
    /**
     * Returns the owner value written into the driver for this lock.
     *
     * @return mixed
     */
    protected function get_current_owner()
    {
        return $this->owner;
    }
}