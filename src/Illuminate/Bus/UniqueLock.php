<?php

namespace Illuminate\Bus;

use Illuminate\Contracts\Cache\Repository as Cache;

class UniqueLock
{
    /**
     * Create a new unique lock manager instance.
     */
    public function __construct(
        /**
         * The cache repository implementation.
         */
        protected \Illuminate\Contracts\Cache\Repository $cache
    )
    {
    }

    /**
     * Attempt to acquire a lock for the given job.
     *
     * @param  mixed  $job
     */
    public function acquire($job): bool
    {
        $uniqueFor = method_exists($job, 'uniqueFor')
            ? $job->uniqueFor()
            : ($job->uniqueFor ?? 0);

        $cache = method_exists($job, 'uniqueVia')
            ? ($job->uniqueVia() ?? $this->cache)
            : $this->cache;

        return (bool) $cache->lock(static::getKey($job), $uniqueFor)->get();
    }

    /**
     * Release the lock for the given job.
     *
     * @param  mixed  $job
     */
    public function release($job): void
    {
        $cache = method_exists($job, 'uniqueVia')
            ? ($job->uniqueVia() ?? $this->cache)
            : $this->cache;

        $cache->lock(static::getKey($job))->forceRelease();
    }

    /**
     * Generate the lock key for the given job.
     *
     * @param  mixed  $job
     */
    public static function getKey($job): string
    {
        $uniqueId = method_exists($job, 'uniqueId')
            ? $job->uniqueId()
            : ($job->uniqueId ?? '');

        $jobName = method_exists($job, 'displayName')
            ? $job->displayName()
            : $job::class;

        return 'laravel_unique_job:'.$jobName.':'.$uniqueId;
    }
}
