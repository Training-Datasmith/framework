<?php

namespace Illuminate\Support\Testing\Fakes;

use Closure;

class ChainedBatchTruthTest
{
    /**
     * Create a new truth test instance.
     *
     * @param  \Closure(\Illuminate\Bus\PendingBatch): bool  $callback
     */
    public function __construct(
        /**
         * The underlying truth test.
         */
        protected \Closure $callback
    )
    {
    }

    /**
     * Invoke the truth test with the given pending batch.
     *
     * @param  \Illuminate\Bus\PendingBatch  $pendingBatch
     */
    public function __invoke($pendingBatch): bool
    {
        return call_user_func($this->callback, $pendingBatch);
    }
}
