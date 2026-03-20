<?php

declare (strict_types=1);
namespace Illuminate\Cache;

trait Has_Cache_Lock
{
    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function lock($name, $seconds = 0, $owner = null): \Illuminate\Cache\Cache_Lock
    {
        return new Cache_Lock($this, $name, $seconds, $owner);
    }
    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function restore_lock($name, $owner)
    {
        return $this->lock($name, 0, $owner);
    }
}