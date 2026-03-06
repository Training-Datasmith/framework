<?php

namespace Illuminate\Cache;

use Illuminate\Database\Connection;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\QueryException;
use Throwable;

class DatabaseLock extends Lock
{
    use DetectsConcurrencyErrors;

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
    public function __construct(/**
     * The database connection instance.
     */
    protected \Illuminate\Database\Connection $connection, /**
     * The database table name.
     */
    protected $table, $name, $seconds, $owner = null, /**
     * The prune probability odds.
     */
    protected $lottery = [2, 100], /**
     * The default number of seconds that a lock should be held.
     */
    protected $defaultTimeoutInSeconds = 86400)
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
            $this->connection->table($this->table)->insert([
                'key' => $this->name,
                'owner' => $this->owner,
                'expiration' => $this->expiresAt(),
            ]);

            $acquired = true;
        } catch (QueryException) {
            $updated = $this->connection->table($this->table)
                ->where('key', $this->name)
                ->where(fn($query) => $query->where('owner', $this->owner)->orWhere('expiration', '<=', $this->currentTime()))->update([
                    'owner' => $this->owner,
                    'expiration' => $this->expiresAt(),
                ]);

            $acquired = $updated >= 1;
        }

        if (count($this->lottery ?? []) === 2 && random_int(1, $this->lottery[1]) <= $this->lottery[0]) {
            $this->pruneExpiredLocks();
        }

        return $acquired;
    }

    /**
     * Get the UNIX timestamp indicating when the lock should expire.
     *
     * @return int
     */
    protected function expiresAt(): float|int|array
    {
        $lockTimeout = $this->seconds > 0 ? $this->seconds : $this->defaultTimeoutInSeconds;

        return $this->currentTime() + $lockTimeout;
    }

    /**
     * Release the lock.
     *
     *
     * @throws \Throwable
     */
    public function release(): bool
    {
        if ($this->isOwnedByCurrentProcess()) {
            try {
                $this->connection->table($this->table)
                    ->where('key', $this->name)
                    ->where('owner', $this->owner)
                    ->delete();

                return true;
            } catch (Throwable $e) {
                if ($this->causedByConcurrencyError($e)) {
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
    public function forceRelease(): void
    {
        $this->connection->table($this->table)
            ->where('key', $this->name)
            ->delete();
    }

    /**
     * Deletes locks that are past expiration.
     *
     *
     * @throws \Throwable
     */
    public function pruneExpiredLocks(): void
    {
        try {
            $this->connection->table($this->table)
                ->where('expiration', '<=', $this->currentTime())
                ->delete();
        } catch (Throwable $e) {
            if (! $this->causedByConcurrencyError($e)) {
                throw $e;
            }
        }
    }

    /**
     * Returns the owner value written into the driver for this lock.
     *
     * @return string|null
     */
    protected function getCurrentOwner()
    {
        return $this->connection->table($this->table)->where('key', $this->name)->first()?->owner;
    }

    /**
     * Get the name of the database connection being used to manage the lock.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connection->getName();
    }
}
