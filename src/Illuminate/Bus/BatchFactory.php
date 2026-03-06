<?php

declare(strict_types=1);

namespace Illuminate\Bus;

use Carbon\CarbonImmutable;

class BatchFactory
{
    /**
     * Create a new batch factory instance.
     */
    public function __construct(
        /**
         * The queue factory implementation.
         */
        protected \Illuminate\Contracts\Queue\Factory $queue
    ) {
    }

    /**
     * Create a new batch instance.
     */
    public function make(
        BatchRepository $repository,
        string $id,
        string $name,
        int $totalJobs,
        int $pendingJobs,
        int $failedJobs,
        array $failedJobIds,
        array $options,
        CarbonImmutable $createdAt,
        ?CarbonImmutable $cancelledAt,
        ?CarbonImmutable $finishedAt
    ): \Illuminate\Bus\Batch {
        return new Batch($this->queue, $repository, $id, $name, $totalJobs, $pendingJobs, $failedJobs, $failedJobIds, $options, $createdAt, $cancelledAt, $finishedAt);
    }
}
