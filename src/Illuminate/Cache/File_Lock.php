<?php

declare (strict_types=1);
namespace Illuminate\Cache;

class File_Lock extends Cache_Lock
{
    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    public function acquire()
    {
        return $this->store->add($this->name, $this->owner, $this->seconds);
    }
}