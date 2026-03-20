<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Queue;

use Illuminate\Bus\Unique_Lock;
use Illuminate\Contracts\Queue\Should_Be_Unique;
use Illuminate\Support\Facades\Context;
trait Interacts_With_Unique_Jobs
{
    /**
     * Store unique job information in the context in case we can't resolve the job on the queue side.
     *
     * @param  mixed  $job
     */
    public function add_unique_job_information_to_context($job): void
    {
        if ($job instanceof Should_Be_Unique) {
            Context::add_hidden(['laravel_unique_job_cache_store' => $this->get_unique_job_cache_store($job), 'laravel_unique_job_key' => Unique_Lock::get_key($job)]);
        }
    }
    /**
     * Remove the unique job information from the context.
     *
     * @param  mixed  $job
     */
    public function remove_unique_job_information_from_context($job): void
    {
        if ($job instanceof Should_Be_Unique) {
            Context::forget_hidden(['laravel_unique_job_cache_store', 'laravel_unique_job_key']);
        }
    }
    /**
     * Determine the cache store used by the unique job to acquire locks.
     *
     * @param  mixed  $job
     */
    protected function get_unique_job_cache_store($job): ?string
    {
        return method_exists($job, 'uniqueVia') ? $job->unique_via()->get_name() : config('cache.default');
    }
}