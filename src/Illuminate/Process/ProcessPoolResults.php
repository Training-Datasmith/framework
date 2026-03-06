<?php

namespace Illuminate\Process;

use ArrayAccess;
use Illuminate\Support\Collection;

class ProcessPoolResults implements ArrayAccess
{
    /**
     * Create a new process pool result set.
     */
    public function __construct(
        /**
         * The results of the processes.
         */
        protected array $results
    )
    {
    }

    /**
     * Determine if all of the processes in the pool were successful.
     *
     * @return bool
     */
    public function successful()
    {
        return $this->collect()->every(fn ($p) => $p->successful());
    }

    /**
     * Determine if any of the processes in the pool failed.
     */
    public function failed(): bool
    {
        return ! $this->successful();
    }

    /**
     * Get the results as a collection.
     */
    public function collect(): \Illuminate\Support\Collection
    {
        return new Collection($this->results);
    }

    /**
     * Determine if the given array offset exists.
     *
     * @param  int  $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->results[$offset]);
    }

    /**
     * Get the result at the given offset.
     *
     * @param  int  $offset
     */
    public function offsetGet($offset): mixed
    {
        return $this->results[$offset];
    }

    /**
     * Set the result at the given offset.
     *
     * @param  int  $offset
     * @param  mixed  $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->results[$offset] = $value;
    }

    /**
     * Unset the result at the given offset.
     *
     * @param  int  $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->results[$offset]);
    }
}
