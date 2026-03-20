<?php

declare (strict_types=1);
namespace Illuminate\Console\Scheduling;

interface Cache_Aware
{
    /**
     * Specify the cache store that should be used.
     *
     * @param  string  $store
     * @return $this
     */
    public function use_store($store);
}