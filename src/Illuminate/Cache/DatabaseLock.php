<?php

declare (strict_types=1);
namespace Illuminate\Cache;

use Illuminate\Database\Connection;
use Illuminate\Database\Detects_Concurrency_Errors;
use Illuminate\Database\Query_Exception;
use Throwable;
class Database_Lock extends Lock
{
    use Detects_Concurrency_Errors;
    /**
     * Create a new lock instance.
     *
     * @param  string  $table
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @param  array{int, int}|null  $lottery
     * @param  int  $defaultTimeoutInSeconds
     */
    public function __construct(
        /**
         * The database connection instance.
         */
        protected \Illuminate\Database\Connection $connection,
        /**
         * The database table name.
         */
        protected $table,
        $name,
        $seconds,
        $owner = null,
        /**
         * The prune probability odds.
         */
        protected $lottery = [2, 100],
        /**
         * The default number of seconds that a lock should be held.
         */
        protected $default_timeout_in_seconds = 86400
    )
    {
        parent::__construct($name, $seconds, $owner);
    }
    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     *
     * @throws \Throwable
     */
    public function acquire()
    {
        try {
            $this->connection->table($this->table)->insert(['key' => $this->name, 'owner' => $this->owner, 'expiration' => $this->expires_at()]);
            $acquired = true;
        } catch (Query_Exception) {
            $updated = $this->connection->table($this->table)->where('key', $this->name)->where(fn($query) => $query->where('owner', $this->owner)->or_where('expiration', '<=', $this->current_time()))->update(['owner' => $this->owner, 'expiration' => $this->expires_at()]);
            $acquired = $updated >= 1;
        }
        if (count($this->lottery ?? []) === 2 && random_int(1, $this->lottery[1]) <= $this->lottery[0]) {
            $this->prune_expired_locks();
        }
        return $acquired;
    }
    /**
     * Get the UNIX timestamp indicating when the lock should expire.
     *
     * @return int
     */
    protected function expires_at(): float|int|array
    {
        $lock_timeout = $this->seconds > 0 ? $this->seconds : $this->default_timeout_in_seconds;
        return $this->current_time() + $lock_timeout;
    }
    /**
     * Release the lock.
     *
     *
     * @throws \Throwable
     */
    public function release(): bool
    {
        if ($this->is_owned_by_current_process()) {
            try {
                $this->connection->table($this->table)->where('key', $this->name)->where('owner', $this->owner)->delete();
                return true;
            } catch (Throwable $e) {
                if ($this->caused_by_concurrency_error($e)) {
                    return true;
                }
                throw $e;
            }
        }
        return false;
    }
    /**
     * Releases this lock in disregard of ownership.
     */
    public function force_release(): void
    {
        $this->connection->table($this->table)->where('key', $this->name)->delete();
    }
    /**
     * Deletes locks that are past expiration.
     *
     *
     * @throws \Throwable
     */
    public function prune_expired_locks(): void
    {
        try {
            $this->connection->table($this->table)->where('expiration', '<=', $this->current_time())->delete();
        } catch (Throwable $e) {
            if (!$this->caused_by_concurrency_error($e)) {
                throw $e;
            }
        }
    }
    /**
     * Returns the owner value written into the driver for this lock.
     *
     * @return string|null
     */
    protected function get_current_owner()
    {
        return $this->connection->table($this->table)->where('key', $this->name)->first()?->owner;
    }
    /**
     * Get the name of the database connection being used to manage the lock.
     *
     * @return string
     */
    public function get_connection_name()
    {
        return $this->connection->get_name();
    }
}