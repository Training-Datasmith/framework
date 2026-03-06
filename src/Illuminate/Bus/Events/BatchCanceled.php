<?php

declare(strict_types=1);

namespace Illuminate\Bus\Events;

use Illuminate\Bus\Batch;

class BatchCanceled
{
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Bus\Batch  $batch  The batch instance.
     */
    public function __construct(
        public Batch $batch,
    ) {
    }
}
