<?php

declare (strict_types=1);
namespace Illuminate\Cache;

class Dynamo_Db_Lock extends Lock
{
    /**
     * Create a new lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     */
    public function __construct(
        /**
         * The DynamoDB client instance.
         */
        protected \Illuminate\Cache\Dynamo_Db_Store $dynamo,
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
        if ($this->seconds > 0) {
            return $this->dynamo->add($this->name, $this->owner, $this->seconds);
        }
        return $this->dynamo->add($this->name, $this->owner, 86400);
    }
    /**
     * Release the lock.
     *
     * @return bool
     */
    public function release()
    {
        if ($this->is_owned_by_current_process()) {
            return $this->dynamo->forget($this->name);
        }
        return false;
    }
    /**
     * Release this lock in disregard of ownership.
     */
    public function force_release(): void
    {
        $this->dynamo->forget($this->name);
    }
    /**
     * Returns the owner value written into the driver for this lock.
     *
     * @return mixed
     */
    protected function get_current_owner()
    {
        return $this->dynamo->get($this->name);
    }
}