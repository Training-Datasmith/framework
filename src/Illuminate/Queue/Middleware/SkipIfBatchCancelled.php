<?php

declare(strict_types=1);

namespace Illuminate\Queue\Middleware;

class SkipIfBatchCancelled
{
    /**
     * Process the job.
     *
     * @param  mixed  $job
     * @param  callable  $next
     */
    public function handle($job, $next): void
    {
        if (method_exists($job, 'batch') && $job->batch()?->cancelled()) {
            return;
        }

        $next($job);
    }
}
