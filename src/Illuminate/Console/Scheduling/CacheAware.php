<?php

declare(strict_types=1);

namespace Illuminate\Console\Scheduling;

interface CacheAware
{
    /**
     * Specify the cache store that should be used.
     *
     * @param  string  $store
     * @return $this
     */
    public function useStore($store);
}
