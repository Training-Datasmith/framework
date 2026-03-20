<?php

declare (strict_types=1);
namespace Illuminate\Bus;

use Illuminate\Contracts\Cache\Repository as Cache;
class Unique_Lock
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
        $unique_for = method_exists($job, 'uniqueFor') ? $job->unique_for() : $job->unique_for ?? 0;
        $cache = method_exists($job, 'uniqueVia') ? $job->unique_via() ?? $this->cache : $this->cache;
        return (bool) $cache->lock(static::get_key($job), $unique_for)->get();
    }
    /**
     * Release the lock for the given job.
     *
     * @param  mixed  $job
     */
    public function release($job): void
    {
        $cache = method_exists($job, 'uniqueVia') ? $job->unique_via() ?? $this->cache : $this->cache;
        $cache->lock(static::get_key($job))->force_release();
    }
    /**
     * Generate the lock key for the given job.
     *
     * @param  mixed  $job
     */
    public static function get_key($job): string
    {
        $unique_id = method_exists($job, 'uniqueId') ? $job->unique_id() : $job->unique_id ?? '';
        $job_name = method_exists($job, 'displayName') ? $job->display_name() : $job::class;
        return 'laravel_unique_job:' . $job_name . ':' . $unique_id;
    }
}