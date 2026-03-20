<?php

declare (strict_types=1);
namespace Illuminate\Cache\Events;

use Throwable;
class Cache_Failed_Over
{
    /**
     * Create a new event instance.
     *
     * @param  string|null  $storeName  The name of the cache store that failed.
     * @param  \Throwable  $exception  The exception that was thrown.
     */
    public function __construct(public ?string $store_name, public Throwable $exception)
    {
    }
}