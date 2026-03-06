<?php

namespace Illuminate\Queue\Failed;

class NullFailedJobProvider implements CountableFailedJobProvider, FailedJobProviderInterface
{
    /**
     * Log a failed job into storage.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  string  $payload
     * @param  \Throwable  $exception
     */
    public function log($connection, $queue, $payload, $exception): void
    {
        //
    }

    /**
     * Get the IDs of all of the failed jobs.
     *
     * @param  string|null  $queue
     */
    public function ids($queue = null): array
    {
        return [];
    }

    /**
     * Get a list of all of the failed jobs.
     */
    public function all(): array
    {
        return [];
    }

    /**
     * Get a single failed job.
     *
     * @param  mixed  $id
     */
    public function find($id): void
    {
        //
    }

    /**
     * Delete a single failed job from storage.
     *
     * @param  mixed  $id
     */
    public function forget($id): bool
    {
        return true;
    }

    /**
     * Flush all of the failed jobs from storage.
     *
     * @param  int|null  $hours
     */
    public function flush($hours = null): void
    {
        //
    }

    /**
     * Count the failed jobs.
     *
     * @param  string|null  $connection
     * @param  string|null  $queue
     */
    public function count($connection = null, $queue = null): int
    {
        return 0;
    }
}
